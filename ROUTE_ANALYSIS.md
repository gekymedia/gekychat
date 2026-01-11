# Route Analysis Report
## API Routes vs Web Routes Analysis
**Generated:** 2025-01-11

### Executive Summary
This report identifies API routes used in Blade files that should use web routes instead, and API routes that don't have corresponding web route equivalents.

---

## CRITICAL ISSUES - Missing Web Routes

### 1. BROADCAST LISTS ⚠️ **CRITICAL**
**API Routes (exist):**
- `GET /api/v1/broadcast-lists` - List all broadcast lists
- `POST /api/v1/broadcast-lists` - Create broadcast list
- `GET /api/v1/broadcast-lists/{id}` - Get single broadcast list
- `PUT /api/v1/broadcast-lists/{id}` - Update broadcast list
- `DELETE /api/v1/broadcast-lists/{id}` - Delete broadcast list
- `POST /api/v1/broadcast-lists/{id}/send` - Send message to broadcast list

**Web Routes (exist):**
- `GET /broadcast-lists` - Index page (web interface) ✅

**Web Routes (MISSING - NEED TO CREATE):**
- `POST /broadcast-lists` - Create broadcast list ❌
- `GET /broadcast-lists/{id}` - Get single broadcast list ❌
- `PUT /broadcast-lists/{id}` - Update broadcast list ❌
- `DELETE /broadcast-lists/{id}` - Delete broadcast list ❌
- `POST /broadcast-lists/{id}/send` - Send message ❌

**Files Currently Using API Routes:**
1. `resources/views/broadcast/index.blade.php`
   - Line 80: `fetch('/api/v1/broadcast-lists')` - GET (should use web route)
   - Line 193: `fetch('/api/v1/broadcast-lists')` - POST (should use web route)
   - Line 243: `fetch(\`/api/v1/broadcast-lists/${id}\`)` - DELETE (should use web route)

2. `resources/views/partials/sidebar_scripts.blade.php`
   - Line 5270: `fetch('/api/v1/broadcast-lists')` - POST in `createBroadcastList()` function (should use web route)

**Impact:** ⚠️ **HIGH** - Broadcast list creation/deletion fails with 401 Unauthorized errors on web.

---

### 2. AUDIO SEARCH/TRENDING ⚠️ **MEDIUM**
**API Routes (exist):**
- `GET /api/v1/audio/search` - Search audio
- `GET /api/v1/audio/trending` - Get trending audio

**Web Routes (exist):**
- `GET /audio/browse` - Browse page (web interface) ✅

**Web Routes (MISSING - NEED TO CREATE):**
- `GET /audio/search` - Search audio (web route) ❌
- `GET /audio/trending` - Get trending (web route) ❌

**Files Currently Using API Routes:**
1. `resources/views/audio/browse.blade.php`
   - Line 96: `fetch(\`/api/v1/audio/search?...\`)` - Should use web route
   - Line 129: `fetch('/api/v1/audio/trending')` - Should use web route

**Impact:** ⚠️ **MEDIUM** - Audio search/trending may fail with 401 errors on web.

---

## ISSUES - Using API Routes Instead of Existing Web Routes

### 3. LIVE BROADCAST ⚠️ **HIGH**
**API Routes (used in Blade files):**
- `POST /api/v1/live/{broadcastId}/join` - Join broadcast
- `POST /api/v1/live/{broadcastId}/end` - End broadcast

**Web Routes (EXIST but NOT USED):**
- `POST /live-broadcast/{broadcastId}/join` - Join broadcast ✅ (should use this)
- `POST /live-broadcast/{broadcastId}/end` - End broadcast ✅ (should use this)

**Files Using API Routes (SHOULD USE WEB ROUTES):**
1. `resources/views/live_broadcast/watch.blade.php`
   - Line 163: `const endpoint = \`/api/v1/live/${broadcastId}/join\`;` - Should use `/live-broadcast/${broadcastId}/join`
   - Line 341: `fetch(\`/api/v1/live/${broadcastId}/end\`)` - Should use `/live-broadcast/${broadcastId}/end`

**Impact:** ⚠️ **HIGH** - Live broadcast join/end fails with 401 errors.

---

### 4. WORLD FEED ⚠️ **MEDIUM**
**API Routes (used in Blade files):**
- `GET /api/v1/world-feed/posts` - Get posts

**Web Routes (EXIST but NOT USED):**
- `GET /world-feed/posts` - Get posts ✅ (should use this)

**Files Using API Routes (SHOULD USE WEB ROUTES):**
1. `resources/views/partials/sidebar_scripts.blade.php`
   - Line 1237: `apiCall(\`/api/v1/world-feed/posts?...\`)` - Should use `/world-feed/posts`

**Impact:** ⚠️ **MEDIUM** - World feed search may fail with 401 errors.

---

## NEEDS REVIEW

### 5. ACCOUNT SWITCHER
**API Routes (used in Blade files):**
- `GET /api/v1/auth/accounts` - Get all accounts
- `POST /api/v1/auth/switch-account` - Switch account
- `DELETE /api/v1/auth/accounts/{accountId}` - Remove account

**Web Routes:**
- None exist (all use API routes)

