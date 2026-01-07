# Media Compression Pipeline Documentation

## Overview

The Media Compression Pipeline reduces storage usage and bandwidth by compressing images and videos server-side after upload. This is designed for small VPS deployments where storage and bandwidth are limited.

## Features

- ✅ Server-side image compression (JPEG/WebP)
- ✅ Server-side video compression (H.264 + AAC)
- ✅ Thumbnail generation
- ✅ Asynchronous processing (queue-based)
- ✅ User-selectable compression levels (Low/Medium/High)
- ✅ Feature flag protection (`media_compression`)

## Compression Rules

### Images

- Format: Converted to JPEG (for compatibility)
- EXIF: Stripped for privacy and size reduction
- Thumbnail: 400x400px generated automatically
- Quality levels:
  - **Low**: 60% quality, 1920x1920 max
  - **Medium**: 75% quality, 2048x2048 max (default)
  - **High**: 90% quality, 2560x2560 max

### Videos

- Codec: H.264 (libx264) + AAC audio
- Resolution limits:
  - Phase 1/2: 720p max
  - Phase 3+: 1080p max
- Bitrate:
  - 720p: 2000k max
  - 1080p: 4000k max
- Thumbnail: Frame extracted at 1 second

## Database Schema

### `attachments` Table Additions

```sql
- compression_status (enum: pending, processing, completed, failed)
- compressed_file_path (string, nullable)
- thumbnail_path (string, nullable)
- original_size (integer, nullable)
- compressed_size (integer, nullable)
- compression_level (enum: low, medium, high)
- compression_error (text, nullable)
```

## Processing Flow

1. **Upload**: File is uploaded and stored immediately
2. **Queue**: Compression job is dispatched to queue
3. **Response**: Upload endpoint returns immediately with `compression_status: 'pending'`
4. **Processing**: Job processes file asynchronously
5. **Completion**: Status updated to `completed` or `failed`
6. **Frontend**: Should poll or use webhooks to detect completion

## Jobs

### CompressImage Job

- Uses Intervention Image library
- Processes images asynchronously
- Generates compressed version and thumbnail
- Updates attachment record with paths

### CompressVideo Job

- Uses FFmpeg for transcoding
- Requires FFmpeg to be installed on server
- Generates H.264 MP4 and thumbnail
- Updates attachment record with paths

## Requirements

### Image Processing

- PHP GD or Imagick extension
- Intervention Image library (already included)

### Video Processing

- FFmpeg must be installed on server
- Should be in PATH or configured in job

## API Integration

### Upload Endpoint

```
POST /api/v1/upload
Body:
{
  "files": [...],
  "compression_level": "medium"  // optional: low, medium, high
}
```

Response includes `compression_status` field that frontend should monitor.

### TODO: Frontend Hooks

Frontend should:
1. Poll attachment status until `compression_status === 'completed'`
2. Use compressed version when available (`compressed_file_path`)
3. Fall back to original if compression failed
4. Display thumbnail from `thumbnail_path`

## Storage Strategy

### Current Behavior

- Original file is kept
- Compressed version is stored alongside original
- Both versions remain accessible

### Future Options (Phase 2/3)

Configuration option to delete original after successful compression:
```php
config('media.delete_original_after_compression', false)
```

## Feature Flag

The entire system is protected by the `media_compression` feature flag:

- If disabled: Raw media is uploaded (existing behavior)
- If enabled: Compression jobs are queued after upload

## Error Handling

If compression fails:
- `compression_status` set to `failed`
- `compression_error` contains error message
- Original file remains accessible
- System continues to work normally

## Performance Considerations

- Compression is async (doesn't block upload)
- Queue workers process jobs in background
- Large files may take time to process
- Frontend should show processing indicator

## TODO: Implementation Notes

- [ ] Add config for FFmpeg path
- [ ] Add config for original file deletion
- [ ] Add webhook/event for compression completion
- [ ] Add Phase Mode checking for max resolution
- [ ] Add max duration enforcement for videos
- [ ] Optimize thumbnail generation
- [ ] Add progress tracking for large files

