<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up()
    {
        // Add slug to users table if missing
        if (!Schema::hasColumn('users', 'slug')) {
            Schema::table('users', function (Blueprint $table) {
                $table->string('slug')->unique()->nullable()->after('name');
            });
        }

        // Add slug to conversations table if missing
        if (!Schema::hasColumn('conversations', 'slug')) {
            Schema::table('conversations', function (Blueprint $table) {
                $table->string('slug')->unique()->nullable()->after('name');
            });
        }

        // Add slug to groups table if missing  
        if (!Schema::hasColumn('groups', 'slug')) {
            Schema::table('groups', function (Blueprint $table) {
                $table->string('slug')->unique()->nullable()->after('name');
            });
        }

        // Generate slugs for existing records
        $this->generateSlugsForExistingRecords();
    }

    public function down()
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('slug');
        });

        Schema::table('conversations', function (Blueprint $table) {
            $table->dropColumn('slug');
        });

        Schema::table('groups', function (Blueprint $table) {
            $table->dropColumn('slug');
        });
    }

    private function generateSlugsForExistingRecords()
    {
        // Generate slugs for existing users
        \App\Models\User::whereNull('slug')->get()->each(function ($user) {
            $user->slug = $user->generateSlug();
            $user->save();
        });

        // Generate slugs for existing conversations
        \App\Models\Conversation::whereNull('slug')->get()->each(function ($conversation) {
            $conversation->slug = $conversation->generateSlug();
            $conversation->save();
        });

        // Generate slugs for existing groups
        \App\Models\Group::whereNull('slug')->get()->each(function ($group) {
            $group->slug = $group->generateSlug();
            $group->save();
        });
    }
};