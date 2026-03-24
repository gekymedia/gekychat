<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\WorldFeedPost;
use App\Models\WorldFeedLike;
use App\Models\WorldFeedComment;
use App\Models\WorldFeedCommentLike;
use App\Models\WorldFeedFollow;
use App\Models\WorldFeedReport;
use App\Models\WorldFeedView;
use App\Models\WorldFeedActivity;
use App\Models\User;
use App\Services\FeatureFlagService;
use App\Services\WorldFeedActivityService;
use App\Services\Audio\AudioService;
use App\Services\VideoUploadLimitService;
use App\Services\EngagementBoostService;
use App\Events\WorldFeedPostEngagement;
use App\Helpers\VideoThumbnailHelper;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;
use Illuminate\Pagination\LengthAwarePaginator;

/**
 * PHASE 2: World Feed Controller
 * 
 * Public discovery feed similar to TikTok - vertical scroll feed with short-form content.
 */
class WorldFeedController extends Controller
{
    public function __construct(private WorldFeedActivityService $activityService)
    {
    }

    /**
     * Get world feed posts (vertical scroll feed)
     * GET /api/v1/world-feed
     */
    public function index(Request $request)
    {
        $user = $request->user();

        // World Feed is available with or without username (view, like, comment, post).
        if (!FeatureFlagService::isEnabled('world_feed', $user)) {
            return response()->json(['message' => 'World feed feature is not available'], 403);
        }

        $perPage = $request->input('per_page', 10);
        $userId = $request->user()->id;
        $creatorId = $request->input('creator_id'); // Filter by creator if provided
        $searchQuery = $request->input('q'); // Search query

        // Get public posts (include original post for duet/stitch)
        $query = WorldFeedPost::where('is_public', true)
            ->with(['creator:id,name,avatar_path,username', 'audio.audio', 'originalPost.creator:id,name,avatar_path,username']);
        
        // Filter by creator_id if provided
        if ($creatorId) {
            $query->where('creator_id', $creatorId);
        }
        
        // Search by query if provided (search in caption, tags, creator username/name, and comments)
        if ($searchQuery) {
            $query->where(function($q) use ($searchQuery) {
                $q->where('caption', 'like', "%{$searchQuery}%")
                  ->orWhereJsonContains('tags', $searchQuery)
                  ->orWhereHas('creator', function($creatorQuery) use ($searchQuery) {
                      $creatorQuery->where('name', 'like', "%{$searchQuery}%")
                                   ->orWhere('username', 'like', "%{$searchQuery}%");
                  })
                  ->orWhereHas('comments', function($commentQuery) use ($searchQuery) {
                      $commentQuery->where('comment', 'like', "%{$searchQuery}%");
                  });
            });
        }
        
        // Filter handling: following-only or friends-only feeds bypass the full ranking
        $filter = $request->input('filter', 'for_you');

        if (!$searchQuery && !$creatorId && $filter === 'following') {
            // Pure following feed — only posts from creators the user follows, newest first
            $followedIds = WorldFeedFollow::where('follower_id', $userId)->pluck('creator_id');
            $posts = (clone $query)->whereIn('creator_id', $followedIds)
                ->orderBy('created_at', 'desc')
                ->paginate($perPage);

        } elseif (!$searchQuery && !$creatorId && $filter === 'friends') {
            // Friends feed — posts from mutual followers (bidirectional follows)
            $following   = WorldFeedFollow::where('follower_id', $userId)->pluck('creator_id')->toArray();
            $followers   = WorldFeedFollow::where('creator_id', $userId)->pluck('follower_id')->toArray();
            $mutualIds   = array_intersect($following, $followers);
            $posts = (clone $query)->whereIn('creator_id', $mutualIds)
                ->orderBy('created_at', 'desc')
                ->paginate($perPage);

        } elseif (!$searchQuery && !$creatorId) {
            // For You — personalized ranking algorithm
            $posts = $this->getPersonalizedFeed($query, $userId, $perPage);
        } else {
            // For creator profile: pinned post first, then engagement/date
            $pinnedPostId = null;
            if ($creatorId) {
                $pinnedPostId = \App\Models\User::where('id', $creatorId)->value('world_feed_pinned_post_id');
                if ($pinnedPostId) {
                    $query->orderByRaw('id != ' . (int) $pinnedPostId);
                }
            }
            $posts = $query->orderByRaw('(likes_count + comments_count + views_count) DESC')
                ->orderBy('created_at', 'desc')
                ->paginate($perPage);
        }

        $pinnedPostId = $creatorId ? \App\Models\User::where('id', $creatorId)->value('world_feed_pinned_post_id') : null;

        // Transform posts data
        $transformedPosts = $posts->getCollection()->map(function ($post) use ($userId, $creatorId, $pinnedPostId) {
            return $this->transformWorldFeedPostForApi($post, $userId, $creatorId, $pinnedPostId);
        });

        // Set the transformed collection back to the paginator
        $posts->setCollection($transformedPosts);

        return response()->json([
            'data' => $transformedPosts,
            'pagination' => [
                'current_page' => $posts->currentPage(),
                'last_page' => $posts->lastPage(),
                'per_page' => $posts->perPage(),
                'total' => $posts->total(),
            ],
        ]);
    }

    /**
     * Resolve a shared post by public share_code (from /wf/{code} links).
     * GET /api/v1/world-feed/posts/by-share/{code}
     */
    public function showByShareCode(Request $request, string $code)
    {
        $user = $request->user();

        if (!FeatureFlagService::isEnabled('world_feed', $user)) {
            return response()->json(['message' => 'World feed feature is not available'], 403);
        }

        $code = trim($code);
        if ($code === '') {
            return response()->json(['message' => 'Post not found'], 404);
        }

        $post = WorldFeedPost::where('share_code', $code)
            ->where('is_public', true)
            ->with(['creator:id,name,avatar_path,username', 'audio.audio', 'originalPost.creator:id,name,avatar_path,username'])
            ->first();

        if (!$post) {
            return response()->json(['message' => 'Post not found'], 404);
        }

        $userId = $user->id;
        $payload = $this->transformWorldFeedPostForApi($post, $userId, null, null);

        return response()->json(['data' => $payload]);
    }

