<?php
// Filename: YYYY_MM_DD_HHMMSS_add_more_fields_to_message_attachments_table.php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddMoreFieldsToMessageAttachmentsTable extends Migration
{
    public function up()
    {
        Schema::table('message_attachments', function (Blueprint $table) {
            $table->integer('file_size')->nullable()->after('file_type');
            $table->string('original_name')->nullable()->after('file_size');
            $table->string('dimensions')->nullable()->after('original_name');
        });
    }

    public function down()
    {
        Schema::table('message_attachments', function (Blueprint $table) {
            $table->dropColumn(['file_size', 'original_name', 'dimensions']);
        });
    }
}