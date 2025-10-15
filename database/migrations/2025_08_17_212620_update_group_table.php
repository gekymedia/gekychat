<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        // Add new columns to groups table
        Schema::table('groups', function (Blueprint $table) {
            if (!Schema::hasColumn('groups', 'is_private')) {
                $table->boolean('is_private')->default(false)->after('avatar_path');
            }
            if (!Schema::hasColumn('groups', 'invite_code')) {
                $table->string('invite_code', 10)->unique()->nullable()->after('is_private');
            }
        });

        // Add new columns to group_members table
        Schema::table('group_members', function (Blueprint $table) {
            if (!Schema::hasColumn('group_members', 'joined_at')) {
                $table->timestamp('joined_at')->useCurrent()->after('role');
            }
        });

        // Add new columns to group_messages table
        Schema::table('group_messages', function (Blueprint $table) {
            if (!Schema::hasColumn('group_messages', 'edited_at')) {
                $table->timestamp('edited_at')->nullable()->after('read_at');
            }
        });

        // Only create reactions table if it doesn't exist
        if (!Schema::hasTable('group_message_reactions')) {
            Schema::create('group_message_reactions', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('group_message_id');
                $table->unsignedBigInteger('user_id');
                $table->string('emoji', 4);
                $table->timestamps();

                $table->foreign('group_message_id')
                    ->references('id')
                    ->on('group_messages')
                    ->onDelete('cascade');
                    
                $table->foreign('user_id')
                    ->references('id')
                    ->on('users')
                    ->onDelete('cascade');
                
                $table->unique(['group_message_id', 'user_id', 'emoji']);
            });
        }

        // Check if attachments table needs any modifications
        Schema::table('attachments', function (Blueprint $table) {
            // Add any missing columns to your existing attachments table
            if (!Schema::hasColumn('attachments', 'size')) {
                $table->unsignedInteger('size')->nullable()->after('mime_type');
            }
            // Add any other missing columns you might need
        });
    }

    public function down(): void
    {
        // Reverse only the changes we made - don't drop existing tables
        Schema::table('groups', function (Blueprint $table) {
            $table->dropColumn(['is_private', 'invite_code']);
        });

        Schema::table('group_members', function (Blueprint $table) {
            $table->dropColumn('joined_at');
        });

        Schema::table('group_messages', function (Blueprint $table) {
            $table->dropColumn('edited_at');
        });

        // Only drop the reactions table if it exists
        if (Schema::hasTable('group_message_reactions')) {
            Schema::dropIfExists('group_message_reactions');
        }

        // Don't modify the existing attachments table in the down() method
    }
};