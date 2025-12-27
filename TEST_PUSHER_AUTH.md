# Testing Pusher Channel Authentication

## Quick Test Steps

1. **Open Browser DevTools (F12)**
2. **Go to Network tab**
3. **Filter by "broadcasting"**
4. **Refresh the page**
5. **Find the failed `broadcasting/auth` request**
6. **Click it and check:**
   - **Request Payload** tab → What is the `channel_name` value?
   - **Response** tab → What error message do you see?

## Common Channel Names

When you see the request, the `channel_name` will be something like:
- `private-user.presence.1` (for user presence)
- `private-user.1` (for private user channel)
- `private-conversation.123` (for conversation)
- `private-group.456` (for group)

## Expected Behavior

- Laravel automatically strips the `private-` prefix when matching channel patterns
- So `private-user.presence.1` → matches `user.presence.{userId}` pattern
- The `{userId}` parameter should be `1` if user ID is 1

## If Still Getting 403

Check:
1. Are you logged in? (Check if `currentUserId: 1` appears in console)
2. Does the channel pattern match? (Check `routes/channels.php`)
3. Is the user authorized? (Check the callback logic in `routes/channels.php`)
