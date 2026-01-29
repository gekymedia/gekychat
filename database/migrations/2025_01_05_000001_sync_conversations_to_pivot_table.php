<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void {
        // Sync existing conversations to conversation_user pivot table
        // This ensures all conversations have entries in the pivot table
        // for proper relationship usage
        // Using raw DB queries to avoid loading Eloquent models that might reference
        // tables that don't exist yet (e.g., email_messages)
        
        // Skip if conversations table doesn't exist yet (fresh migrations)
        if (!Schema::hasTable('conversations') || !Schema::hasTable('conversation_user')) {
            \Log::info("Skipping conversation sync - tables don't exist yet (fresh migration)");
            return;
        }
        
        $conversations = DB::table('conversations')
            ->where('is_group', false)
            ->whereNotNull('user_one_id')
            ->whereNotNull('user_two_id')
            ->select('id', 'user_one_id', 'user_two_id', 'created_at', 'updated_at')
            ->get();
        
        $synced = 0;
        foreach ($conversations as $conv) {
            // Check if pivot entry exists for user_one
            $existsOne = DB::table('conversation_user')
                ->where('conversation_id', $conv->id)
                ->where('user_id', $conv->user_one_id)
                ->exists();
            
            if (!$existsOne) {
                DB::table('conversation_user')->insert([
                    'conversation_id' => $conv->id,
                    'user_id' => $conv->user_one_id,
                    'role' => 'member',
                    'created_at' => $conv->created_at ?? now(),
                    'updated_at' => $conv->updated_at ?? now(),
                ]);
                $synced++;
            }
            
            // Check if pivot entry exists for user_two
            $existsTwo = DB::table('conversation_user')
                ->where('conversation_id', $conv->id)
                ->where('user_id', $conv->user_two_id)
                ->exists();
            
            if (!$existsTwo) {
                DB::table('conversation_user')->insert([
                    'conversation_id' => $conv->id,
                    'user_id' => $conv->user_two_id,
                    'role' => 'member',
                    'created_at' => $conv->created_at ?? now(),
                    'updated_at' => $conv->updated_at ?? now(),
                ]);
                $synced++;
            }
        }
        
        \Log::info("Synced {$synced} conversation-user relationships to pivot table");
    }

    public function down(): void {
        // Optionally remove pivot entries that were created by this migration
        // For safety, we'll leave them as they're needed for proper functionality
    }
};
