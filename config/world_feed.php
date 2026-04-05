<?php

return [

    /*
    |--------------------------------------------------------------------------
    | World Feed Video Watermark
    |--------------------------------------------------------------------------
    |
    | Server-side watermark is written to a separate file; media_url stays the clean original for playback.
    | media_url_watermarked is set for downloads/shares once processing finishes.
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