    /**
     * @param  WorldFeedPost  $post
     */
    private function transformWorldFeedPostForApi($post, int $userId, ?int $creatorId = null, $pinnedPostId = null): array
    {
        try {
            if (method_exists($post, 'markAsViewed')) {
                $post->markAsViewed($userId);
            }
        } catch (\Exception $e) {
            \Log::warning('Failed to mark post as viewed', ['post_id' => $post->id, 'error' => $e->getMessage()]);
        }

        $creator = $post->creator ?? null;

        $rawMediaUrl = $post->getRawOriginal('media_url');
        $mediaUrl = $this->resolveWorldFeedStoragePathToUrl($rawMediaUrl);
        $thumbnailUrl = $post->getRawOriginal('thumbnail_url');

        $galleryRaw = $post->getRawOriginal('media_gallery');
        $galleryPaths = [];
        if ($galleryRaw !== null && $galleryRaw !== '') {
            $decoded = is_string($galleryRaw) ? json_decode($galleryRaw, true) : $galleryRaw;
            if (is_array($decoded)) {
                $galleryPaths = array_values(array_filter($decoded, fn ($p) => is_string($p) && $p !== ''));
            }
        }
        if ($galleryPaths === [] && $rawMediaUrl) {
            $galleryPaths = [$rawMediaUrl];
        }
        $mediaUrls = array_values(array_filter(array_map(
            fn (string $p) => $this->resolveWorldFeedStoragePathToUrl($p),
            $galleryPaths
        )));

        if ($thumbnailUrl && !str_starts_with($thumbnailUrl, 'http')) {
            try {
                $thumbnailUrl = \App\Helpers\UrlHelper::secureStorageUrl($thumbnailUrl, 'public');
            } catch (\Exception $e) {
                \Log::error('Failed to generate thumbnail URL', [
                    'path' => $thumbnailUrl,
                    'error' => $e->getMessage(),
                    'post_id' => $post->id,
                ]);
                $thumbnailUrl = asset('storage/' . ltrim($thumbnailUrl, '/'));
            }
        }

        $audioData = null;
        if ($post->has_audio && $post->audio) {
            $audioLib = $post->audio->audio;
            $audioData = [
                'id' => $audioLib->id,
                'name' => $audioLib->name,
                'preview_url' => $audioLib->preview_url,
                'duration' => $audioLib->duration,
                'attribution' => $audioLib->attribution_text,
                'volume' => $post->audio->volume_level,
                'loop' => $post->audio->loop_audio,
            ];
        }

        $payload = [
            'id' => $post->id,
            'share_code' => $post->share_code,
            'type' => $post->type ?? 'image',
            'media_type' => $post->type ?? 'image',
            'post_type' => $post->post_type ?? 'original',
            'original_post_id' => $post->original_post_id,
            'stitch_start_ms' => $post->stitch_start_ms,
            'stitch_end_ms' => $post->stitch_end_ms,
            'caption' => $post->caption,
            'media_url' => $mediaUrl,
            'media_urls' => $mediaUrls,
            'thumbnail_url' => $thumbnailUrl,
            'duration' => $post->duration,
            'likes_count' => EngagementBoostService::boostLikes($post->likes_count ?? 0),
            'comments_count' => EngagementBoostService::boostComments($post->comments_count ?? 0),
            'views_count' => EngagementBoostService::boostViews($post->views_count ?? 0),
            'shares_count' => EngagementBoostService::boostShares($post->shares_count ?? 0),
            'tips_count' => $post->tips_count ?? 0,
            'tips_total' => $post->tips_total ?? 0,
            'is_liked' => method_exists($post, 'isLikedBy') ? $post->isLikedBy($userId) : false,
            'tags' => $post->tags ?? [],
            'has_audio' => $post->has_audio,
            'audio' => $audioData,
            'creator' => [
                'id' => $creator->id ?? $post->creator_id,
                'name' => $creator->name ?? 'Unknown',
                'username' => $creator->username ?? null,
                'avatar_url' => $creator ? $creator->avatar_url : null,
                'is_following' => $this->isFollowingCreator($userId, $post->creator_id),
            ],
            'created_at' => $post->created_at ? $post->created_at->toIso8601String() : now()->toIso8601String(),
            'is_pinned' => $creatorId && $pinnedPostId && (int) $post->id === (int) $pinnedPostId,
        ];

        if ($post->original_post_id && $post->relationLoaded('originalPost') && $post->originalPost) {
            $orig = $post->originalPost;
            $origMediaUrl = $orig->getRawOriginal('media_url');
            $origThumbUrl = $orig->getRawOriginal('thumbnail_url');
            if ($origMediaUrl && !str_starts_with($origMediaUrl, 'http')) {
                try {
                    $origMediaUrl = \App\Helpers\UrlHelper::secureStorageUrl($origMediaUrl, 'public');
                } catch (\Exception $e) {
                    $origMediaUrl = asset('storage/' . ltrim($origMediaUrl, '/'));
                }
            }
            if ($origThumbUrl && !str_starts_with($origThumbUrl, 'http')) {
                try {
                    $origThumbUrl = \App\Helpers\UrlHelper::secureStorageUrl($origThumbUrl, 'public');
                } catch (\Exception $e) {
                    $origThumbUrl = asset('storage/' . ltrim($origThumbUrl, '/'));
                }
            }
            $origCreator = $orig->creator;
            $payload['original_post'] = [
                'id' => $orig->id,
                'media_url' => $origMediaUrl,
                'thumbnail_url' => $origThumbUrl,
                'duration' => $orig->duration,
                'creator' => $origCreator ? [
                    'id' => $origCreator->id,
                    'name' => $origCreator->name ?? 'Unknown',
                    'username' => $origCreator->username ?? null,
                    'avatar_url' => $origCreator->avatar_url ?? null,
                ] : null,
            ];
        }

        return $payload;
    }

