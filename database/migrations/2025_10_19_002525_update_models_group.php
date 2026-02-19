<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('groups', function (Blueprint $table) {
            if (!Schema::hasColumn('groups', 'deleted_at')) {
                $table->softDeletes();
            }
        });

        Schema::table('groups', function (Blueprint $table) {
            // Add slug for unique URLs
            if (!Schema::hasColumn('groups', 'slug')) {
                $table->string('slug')->unique()->nullable()->after('id');
            }
            
            // Add type to distinguish between public channels and private groups
            $table->enum('type', ['channel', 'group'])->default('group')->after('slug');
            
            // Rename is_private to is_public for clarity (optional but recommended)
            $table->boolean('is_public')->default(false)->after('type');
            
            // Make invite_code unique since we'll use it for private group access
            // $table->string('invite_code')->unique()->nullable()->change();
        });

        // Generate slugs for existing groups
        \App\Models\Group::whereNull('slug')->each(function ($group) {
            $group->update(['slug' => $group->generateSlug()]);
        });

        // Migrate existing is_private to is_public (invert the logic)
        \App\Models\Group::where('is_private', true)->update(['is_public' => false]);
        \App\Models\Group::where('is_private', false)->update(['is_public' => true]);
        
        // Set type based on is_public (you can adjust this logic)
        \App\Models\Group::where('is_public', true)->update(['type' => 'channel']);
        \App\Models\Group::where('is_public', false)->update(['type' => 'group']);
    }

    public function down(): void
    {
        Schema::table('groups', function (Blueprint $table) {
            $table->dropColumn(['slug', 'type', 'is_public']);
            $table->string('invite_code')->nullable()->change();
        });
    }
};