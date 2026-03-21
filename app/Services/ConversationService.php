<?php

namespace App\Services;

use App\Models\Conversation;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Single entry point for creating/finding conversations and keeping
 * denormalized user_one_id / user_two_id in sync with conversation_user (pivot).
 *
 * Architecture: conversation_user is the source of truth for membership.
 * For non-group DMs with exactly two pivot members, user_one_id/user_two_id
 * are a maintained cache (min/max of the two user IDs) for legacy queries.
 */
class ConversationService
{
    /**
     * Create or find a deterministic 1:1 conversation (or saved messages when $a === $b).
     */
    public function findOrCreateDirect(int $a, int $b, ?int $createdBy = null): Conversation
    {
        $isSavedMessages = $a === $b;

        if ($isSavedMessages) {
            return DB::transaction(function () use ($a, $createdBy) {
                $existing = Conversation::query()
                    ->savedMessages($a)
                    ->lockForUpdate()
                    ->first();

                if ($existing) {
                    $this->syncDenormalizedPairColumnsFromPivot($existing);

                    return $existing;
                }

                $existing = Conversation::query()->savedMessages($a)->first();
                if ($existing) {
                    $this->syncDenormalizedPairColumnsFromPivot($existing);

                    return $existing;
                }

                $conv = Conversation::create([
                    'is_group' => false,
                    'name' => 'Saved Messages',
                    'created_by' => $createdBy ?? $a,
                    'slug' => 'saved-messages-'.Str::random(8),
                ]);

                $conv->members()->syncWithPivotValues([$a], ['role' => 'member']);
                $conv = $conv->fresh(['members', 'latestMessage']);
                $this->syncDenormalizedPairColumnsFromPivot($conv);

                return $conv;
            });
        }

        $minUserId = min($a, $b);
        $maxUserId = max($a, $b);

        return DB::transaction(function () use ($minUserId, $maxUserId, $a, $b, $createdBy) {
            $existing = Conversation::query()
                ->where('is_group', false)
                ->whereHas('members', function ($q) use ($minUserId) {
                    $q->where('users.id', $minUserId);
                })
                ->whereHas('members', function ($q) use ($maxUserId) {
                    $q->where('users.id', $maxUserId);
                })
                ->whereDoesntHave('members', function ($q) use ($minUserId, $maxUserId) {
                    $q->whereNotIn('users.id', [$minUserId, $maxUserId]);
                })
                ->lockForUpdate()
                ->first();

            if ($existing) {
                $memberCount = $existing->members()->count();
                if ($memberCount === 2) {
                    $this->syncDenormalizedPairColumnsFromPivot($existing);

                    return $existing;
                }
            }

            $existing = Conversation::query()
                ->where('is_group', false)
                ->whereHas('members', function ($q) use ($minUserId) {
                    $q->where('users.id', $minUserId);
                })
                ->whereHas('members', function ($q) use ($maxUserId) {
                    $q->where('users.id', $maxUserId);
                })
                ->whereDoesntHave('members', function ($q) use ($minUserId, $maxUserId) {
                    $q->whereNotIn('users.id', [$minUserId, $maxUserId]);
                })
                ->first();

            if ($existing) {
                $memberCount = $existing->members()->count();
                if ($memberCount === 2) {
                    $this->syncDenormalizedPairColumnsFromPivot($existing);

                    return $existing;
                }
            }

            $conv = Conversation::create([
                'is_group' => false,
                'name' => null,
                'created_by' => $createdBy ?? $a,
                'user_one_id' => $minUserId,
                'user_two_id' => $maxUserId,
            ]);

            $conv->members()->syncWithPivotValues([$a, $b], ['role' => 'member']);
            $conv = $conv->fresh(['members', 'latestMessage']);
            $this->syncDenormalizedPairColumnsFromPivot($conv);

            return $conv;
        });
    }

    public function findOrCreateSavedMessages(int $userId): Conversation
    {
        return $this->findOrCreateDirect($userId, $userId, $userId);
    }

