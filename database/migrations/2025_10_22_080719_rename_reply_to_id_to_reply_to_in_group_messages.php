<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::table('group_messages', function (Blueprint $table) {
            // Rename the column
            $table->renameColumn('reply_to_id', 'reply_to');
            
            // Update the foreign key constraint name if needed
            // (Laravel usually handles this automatically)
        });
    }

    public function down()
    {
        Schema::table('group_messages', function (Blueprint $table) {
            $table->renameColumn('reply_to', 'reply_to_id');
        });
    }
};