<?php

namespace App\Services;

use App\Models\Contact;
use App\Models\Conversation;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class BirthdayCelebrantService
{
    /**
     * Mutual contacts: you saved them AND they saved you (registered users only).
     *
     * @return Collection<int, Contact>
     */
    public function mutualContactsWithBirthdayOn(User $viewer, Carbon $date): Collection
    {
        $month = (int) $date->month;
        $day = (int) $date->day;

        return Contact::query()
            ->with(['contactUser:id,name,phone,avatar_path,avatar_url,last_seen_at,dob_month,dob_day'])
            ->where('user_id', $viewer->id)
            ->whereNotNull('contact_user_id')
            ->where(function ($q) {
                $q->whereNull('is_deleted')->orWhere('is_deleted', false);
            })
            ->whereHas('contactUser', function ($q) use ($month, $day) {
                $q->where('dob_month', $month)->where('dob_day', $day);
            })
            ->whereExists(function ($query) use ($viewer) {
                $query->select(DB::raw(1))
                    ->from('contacts as reverse_contacts')
                    ->whereColumn('reverse_contacts.user_id', 'contacts.contact_user_id')
                    ->where('reverse_contacts.contact_user_id', $viewer->id)
                    ->where(function ($q) {
                        $q->whereNull('reverse_contacts.is_deleted')
                            ->orWhere('reverse_contacts.is_deleted', false);
                    });
            })
            ->orderByRaw('LOWER(COALESCE(NULLIF(display_name, ""), ""))')
            ->get();
    }

    /**
     * @return array<string, mixed>
     */
    public function summaryForUser(User $viewer): array
    {
        $today = Carbon::today();
        $yesterday = $today->copy()->subDay();

        $todayContacts = $this->mutualContactsWithBirthdayOn($viewer, $today);
        $yesterdayContacts = $this->mutualContactsWithBirthdayOn($viewer, $yesterday);

        $todayPayload = $todayContacts
            ->map(fn (Contact $c) => $this->toCelebrantPayload($viewer, $c))
            ->values()
            ->all();

        $yesterdayPayload = $yesterdayContacts
            ->map(fn (Contact $c) => $this->toCelebrantPayload($viewer, $c))
            ->values()
            ->all();

        $selfToday = $this->selfBirthdayPayload($viewer, $today);

        $previewAvatars = collect($todayPayload)
            ->pluck('avatar_url')
            ->filter()
            ->take(3)
            ->values()
            ->all();

        $todayCount = count($todayPayload);
        $yesterdayCount = count($yesterdayPayload);
        $dismissKey = 'birthday_banner_'.$today->format('Y-m-d');

        return [
            'dismiss_key' => $dismissKey,
            'today_count' => $todayCount,
            'yesterday_count' => $yesterdayCount,
            'show_banner' => ($todayCount + $yesterdayCount) > 0,
            'preview_avatars' => $previewAvatars,
            'banner_title' => $this->bannerTitle($todayCount, $yesterdayCount),
            'banner_subtitle' => $todayCount > 0
                ? 'Send them a Gift'
                : 'Catch up on yesterday\'s birthdays',
            'today' => $todayPayload,
            'yesterday' => $yesterdayPayload,
            'self_today' => $selfToday,
            'has_birthday_set' => $viewer->dob_month !== null && $viewer->dob_day !== null,
        ];
    }

    private function bannerTitle(int $todayCount, int $yesterdayCount): string
    {
        if ($todayCount > 0) {
            $noun = $todayCount === 1 ? 'contact has a' : 'contacts have';

            return "{$todayCount} {$noun} birthday today";
        }
        if ($yesterdayCount > 0) {
            $noun = $yesterdayCount === 1 ? 'contact had a' : 'contacts had';

            return "{$yesterdayCount} {$noun} birthday yesterday";
        }

        return '';
    }

    /**
     * @return array<string, mixed>|null
     */
    private function selfBirthdayPayload(User $viewer, Carbon $today): ?array
    {
        if ((int) $viewer->dob_month !== (int) $today->month
            || (int) $viewer->dob_day !== (int) $today->day) {
            return null;
        }

        return [
            'user_id' => $viewer->id,
            'conversation_id' => null,
            'name' => $viewer->name ?? 'You',
            'avatar_url' => $viewer->avatar_url,
            'last_seen_label' => 'Treat yourself today',
            'is_self' => true,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function toCelebrantPayload(User $viewer, Contact $contact): array
    {
        $user = $contact->contactUser;
        $name = trim((string) ($contact->display_name ?: $user?->name ?: $user?->phone ?: 'Contact'));

        $conversationId = null;
        if ($user) {
            try {
                $conversationId = Conversation::findOrCreateDirect(
                    $viewer->id,
                    $user->id,
                    $viewer->id
                )->id;
            } catch (\Throwable) {
                $conversationId = null;
            }
        }

        return [
            'user_id' => (int) ($user?->id ?? $contact->contact_user_id),
            'conversation_id' => $conversationId,
            'name' => $name,
            'avatar_url' => $user?->avatar_url,
            'last_seen_label' => $user?->last_seen_formatted
                ?? ($user?->last_seen_at?->diffForHumans()),
            'is_self' => false,
        ];
    }
}
