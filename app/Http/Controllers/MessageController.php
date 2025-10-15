<?php

namespace App\Http\Controllers;

use App\Events\MessageCreated;
use App\Models\Attachment;
use App\Models\Conversation;
use App\Models\Message;
use App\Models\MessageReceipt;
use App\Support\ApiResponse;
use App\Support\Cursor;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class MessageController extends Controller
{
    // Cursor paging: ascending order by (created_at, id)
    public function index(Request $r, int $conversationId) {
        $uid = $r->user()->id;

        // membership check
        $isMember = DB::table('conversation_user')
            ->where('conversation_id',$conversationId)->where('user_id',$uid)->exists();
        abort_unless($isMember, 403);

        $limit  = min((int)$r->query('limit', 50), 100);
        $cursor = Cursor::decode($r->query('cursor'));

        $q = Message::with(['sender:id,name,avatar','attachments'])
            ->where('conversation_id', $conversationId)
            ->orderBy('created_at', 'asc')
            ->orderBy('id', 'asc');

        if ($cursor) {
            [$ts, $id] = $cursor;
            $q->where(function($w) use ($ts, $id) {
                $w->where('created_at', '>', $ts)
                  ->orWhere(function($w2) use ($ts, $id) {
                      $w2->where('created_at', '=', $ts)->where('id', '>', $id);
                  });
            });
        }

        $items = $q->limit($limit + 1)->get();
        $hasMore = $items->count() > $limit;
        $data = $items->take($limit)->values();

        $nextCursor = null;
        if ($hasMore) {
            $last = $data->last();
            $nextCursor = Cursor::encode($last->created_at, $last->id);
        }

        // Mark delivered for messages not sent by me
        $now = now();
        $toDeliver = $data->filter(fn($m) => $m->sender_id !== $uid)->pluck('id');
        if ($toDeliver->isNotEmpty()) {
            foreach ($toDeliver as $mid) {
                MessageReceipt::updateOrCreate(
                    ['message_id'=>$mid,'user_id'=>$uid],
                    ['delivered_at'=>$now]
                );
                broadcast(new \App\Events\ReceiptUpdated($mid, $uid, $now, null))->toOthers();
            }
        }

        return ApiResponse::data($data, [
            'cursor' => $nextCursor,
            'has_more' => $hasMore,
        ]);
    }

    // Create message (body or attachments)
    public function store(Request $r, int $conversationId) {
        $uid = $r->user()->id;
        $data = $r->validate([
            'body'         => 'nullable|string',
            'reply_to'     => 'nullable|exists:messages,id',
            'forward_from' => 'nullable|exists:messages,id',
            'attachments'  => 'array',          // array of attachment IDs already uploaded (optional)
            'client_uuid'  => 'nullable|uuid',
        ]);

        $isMember = DB::table('conversation_user')
            ->where('conversation_id',$conversationId)->where('user_id',$uid)->exists();
        abort_unless($isMember, 403);

        if (empty($data['body']) && empty($data['attachments'])) {
            return ApiResponse::data(['error'=>'EMPTY_MESSAGE'], [], 422);
        }

        // idempotency by client_uuid (mobile retry)
        if (!empty($data['client_uuid'])) {
            $existing = Message::where('client_uuid', $data['client_uuid'])->first();
            if ($existing) {
                return ApiResponse::data($existing->load('sender:id,name,avatar','attachments'));
            }
        }

        // Create the message, mapping forward_from into forwarded_from_id. If a forward
        // was specified, also build the forward_chain from the original message.
        $forwardChain = null;
        if (!empty($data['forward_from'])) {
            $orig = Message::with('sender')->find($data['forward_from']);
            if ($orig) {
                $forwardChain = $orig->buildForwardChain();
            }
        }

        $msg = Message::create([
            'client_uuid'       => $data['client_uuid'] ?? null,
            'conversation_id'   => $conversationId,
            'sender_id'         => $uid,
            'body'              => $data['body'] ?? null,
            'reply_to'          => $data['reply_to'] ?? null,
            'forwarded_from_id' => $data['forward_from'] ?? null,
            'forward_chain'     => $forwardChain,
            'is_encrypted'      => false,
            // created_at/updated_at handled automatically by Eloquent
        ]);

        // Attach uploaded attachments if provided.  Attachments are polymorphic, so
        // we update the attachable_id/type instead of a message_id column.
        if (!empty($data['attachments'])) {
            Attachment::whereIn('id', $data['attachments'])
                ->update(['attachable_id' => $msg->id, 'attachable_type' => Message::class]);
        }

        // Update conversation updated_at
        Conversation::where('id', $conversationId)->update(['updated_at' => now()]);

        $payload = $msg->load('sender:id,name,avatar','attachments')->toArray();

        // Broadcast new message
        broadcast(new MessageCreated($conversationId, $payload))->toOthers();

        return ApiResponse::data($payload);
    }

    public function destroy(Request $r, int $id) {
        $uid = $r->user()->id;
        $msg = Message::findOrFail($id);
        $isMember = DB::table('conversation_user')
            ->where('conversation_id',$msg->conversation_id)
            ->where('user_id',$uid)->exists();
        abort_unless($isMember, 403);

        // delete-for-everyone policy: allow within e.g., 1 hour or if sender/admin
        if ($msg->sender_id !== $uid) {
            return ApiResponse::data(['error'=>'NOT_OWNER'], [], 403);
        }
        $msg->delete();
        return ApiResponse::data(['ok'=>true]);
    }
}
