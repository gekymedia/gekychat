<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('conversations', function (Blueprint $table) {
            // Description of the conversation / channel
            if (!Schema::hasColumn('conversations', 'description')) {
                $table->text('description')->nullable()->after('name');
            }

            // Optional avatar image
            if (!Schema::hasColumn('conversations', 'avatar_path')) {
                $table->string('avatar_path')->nullable()->after('description');
            }

            // Public vs private groups
            if (!Schema::hasColumn('conversations', 'is_private')) {
                $table->boolean('is_private')->default(false)->after('is_group');
            }

            // Invite code for private groups
            if (!Schema::hasColumn('conversations', 'invite_code')) {
                $table->string('invite_code')->nullable()->unique();
            }

            // Slug for pretty URLs
            if (!Schema::hasColumn('conversations', 'slug')) {
                $table->string('slug')->nullable()->unique();
            }

            // Creator user (nullable to avoid breaking existing rows)
            if (!Schema::hasColumn('conversations', 'created_by')) {
                $table->foreignId('created_by')
                    ->nullable()
                    ->constrained('users')
                    ->nullOnDelete();
            }
        });
    }

    public function down(): void
    {
        Schema::table('conversations', function (Blueprint $table) {
            if (Schema::hasColumn('conversations', 'created_by')) {
                $table->dropConstrainedForeignId('created_by');
            }
            if (Schema::hasColumn('conversations', 'slug')) {
                $table->dropColumn('slug');
            }
            if (Schema::hasColumn('conversations', 'invite_code')) {
                $table->dropColumn('invite_code');
            }
            if (Schema::hasColumn('conversations', 'is_private')) {
                $table->dropColumn('is_private');
            }
            if (Schema::hasColumn('conversations', 'avatar_path')) {
                $table->dropColumn('avatar_path');
            }
            if (Schema::hasColumn('conversations', 'description')) {
                $table->dropColumn('description');
            }
        });
    }
};
