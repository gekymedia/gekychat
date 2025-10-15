<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::table('messages', function (Blueprint $table) {
            $table->foreignId('deleted_for_user_id')
                  ->nullable()
                  ->constrained('users')
                  ->onDelete('cascade');
        });

        Schema::table('group_messages', function (Blueprint $table) {
            $table->foreignId('deleted_for_user_id')
                  ->nullable()
                  ->constrained('users')
                  ->onDelete('cascade');
        });
    }

    public function down()
    {
        Schema::table('messages', function (Blueprint $table) {
            $table->dropForeign(['deleted_for_user_id']);
            $table->dropColumn('deleted_for_user_id');
        });

        Schema::table('group_messages', function (Blueprint $table) {
            $table->dropForeign(['deleted_for_user_id']);
            $table->dropColumn('deleted_for_user_id');
        });
    }
};