**Files Using API Routes:**
1. `resources/views/partials/sidebar_scripts.blade.php`
   - Line 5024: `fetch(\`/api/v1/auth/accounts?...\`)`
   - Line 5121: `fetch('/api/v1/auth/switch-account')`
   - Line 5169: `fetch(\`/api/v1/auth/accounts/${accountId}?...\`)`

**Status:** ⚠️ **REVIEW NEEDED** - These routes use `auth:sanctum` middleware. For web, they might work with session-based auth if Sanctum is configured for web, but should be verified. Consider creating web routes if they fail.

---

## SUMMARY STATISTICS

**Critical Issues (Missing Web Routes):**
- Broadcast Lists: 5 missing routes (POST, GET/{id}, PUT/{id}, DELETE/{id}, POST/{id}/send)
- Audio: 2 missing routes (GET /search, GET /trending)

**Using API Routes Instead of Web Routes:**
- Live Broadcast: 2 routes (join, end)
- World Feed: 1 route (posts)

**Total Files Affected:** 4 files
- `resources/views/broadcast/index.blade.php`
- `resources/views/partials/sidebar_scripts.blade.php`
- `resources/views/live_broadcast/watch.blade.php`
- `resources/views/audio/browse.blade.php`

---

## RECOMMENDATIONS

### Priority 1 (Critical - Fix Immediately):
1. ✅ Add web routes for broadcast lists (POST, GET/{id}, PUT/{id}, DELETE/{id}, POST/{id}/send)
2. ✅ Update Blade files to use web routes instead of API routes for broadcast lists
3. ✅ Update `live_broadcast/watch.blade.php` to use web routes instead of API routes

### Priority 2 (High - Fix Soon):
4. ✅ Add web routes for audio (GET /search, GET /trending)
5. ✅ Update `audio/browse.blade.php` to use web routes
6. ✅ Update `sidebar_scripts.blade.php` to use web route for world-feed

### Priority 3 (Review):
7. ⚠️ Review account switcher routes - verify if they work with session auth or need web routes

#### 2. ACCOUNT SWITCHER (Auth Routes)
**API Routes (exist):**
- GET `/api/v1/auth/accounts` - Get all accounts
- POST `/api/v1/auth/switch-account` - Switch account
- DELETE `/api/v1/auth/accounts/{accountId}` - Remove account

**Web Routes (MISSING):**
- All account switcher routes use API routes, but these are auth routes that use sanctum
- These might be OK as API routes since they're session-based for web, but should check if they work with session auth

**Files Using API Routes:**
- `resources/views/partials/sidebar_scripts.blade.php` - Uses all three routes

#### 3. AUDIO ROUTES
**API Routes (exist):**
- GET `/api/v1/audio/search` - Search audio
- GET `/api/v1/audio/trending` - Get trending audio

**Web Routes (exist):**
- GET `/audio/browse` - Browse page (web interface)

**Web Routes (MISSING):**
- GET `/audio/search` - Search audio (web route)
- GET `/audio/trending` - Get trending (web route)

**Files Using API Routes:**
- `resources/views/audio/browse.blade.php` - Uses `/api/v1/audio/search` and `/api/v1/audio/trending`

#### 4. LIVE BROADCAST
**API Routes (exist):**
- POST `/api/v1/live/{broadcastId}/join` - Join broadcast
- POST `/api/v1/live/{broadcastId}/end` - End broadcast

**Web Routes (exist):**
- POST `/live-broadcast/{broadcastId}/join` - Join broadcast (web route)
- POST `/live-broadcast/{broadcastId}/end` - End broadcast (web route)

**Files Using API Routes (SHOULD USE WEB ROUTES):**
- `resources/views/live_broadcast/watch.blade.php` - Uses `/api/v1/live/${broadcastId}/join` and `/api/v1/live/${broadcastId}/end` instead of web routes

#### 5. WORLD FEED
**API Routes (exist):**
- GET `/api/v1/world-feed/posts` - Get posts
- POST `/api/v1/world-feed/posts` - Create post
- etc.

**Web Routes (exist):**
- GET `/world-feed/posts` - Get posts (web route)
- POST `/world-feed/posts` - Create post (web route)
- etc.

**Files Using API Routes (SHOULD USE WEB ROUTES):**
- `resources/views/partials/sidebar_scripts.blade.php` - Uses `/api/v1/world-feed/posts` instead of `/world-feed/posts`

#### 6. SEARCH
**API Routes (exist):**
- GET `/api/v1/search` - Search

**Web Routes (exist):**
- GET `/api/search` - Search (web route) - Note: uses `/api/search` not `/api/v1/search`

**Files Using API Routes:**
- `resources/views/partials/sidebar_scripts.blade.php` - Uses `/api/v1/search` as fallback (OK, has web route as primary)

### Summary

**CRITICAL ISSUES - Need Web Routes:**
1. Broadcast Lists - Missing ALL web routes except index (GET, POST, PUT, DELETE, send)
2. Audio Search/Trending - Missing web routes

**SHOULD FIX - Using API Routes Instead of Existing Web Routes:**
1. Live Broadcast - watch.blade.php uses API routes instead of web routes
2. World Feed - sidebar_scripts.blade.php uses API routes instead of web routes

**NEEDS REVIEW:**
1. Account Switcher - Uses API routes with session auth, might be OK but should verify
