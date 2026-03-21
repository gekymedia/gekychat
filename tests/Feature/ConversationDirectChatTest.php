<?php

namespace Tests\Feature;

use App\Models\Conversation;
use App\Models\User;
use App\Services\ConversationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * ConversationService / findOrCreateDirect: pivot is source of truth; pair columns stay in sync.
 */
class ConversationDirectChatTest extends TestCase
{
    use RefreshDatabase;

    private function makeUser(): User
    {
        return User::factory()->create([
            'phone' => fake()->unique()->numerify('+23354########'),
            'phone_verified_at' => now(),
        ]);
    }

    public function test_find_or_create_direct_sets_pivot_and_pair_columns(): void
    {
        $u1 = $this->makeUser();
        $u2 = $this->makeUser();

        $conv = Conversation::findOrCreateDirect($u1->id, $u2->id, $u1->id);

        $this->assertNotNull($conv->id);
        $this->assertFalse($conv->is_group);

        $minId = min($u1->id, $u2->id);
        $maxId = max($u1->id, $u2->id);

        $conv->refresh();
        $this->assertSame($minId, (int) $conv->user_one_id);
        $this->assertSame($maxId, (int) $conv->user_two_id);

        $pivotIds = DB::table('conversation_user')
            ->where('conversation_id', $conv->id)
            ->orderBy('user_id')
            ->pluck('user_id')
            ->values();

        $this->assertCount(2, $pivotIds);
        $this->assertSame($minId, (int) $pivotIds[0]);
        $this->assertSame($maxId, (int) $pivotIds[1]);

        $again = Conversation::findOrCreateDirect($u2->id, $u1->id, $u2->id);
        $this->assertSame($conv->id, $again->id);
    }

    public function test_find_or_create_saved_messages_has_one_member_and_null_pair_columns(): void
    {
        $u = $this->makeUser();
        $conv = Conversation::findOrCreateSavedMessages($u->id);
        $conv->refresh();

        $this->assertNull($conv->user_one_id);
        $this->assertNull($conv->user_two_id);
        $this->assertSame(1, (int) DB::table('conversation_user')->where('conversation_id', $conv->id)->count());
    }

    public function test_is_participant_uses_pivot_not_only_pair_columns(): void
    {
        $u1 = $this->makeUser();
        $u2 = $this->makeUser();
        $conv = Conversation::findOrCreateDirect($u1->id, $u2->id, $u1->id);

        $this->assertTrue($conv->isParticipant($u1->id));
        $this->assertTrue($conv->isParticipant($u2->id));
        $this->assertFalse($conv->isParticipant(999_999_999));
    }

    public function test_conversation_service_resolve_other_user_id_from_pivot(): void
    {
        $u1 = $this->makeUser();
        $u2 = $this->makeUser();
        $conv = Conversation::findOrCreateDirect($u1->id, $u2->id, $u1->id);

        $service = app(ConversationService::class);
        $otherId = $service->resolveOtherUserId($conv, $u1->id);

        $this->assertSame($u2->id, (int) $otherId);
    }

    public function test_artisan_sync_dm_columns_from_pivot_succeeds(): void
    {
        $u1 = $this->makeUser();
        $u2 = $this->makeUser();
        Conversation::findOrCreateDirect($u1->id, $u2->id);

        $this->artisan('conversations:sync-dm-columns-from-pivot')
            ->assertSuccessful();
    }
}