    /**
     * Email / special threads: not the same as findOrCreateDirect (separate row per thread).
     * Membership is pivot-only truth; pair columns set only when exactly two distinct users.
     */
    public function createEmailThreadConversation(
        string $displayName,
        array $metadata,
        int $mailboxUserId,
        ?int $senderUserId
    ): Conversation {
        return DB::transaction(function () use ($displayName, $metadata, $mailboxUserId, $senderUserId) {
            $conv = Conversation::create([
                'is_group' => false,
                'name' => $displayName,
                'metadata' => $metadata,
            ]);

            $ids = array_values(array_unique(array_filter([$mailboxUserId, $senderUserId])));
            $sync = [];
            foreach ($ids as $id) {
                $sync[$id] = ['role' => 'member'];
            }
            $conv->members()->sync($sync);
            $conv = $conv->fresh(['members']);
            $this->syncDenormalizedPairColumnsFromPivot($conv);

            return $conv;
        });
    }

    /**
     * Update user_one_id / user_two_id from conversation_user for non-group rows.
     * - Exactly 2 members: min/max user_id
     * - Else: null (saved messages, email with one user, groups, corrupt)
     */
    public function syncDenormalizedPairColumnsFromPivot(?Conversation $conversation = null): void
    {
        if ($conversation === null) {
            return;
        }

        $conversation->refresh();

        if ($conversation->is_group) {
            $this->applyPairColumns($conversation, null, null);

            return;
        }

        $ids = DB::table('conversation_user')
            ->where('conversation_id', $conversation->id)
            ->orderBy('user_id')
            ->pluck('user_id')
            ->unique()
            ->values();

        if ($ids->count() === 2) {
            $this->applyPairColumns($conversation, (int) $ids[0], (int) $ids[1]);
        } else {
            $this->applyPairColumns($conversation, null, null);
        }
    }

    /**
     * Batch repair: all non-group conversations (optionally limit).
     *
     * @return array{processed: int, changed: int, errors: int}
     */
    public function syncAllDenormalizedColumnsFromPivot(?int $limit = null): array
    {
        $processed = 0;
        $changed = 0;
        $errors = 0;

        $process = function (Conversation $conv) use (&$processed, &$changed, &$errors) {
            try {
                $before = [$conv->user_one_id, $conv->user_two_id];
                $this->syncDenormalizedPairColumnsFromPivot($conv);
                $conv->refresh();
                $after = [$conv->user_one_id, $conv->user_two_id];
                $processed++;
                if ($before !== $after) {
                    $changed++;
                }
            } catch (\Throwable $e) {
                $errors++;
                \Log::warning('ConversationService: sync failed for conversation '.$conv->id.': '.$e->getMessage());
            }
        };

        if ($limit !== null) {
            Conversation::query()->orderBy('id')->limit($limit)->get()->each($process);
        } else {
            Conversation::query()->orderBy('id')->chunkById(100, function ($conversations) use ($process) {
                foreach ($conversations as $conv) {
                    $process($conv);
                }
            });
        }

        return ['processed' => $processed, 'changed' => $changed, 'errors' => $errors];
    }

    private function applyPairColumns(Conversation $conversation, ?int $minId, ?int $maxId): void
    {
        if ($minId !== null && $maxId !== null) {
            $a = min($minId, $maxId);
            $b = max($minId, $maxId);
            if ($conversation->user_one_id !== $a || $conversation->user_two_id !== $b) {
                $conversation->forceFill([
                    'user_one_id' => $a,
                    'user_two_id' => $b,
                ])->saveQuietly();
            }
        } else {
            if ($conversation->user_one_id !== null || $conversation->user_two_id !== null) {
                $conversation->forceFill([
                    'user_one_id' => null,
                    'user_two_id' => null,
                ])->saveQuietly();
            }
        }
    }

    /**
     * Resolve the other user in a 1:1 chat using pivot (works when members() hides soft-deleted users).
     */
    public function resolveOtherUserId(Conversation $conversation, int $viewerUserId): ?int
    {
        if ($conversation->is_group) {
            return null;
        }

        return DB::table('conversation_user')
            ->where('conversation_id', $conversation->id)
            ->where('user_id', '!=', $viewerUserId)
            ->value('user_id');
    }

    public function resolveOtherParticipant(Conversation $conversation, int $viewerUserId): ?User
    {
        if ($conversation->is_group) {
            return null;
        }

        if ($conversation->is_saved_messages) {
            return null;
        }

        $otherId = $this->resolveOtherUserId($conversation, $viewerUserId);
        if (! $otherId) {
            return null;
        }

        $user = User::find($otherId);
        if ($user) {
            return $user;
        }

        return User::withTrashed()->find($otherId);
    }
}
