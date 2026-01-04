<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;
use App\Models\Conversation;
use App\Models\User;

return new class extends Migration {
    public function up(): void {
        // Sync existing conversations to conversation_user pivot table
        // This ensures all conversations have entries in the pivot table
        // for proper relationship usage
        
        $conversations = Conversation::where('is_group', false)
            ->whereNotNull('user_one_id')
            ->whereNotNull('user_two_id')
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
