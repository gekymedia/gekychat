# Conversation architecture (permanent model)

## Source of truth

- **`conversation_user`** is the canonical membership store for rows in `conversations`.
- **`conversations.user_one_id` / `user_two_id`** are a **denormalized cache** for non-group rows with **exactly two** pivot members: `min(user_id)` and `max(user_id)` in lexicographic order of the two IDs.
- Rows with **one** member (e.g. Saved Messages) or **not exactly two** members on a non-group row: pair columns are **`NULL`**.
- **`is_group = true`**: pair columns are **`NULL`** (cleared on sync).

## Single write path for standard DMs

- **`App\Services\ConversationService`**
  - `findOrCreateDirect($a, $b, $createdBy)` — 1:1 and Saved Messages (`$a === $b`).
  - `findOrCreateSavedMessages($userId)` — wrapper.
  - `createEmailThreadConversation(...)` — email threads (separate from normal DMs; still syncs pair columns when there are two users).
  - `syncDenormalizedPairColumnsFromPivot($conversation)` — repair one row.
  - `syncAllDenormalizedColumnsFromPivot($limit)` — batch repair.

- **`Conversation::findOrCreateDirect`** delegates to the service (existing call sites unchanged).

## Reading participants

- **`Conversation::isParticipant($userId)`** uses the **pivot only** (not pair columns).
- **`Conversation::otherParticipant()`** uses loaded `members` first, then **`ConversationService::resolveOtherParticipant()`** (pivot + `User::find`, then `withTrashed()` if needed).

## Backfill / production checklist

1. Deploy code.
2. Run once (idempotent):

   ```bash
   php artisan conversations:sync-dm-columns-from-pivot
   ```

3. Optional dry run on a subset:

   ```bash
   php artisan conversations:sync-dm-columns-from-pivot --limit=100
   ```

4. Verify counts: DMs with two pivot users should have non-null pair columns; groups and saved messages should have null pair columns.

## Related

- `docs/CONTACT_INFO_API_ANALYSIS.md` — API shape for `other_user` / mobile clients.
