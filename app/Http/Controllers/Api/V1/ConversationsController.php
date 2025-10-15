<?php
// app/Http/Controllers/Api/V1/ConversationsController.php
namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Conversation;
use App\Models\Message;
use Illuminate\Http\Request;

class ConversationsController extends Controller
{
    public function index(Request $r)
    {
        $userId = $r->user()->id;

        $conversations = Conversation::with(['userOne:id,name,phone,avatar_path', 'userTwo:id,name,phone,avatar_path'])
            ->where(fn($q)=>$q->where('user_one_id',$userId)->orWhere('user_two_id',$userId))
            ->orderByDesc(
                Message::select('created_at')
                    ->whereColumn('messages.conversation_id','conversations.id')
                    ->latest()
                    ->take(1)
            )
            ->get();

        $data = $conversations->map(function ($c) use ($userId) {
            $last = $c->messages()
                ->where(function($q){
                    $q->whereNull('expires_at')->orWhere('expires_at','>', now());
                })
                ->latest()->first();

            $unread = $c->messages()
                ->whereNull('read_at')
                ->where('sender_id','!=',$userId)
                ->where(function($q){
                    $q->whereNull('expires_at')->orWhere('expires_at','>', now());
                })
                ->where(function($q) use ($userId){
                    $q->whereNull('deleted_for_user_id')->orWhere('deleted_for_user_id','!=',$userId);
                })
                ->count();

            $other = $c->user_one_id == $userId ? $c->userTwo : $c->userOne;

            return [
                'id'               => $c->id,
                'type'             => 'dm',
                'title'            => $other?->name ?: 'DM #'.$c->id,
                'other_user'       => $other,
                'last_message_at'  => optional($last?->created_at)?->toIso8601String(),
                'last_message'     => $this->preview($last),
                'unread'           => $unread,
            ];
        });

        return response()->json(['data' => $data]);
    }

    protected function preview(?Message $m): ?string
    {
        if (!$m) return null;

        // Hide expired or deleted is already handled in query above
        $text = '';
        if ($m->is_encrypted) {
            try { $text = decrypt($m->body); } catch (\Throwable $e) { $text = '(encrypted)'; }
        } else {
            $text = trim((string) $m->body);
        }

        if ($m->attachments()->count() > 0) {
            $attCount = $m->attachments()->count();
            $paperclip = '📎';
            return $text !== '' ? "{$paperclip} {$attCount} • {$text}" : "{$paperclip} {$attCount} attachment".($attCount>1?'s':'');
        }

        if ($text === '') $text = '(no text)';
        if ($m->reply_to) $text = "↩︎ ".$text;

        return mb_strimwidth($text, 0, 140, '…');
    }
}
