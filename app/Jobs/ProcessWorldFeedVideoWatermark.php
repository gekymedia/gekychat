<?php

namespace App\Jobs;

use App\Models\WorldFeedPost;
use App\Services\WorldFeedVideoWatermarkService;
use App\Helpers\VideoThumbnailHelper;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

/**
 * Applies server-side watermark (logo + username) to a World Feed video after upload.
 * Runs in queue so upload response is fast; stored media then has the overlay (no mobile FFmpeg).
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

        $applied = $watermarkService->applyWatermark(
            $rawMediaUrl,
            $creatorName,
            $username,
            'public'
        );

        if (!$applied) {
            return;
        }

        // Regenerate thumbnail from watermarked video so it matches
        try {
            $thumbRelPath = dirname($rawMediaUrl) . '/' . pathinfo($rawMediaUrl, PATHINFO_FILENAME) . '_thumb.jpg';
            $thumbPath = VideoThumbnailHelper::generateThumbnail(
                $rawMediaUrl,
                'public',
                $thumbRelPath,
                1,
                640,
                360
            );
            if ($thumbPath) {
                $post->update(['thumbnail_url' => $thumbPath]);
            }
        } catch (\Throwable $e) {
            Log::warning('ProcessWorldFeedVideoWatermark: thumbnail regen failed', [
                'post_id' => $this->postId,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
