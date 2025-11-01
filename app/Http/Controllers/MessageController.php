<?php

namespace App\Http\Controllers;

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
    \Log::info('=== MESSAGE CREATION START ===', [
        'user_id' => $r->user()->id,
        'conversation_id' => $conversationId,
        'data' => $r->all()
    ]);

    $uid = $r->user()->id;
    $data = $r->validate([
        'body'         => 'nullable|string',
        'reply_to'     => 'nullable|exists:messages,id',
        'forward_from' => 'nullable|exists:messages,id',
        'attachments'  => 'array',
        'client_uuid'  => 'nullable|uuid',
    ]);

    $isMember = DB::table('conversation_user')
        ->where('conversation_id',$conversationId)->where('user_id',$uid)->exists();
    abort_unless($isMember, 403);

    if (empty($data['body']) && empty($data['attachments'])) {
        return ApiResponse::data(['error'=>'EMPTY_MESSAGE'], [], 422);
    }

    // idempotency by client_uuid
    if (!empty($data['client_uuid'])) {
        $existing = Message::where('client_uuid', $data['client_uuid'])->first();
        if ($existing) {
            return ApiResponse::data($existing->load('sender:id,name,avatar','attachments'));
        }
    }

    // Create the message
    $forwardChain = null;
    if (!empty($data['forward_from'])) {
        $orig = Message::with('sender')->find($data['forward_from']);
        if ($orig) {
            $forwardChain = $orig->buildForwardChain();
        }
    }

    DB::beginTransaction();
    
    try {
        \Log::info('Creating message record...');
        
        $msg = Message::create([
            'client_uuid'       => $data['client_uuid'] ?? null,
            'conversation_id'   => $conversationId,
            'sender_id'         => $uid,
            'body'              => $data['body'] ?? null,
            'reply_to'          => $data['reply_to'] ?? null,
            'forwarded_from_id' => $data['forward_from'] ?? null,
            'forward_chain'     => $forwardChain,
            'is_encrypted'      => false,
        ]);

        \Log::info('Message created successfully', ['message_id' => $msg->id]);

        // Attach uploaded attachments
        if (!empty($data['attachments'])) {
            Attachment::whereIn('id', $data['attachments'])
                ->update(['attachable_id' => $msg->id, 'attachable_type' => Message::class]);
            \Log::info('Attachments attached', ['attachment_count' => count($data['attachments'])]);
        }

        // ✅ Create initial status for sender
        \Log::info('Creating message status...', ['message_id' => $msg->id, 'user_id' => $uid]);
        
        $status = \App\Models\MessageStatus::create([
            'message_id' => $msg->id,
            'user_id'    => $uid,
            'status'     => 'sent'
        ]);

        \Log::info('Message status created successfully', ['status_id' => $status->id]);

        // Update conversation
        Conversation::where('id', $conversationId)->update(['updated_at' => now()]);

        DB::commit();
        \Log::info('=== MESSAGE CREATION SUCCESS ===', ['message_id' => $msg->id]);

        $payload = $msg->load('sender:id,name,avatar','attachments')->toArray();

       // With this:
broadcast(new \App\Events\MessageSent($msg))->toOthers();
        return ApiResponse::data($payload);

    } catch (\Exception $e) {
        DB::rollback();
        \Log::error('=== MESSAGE CREATION FAILED ===', [
            'error' => $e->getMessage(),
            'trace' => $e->getTraceAsString()
        ]);
        throw $e;
    }
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
