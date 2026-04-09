<?php

namespace App\Services;

use App\Models\Contact;
use App\Models\InAppNotice;
use App\Models\InAppNoticeDismissal;
use App\Models\User;
use Illuminate\Support\Collection;

class InAppNoticeService
{
    /**
     * Active notices for the user (not dismissed, in date window, is_active).
     *
     * @return Collection<int, InAppNotice>
     */
    public function activeForUser(User $user, array $context = []): Collection
    {
        $now = now();

        $dismissedKeys = InAppNoticeDismissal::query()
            ->where('user_id', $user->id)
            ->pluck('notice_key');

        $items = InAppNotice::query()
            ->where('is_active', true)
            ->where(function ($q) use ($now) {
                $q->whereNull('starts_at')
                    ->orWhere('starts_at', '<=', $now);
            })
            ->where(function ($q) use ($now) {
                $q->whereNull('ends_at')
                    ->orWhere('ends_at', '>=', $now);
            })
            ->whereNotIn('notice_key', $dismissedKeys)
            ->orderByDesc('sort_order')
            ->orderBy('id')
            ->get();

        return $items->filter(function (InAppNotice $notice) use ($user, $context) {
            return $this->passesCondition($notice, $user, $context);
        })->values();
    }

    private function passesCondition(InAppNotice $notice, User $user, array $context): bool
    {
        $type = trim((string) ($notice->condition_type ?? ''));
        if ($type === '' || $type === 'always') {
            return true;
        }

        if ($type === 'birthday_contact_today') {
            $now = now();
            return Contact::query()
                ->where('user_id', $user->id)
                ->where(function ($q) {
                    $q->whereNull('is_deleted')->orWhere('is_deleted', false);
                })
                ->whereNotNull('contact_user_id')
                ->whereHas('contactUser', function ($q) use ($now) {
                    $q->where('dob_month', (int) $now->month)
                        ->where('dob_day', (int) $now->day);
                })
                ->exists();
        }

        if ($type === 'device_storage_low') {
            $flag = $context['device_storage_low'] ?? false;
            return $flag === true;
        }

        return true;
    }

    public function dismiss(User $user, string $noticeKey): void
    {
        $noticeKey = trim($noticeKey);
        if ($noticeKey === '') {
            return;
        }

        InAppNoticeDismissal::query()->updateOrCreate(
            [
                'user_id' => $user->id,
                'notice_key' => $noticeKey,
            ],
            []
        );
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function toApiPayloads(Collection $notices): array
    {
        return $notices->map(function (InAppNotice $n) {
            return [
                'id' => $n->id,
                'notice_key' => $n->notice_key,
                'title' => $n->title,
                'body' => $n->body,
                'style' => $n->style,
                'action_label' => $n->action_label,
                'action_url' => $n->action_url,
                'condition_type' => $n->condition_type,
            ];
        })->values()->all();
    }
}
