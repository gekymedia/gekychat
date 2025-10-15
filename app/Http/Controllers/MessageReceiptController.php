<?php

// namespace App\Http\Controllers;

// use App\Models\Message;
// use App\Models\MessageReceipt;
// use App\Support\ApiResponse;
// use Illuminate\Http\Request;
// use Illuminate\Support\Facades\DB;

// class MessageReceiptController extends Controller
// {
//     public function store(Request $request, int $id) {
//         $data = $request->validate([
//             'type' => 'required|in:delivered,read',
//         ]);

//         $userId = $request->user()->id;
//         $receipt = MessageReceipt::firstOrCreate(['message_id' => $id, 'user_id' => $userId]);

//         $now = now();
//         if ($data['type'] === 'delivered' && !$receipt->delivered_at) {
//             $receipt->delivered_at = $now;
//         }
//         if ($data['type'] === 'read' && !$receipt->read_at) {
//             $receipt->read_at = $now;

//             $msg = Message::findOrFail($id);
//             DB::table('conversation_user')
//                 ->where('conversation_id', $msg->conversation_id)
//                 ->where('user_id', $userId)
//                 ->update(['last_read_message_id' => $msg->id]);
//         }
//         $receipt->save();

//         // Broadcast receipt.updated (event defined below)
//         broadcast(new \App\Events\ReceiptUpdated($id, $userId, $receipt->delivered_at, $receipt->read_at))->toOthers();

//         return ApiResponse::data([
//             'message_id'   => $id,
//             'user_id'      => $userId,
//             'delivered_at' => $receipt->delivered_at,
//             'read_at'      => $receipt->read_at,
//         ]);
//     }
// }


namespace App\Http\Controllers;

use App\Models\Message;
use App\Models\MessageReceipt;
use App\Support\ApiResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class MessageReceiptController extends Controller
{
    public function store(Request $request, int $id) {
        $data = $request->validate([
            'type' => 'required|in:delivered,read',
        ]);

        $userId = $request->user()->id;
        $receipt = MessageReceipt::firstOrCreate(['message_id' => $id, 'user_id' => $userId]);

        $now = now();
        if ($data['type'] === 'delivered' && !$receipt->delivered_at) {
            $receipt->delivered_at = $now;
        }
        if ($data['type'] === 'read' && !$receipt->read_at) {
            $receipt->read_at = $now;
            $msg = Message::findOrFail($id);
            DB::table('conversation_user')
                ->where('conversation_id', $msg->conversation_id)
                ->where('user_id', $userId)
                ->update(['last_read_message_id' => $msg->id]);
        }
        $receipt->save();

        broadcast(new \App\Events\ReceiptUpdated($id, $userId, $receipt->delivered_at, $receipt->read_at))->toOthers();

        return ApiResponse::data([
            'message_id'   => $id,
            'user_id'      => $userId,
            'delivered_at' => $receipt->delivered_at,
            'read_at'      => $receipt->read_at,
        ]);
    }
}
