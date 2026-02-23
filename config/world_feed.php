<?php

return [

    /*
    |--------------------------------------------------------------------------
    | World Feed Video Watermark
    |--------------------------------------------------------------------------
    |
    | Server-side watermark (logo + username) is applied during upload/transcode
    | so stored media already has the overlay (no FFmpeg dependency on mobile).
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

];
