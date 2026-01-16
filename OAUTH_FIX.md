# OAuth API Client Table Fix

## Issue
The OAuth token endpoint was failing with error:
```
Table 'gekychat.api_clients' doesn't exist
```

## Root Cause
The `api_clients` table migration exists but hasn't been run on the database. The OAuth controller was trying to query this table without checking if it exists first.

## Fix Applied
Modified `app/Http/Controllers/Api/Platform/OAuthController.php` to:
1. Check if the `api_clients` table exists before querying it
2. Gracefully handle the case where the table doesn't exist
3. Continue with user API key authentication (which uses `developer_client_id` on users table)

## Migration Required
To fully enable platform API clients, run the migrations:
```bash
php artisan migrate
```

This will create:
- `api_clients` table (migration: `2025_11_03_000004_create_api_clients_table.php`)
- OAuth fields on `api_clients` table (migration: `2025_01_20_000001_add_oauth_fields_to_api_clients.php`)

## Current Behavior
- The OAuth endpoint now works with user API keys (client_id starting with `dev_`)
- Platform API clients will work once migrations are run
- No breaking changes - existing functionality preserved
