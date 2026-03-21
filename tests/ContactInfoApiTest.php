<?php

/**
 * Test script to verify how the API sends other_user data for contact info screen.
 *
 * Run from project root:
 *   php artisan tinker
 *   include 'tests/ContactInfoApiTest.php';
 *   (new ContactInfoApiTest())->inspectConversationsResponse(1);
 *   (new ContactInfoApiTest())->findConversationsWithNullOtherUser();
 */

use App\Models\Conversation;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class ContactInfoApiTest
{
    /**
     * Inspect how conversations API structures other_user for a given user.
     * Call: (new \Tests\ContactInfoApiTest())->inspectConversationsResponse(1);
     */
    public function inspectConversationsResponse(int $userId): array
    {
        $user = User::find($userId);
        if (!$user) {
            return ['error' => "User $userId not found"];
        }

        $convs = $user->conversations()
            ->with([
                'userOne:id,name,phone,username,avatar_path,last_seen_at',
                'userTwo:id,name,phone,username,avatar_path,last_seen_at',
                'members:id,name,phone,username,avatar_path,last_seen_at',
            ])
            ->whereNull('conversation_user.archived_at')
            ->limit(5)
            ->get();

        $results = [];
        foreach ($convs as $c) {
            $u = $user->id;

            // Mirror ConversationController logic
            $other = $c->otherParticipant($u);
            if (!$other) {
                $other = $c->user_one_id === $u ? $c->userTwo : $c->userOne;
            }
            if (!$other && !$c->is_group) {
                $otherUserId = DB::table('conversation_user')
                    ->where('conversation_id', $c->id)
                    ->where('user_id', '!=', $u)
                    ->value('user_id');
                if ($otherUserId) {
                    $other = User::find($otherUserId);
                }
            }

            $results[] = [
                'conversation_id' => $c->id,
                'is_group' => $c->is_group,
                'is_saved_messages' => $c->is_saved_messages ?? false,
                'user_one_id' => $c->user_one_id,
                'user_two_id' => $c->user_two_id,
                'other_resolved' => $other ? [
                    'id' => $other->id,
                    'name' => $other->name,
                    'phone' => $other->phone,
                    'deleted_at' => $other->deleted_at?->toISOString(),
                ] : null,
                'members_count' => $c->members()->count(),
                'members_ids' => $c->members()->pluck('users.id')->toArray(),
            ];
        }

        return [
            'user_id' => $userId,
            'conversations' => $results,
        ];
    }

    /**
     * Find conversations where other_user would be null (potential bugs).
     */
    public function findConversationsWithNullOtherUser(): array
    {
        $issues = [];

        $convs = Conversation::where('is_group', false)
            ->whereNull('name') // Exclude saved messages which have name
            ->orWhere('name', '!=', 'Saved Messages')
            ->limit(100)
            ->get();

        foreach ($convs as $c) {
            // Get a participant to test with
            $memberIds = DB::table('conversation_user')
                ->where('conversation_id', $c->id)
                ->pluck('user_id')
                ->toArray();

            foreach ($memberIds as $uid) {
                $other = $c->otherParticipant($uid);
                if (!$other) {
                    $other = $c->user_one_id == $uid ? $c->userTwo : $c->userOne;
                }
                if (!$other) {
                    $oid = DB::table('conversation_user')
                        ->where('conversation_id', $c->id)
                        ->where('user_id', '!=', $uid)
                        ->value('user_id');
                    if ($oid) {
                        $other = User::withTrashed()->find($oid);
                    }
                }

                if (!$other || $other->id == $uid) {
                    $issues[] = [
                        'conversation_id' => $c->id,
                        'viewer_user_id' => $uid,
                        'user_one_id' => $c->user_one_id,
                        'user_two_id' => $c->user_two_id,
                        'pivot_user_ids' => $memberIds,
                        'other_is_null' => !$other,
                        'other_is_self' => $other && $other->id == $uid,
                    ];
                }
            }
        }

        return ['issues' => $issues, 'count' => count($issues)];
    }
}
