<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('status_privacy_settings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade')->unique();
            $table->enum('privacy', ['everyone', 'contacts', 'contacts_except', 'only_share_with'])->default('contacts');
            $table->json('excluded_user_ids')->nullable();
            $table->json('included_user_ids')->nullable();
            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('status_privacy_settings');
    }
};

