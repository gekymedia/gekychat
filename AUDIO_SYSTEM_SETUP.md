# GekyChat Audio System Setup Guide

## Overview
This system allows users to add copyright-safe background audio to World Feed videos using the Freesound API.

## Features
- ✅ Search and browse audio from Freesound
- ✅ Preview audio before selection (10-second preview)
- ✅ Attach audio to video posts with volume control
- ✅ Automatic license validation (CC0 and CC BY only)
- ✅ Attribution handling for CC BY licenses
- ✅ Trending audio tracking
- ✅ Admin panel for audio library management
- ✅ Compliance snapshots for legal protection

## Setup Instructions

### 1. Get Freesound API Key

1. Go to https://freesound.org/
2. Create a free account
3. Go to https://freesound.org/apiv2/apply/
4. Fill out the API application form:
   - Name: GekyChat
   - Description: Chat application with world feed feature
   - URL: https://chat.gekychat.com
5. Once approved, you'll receive:
   - API Key
   - Client ID
   - Client Secret

### 2. Configure Environment Variables

Add to your `.env` file:

```env
# Freesound API Configuration
FREESOUND_API_KEY=your_api_key_here
FREESOUND_CLIENT_ID=your_client_id_here
FREESOUND_CLIENT_SECRET=your_client_secret_here
```

### 3. Run Database Migrations

```bash
php artisan migrate
```

This will create the following tables:
- `audio_library` - Cached audio tracks
- `world_feed_audio` - Video-audio associations
- `audio_usage_stats` - Trending and analytics
- `audio_license_snapshots` - Compliance records

### 4. Test the System

#### Web
1. Go to `/world-feed`
2. Click "Create Post"
3. Upload a video
4. Click "Add Audio"
5. Search for sounds
6. Preview and select

#### Mobile/Desktop
1. Open World Feed
2. Tap "Create Post"
3. Select a video
4. Tap "Add Audio"
5. Search, preview, and select

#### Admin Panel
1. Go to `/admin/audio`
2. View all cached audio
3. Monitor usage statistics
4. Toggle audio active/inactive
5. Review license compliance

## License Types Allowed

### ✅ Safe Licenses (Allowed)
- **CC0 (Creative Commons 0)** - Public Domain
  - No attribution required
  - Free to use commercially
  - Can be modified
  
- **CC BY (Attribution)** - Creative Commons Attribution
  - Attribution required (automatically handled)
  - Free to use commercially
  - Can be modified

### ❌ Blocked Licenses (Not Allowed)
- CC BY-NC (Non-Commercial)
- CC BY-SA (Share-Alike)
- CC BY-NC-SA (Non-Commercial Share-Alike)
- CC BY-ND (No Derivatives)
- Sampling+
- Any unclear or proprietary licenses

## How It Works

### Audio Search Flow
```
User searches "happy music"
    ↓
Check local cache first (audio_library)
    ↓
If not found → Query Freesound API
    ↓
Filter results (only CC0 and CC BY)
    ↓
Cache safe results
    ↓
Return to user with preview URLs
```

### Audio Attachment Flow
```
User uploads video + selects audio
    ↓
Validate audio license
    ↓
Create world_feed_post
    ↓
Create world_feed_audio association
    ↓
Create license snapshot (compliance)
    ↓
Increment usage count
    ↓
Update trending stats
    ↓
Video plays with audio overlay
```

### Attribution Display
- For CC BY licenses, attribution is automatically:
  - Stored in post metadata
  - Displayed in video details
  - Included in license snapshot

Example: `"Happy Tune" by JohnDoe (Freesound)`

## API Endpoints

### User Endpoints (Mobile/Desktop/Web)
```
GET  /api/v1/audio/search?q={query}&max_duration=120
GET  /api/v1/audio/trending?days=7&limit=20
GET  /api/v1/audio/{id}
GET  /api/v1/audio/{id}/preview
GET  /api/v1/audio/{id}/similar
POST /api/v1/audio/{id}/validate

POST /api/v1/world-feed/posts
     - media (file, required)
     - caption (string, optional)
     - audio_id (int, optional)
     - audio_volume (int, 0-100, optional)
     - audio_loop (boolean, optional)
```

### Admin Endpoints
```
GET  /admin/audio
POST /admin/audio/{id}/toggle-status
POST /admin/audio/{id}/validation
```

## Database Schema

### audio_library
- Stores cached audio from Freesound
- Includes license info and attribution
- Tracks usage count for trending

### world_feed_audio
- Links videos to audio tracks
- Stores playback settings (volume, loop)
- Snapshots license at time of use

### audio_usage_stats
- Tracks daily/hourly usage
- Powers trending audio feature
- Helps identify popular sounds

### audio_license_snapshots
- Legal compliance records
- Stores full license at time of use
- Protects against future license changes

## Caching Strategy

### What Gets Cached
- Audio metadata (name, duration, license)
- Preview URLs
- Attribution text
- Tags and categories

### What Doesn't Get Cached
- Actual audio files (uses Freesound URLs)
- User-specific data

### Cache Expiration
- Audio metadata: 30 days
- Search results: 1 hour
- Trending data: Updated hourly

## Compliance & Safety

### License Validation
1. Every audio is validated before caching
2. Only CC0 and CC BY are allowed
3. License snapshot created on use
4. Regular validation jobs check for changes

### Attribution Handling
- Automatically formatted and stored
- Displayed on video details
- Included in all API responses
- Cannot be removed by users

### DMCA Protection
- Admin can quickly deactivate audio
- Deactivation affects all posts using that audio
- License snapshots provide proof of good faith

## Troubleshooting

### "Freesound API key not configured"
- Check `.env` file has `FREESOUND_API_KEY`
- Verify key is valid
- Check `config/services.php` loads the key

### "License not allowed for redistribution"
- Audio has NC, SA, or ND restrictions
- Only CC0 and CC BY are permitted
- User must select different audio

### "Audio no longer available"
- Freesound user deleted the sound
- Audio marked as inactive by admin
- User can select different audio

### No trending audio
- No audio has been used yet
- Run `php artisan migrate` to ensure tables exist
- Check `audio_usage_stats` table has data

## Future Enhancements

### Phase 2
- User-uploaded original sounds
- AI-generated music integration
- Audio trimming and editing
- Audio effects (fade, reverb)

### Phase 3
- Curated audio packs
- Seasonal/themed collections
- Audio duets/remixes
- Premium audio (monetization)

## Support

For issues or questions:
- Check admin panel: `/admin/audio`
- View logs: `storage/logs/laravel.log`
- Test API: Use Postman with `/api/v1/audio/search`

## Legal Notes

⚠️ **IMPORTANT**: This system only allows Creative Commons licenses that permit commercial use and redistribution. Always verify license compliance before enabling audio for users.

- CC0: No restrictions
- CC BY: Attribution required (automatically handled)
- All other licenses: Blocked by default

The system creates license snapshots to protect against future license changes. If a Freesound user changes their license after we cache it, we have proof of the original license at time of use.
