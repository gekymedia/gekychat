<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Backfill channel_followers table from group_members for existing channels.
     * 
     * This migration ensures that all existing channel members are also recorded
     * in the channel_followers table for consistency. Going forward, both tables
     * will be updated when users join/leave channels.
     */
    public function up(): void
    {
        // Get all channels (groups with type='channel')
        $channels = DB::table('groups')
            ->where('type', 'channel')
            ->pluck('id');

        if ($channels->isEmpty()) {
            return;
        }

        // Get all members of channels from group_members table
        $channelMembers = DB::table('group_members')
            ->whereIn('group_id', $channels)
            ->get();

        // Insert into channel_followers if not already exists
        foreach ($channelMembers as $member) {
            $exists = DB::table('channel_followers')
                ->where('channel_id', $member->group_id)
                ->where('user_id', $member->user_id)
                ->exists();

            if (!$exists) {
                DB::table('channel_followers')->insert([
                    'channel_id' => $member->group_id,
                    'user_id' => $member->user_id,
                    'followed_at' => $member->joined_at ?? now(),
                    'muted_until' => null,
                ]);
            }
        }
    }

    /**
     * Reverse the migration - remove backfilled channel_followers entries.
     * Note: This only removes entries that were backfilled, not new ones created after this migration.
     */
    public function down(): void
    {
        // Get all channels
        $channels = DB::table('groups')
            ->where('type', 'channel')
            ->pluck('id');

        if ($channels->isEmpty()) {
            return;
        }

        // Get all members from group_members for these channels
        $memberPairs = DB::table('group_members')
            ->whereIn('group_id', $channels)
            ->select('group_id', 'user_id')
            ->get();

        // Delete matching entries from channel_followers
        foreach ($memberPairs as $pair) {
            DB::table('channel_followers')
                ->where('channel_id', $pair->group_id)
                ->where('user_id', $pair->user_id)
                ->delete();
        }
    }
};
