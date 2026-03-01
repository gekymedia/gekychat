<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Backfill user_one_id and user_two_id in conversations table from conversation_user pivot table.
 * 
 * This fixes an issue where some DM conversations have NULL user_one_id/user_two_id,
 * causing the API to return other_user with NULL phone numbers (because $other is null).
 * 
 * The conversation_user pivot table is the source of truth for participants,
 * so we populate the denormalized columns from there.
 */
return new class extends Migration
{
    public function up(): void
    {
        // Find all DM conversations (is_group = 0) with NULL user_one_id or user_two_id
        $conversations = DB::table('conversations')
            ->where('is_group', false)
            ->where(function($query) {
                $query->whereNull('user_one_id')
                      ->orWhereNull('user_two_id');
            })
            ->select('id', 'user_one_id', 'user_two_id', 'name')
            ->get();
        
        $updated = 0;
        $skipped = 0;
        
        foreach ($conversations as $conv) {
            // Skip "Saved Messages" conversations - they only have one participant
            if ($conv->name === 'Saved Messages') {
                $skipped++;
                continue;
            }
            
            // Get participants from pivot table
            $participants = DB::table('conversation_user')
                ->where('conversation_id', $conv->id)
                ->orderBy('user_id', 'asc')
                ->pluck('user_id')
                ->toArray();
            
            if (count($participants) < 2) {
                // Not enough participants for a DM, skip
                $skipped++;
                continue;
            }
            
            // Take first two participants (sorted by user_id for consistency)
            $userOneId = $participants[0];
            $userTwoId = $participants[1];
            
            // Only update if values are actually NULL
            $updates = [];
            if ($conv->user_one_id === null) {
                $updates['user_one_id'] = $userOneId;
            }
            if ($conv->user_two_id === null) {
                $updates['user_two_id'] = $userTwoId;
            }
            
            if (!empty($updates)) {
                DB::table('conversations')
                    ->where('id', $conv->id)
                    ->update($updates);
                $updated++;
            }
        }
        
        echo "✅ Backfilled user_one_id/user_two_id for {$updated} conversations (skipped {$skipped})\n";
    }

    public function down(): void
    {
        // This migration is a data fix, not reversible
        // The data was already NULL before, we just populated it
        echo "⚠️ This migration cannot be reversed (data fix only)\n";
    }
};
