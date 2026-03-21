# In-app notices (chat list banners)

Telegram-style / WhatsApp Business–style **dismissible banners** above the conversation list filters (labeled lists, All, Unread, …).

## Database

- `in_app_notices` — content and scheduling  
- `in_app_notice_dismissals` — per-user dismissals (`user_id` + `notice_key`)

Run migrations, then optionally seed a **disabled** demo row:

```bash
php artisan migrate
php artisan db:seed --class=InAppNoticeDemoSeeder
```

To show a notice, set `is_active = 1` (and adjust `starts_at` / `ends_at` if needed):

- `notice_key` — stable string (used for dismiss + API); must stay stable across edits.
- `style` — `info`, `warning`, or `promo` (UI theming).
- `action_label` + `action_url` — optional CTA (opens in new tab on web; external app on Flutter).

## API (mobile / desktop)

- `GET /api/v1/in-app-notices` → `{ "data": [ { id, notice_key, title, body, style, action_label, action_url } ] }`
- `POST /api/v1/in-app-notices/dismiss` → JSON `{ "notice_key": "..." }`

## Web

- Sidebar composer injects `in_app_notices` into `partials.chat_sidebar`.
- `POST /in-app-notices/dismiss` (session auth, same chat domain) — used by the inline dismiss script.

## Clients

- **Mobile** (`gekychat_mobile`): `lib/src/features/notices/*`, strip above filter chips on **Chats** tab.
- **Desktop** (`gekychat_desktop`): same modules; strip above filter chips in `desktop_chat_screen.dart`.

Dismiss is stored **locally** (per account) immediately and synced to the server when online so the banner stays hidden offline.

## Future ideas

- Admin UI / Filament for notices  
- Audience targeting (e.g. `business_only`)  
- Birthday cron that inserts a short-lived row per user  
