<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Soft-delete never-logged-in accounts that were auto-created via CUG
 * Priority Admissions (or optionally SchoolsGH) platform messaging.
 *
 * Identification (all must match):
 * - In a 1:1 conversation with the CUG bot user (phone 0000000001)
 * - last_login_at IS NULL / total_logins = 0
 * - No Sanctum tokens, no user_sessions
 * - Never sent a non-platform message
 * - name == phone (auto-create fingerprint), unless --relax-name is set
 */
class PurgeNeverLoggedInPlatformUsers extends Command
{
    protected $signature = 'users:purge-never-logged-in-platform
                            {--source=cug : cug|schoolsgh|both}
                            {--execute : Actually soft-delete (default is dry-run)}
                            {--force : Skip confirmation when using --execute}
                            {--relax-name : Do not require name === phone}
                            {--limit=0 : Max users to process (0 = all)}';

    protected $description = 'Soft-delete never-logged-in CUG/SchoolsGH auto-created stub accounts';

    public function handle(): int
    {
        $source = strtolower((string) $this->option('source'));
        $execute = (bool) $this->option('execute');
        $relaxName = (bool) $this->option('relax-name');
        $limit = (int) $this->option('limit');

        if (!in_array($source, ['cug', 'schoolsgh', 'both'], true)) {
            $this->error('Invalid --source. Use cug, schoolsgh, or both.');
            return 1;
        }

        if (!$execute) {
            $this->warn('DRY RUN — no accounts will be deleted. Pass --execute to apply.');
        } else {
            $this->warn('EXECUTE MODE — matching accounts will be soft-deleted and phones freed.');
        }

        $botPhones = [];
        if ($source === 'cug' || $source === 'both') {
            $botPhones[] = '0000000001';
        }
        if ($source === 'schoolsgh' || $source === 'both') {
            $botPhones[] = '0000000004';
        }

        $botIds = User::query()
            ->whereIn('phone', $botPhones)
            ->pluck('id', 'phone');

        if ($botIds->isEmpty()) {
            $this->error('No platform bot users found for source=' . $source);
            return 1;
        }

        foreach ($botIds as $phone => $id) {
            $this->info("Bot {$phone} => user_id {$id}");
        }

        $candidates = $this->findCandidates($botIds->values()->all(), $relaxName);
        if ($limit > 0) {
            $candidates = array_slice($candidates, 0, $limit);
        }

        $this->info('Candidates: ' . count($candidates));
        if (empty($candidates)) {
            return 0;
        }

        $rows = array_map(static function ($u) {
            return [
                $u->id,
                $u->phone,
                $u->name,
                (string) $u->created_at,
                $u->last_seen_at ? (string) $u->last_seen_at : '-',
            ];
        }, $candidates);
        $this->table(['id', 'phone', 'name', 'created_at', 'last_seen_at'], array_slice($rows, 0, 30));
        if (count($rows) > 30) {
            $this->line('… and ' . (count($rows) - 30) . ' more');
        }

        if (!$execute) {
            $this->info('Dry run complete. Re-run with --execute to soft-delete these accounts.');
            return 0;
        }

        if (!$this->option('force') && !$this->confirm('Soft-delete ' . count($candidates) . ' accounts and free their phone numbers?', false)) {
            $this->warn('Aborted.');
            return 1;
        }

        $deleted = 0;
        $failed = 0;
        $logPath = storage_path('logs/purge_never_logged_in_' . now()->format('Ymd_His') . '.csv');
        $fh = fopen($logPath, 'w');
        fputcsv($fh, ['id', 'old_phone', 'old_name', 'created_at', 'new_phone']);

        DB::beginTransaction();
        try {
            foreach ($candidates as $user) {
                $oldPhone = $user->phone;
                $oldName = $user->name;
                $digits = preg_replace('/\D+/', '', (string) $oldPhone) ?: '0';
                // phone is varchar(20) — keep anonymized value short and unique
                $newPhone = 'd' . $user->id . '_' . substr($digits, -9);
                if (strlen($newPhone) > 20) {
                    $newPhone = 'd' . $user->id . '_' . substr(md5($digits), 0, max(1, 18 - strlen((string) $user->id)));
                }

                // Free the unique phone so the number can register organically later.
                $user->phone = $newPhone;
                if ($user->name === $oldPhone) {
                    $user->name = $newPhone;
                }
                $user->save();
                $user->delete(); // SoftDeletes

                fputcsv($fh, [$user->id, $oldPhone, $oldName, (string) $user->created_at, $newPhone]);
                $deleted++;
            }
            DB::commit();
        } catch (\Throwable $e) {
            DB::rollBack();
            fclose($fh);
            $this->error('Purge failed, rolled back: ' . $e->getMessage());
            return 1;
        }
        fclose($fh);

        $this->info("Soft-deleted {$deleted} accounts (failed={$failed}).");
        $this->info("Audit log: {$logPath}");

        return 0;
    }

    /**
     * @param  list<int>  $botIds
     * @return list<User>
     */
    protected function findCandidates(array $botIds, bool $relaxName): array
    {
        $placeholders = implode(',', array_fill(0, count($botIds), '?'));
        $sql = "
            SELECT DISTINCT u.id
            FROM users u
            INNER JOIN conversation_user cu ON cu.user_id = u.id
            INNER JOIN conversation_user cu2
                ON cu2.conversation_id = cu.conversation_id
               AND cu2.user_id IN ({$placeholders})
            WHERE u.id NOT IN ({$placeholders})
              AND u.deleted_at IS NULL
              AND u.last_login_at IS NULL
              AND (u.total_logins IS NULL OR u.total_logins = 0)
              AND u.phone NOT IN ('0000000000','0000000001','0000000002','0000000004')
              AND (u.is_admin IS NULL OR u.is_admin = 0)
        ";
        $params = array_merge($botIds, $botIds);
        $ids = collect(DB::select($sql, $params))->pluck('id')->map(fn ($id) => (int) $id)->all();

        if (empty($ids)) {
            return [];
        }

        $users = User::query()->whereIn('id', $ids)->orderBy('id')->get();
        $out = [];
        foreach ($users as $user) {
            if (!$relaxName && $user->name !== $user->phone) {
                continue;
            }
            if ($this->hasAuthActivity($user->id)) {
                continue;
            }
            if ($this->hasSentUserMessage($user->id)) {
                continue;
            }
            $out[] = $user;
        }

        return $out;
    }

    protected function hasAuthActivity(int $userId): bool
    {
        $hasToken = DB::table('personal_access_tokens')
            ->where('tokenable_type', User::class)
            ->where('tokenable_id', $userId)
            ->exists();
        if ($hasToken) {
            return true;
        }

        if (Schema::hasTable('user_sessions')) {
            return DB::table('user_sessions')->where('user_id', $userId)->exists();
        }

        return false;
    }

    protected function hasSentUserMessage(int $userId): bool
    {
        return DB::table('messages')
            ->where('sender_id', $userId)
            ->where(function ($q) {
                $q->whereNull('sender_type')->orWhere('sender_type', '!=', 'platform');
            })
            ->exists();
    }
}
