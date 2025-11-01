<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::table('messages', function (Blueprint $table) {
            $table->json('location_data')->nullable()->after('body');
            $table->json('contact_data')->nullable()->after('location_data');
        });

        Schema::table('group_messages', function (Blueprint $table) {
            $table->json('location_data')->nullable()->after('body');
            $table->json('contact_data')->nullable()->after('location_data');
        });
    }

    public function down()
    {
        Schema::table('messages', function (Blueprint $table) {
            $table->dropColumn(['location_data', 'contact_data']);
        });

        Schema::table('group_messages', function (Blueprint $table) {
            $table->dropColumn(['location_data', 'contact_data']);
        });
    }
};