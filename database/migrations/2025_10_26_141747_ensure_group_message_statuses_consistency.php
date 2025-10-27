<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        // Ensure all existing group messages have status entries for their senders
        $messages = \App\Models\GroupMessage::whereDoesntHave('statuses', function ($query) {
            $query->whereColumn('user_id', 'group_messages.sender_id');
        })->get();

        foreach ($messages as $message) {
            $message->statuses()->create([
                'user_id' => $message->sender_id,
                'status' => \App\Models\GroupMessageStatus::STATUS_SENT,
            ]);
        }
    }

    public function down()
    {
        // No rollback needed
    }
};