<?php

namespace App\Console\Commands;

use App\Models\Conversation;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Soft-delete empty stub DMs that were auto-seeded onto an admin account.
 *
 * Keeps:
 * - DMs with any messages
 * - DMs with genuine partners (phone verified, not bot/system, not owner cluster)
 * - DMs with bots/system accounts
 * - DMs with the owner's other phone accounts
 * - Saved Messages
 */
class PurgeEmptyStubAdminDms extends Command
{
    protected $signature = 'conversations:purge-empty-stub-admin-dms
                            {--phone=0248229540 : Admin phone whose empty stub DMs should be purged}
                            {--execute : Actually soft-delete (default is dry-run)}
                            {--force : Skip confirmation when using --execute}';

    protected $description = 'Soft-delete empty auto-seeded stub DMs for an admin phone, keeping genuine and messaged chats';

    public function handle(): int
    {
        $phone = (string) $this->option('phone');
        $execute = (bool) $this->option('execute');

        $owner = User::where('phone', $phone)->first();
        if (!$owner) {
            $this->error("No user found for phone {$phone}");
            return 1;
        }

        if (!$execute) {
            $this->warn('DRY RUN — no conversations will be deleted. Pass --execute to apply.');
        } else {
            $this->warn('EXECUTE MODE — matching empty stub DMs will be soft-deleted.');
            if (!$this->option('force') && !$this->confirm("Purge empty stub DMs for {$phone} (user #{$owner->id})?")) {
                $this->info('Aborted.');
                return 0;
            }
        }

        $ownerId = (int) $owner->id;
        $botPhones = ['0000000000', '0000000001', '0000000002', '0000000003', '0000000004', '0000000005'];
        $ownerCluster = User::query()
            ->whereIn('phone', ['0248229540', '0245790807', '0242186025', '0209591149', '0205440495'])
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->all();

        $convIds = DB::table('conversation_user as cu')
            ->join('conversations as c', 'c.id', '=', 'cu.conversation_id')
            ->where('cu.user_id', $ownerId)
            ->where('c.is_group', 0)
            ->whereNull('c.deleted_at')
            ->pluck('c.id')
            ->map(fn ($id) => (int) $id)
            ->all();

        $keep = 0;
        $deleteIds = [];

        foreach ($convIds as $convId) {
            $otherId = DB::table('conversation_user')
                ->where('conversation_id', $convId)
                ->where('user_id', '!=', $ownerId)
                ->value('user_id');

            $msgCount = (int) DB::table('messages')->where('conversation_id', $convId)->count();

            if (!$otherId) {
                $keep++;
                continue; // Saved Messages / self
            }

            $other = User::withTrashed()->find($otherId);
            if (!$other) {
                $deleteIds[] = $convId;
                continue;
            }

            $otherPhone = (string) $other->phone;
            $isBot = in_array($otherPhone, $botPhones, true) || str_starts_with($otherPhone, '000000');
            $isOwn = in_array((int) $other->id, $ownerCluster, true);
            $genuine = !empty($other->phone_verified_at) && !$isBot && !$isOwn && !empty($other->last_seen_at);

            if ($msgCount > 0 || $genuine || $isBot || $isOwn) {
                $keep++;
                continue;
            }

            $deleteIds[] = $convId;
        }

        $this->info("Owner #{$ownerId} ({$phone})");
        $this->info('Total DMs: ' . count($convIds));
        $this->info('Keep: ' . $keep);
        $this->info('Soft-delete candidates: ' . count($deleteIds));

        if (!$execute || empty($deleteIds)) {
            return 0;
        }

        $deleted = 0;
        foreach (array_chunk($deleteIds, 100) as $chunk) {
            $deleted += Conversation::whereIn('id', $chunk)->delete();
        }

        $remaining = DB::table('conversation_user as cu')
            ->join('conversations as c', 'c.id', '=', 'cu.conversation_id')
            ->where('cu.user_id', $ownerId)
            ->where('c.is_group', 0)
            ->whereNull('c.deleted_at')
            ->count();

        $this->info("Soft-deleted: {$deleted}");
        $this->info("Remaining DMs for owner: {$remaining}");

        return 0;
    }
}
