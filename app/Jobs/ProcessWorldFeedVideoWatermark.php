<?php

namespace App\Jobs;

use App\Models\WorldFeedPost;
use App\Services\WorldFeedVideoWatermarkService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

/**
 * Applies server-side watermark to a copy of the video; original file stays for feed playback.
 */
class ProcessWorldFeedVideoWatermark implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $postId;

    public function __construct(int $postId)
    {
        $this->postId = $postId;
    }

    public function handle(WorldFeedVideoWatermarkService $watermarkService): void
    {
        $post = WorldFeedPost::with('creator')->find($this->postId);
        if (!$post || $post->type !== 'video') {
            return;
        }

        // Get raw media_url from database (bypass accessor that converts to full URL)
        $rawMediaUrl = $post->getRawOriginal('media_url');
        if (!$rawMediaUrl) {
            return;
        }

        // If it's already a full URL, extract the relative path
        // e.g., "https://web.gekychat.com/storage/world-feed/file.mp4" -> "world-feed/file.mp4"
        if (str_starts_with($rawMediaUrl, 'http')) {
            // Try to extract path after /storage/
            if (preg_match('#/storage/(.+)$#', $rawMediaUrl, $matches)) {
                $rawMediaUrl = $matches[1];
            } else {
                Log::warning('ProcessWorldFeedVideoWatermark: cannot extract path from URL', [
                    'post_id' => $this->postId,
                    'media_url' => $rawMediaUrl,
                ]);
                return;
            }
        }

        $creator = $post->creator;
        $creatorName = $creator ? ($creator->name ?? 'User') : 'User';
        $username = $creator && $creator->username ? $creator->username : null;

        $watermarkedRelPath = $watermarkService->applyWatermark(
            $rawMediaUrl,
            $creatorName,
            $username,
            'public'
        );

        if ($watermarkedRelPath === null || $watermarkedRelPath === '') {
            return;
        }

        $post->update(['media_url_watermarked' => $watermarkedRelPath]);
    }
}
