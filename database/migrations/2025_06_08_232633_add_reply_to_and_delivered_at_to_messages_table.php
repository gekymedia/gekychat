<?php
// Filename: YYYY_MM_DD_HHMMSS_add_reply_to_and_delivered_at_to_messages_table.php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddReplyToAndDeliveredAtToMessagesTable extends Migration
{
    public function up()
    {
        Schema::table('messages', function (Blueprint $table) {
            $table->foreignId('reply_to')
                ->nullable()
                ->after('body')
                ->constrained('messages')
                ->nullOnDelete();
                
            $table->timestamp('delivered_at')
                ->nullable()
                ->after('read_at');
        });
    }

    public function down()
    {
        Schema::table('messages', function (Blueprint $table) {
            $table->dropForeign(['reply_to']);
            $table->dropColumn(['reply_to', 'delivered_at']);
        });
    }
}