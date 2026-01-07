# Auto-Reply System Documentation

## Overview

The Auto-Reply system allows users to create rules that automatically reply to messages containing specific keywords. This feature is designed for one-to-one private chats only, with comprehensive anti-loop protection.

## Features

- ✅ Keyword-based matching (case-insensitive)
- ✅ Optional delay before sending replies
- ✅ Enable/disable rules
- ✅ Anti-loop protection (24-hour cooldown per conversation per rule)
- ✅ Only applies to one-to-one private chats
- ✅ Feature flag protection (`auto_reply`)

## Safety Rules

### Scope Restrictions

Auto replies **ONLY** apply to:
- ✅ One-to-one private chats

Auto replies **MUST NOT** apply to:
- ❌ Group chats
- ❌ Channels
- ❌ World feed
- ❌ Live chat
- ❌ Email-chat

### Anti-Loop Protection

The system implements multiple layers of protection:

1. **24-Hour Cooldown**: Only one auto-reply per conversation per rule every 24 hours
2. **System-Generated Flag**: Auto-replies are marked with `system_generated: true` in metadata
3. **No Recursion**: Auto-replies never trigger other auto-replies
4. **Plain Text Only**: Only processes messages with text body (ignores media-only messages)

## Database Schema

### `auto_reply_rules` Table

```sql
- id (primary key)
- user_id (foreign key to users)
- keyword (string) - Keyword to match (case-insensitive)
- reply_text (text) - Text to reply with
- delay_seconds (integer, nullable) - Optional delay before sending
- is_active (boolean) - Enable/disable rule
- timestamps
```

### `auto_reply_cooldowns` Table

```sql
- id (primary key)
- conversation_id (foreign key to conversations)
- rule_id (foreign key to auto_reply_rules)
- last_auto_reply_at (timestamp)
- timestamps
```

## API Endpoints

### List Rules
```
GET /api/v1/auto-replies
```

### Get Rule
```
GET /api/v1/auto-replies/{id}
```

### Create Rule
```
POST /api/v1/auto-replies
Body:
{
  "keyword": "hello",
  "reply_text": "Hi! How can I help?",
  "delay_seconds": 5,  // optional
  "is_active": true    // optional, defaults to true
}
```

### Update Rule
```
PUT /api/v1/auto-replies/{id}
Body: (same as create)
```

### Delete Rule
```
DELETE /api/v1/auto-replies/{id}
```

## Implementation Details

### Event Listener

The `ProcessAutoReply` listener is registered to handle `MessageSent` events. It:

1. Checks feature flag
2. Validates conversation type (one-to-one only)
3. Checks if message has text body
4. Ensures message is not system-generated
5. Finds matching rules for recipient
6. Checks cooldown period
7. Sends auto-reply (immediately or queued with delay)

### Delayed Replies

If `delay_seconds` is set, the reply is queued using `SendDelayedAutoReply` job. Otherwise, it's sent immediately.

## Feature Flag

The entire system is protected by the `auto_reply` feature flag:

- If disabled: No rules are executed, no replies are sent
- If enabled: Rules are processed according to safety rules

## TODO: Frontend Integration

Frontend should provide UI for:
- Creating/editing/deleting rules
- Viewing rule list
- Testing rules
- Viewing cooldown status (optional)

## Security Considerations

- Users can only manage their own rules
- Anti-loop protection prevents infinite reply chains
- Cooldown prevents spam
- Scope restrictions ensure replies only go to intended recipients

