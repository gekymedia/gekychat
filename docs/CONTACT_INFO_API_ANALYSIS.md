# Contact Info API – Data Flow & Issue Analysis

## Summary

When viewing contact info from a chat, the app needs the **other user's ID**. The API can return `null` or incorrect data for `other_user` in several scenarios, forcing the mobile app to use multiple fallbacks (contacts table lookup by phone/name, by-username profile, etc.).

---

## API Data Flow

### 1. **Conversations list** – `GET /api/v1/conversations`

**Source:** `ConversationController::index()`

Returns for each DM:
```json
{
  "id": 123,
  "type": "dm",
  "title": "John Doe",
  "other_user": {
    "id": 456,
    "name": "John Doe",
    "phone": "+1234567890",
    "avatar_url": "https://...",
    "online": false,
    "last_seen_at": "2025-03-20T..."
  },
  ...
}
```

**Note:** `other_user_id` is **not** included in the index response (only in `show`).

### 2. **Single conversation** – `GET /api/v1/conversations/{id}`

**Source:** `ConversationController::show()`

Same structure as above, plus:
```json
"other_user_id": 456
```

### 3. **Contact profile** – `GET /api/v1/contacts/user/{userId}/profile`

**Source:** `ContactsController::getUserProfile()`

Used when the app has a valid user ID and needs full contact details.

### 4. **Fallback by username** – `GET /api/v1/contacts/user/by-username/{username}/profile`

Used when the app has username but not user ID (e.g. from contact info flow when ID was null).

---

## Root Causes of `other_user` Being Null or Wrong

### Issue 1: API fallback sends **current user’s ID** when `$other` is null

**Location:** `ConversationController.php` lines 337–349 (index) and 481–491 (show)

When the server cannot resolve the other participant, it falls back to:

```php
$otherUserData = [
    'id' => $user->id,  // ← CURRENT USER's ID, not the other person!
    'name' => $title ?? 'Unknown',
    'phone' => null,
    ...
];
```

**Effect:** The app thinks the “other user” is the current user. The contact info screen may show the wrong profile or behave oddly.

---

### Issue 2: `index()` does not include `other_user_id`

**Location:** `ConversationController::index()` return payload

The list response only has `other_user`, not `other_user_id`. The mobile `_createFallbackUser()` logic looks for `other_user_id` when `other_user` is missing or invalid. That field is never present in the index response, so the fallback cannot recover an ID.

---

### Issue 3: `otherParticipant()` can return null in several cases

**Location:** `Conversation::otherParticipant()` (uses `members`)

`otherParticipant()` uses the `members` relationship. `$other` can be null when:

| Scenario | Reason |
|----------|--------|
| Pivot-only DMs | `user_one_id` / `user_two_id` are NULL (older or migrated data) |
| Soft-deleted users | `User` uses `SoftDeletes`; `members()` does not use `withTrashed()`, so deleted users are excluded |
| Broken pivot data | `conversation_user` missing or inconsistent rows |
| Exception in processing | Controller catch block returns `other_user: null` |

---

### Issue 4: Archived conversations use different logic

**Location:** `ConversationController::archived()` around lines 713–716

Archived uses:

```php
$other = $c->user_one_id === $u ? $c->userTwo : $c->userOne;
```

It does **not** use `otherParticipant()` and does **not** have the pivot fallback. For DMs with only pivot data (`user_one_id` / `user_two_id` null), `$other` will be null.

---

### Issue 5: `members` eager load omits `last_seen_at` in index

**Location:** `ConversationController::index()` – `members` load

```php
'members:id,name,phone,username,avatar_path',  // No last_seen_at
```

`show()` correctly loads `last_seen_at` for members. In the index, `other_user.last_seen_at` can be wrong or missing if it relies on the member data.

---

## Mobile Fallbacks (ContactInfoScreen)

When `userId` is 0 or invalid, the app:

1. Calls `contactsRepo.getUserIdForNameOrPhone(displayName, phone)` (local contacts DB)
2. Falls back to `/contacts/user/by-username/{username}/profile` if username is available
3. Uses `initialDisplayName` / `initialPhone` for display when a valid ID cannot be resolved

---

## Recommended Fixes

### 1. Avoid using current user’s ID as fallback for `other_user`

When `$other` is null, prefer:

- Returning `other_user: null` and a separate `other_user_id: null`, or
- Including a top-level `other_user_id` from the pivot when `$other` is null but the other participant can still be inferred from `conversation_user`

**Example (conceptual):**

```php
if (!$other && !$c->is_group) {
    $otherUserId = DB::table('conversation_user')
        ->where('conversation_id', $c->id)
        ->where('user_id', '!=', $u)
        ->value('user_id');
    if ($otherUserId) {
        // Return minimal other_user with correct ID, even if User is soft-deleted
        $otherUserData = [
            'id' => $otherUserId,
            'name' => $title ?? 'Unknown',
            'phone' => null,
            'avatar' => null,
            'avatar_url' => null,
            'online' => false,
            'last_seen_at' => null,
        ];
    }
}
```

Do not use `'id' => $user->id` when you mean “other user unknown”.

---

### 2. Add `other_user_id` to the index response

Always include `other_user_id` for DMs, so the client can fall back when `other_user` is incomplete:

```php
return [
    'id' => $c->id,
    'other_user' => $otherUserData,
    'other_user_id' => $other?->id ?? $otherUserIdFromPivot ?? null,  // Add this
    ...
];
```

---

### 3. Make `archived()` use the same resolution logic as `index()` and `show()`

Use `otherParticipant()` plus the pivot fallback instead of only `user_one_id` / `user_two_id`, so archived DMs with pivot-only data still get a correct `other_user`.

---

### 4. Handle soft-deleted users explicitly

If you want to show contact info for soft-deleted users (e.g. to display historical data):

- Use `User::withTrashed()->find($id)` when resolving from the pivot, or
- Ensure `members()` includes trashed users where appropriate.

---

### 5. Run the backfill migration

Ensure `2026_03_01_000001_backfill_conversation_user_ids_from_pivot` has been run so `user_one_id` and `user_two_id` are populated from the pivot for older DMs.

---

## How to Test

1. **Inspect API responses**

   ```bash
   # Get conversations (replace TOKEN and BASE_URL)
   curl -H "Authorization: Bearer YOUR_TOKEN" \
     "https://your-api/api/v1/conversations" | jq '.data[] | {id, other_user, other_user_id}'
   ```

2. **Check a specific conversation**

   ```bash
   curl -H "Authorization: Bearer YOUR_TOKEN" \
     "https://your-api/api/v1/conversations/123" | jq '.data | {other_user, other_user_id}'
   ```

3. **Find problematic conversations**

   Use `ContactInfoApiTest::findConversationsWithNullOtherUser()` (or equivalent) to list DMs where `other_user` resolution fails.

---

## Quick Reference: API Response Shape

| Endpoint | Has `other_user` | Has `other_user_id` |
|----------|------------------|----------------------|
| GET /conversations | ✅ | ❌ (should add) |
| GET /conversations/{id} | ✅ | ✅ |
| GET /contacts/user/{id}/profile | N/A | Uses `{id}` in path |
