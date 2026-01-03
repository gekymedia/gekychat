# Subdomain Routing Implementation Guide

## Overview
This document outlines the implementation of subdomain-based routing for GekyChat:
- `gekychat.com` → Landing page
- `chat.gekychat.com` → Chat application (web interface)
- `api.gekychat.com` → API endpoints (mobile & platform APIs)

## Architecture Decision

**✅ RECOMMENDED: Single Project with Subdomain Routing**

### Why Single Project?
1. **Shared Codebase**: All models, services, and business logic remain in one place
2. **Easier Maintenance**: One codebase to update, test, and deploy
3. **Shared Authentication**: Users can seamlessly move between landing page and chat
4. **Cost Effective**: Single server/deployment pipeline
5. **Simpler Development**: No need to sync changes across multiple projects

### When to Split Projects?
Only consider separate projects if:
- Landing page needs completely different tech stack (e.g., Next.js/React)
- Different teams maintaining each part
- Different deployment schedules/requirements
- Different scaling needs (very rare)

## Implementation Steps

### Step 1: Update Route Files

#### `routes/web.php`
- Add subdomain constraint: `->domain('chat.gekychat.com')`
- Keep all existing chat routes

#### `routes/api.php` (or separate route files)
- Add subdomain constraint: `->domain('api.gekychat.com')`
- Include both mobile API and platform API routes

#### Create `routes/landing.php`
- New file for landing page routes
- Subdomain constraint: `->domain('gekychat.com')` or no subdomain

### Step 2: Update Bootstrap Configuration

Modify `bootstrap/app.php` to register subdomain routes.

### Step 3: Environment Configuration

Update `.env`:
```env
APP_URL=https://gekychat.com
SESSION_DOMAIN=.gekychat.com
SANCTUM_STATEFUL_DOMAINS=gekychat.com,chat.gekychat.com,api.gekychat.com
```

### Step 4: Create Landing Page

Create views and controllers for the landing page.

### Step 5: Update CORS Configuration

Ensure API subdomain allows requests from chat subdomain.

## Route Structure

```
gekychat.com/
  ├── / (landing page)
  ├── /features
  ├── /pricing
  ├── /docs
  └── /login (redirects to chat.gekychat.com/login)

chat.gekychat.com/
  ├── / (chat interface - existing routes)
  ├── /c (conversations)
  ├── /g (groups)
  └── ... (all existing web routes)

api.gekychat.com/
  ├── /api/v1/* (mobile API)
  └── /api/platform/* (platform API)
```

## Benefits of This Approach

1. **SEO Friendly**: Landing page on main domain
2. **Clear Separation**: Each subdomain has distinct purpose
3. **Scalability**: Can move subdomains to different servers later if needed
4. **Security**: API isolated on separate subdomain
5. **User Experience**: Clean URLs for each service

## Testing

1. **Local Development**: Use `hosts` file or local DNS
2. **Staging**: Configure DNS records
3. **Production**: Set up proper DNS A/CNAME records

## Migration Notes

- Existing users/bookmarks will continue to work
- API clients need to update base URL to `api.gekychat.com`
- Session cookies will work across subdomains with `.gekychat.com` domain