    /**
     * Create a world feed post
     * POST /api/v1/world-feed/posts
     */
    public function createPost(Request $request)
    {
        $user = $request->user();

        // World Feed posting allowed with or without username (display uses name or fallback).
        if (!FeatureFlagService::isEnabled('world_feed', $user)) {
            return response()->json(['message' => 'World feed feature is not available'], 403);
        }

        $request->validate([
            'caption'      => 'nullable|string|max:500',
            'tags'         => 'nullable|array',
            'tags.*'       => 'string|max:50',
            'audio_id'     => 'nullable|integer|exists:audio_library,id',
            'audio_volume' => 'nullable|integer|min:0|max:100',
            'audio_loop'   => 'nullable|boolean',
            // Duet/Stitch (TikTok-style)
            'original_post_id' => 'nullable|integer|exists:world_feed_posts,id',
            'post_type'        => 'nullable|string|in:original,duet,stitch',
            'stitch_start_ms'  => 'nullable|integer|min:0',
            'stitch_end_ms'    => 'nullable|integer|min:0',
            // Boomerang & filter params from mobile
            'is_boomerang'     => 'nullable|boolean',
            'filter_values'    => 'nullable|array',
            'filter_values.brightness' => 'nullable|numeric|between:-1,1',
            'filter_values.contrast'   => 'nullable|numeric|between:0,2',
            'filter_values.saturation' => 'nullable|numeric|between:0,3',
        ]);

        $files = $this->normalizeWorldFeedMediaFiles($request);
        if (count($files) === 0) {
            return response()->json([
                'message' => 'Media is required. World feed only supports image or video posts.',
            ], 422);
        }

        $mediaMimeRule = 'mimes:jpeg,png,jpg,gif,webp,heic,heif,mp4,mov,avi|max:100000';
        foreach ($files as $index => $file) {
            $v = Validator::make(
                ['media' => $file],
                ['media' => ['required', 'file', $mediaMimeRule]]
            );
            if ($v->fails()) {
                return response()->json([
                    'message' => 'Invalid media file.',
                    'errors' => $v->errors(),
                    'file_index' => $index,
                ], 422);
            }
        }

        if ($request->filled('original_post_id') && count($files) > 1) {
            return response()->json([
                'message' => 'Duet and stitch support a single video file only.',
            ], 422);
        }

        if (count($files) > 1) {
            foreach ($files as $file) {
                if (str_starts_with((string) $file->getMimeType(), 'video/')) {
                    return response()->json([
                        'message' => 'Upload one video at a time. You can select multiple photos for a single carousel post.',
                    ], 422);
                }
            }
            if (count($files) > 10) {
                return response()->json([
                    'message' => 'You can attach at most 10 photos per post.',
                ], 422);
            }

            try {
                $post = DB::transaction(function () use ($request, $user, $files) {
                    return $this->storeWorldFeedCarouselPost($request, $user, $files);
                });
            } catch (\InvalidArgumentException $e) {
                return response()->json(['message' => $e->getMessage()], 422);
            }

            return response()->json([
                'message' => 'Post created',
                'data' => $post->load('creator'),
            ], 201);
        }

        try {
            $post = DB::transaction(function () use ($request, $user, $files) {
                return $this->storeWorldFeedPostFromUploadedFile($request, $user, $files[0], true);
            });
        } catch (\InvalidArgumentException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json([
            'message' => 'Post created',
            'data' => $post->load('creator'),
        ], 201);
    }

    /**
     * One world-feed post with multiple images (TikTok-style swipe gallery).
     *
     * @param  list<UploadedFile>  $files
     */
    private function storeWorldFeedCarouselPost(Request $request, User $user, array $files): WorldFeedPost
    {
        $paths = [];
        foreach ($files as $file) {
            $filename = 'worldfeed_' . uniqid() . '.' . $file->getClientOriginalExtension();
            $paths[] = $file->storeAs('world-feed', $filename, 'public');
        }

        $post = WorldFeedPost::create([
            'creator_id' => $user->id,
            'original_post_id' => null,
            'post_type' => 'original',
            'stitch_start_ms' => null,
            'stitch_end_ms' => null,
            'type' => 'image',
            'caption' => $request->caption,
            'media_url' => $paths[0],
            'media_gallery' => $paths,
            'thumbnail_url' => null,
            'is_public' => true,
            'tags' => $request->tags ?? [],
            'duration' => null,
        ]);

        if ($request->filled('audio_id')) {
            try {
                $audioService = app(AudioService::class);
                $audioService->attachToPost(
                    $post->id,
                    (int) $request->audio_id,
                    $user->id,
                    [
                        'volume' => $request->input('audio_volume', 100),
                        'loop' => $request->input('audio_loop', true),
                    ]
                );
            } catch (\Exception $e) {
                \Log::error('Failed to attach audio to carousel post', [
                    'post_id' => $post->id,
                    'audio_id' => $request->audio_id,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return $post;
    }

    private function resolveWorldFeedStoragePathToUrl(?string $path): ?string
    {
        if ($path === null || $path === '') {
            return null;
        }
        if (str_starts_with($path, 'http')) {
            return $path;
        }
        try {
            return \App\Helpers\UrlHelper::secureStorageUrl($path, 'public');
        } catch (\Exception $e) {
            \Log::error('Failed to generate world feed media URL', [
                'path' => $path,
                'error' => $e->getMessage(),
            ]);

            return asset('storage/' . ltrim($path, '/'));
        }
    }

    private function deleteWorldFeedPostStorageFiles(WorldFeedPost $post): void
    {
        $deleted = [];
        $rawGallery = $post->getRawOriginal('media_gallery');
        if ($rawGallery) {
            $decoded = is_string($rawGallery) ? json_decode($rawGallery, true) : $rawGallery;
            if (is_array($decoded)) {
                foreach ($decoded as $p) {
                    if (! is_string($p) || $p === '' || isset($deleted[$p])) {
                        continue;
                    }
                    $deleted[$p] = true;
                    if (Storage::disk('public')->exists($p)) {
                        Storage::disk('public')->delete($p);
                    }
                }
            }
        }
        $main = $post->getRawOriginal('media_url');
        if (is_string($main) && $main !== '' && ! isset($deleted[$main]) && Storage::disk('public')->exists($main)) {
            Storage::disk('public')->delete($main);
        }
        $thumb = $post->getRawOriginal('thumbnail_url');
        if (is_string($thumb) && $thumb !== '' && Storage::disk('public')->exists($thumb)) {
            Storage::disk('public')->delete($thumb);
        }
    }

    /**
     * @return list<UploadedFile>
     */
    private function normalizeWorldFeedMediaFiles(Request $request): array
    {
        if (! $request->hasFile('media')) {
            return [];
        }

        $media = $request->file('media');
        if ($media instanceof UploadedFile) {
            return [$media];
        }

        if (is_array($media)) {
            return array_values(array_filter(
                $media,
                fn ($f) => $f instanceof UploadedFile
            ));
        }

        return [];
    }

    private function storeWorldFeedPostFromUploadedFile(
        Request $request,
        User $user,
        UploadedFile $file,
        bool $attachAudio
    ): WorldFeedPost {
        $mimeType = $file->getMimeType();
        $isVideo = str_starts_with((string) $mimeType, 'video/');
        $duration = null;

        if ($isVideo) {
            $limitService = app(VideoUploadLimitService::class);
            $validation = $limitService->validateWorldFeedVideo($file, $user->id);

            if (! $validation['valid']) {
                throw new \InvalidArgumentException($validation['error'] ?? 'Video validation failed');
            }

            $duration = $validation['duration'] ?? null;
        }

        $filename = 'worldfeed_' . uniqid() . '.' . $file->getClientOriginalExtension();
        $path = $file->storeAs('world-feed', $filename, 'public');

        $type = $isVideo ? 'video' : 'image';

        $thumbnailPath = null;
        if ($isVideo) {
            try {
                $thumbnailPath = VideoThumbnailHelper::generateThumbnail(
                    $path,
                    'public',
                    'world-feed/' . pathinfo($filename, PATHINFO_FILENAME) . '_thumb.jpg',
                    1,
                    640,
                    360
                );
            } catch (\Exception $e) {
                \Log::error('Failed to generate world feed video thumbnail', [
                    'error' => $e->getMessage(),
                    'video_path' => $path,
                ]);
            }
        }

        $originalPostId = $request->input('original_post_id');
        $postType = $request->input('post_type', 'original');
        if ($originalPostId) {
            $original = WorldFeedPost::find($originalPostId);
            if (! $original || $original->type !== 'video') {
                throw new \InvalidArgumentException('Duet/Stitch is only allowed with a video post.');
            }
            $postType = in_array($postType, ['duet', 'stitch'], true) ? $postType : 'duet';
        } else {
            $postType = 'original';
        }

        $data = [
            'creator_id' => $user->id,
            'original_post_id' => $originalPostId,
            'post_type' => $postType,
            'stitch_start_ms' => $postType === 'stitch' ? $request->input('stitch_start_ms') : null,
            'stitch_end_ms' => $postType === 'stitch' ? $request->input('stitch_end_ms') : null,
            'type' => $type,
            'caption' => $request->caption,
            'media_url' => $path,
            'media_gallery' => null,
            'thumbnail_url' => $thumbnailPath,
            'is_public' => true,
            'tags' => $request->tags ?? [],
            'duration' => $duration,
        ];

        $post = WorldFeedPost::create($data);

        if ($isVideo && config('world_feed.watermark_videos', true)) {
            try {
                \App\Jobs\ProcessWorldFeedVideoWatermark::dispatch($post->id)->delay(now()->addSeconds(90));
            } catch (\Throwable $e) {
                Log::warning('Failed to dispatch world feed watermark job', ['post_id' => $post->id, 'error' => $e->getMessage()]);
            }
        }

        if ($isVideo && $request->boolean('is_boomerang')) {
            $fakeAttachment = \App\Models\Attachment::create([
                'attachable_type' => \App\Models\WorldFeedPost::class,
                'attachable_id' => $post->id,
                'file_path' => $path,
                'mime_type' => $mimeType,
                'is_video' => true,
                'compression_status' => 'pending',
            ]);
            \App\Jobs\ProcessBoomerangVideo::dispatch($fakeAttachment->id);
        }

        if ($isVideo && $request->has('filter_values')) {
            $filters = $request->input('filter_values', []);
            $b = (float) ($filters['brightness'] ?? 0.0);
            $c = (float) ($filters['contrast'] ?? 1.0);
            $s = (float) ($filters['saturation'] ?? 1.0);
            if (abs($b) > 0.01 || abs($c - 1.0) > 0.01 || abs($s - 1.0) > 0.01) {
                $filterAttachment = \App\Models\Attachment::firstOrCreate(
                    [
                        'attachable_type' => \App\Models\WorldFeedPost::class,
                        'attachable_id' => $post->id,
                        'file_path' => $path,
                    ],
                    ['mime_type' => $mimeType, 'is_video' => true, 'compression_status' => 'pending']
                );
                \App\Jobs\ProcessVideoFilters::dispatch($filterAttachment->id, $filters);
            }
        }

        if ($attachAudio && $request->filled('audio_id')) {
            try {
                $audioService = app(AudioService::class);
                $audioService->attachToPost(
                    $post->id,
                    (int) $request->audio_id,
                    $user->id,
                    [
                        'volume' => $request->input('audio_volume', 100),
                        'loop' => $request->input('audio_loop', true),
                    ]
                );
            } catch (\Exception $e) {
                \Log::error('Failed to attach audio to post', [
                    'post_id' => $post->id,
                    'audio_id' => $request->audio_id,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return $post;
    }

    /**
     * Like/unlike a post
     * POST /api/v1/world-feed/posts/{postId}/like
     */
    public function like(Request $request, $postId)
    {
        $post = WorldFeedPost::findOrFail($postId);
        $userId = $request->user()->id;

        $existing = $post->likes()->where('user_id', $userId)->first();

        if ($existing) {
            $existing->delete();
            $post->decrement('likes_count');
            $liked = false;
        } else {
            $post->likes()->create(['user_id' => $userId]);
            $post->increment('likes_count');
            $liked = true;
            $this->activityService->onPostLiked((int) $post->creator_id, $userId, (int) $post->id);

            if ((int) $post->creator_id !== (int) $userId) {
                $actor = $request->user();
                broadcast(new WorldFeedPostEngagement(
                    (int) $post->id,
                    'like',
                    [
                        'id' => $actor->id,
                        'name' => $actor->name,
                        'username' => $actor->username,
                    ],
                    null,
                    EngagementBoostService::boostLikes((int) $post->fresh()->likes_count),
                ));
            }
        }

        return response()->json([
            'message' => $liked ? 'Post liked' : 'Post unliked',
            'likes_count' => EngagementBoostService::boostLikes($post->fresh()->likes_count),
            'is_liked' => $liked,
        ]);
    }

    /**
     * Tip a post with Sika coins
     * POST /api/v1/world-feed/posts/{postId}/tip
     */
    public function tipPost(Request $request, $postId)
    {
        $request->validate([
            'coins' => 'required|integer|min:1|max:100000',
            'note' => 'nullable|string|max:200',
        ]);

        $post = WorldFeedPost::findOrFail($postId);
        $userId = $request->user()->id;
        $creatorId = $post->creator_id;

        // Can't tip your own post
        if ($userId === $creatorId) {
            return response()->json(['message' => 'Cannot tip your own post'], 422);
        }

        $coins = (int) $request->input('coins');
        $note = $request->input('note');

        // Use Sika wallet service to transfer coins
        try {
            $sikaService = app(\App\Services\Sika\SikaWalletService::class);
            $idempotencyKey = 'tip_' . $userId . '_' . $postId . '_' . now()->timestamp;

            $result = $sikaService->gift(
                fromUserId: $userId,
                toUserId: $creatorId,
                coins: $coins,
                idempotencyKey: $idempotencyKey,
                postId: $postId,
                note: $note
            );

            // Update post tip counters
            $post->increment('tips_count');
            $post->increment('tips_total', $coins);

            // Create activity notification for creator
            $this->activityService->onPostTipped($creatorId, $userId, $postId, $coins);

            return response()->json([
                'success' => true,
                'message' => 'Tip sent successfully',
                'coins' => $coins,
                'tips_count' => $post->fresh()->tips_count,
                'tips_total' => $post->fresh()->tips_total,
                'new_balance' => $result['new_balance'],
            ]);

        } catch (\App\Exceptions\Sika\SikaWalletException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 422);
        }
    }

    /**
     * Get comments for a post
     * GET /api/v1/world-feed/posts/{postId}/comments
     */
    public function comments(Request $request, $postId)
    {
        $post = WorldFeedPost::findOrFail($postId);

        $comments = $post->comments()
            ->whereNull('parent_id') // Top-level comments only
            ->with(['user:id,name,avatar_path', 'replies.user:id,name,avatar_path'])
            ->orderBy('created_at', 'desc')
            ->paginate($request->input('per_page', 20));

        $userId = (int) $request->user()->id;
        $comments->getCollection()->transform(function (WorldFeedComment $c) use ($userId) {
            $c->loadMissing('user');

            return array_merge($c->toArray(), [
                'likes_count' => (int) $c->likes_count,
                'dislikes_count' => (int) ($c->dislikes_count ?? 0),
                'is_liked' => $c->isLikedBy($userId),
                'is_disliked' => $c->isDislikedBy($userId),
            ]);
        });

        return response()->json([
            'data' => $comments,
        ]);
    }

    /**
     * Add a comment
     * POST /api/v1/world-feed/posts/{postId}/comments
     */
    public function addComment(Request $request, $postId)
    {
        $request->validate([
            'comment' => 'required|string|max:500',
            'parent_id' => 'nullable|exists:world_feed_comments,id',
        ]);

        $post = WorldFeedPost::findOrFail($postId);

        $comment = $post->comments()->create([
            'user_id' => $request->user()->id,
            'comment' => $request->comment,
            'parent_id' => $request->parent_id,
        ]);

        $post->increment('comments_count');

        $parentAuthorId = null;
        if ($request->parent_id) {
            $parent = WorldFeedComment::find($request->parent_id);
            $parentAuthorId = $parent ? (int) $parent->user_id : null;
        }
        $this->activityService->onCommentAdded(
            (int) $post->creator_id,
            $parentAuthorId ?? 0,
            $request->user()->id,
            (int) $postId,
            (int) $comment->id,
            (bool) $request->parent_id
        );

        if ((int) $post->creator_id !== (int) $request->user()->id) {
            $actor = $request->user();
            $preview = mb_substr((string) $comment->comment, 0, 100);
            broadcast(new WorldFeedPostEngagement(
                (int) $post->id,
                'comment',
                [
                    'id' => $actor->id,
                    'name' => $actor->name,
                    'username' => $actor->username,
                ],
                $preview,
                null,
            ));
        }

        return response()->json([
            'message' => 'Comment added',
            'data' => $comment->load('user'),
        ], 201);
    }

    /**
     * Follow a creator
     * POST /api/v1/world-feed/creators/{creatorId}/follow
     */
    public function followCreator(Request $request, $creatorId)
    {
        $userId = $request->user()->id;

        if ($userId == $creatorId) {
            return response()->json(['message' => 'Cannot follow yourself'], 422);
        }

        $existing = WorldFeedFollow::where('follower_id', $userId)
            ->where('creator_id', $creatorId)
            ->first();

        if ($existing) {
            $existing->delete();
            $following = false;
        } else {
            WorldFeedFollow::create([
                'follower_id' => $userId,
                'creator_id' => $creatorId,
                'followed_at' => now(),
            ]);
            $following = true;
            $this->activityService->onNewFollower((int) $creatorId, $userId);
        }

        return response()->json([
            'message' => $following ? 'Creator followed' : 'Creator unfollowed',
            'is_following' => $following,
        ]);
    }

    /**
     * Follow a user (world feed creator) by user ID
     * POST /api/v1/users/{userId}/follow
     */
    public function followUser(Request $request, $userId)
    {
        $followerId = $request->user()->id;

        if ((int) $userId === $followerId) {
            return response()->json(['message' => 'Cannot follow yourself'], 422);
        }

        $user = \App\Models\User::find($userId);
        if (!$user) {
            return response()->json(['message' => 'User not found'], 404);
        }

        $existing = WorldFeedFollow::where('follower_id', $followerId)
            ->where('creator_id', $userId)
            ->first();

        if ($existing) {
            return response()->json([
                'message' => 'Already following',
                'is_following' => true,
            ]);
        }

        WorldFeedFollow::create([
            'follower_id' => $followerId,
            'creator_id' => (int) $userId,
            'followed_at' => now(),
        ]);

        return response()->json([
            'message' => 'User followed',
            'is_following' => true,
        ]);
    }

    /**
     * Unfollow a user (world feed creator) by user ID
     * POST /api/v1/users/{userId}/unfollow
     */
    public function unfollowUser(Request $request, $userId)
    {
        $followerId = $request->user()->id;

        WorldFeedFollow::where('follower_id', $followerId)
            ->where('creator_id', $userId)
            ->delete();

        return response()->json([
            'message' => 'User unfollowed',
            'is_following' => false,
        ]);
    }

    /**
     * Pin a post to the current user's profile (only own posts). Anyone visiting the profile sees it first.
     * POST /api/v1/world-feed/posts/{postId}/pin
     */
    public function pinPost(Request $request, $postId)
    {
        $user = $request->user();
        $post = WorldFeedPost::find($postId);

        if (!$post) {
            return response()->json(['message' => 'Post not found'], 404);
        }
        if ((int) $post->creator_id !== (int) $user->id) {
            return response()->json(['message' => 'You can only pin your own posts'], 403);
        }

        $user->update(['world_feed_pinned_post_id' => $post->id]);

        return response()->json([
            'message' => 'Post pinned to profile',
            'pinned_post_id' => $post->id,
        ]);
    }

    /**
     * Unpin the post from the current user's profile.
     * DELETE /api/v1/world-feed/profile/pin
     */
    public function unpinPost(Request $request)
    {
        $request->user()->update(['world_feed_pinned_post_id' => null]);

        return response()->json([
            'message' => 'Post unpinned from profile',
            'pinned_post_id' => null,
        ]);
    }

    /**
     * List users who follow the given user (followers of profile user).
     * GET /api/v1/world-feed/users/{userId}/followers
     */
    public function listFollowers(Request $request, $userId)
    {
        $currentUserId = $request->user()->id;
        $perPage = (int) $request->input('per_page', 20);
        $perPage = min(max($perPage, 1), 50);

        $followerIds = WorldFeedFollow::where('creator_id', $userId)->pluck('follower_id');
        $users = User::whereIn('id', $followerIds)
            ->select('id', 'name', 'username', 'avatar_path')
            ->orderBy('name')
            ->paginate($perPage);

        $items = $users->getCollection()->map(function ($user) use ($currentUserId) {
            return $this->formatUserForFollowList($user, $currentUserId);
        });
        $users->setCollection($items);

        return response()->json([
            'data' => $users->items(),
            'pagination' => [
                'current_page' => $users->currentPage(),
                'last_page' => $users->lastPage(),
                'per_page' => $users->perPage(),
                'total' => $users->total(),
            ],
        ]);
    }

    /**
     * List users that the given user follows (following list of profile user).
     * GET /api/v1/world-feed/users/{userId}/following
     */
    public function listFollowing(Request $request, $userId)
    {
        $currentUserId = $request->user()->id;
        $perPage = (int) $request->input('per_page', 20);
        $perPage = min(max($perPage, 1), 50);

        $creatorIds = WorldFeedFollow::where('follower_id', $userId)->pluck('creator_id');
        $users = User::whereIn('id', $creatorIds)
            ->select('id', 'name', 'username', 'avatar_path')
            ->orderBy('name')
            ->paginate($perPage);

        $items = $users->getCollection()->map(function ($user) use ($currentUserId) {
            return $this->formatUserForFollowList($user, $currentUserId);
        });
        $users->setCollection($items);

        return response()->json([
            'data' => $users->items(),
            'pagination' => [
                'current_page' => $users->currentPage(),
                'last_page' => $users->lastPage(),
                'per_page' => $users->perPage(),
                'total' => $users->total(),
            ],
        ]);
    }

    /**
     * List suggested users to follow (not yet followed by current user).
     * GET /api/v1/world-feed/suggestions
     */
    public function listSuggestions(Request $request)
    {
        $currentUserId = $request->user()->id;
        $perPage = (int) $request->input('per_page', 20);
        $perPage = min(max($perPage, 1), 50);

        $followingIds = WorldFeedFollow::where('follower_id', $currentUserId)->pluck('creator_id')->push($currentUserId)->all();
        $users = User::whereNotIn('id', $followingIds)
            ->select('id', 'name', 'username', 'avatar_path')
            ->orderBy('name')
            ->paginate($perPage);

        $items = $users->getCollection()->map(function ($user) use ($currentUserId) {
            return $this->formatUserForFollowList($user, $currentUserId);
        });
        $users->setCollection($items);

        return response()->json([
            'data' => $users->items(),
            'pagination' => [
                'current_page' => $users->currentPage(),
                'last_page' => $users->lastPage(),
                'per_page' => $users->perPage(),
                'total' => $users->total(),
            ],
        ]);
    }

    private function formatUserForFollowList(User $user, int $currentUserId): array
    {
        $avatarUrl = $user->avatar_path
            ? \Illuminate\Support\Facades\Storage::disk('public')->url($user->avatar_path)
            : ($user->avatar_url ?? null);

        return [
            'id' => $user->id,
            'name' => $user->name ?? 'User',
            'username' => $user->username,
            'avatar_url' => $avatarUrl,
            'is_following' => $currentUserId !== $user->id
                ? WorldFeedFollow::where('follower_id', $currentUserId)->where('creator_id', $user->id)->exists()
                : false,
        ];
    }

    /**
     * Like/unlike a comment
     * POST /api/v1/world-feed/comments/{commentId}/like
     */
    public function likeComment(Request $request, $commentId)
    {
        $comment = WorldFeedComment::findOrFail($commentId);
        $userId = $request->user()->id;

        // Remove dislike if present (mutually exclusive with like)
        $existingDislike = $comment->dislikes()->where('user_id', $userId)->first();
        if ($existingDislike) {
            $existingDislike->delete();
            $comment->decrement('dislikes_count');
        }

        $existing = $comment->likes()->where('user_id', $userId)->first();

        if ($existing) {
            $existing->delete();
            $comment->decrement('likes_count');
            $liked = false;
        } else {
            $comment->likes()->create(['user_id' => $userId]);
            $comment->increment('likes_count');
            $liked = true;
        }

        $fresh = $comment->fresh();

        return response()->json([
            'message' => $liked ? 'Comment liked' : 'Comment unliked',
            'likes_count' => (int) $fresh->likes_count,
            'dislikes_count' => (int) ($fresh->dislikes_count ?? 0),
            'is_liked' => $liked,
            'is_disliked' => $fresh->isDislikedBy((int) $userId),
        ]);
    }

    /**
     * Dislike / undislike a comment (toggle). Removes like if set.
     * POST /api/v1/world-feed/comments/{commentId}/dislike
     */
    public function dislikeComment(Request $request, $commentId)
    {
        $comment = WorldFeedComment::findOrFail($commentId);
        $userId = $request->user()->id;

        $existingLike = $comment->likes()->where('user_id', $userId)->first();
        if ($existingLike) {
            $existingLike->delete();
            $comment->decrement('likes_count');
        }

        $existing = $comment->dislikes()->where('user_id', $userId)->first();

        if ($existing) {
            $existing->delete();
            $comment->decrement('dislikes_count');
            $disliked = false;
        } else {
            $comment->dislikes()->create(['user_id' => $userId]);
            $comment->increment('dislikes_count');
            $disliked = true;
        }

        $fresh = $comment->fresh();

        return response()->json([
            'message' => $disliked ? 'Comment disliked' : 'Comment undisliked',
            'likes_count' => (int) $fresh->likes_count,
            'dislikes_count' => (int) ($fresh->dislikes_count ?? 0),
            'is_liked' => $fresh->isLikedBy((int) $userId),
            'is_disliked' => $disliked,
        ]);
    }

    /**
     * Get share URL for a post
     * GET /api/v1/world-feed/posts/{postId}/share-url
     */
    public function getShareUrl(Request $request, $postId)
    {
        $post = WorldFeedPost::findOrFail($postId);
        
        if (!$post->share_code) {
            // Generate share code if it doesn't exist (for existing posts)
            $post->share_code = WorldFeedPost::generateShareCode();
            $post->save();
        }
        
        $shareUrl = 'https://chat.gekychat.com/wf/' . $post->share_code;
        
        return response()->json([
            'share_url' => $shareUrl,
            'share_code' => $post->share_code,
        ]);
    }

    /**
     * Record a share (increment count). Idempotent-friendly: one increment per client call.
     * POST /api/v1/world-feed/posts/{postId}/share
     */
    public function recordShare(Request $request, $postId)
    {
        $post = WorldFeedPost::findOrFail($postId);
        $post->increment('shares_count');
        $post->refresh();

        return response()->json([
            'message' => 'Share recorded',
            'shares_count' => EngagementBoostService::boostShares((int) ($post->shares_count ?? 0)),
        ]);
    }

    /**
     * Report a post (inappropriate, not_interested, etc.)
     * POST /api/v1/world-feed/posts/{postId}/report
     */
    public function reportPost(Request $request, $postId)
    {
        $request->validate([
            'reason' => 'nullable|string|max:100',
        ]);

        $post = WorldFeedPost::findOrFail($postId);
        $reporterId = $request->user()->id;

        WorldFeedReport::firstOrCreate(
            [
                'post_id' => $post->id,
                'reporter_id' => $reporterId,
            ],
            [
                'reason' => $request->input('reason', 'inappropriate'),
            ]
        );

        return response()->json([
            'success' => true,
            'message' => 'Report submitted',
        ]);
    }

    /**
     * Update a world feed post
     * PUT /api/v1/world-feed/posts/{postId}
     */
    public function updatePost(Request $request, $postId)
    {
        $post = WorldFeedPost::findOrFail($postId);
        $userId = $request->user()->id;

        // Only the creator can update their post
        if ($post->creator_id !== $userId) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $request->validate([
            'caption' => 'nullable|string|max:500',
            'tags' => 'nullable|array',
            'tags.*' => 'string|max:50',
        ]);

        $post->update([
            'caption' => $request->input('caption', $post->caption),
            'tags' => $request->input('tags', $post->tags),
        ]);

        return response()->json([
            'message' => 'Post updated',
            'data' => $post->load('creator'),
        ]);
    }

    /**
     * Trending hashtags for the past 7 days
     * GET /api/v1/world-feed/trending-hashtags
     */
    public function trendingHashtags(Request $request)
    {
        $limit = min((int) $request->input('limit', 20), 50);

        // Tags are stored as a JSON array on world_feed_posts
        $rows = WorldFeedPost::query()
            ->where('is_public', true)
            ->where('created_at', '>=', now()->subDays(7))
            ->whereNotNull('tags')
            ->pluck('tags');

        // Flatten all tag arrays and count occurrences
        $counts = [];
        foreach ($rows as $tagArr) {
            if (!is_array($tagArr)) continue;
            foreach ($tagArr as $tag) {
                $tag = ltrim(strtolower(trim((string) $tag)), '#');
                if ($tag === '') continue;
                $counts[$tag] = ($counts[$tag] ?? 0) + 1;
            }
        }

        // Also mine #hashtags from captions
        $captions = WorldFeedPost::query()
            ->where('is_public', true)
            ->where('created_at', '>=', now()->subDays(7))
            ->whereNotNull('caption')
            ->pluck('caption');

        foreach ($captions as $caption) {
            preg_match_all('/#(\w+)/u', (string) $caption, $matches);
            foreach ($matches[1] as $tag) {
                $tag = strtolower($tag);
                $counts[$tag] = ($counts[$tag] ?? 0) + 1;
            }
        }

        arsort($counts);
        $trending = array_slice(array_keys($counts), 0, $limit);

        return response()->json([
            'data' => array_values(array_map(fn($tag) => [
                'tag'   => "#$tag",
                'count' => $counts[$tag],
            ], $trending)),
        ]);
    }

    /**
     * Delete a world feed post
     * DELETE /api/v1/world-feed/posts/{postId}
     */
    public function deletePost(Request $request, $postId)
    {
        $post = WorldFeedPost::findOrFail($postId);
        $userId = $request->user()->id;

        // Only the creator can delete their post
        if ($post->creator_id !== $userId) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $this->deleteWorldFeedPostStorageFiles($post);

        $post->delete();

        return response()->json([
            'message' => 'Post deleted',
        ]);
    }

    /**
     * Get upload limits for current user
     * GET /api/v1/world-feed/upload-limits
     */
    public function getUploadLimits(Request $request)
    {
        $user = $request->user();
        $limitService = app(VideoUploadLimitService::class);
        
        return response()->json([
            'data' => $limitService->getUserLimits($user->id),
        ]);
    }

    /**
     * Check if user is following a creator
     */
    private function isFollowingCreator(int $followerId, int $creatorId): bool
    {
        return WorldFeedFollow::where('follower_id', $followerId)
            ->where('creator_id', $creatorId)
            ->exists();
    }
    
    /**
     * TikTok-like personalized feed algorithm
     * Personalizes feed based on:
     * - Followed creators (boost)
     * - User's interaction history (likes, comments, views)
     * - Content similarity (tags)
     * - Recency (newer posts get slight boost)
     * - Engagement metrics (likes, comments, views)
     */
    private function getPersonalizedFeed($query, int $userId, int $perPage)
    {
        // Get user's followed creators
        $followedCreatorIds = WorldFeedFollow::where('follower_id', $userId)
            ->pluck('creator_id')
            ->toArray();
        
        // Get user's interaction history (posts they've liked or commented on)
        $likedPostIds = WorldFeedLike::where('user_id', $userId)->pluck('post_id')->toArray();
        $commentedPostIds = WorldFeedComment::where('user_id', $userId)->pluck('post_id')->toArray();
        $viewedPostIds = WorldFeedView::where('user_id', $userId)->pluck('post_id')->toArray();
        
        // Get tags from posts user interacted with (to find similar content)
        $interactedPostIds = array_unique(array_merge($likedPostIds, $commentedPostIds, $viewedPostIds));
        $preferredTags = [];
        if (!empty($interactedPostIds)) {
            $preferredTags = WorldFeedPost::whereIn('id', $interactedPostIds)
                ->whereNotNull('tags')
                ->pluck('tags')
                ->flatten()
                ->unique()
                ->values()
                ->toArray();
        }
        
        // Fetch posts with engagement metrics
        $posts = $query->get();
        
        // Calculate personalized score for each post
        $postsWithScores = $posts->map(function ($post) use ($userId, $followedCreatorIds, $likedPostIds, $commentedPostIds, $viewedPostIds, $preferredTags) {
            $score = 0;
            
            // Base engagement score (normalized)
            $engagement = ($post->likes_count ?? 0) + ($post->comments_count ?? 0) * 2 + ($post->views_count ?? 0) * 0.1;
            $score += min($engagement / 100, 50); // Cap at 50 points, normalize
            
            // Boost for followed creators (strong signal)
            if (in_array($post->creator_id, $followedCreatorIds)) {
                $score += 40;
            }
            
            // Boost for similar content (tags matching user's interests)
            if (!empty($preferredTags) && !empty($post->tags)) {
                $matchingTags = count(array_intersect($post->tags, $preferredTags));
                $score += $matchingTags * 10; // 10 points per matching tag
            }
            
            // Recency boost (newer posts get slight boost)
            $daysSinceCreated = now()->diffInDays($post->created_at);
            if ($daysSinceCreated <= 1) {
                $score += 20; // Very recent posts
            } elseif ($daysSinceCreated <= 7) {
                $score += 10; // This week
            } elseif ($daysSinceCreated <= 30) {
                $score += 5; // This month
            }
            // Older posts get no recency boost
            
            // Penalty for posts user already interacted with (reduce duplicates)
            if (in_array($post->id, $likedPostIds) || in_array($post->id, $commentedPostIds)) {
                $score *= 0.3; // Heavy penalty - still show but lower priority
            } elseif (in_array($post->id, $viewedPostIds)) {
                $score *= 0.7; // Moderate penalty for viewed posts
            }
            
            // Avoid showing user's own posts (unless they have high engagement)
            if ($post->creator_id === $userId) {
                $score *= 0.2; // Heavy penalty for own posts
            }
            
            // ── Collaborative filtering: boost posts popular with similar users ──
            // "Similar users" = users who liked the same posts as me
            // We approximate this with the engagement rate of the post among recent viewers
            $recentViews = $post->views_count ?? 0;
            if ($recentViews > 0) {
                $likeRate = ($post->likes_count ?? 0) / $recentViews;
                $score += min($likeRate * 30, 15); // Up to 15 points for viral content
            }

            // ── Diversity nudge: de-boost if creator recently shown ────────────────
            // (handled by caller via viewed-post penalty above)

            // Add some randomness to prevent exact same feed every time
            $score += random_int(0, 5);
            
            return [
                'post' => $post,
                'score' => $score,
            ];
        });
        
        // Sort by score (highest first) and then by created_at (newest first) for tie-breaking
        $sortedPosts = $postsWithScores->sortByDesc(function ($item) {
            return [$item['score'], $item['post']->created_at->timestamp];
        })->values();
        
        // Paginate manually
        $currentPage = request()->input('page', 1);
        $offset = ($currentPage - 1) * $perPage;
        $paginatedPosts = $sortedPosts->slice($offset, $perPage);
        
        // Create a custom paginator
        return new \Illuminate\Pagination\LengthAwarePaginator(
            $paginatedPosts->pluck('post'),
            $sortedPosts->count(),
            $perPage,
            $currentPage,
            ['path' => request()->url(), 'query' => request()->query()]
        );
    }

    /**
     * Get activity feed (Instagram/TikTok-style notifications for world feed & live).
     * GET /api/v1/world-feed/activity
     */
    public function indexActivity(Request $request)
    {
        $user = $request->user();
        if (!FeatureFlagService::isEnabled('world_feed', $user)) {
            return response()->json(['message' => 'World feed feature is not available'], 403);
        }

        $perPage = min(max((int) $request->input('per_page', 20), 1), 50);
        $activities = WorldFeedActivity::where('user_id', $user->id)
            ->with(['actor:id,name,username,avatar_path', 'post:id,type,thumbnail_url,media_url', 'broadcast:id,title,slug,status'])
            ->orderBy('created_at', 'desc')
            ->paginate($perPage);

        $items = $activities->getCollection()->map(function (WorldFeedActivity $a) {
            $actor = $a->actor;
            $avatarUrl = $actor && $actor->avatar_path
                ? Storage::disk('public')->url($actor->avatar_path)
                : ($actor->avatar_url ?? null);

            // WorldFeedPost accessors already return absolute URLs; do not wrap with Storage::url again.
            $postThumbnailUrl = null;
            if ($a->post) {
                $p = $a->post;
                $postThumbnailUrl = $p->thumbnail_url;
                if (! $postThumbnailUrl && ($p->type ?? 'image') === 'image' && $p->media_url) {
                    $postThumbnailUrl = $p->media_url;
                }
            }

            return [
                'id' => $a->id,
                'type' => $a->type,
                'summary' => $a->summary,
                'read_at' => $a->read_at?->toIso8601String(),
                'created_at' => $a->created_at->toIso8601String(),
                'actor' => $actor ? [
                    'id' => $actor->id,
                    'name' => $actor->name ?? 'User',
                    'username' => $actor->username,
                    'avatar_url' => $avatarUrl,
                ] : null,
                'post_id' => $a->post_id,
                'post_thumbnail_url' => $postThumbnailUrl,
                'comment_id' => $a->comment_id,
                'broadcast_id' => $a->broadcast_id,
                'broadcast_slug' => $a->broadcast?->slug,
                'broadcast_title' => $a->broadcast?->title,
            ];
        });
        $activities->setCollection($items);

        $unreadCount = WorldFeedActivity::where('user_id', $user->id)->unread()->count();

        return response()->json([
            'data' => $activities->items(),
            'pagination' => [
                'current_page' => $activities->currentPage(),
                'last_page' => $activities->lastPage(),
                'per_page' => $activities->perPage(),
                'total' => $activities->total(),
            ],
            'unread_count' => $unreadCount,
        ]);
    }

    /**
     * Mark activity items as read.
     * POST /api/v1/world-feed/activity/read
     * Body: { "activity_ids": [1,2,...] } or { "all": true }
     */
    public function markActivityRead(Request $request)
    {
        $user = $request->user();
        if (!FeatureFlagService::isEnabled('world_feed', $user)) {
            return response()->json(['message' => 'World feed feature is not available'], 403);
        }

        $query = WorldFeedActivity::where('user_id', $user->id)->whereNull('read_at');
        if ($request->boolean('all')) {
            $query->update(['read_at' => now()]);
            return response()->json(['message' => 'All activity marked as read', 'marked' => true]);
        }
        $ids = $request->input('activity_ids', []);
        if (!is_array($ids)) {
            return response()->json(['message' => 'activity_ids must be an array'], 422);
        }
        $ids = array_map('intval', array_filter($ids));
        if (empty($ids)) {
            return response()->json(['message' => 'No activity IDs provided', 'marked' => 0]);
        }
        $count = $query->whereIn('id', $ids)->update(['read_at' => now()]);
        return response()->json(['message' => 'Activity marked as read', 'marked' => $count]);
    }

    /**
     * Get unread activity count (for bell badge).
     * GET /api/v1/world-feed/activity/unread-count
     */
    public function unreadCount(Request $request)
    {
        $user = $request->user();
        if (!FeatureFlagService::isEnabled('world_feed', $user)) {
            return response()->json(['unread_count' => 0]);
        }
        $count = WorldFeedActivity::where('user_id', $user->id)->unread()->count();
        return response()->json(['unread_count' => $count]);
    }
}
