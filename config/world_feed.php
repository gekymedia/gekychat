<?php

return [

    /*
    |--------------------------------------------------------------------------
    | World Feed Video Watermark
    |--------------------------------------------------------------------------
    |
    | Server-side watermark is written to a separate file; media_url stays the
    | clean playback file (compressed when ready). media_url_watermarked is set
    | for downloads/shares once processing finishes.
    |
    */

    /** Set to false to disable video watermarking (e.g. if FFmpeg is not on the server). */
    'watermark_videos' => env('WORLD_FEED_WATERMARK_VIDEOS', true),

    /**
     * Optional logo image path for overlay (top-right of video).
     * Relative to public/ or absolute path. Example: 'images/watermark-logo.png'
     * If null or file missing, only username/creator text is drawn (bottom-left).
     */
    'watermark_logo_path' => env('WORLD_FEED_WATERMARK_LOGO_PATH', null),

    /*
    |--------------------------------------------------------------------------
    | Phase 1 playback ladder (H.264 progressive MP4)
    |--------------------------------------------------------------------------
    */

    /** Optional FFmpeg / FFprobe binaries (falls back to PATH). */
    'ffmpeg_path' => env('FFMPEG_PATH', env('WORLD_FEED_FFMPEG_PATH', null)),
    'ffprobe_path' => env('FFPROBE_PATH', env('WORLD_FEED_FFPROBE_PATH', null)),

    /**
     * Optional CDN origin for public storage URLs (e.g. https://cdn.example.com).
     * When set, /storage/... paths are rewritten to this host. Leave empty to use APP_URL.
     */
    'cdn_url' => env('WORLD_FEED_CDN_URL', null),

    /**
     * Which ladder rung is "preferred" in the API when both exist: 480 (faster start) or 720.
     */
    'playback_preferred' => env('WORLD_FEED_PLAYBACK_PREFERRED', '480'),

    /** If true, delete media_url_original after a successful compress (saves disk). */
    'delete_original_after_compress' => env('WORLD_FEED_DELETE_ORIGINAL_AFTER_COMPRESS', false),

];
