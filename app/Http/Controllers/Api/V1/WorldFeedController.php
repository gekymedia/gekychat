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
use App\Services\FeatureFlagService;
use App\Services\Audio\AudioService;
use App\Services\VideoUploadLimitService;
use App\Helpers\VideoThumbnailHelper;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;
use Illuminate\Pagination\LengthAwarePaginator;

/**
 * PHASE 2: World Feed Controller
 * 
 * Public discovery feed similar to TikTok - vertical scroll feed with short-form content.
 */
class WorldFeedController extends Controller
{
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
            // For searches and creator filters, use simple engagement-based ordering
            $posts = $query->orderByRaw('(likes_count + comments_count + views_count) DESC')
                ->orderBy('created_at', 'desc')
                ->paginate($perPage);
        }

        // Transform posts data
        $transformedPosts = $posts->getCollection()->map(function ($post) use ($userId) {
            try {
                if (method_exists($post, 'markAsViewed')) {
                    $post->markAsViewed($userId); // Track view
                }
            } catch (\Exception $e) {
                // Silently handle if markAsViewed fails
                \Log::warning('Failed to mark post as viewed', ['post_id' => $post->id, 'error' => $e->getMessage()]);
            }

            // Get creator info safely
            $creator = $post->creator ?? null;

            // Get media URLs - use accessor which handles URL generation properly
            // The model accessor uses UrlHelper::secureStorageUrl which handles HTTPS
            $mediaUrl = $post->getRawOriginal('media_url');
            $thumbnailUrl = $post->getRawOriginal('thumbnail_url');
            
            // Generate full URLs if paths exist
            if ($mediaUrl && !str_starts_with($mediaUrl, 'http')) {
                try {
                    $mediaUrl = \App\Helpers\UrlHelper::secureStorageUrl($mediaUrl, 'public');
                } catch (\Exception $e) {
                    \Log::error('Failed to generate media URL', [
                        'path' => $mediaUrl,
                        'error' => $e->getMessage(),
                        'post_id' => $post->id,
                    ]);
                    // Fallback to asset helper
                    $mediaUrl = asset('storage/' . ltrim($mediaUrl, '/'));
                }
            }
            
            if ($thumbnailUrl && !str_starts_with($thumbnailUrl, 'http')) {
                try {
                    $thumbnailUrl = \App\Helpers\UrlHelper::secureStorageUrl($thumbnailUrl, 'public');
                } catch (\Exception $e) {
                    \Log::error('Failed to generate thumbnail URL', [
                        'path' => $thumbnailUrl,
                        'error' => $e->getMessage(),
                        'post_id' => $post->id,
                    ]);
                    // Fallback to asset helper
                    $thumbnailUrl = asset('storage/' . ltrim($thumbnailUrl, '/'));
                }
            }
            
            // Get audio data if attached
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
                'thumbnail_url' => $thumbnailUrl,
                'duration' => $post->duration,
                'likes_count' => $post->likes_count ?? 0,
                'comments_count' => $post->comments_count ?? 0,
                'views_count' => $post->views_count ?? 0,
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
            ];

            // Include original post for duet/stitch playback (media_url, creator, duration, stitch segment)
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
            'media'        => 'required|file|mimes:jpeg,png,jpg,gif,mp4,mov,avi|max:100000',
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

        // Media is required - World feed is like TikTok (no text-only posts)
        if (!$request->hasFile('media')) {
            return response()->json([
                'message' => 'Media is required. World feed only supports image or video posts.',
            ], 422);
        }

        $file = $request->file('media');
        
        // Validate video upload limits if it's a video
        $mimeType = $file->getMimeType();
        $isVideo = str_starts_with($mimeType, 'video/');
        $duration = null;
        $validation = null;
        
        if ($isVideo) {
            $limitService = app(VideoUploadLimitService::class);
            $validation = $limitService->validateWorldFeedVideo($file, $user->id);
            
            if (!$validation['valid']) {
                return response()->json([
                    'message' => $validation['error'],
                    'requires_trim' => $validation['requires_trim'] ?? false,
                    'duration' => $validation['duration'] ?? null,
                    'max_duration' => $validation['max_duration'] ?? null,
                ], 422);
            }
            
            // Extract duration if available
            $duration = $validation['duration'] ?? null;
        }
        
        $filename = 'worldfeed_' . uniqid() . '.' . $file->getClientOriginalExtension();
        $path = $file->storeAs('world-feed', $filename, 'public');

        // Auto-detect type from file MIME type (already detected above)
        $type = $isVideo ? 'video' : 'image';

        // Generate thumbnail for videos
        $thumbnailPath = null;
        if ($isVideo) {
            try {
                $thumbnailPath = VideoThumbnailHelper::generateThumbnail(
                    $path,
                    'public',
                    'world-feed/' . pathinfo($filename, PATHINFO_FILENAME) . '_thumb.jpg',
                    1, // Capture at 1 second
                    640, // Width (larger for world feed)
                    360  // Height (16:9 aspect ratio)
                );
            } catch (\Exception $e) {
                \Log::error('Failed to generate world feed video thumbnail', [
                    'error' => $e->getMessage(),
                    'video_path' => $path,
                ]);
                // Continue without thumbnail if generation fails
            }
        }

        $originalPostId = $request->input('original_post_id');
        $postType = $request->input('post_type', 'original');
        if ($originalPostId) {
            $original = WorldFeedPost::find($originalPostId);
            if (!$original || $original->type !== 'video') {
                return response()->json(['message' => 'Duet/Stitch is only allowed with a video post.'], 422);
            }
            $postType = in_array($postType, ['duet', 'stitch']) ? $postType : 'duet';
        } else {
            $postType = 'original';
        }

        $data = [
            'creator_id' => $request->user()->id,
            'original_post_id' => $originalPostId,
            'post_type' => $postType,
            'stitch_start_ms' => $postType === 'stitch' ? $request->input('stitch_start_ms') : null,
            'stitch_end_ms' => $postType === 'stitch' ? $request->input('stitch_end_ms') : null,
            'type' => $type,
            'caption' => $request->caption,
            'media_url' => $path,
            'thumbnail_url' => $thumbnailPath,
            'is_public' => true,
            'tags' => $request->tags ?? [],
            'duration' => $duration,
        ];

        $post = WorldFeedPost::create($data);

        // Dispatch boomerang processing job if requested
        if ($isVideo && $request->boolean('is_boomerang')) {
            // We use the attachment via a pseudo-attachment row, or simply queue on the post path.
            // Create a temporary Attachment record the job can operate on.
            $fakeAttachment = \App\Models\Attachment::create([
                'attachable_type'    => \App\Models\WorldFeedPost::class,
                'attachable_id'      => $post->id,
                'file_path'          => $path,
                'mime_type'          => $mimeType,
                'is_video'           => true,
                'compression_status' => 'pending',
            ]);
            \App\Jobs\ProcessBoomerangVideo::dispatch($fakeAttachment->id);
        }

        // Dispatch video filter baking if non-default filter values provided
        if ($isVideo && $request->has('filter_values')) {
            $filters = $request->input('filter_values', []);
            $b = (float) ($filters['brightness'] ?? 0.0);
            $c = (float) ($filters['contrast']   ?? 1.0);
            $s = (float) ($filters['saturation'] ?? 1.0);
            if (abs($b) > 0.01 || abs($c - 1.0) > 0.01 || abs($s - 1.0) > 0.01) {
                $filterAttachment = \App\Models\Attachment::firstOrCreate(
                    [
                        'attachable_type' => \App\Models\WorldFeedPost::class,
                        'attachable_id'   => $post->id,
                        'file_path'       => $path,
                    ],
                    ['mime_type' => $mimeType, 'is_video' => true, 'compression_status' => 'pending']
                );
                \App\Jobs\ProcessVideoFilters::dispatch($filterAttachment->id, $filters);
            }
        }

        // Attach audio if provided
        if ($request->has('audio_id') && $request->audio_id) {
            try {
                $audioService = app(AudioService::class);
                $audioService->attachToPost(
                    $post->id,
                    $request->audio_id,
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
                // Don't fail the post creation if audio attachment fails
            }
        }

        return response()->json([
            'message' => 'Post created',
            'data' => $post->load('creator'),
        ], 201);
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
        }

        return response()->json([
            'message' => $liked ? 'Post liked' : 'Post unliked',
            'likes_count' => $post->fresh()->likes_count,
            'is_liked' => $liked,
        ]);
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
     * Like/unlike a comment
     * POST /api/v1/world-feed/comments/{commentId}/like
     */
    public function likeComment(Request $request, $commentId)
    {
        $comment = WorldFeedComment::findOrFail($commentId);
        $userId = $request->user()->id;

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

        return response()->json([
            'message' => $liked ? 'Comment liked' : 'Comment unliked',
            'likes_count' => $comment->fresh()->likes_count,
            'is_liked' => $liked,
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

        // Delete associated media file if it exists
        if ($post->media_url && Storage::disk('public')->exists($post->media_url)) {
            Storage::disk('public')->delete($post->media_url);
        }
        if ($post->thumbnail_url && Storage::disk('public')->exists($post->thumbnail_url)) {
            Storage::disk('public')->delete($post->thumbnail_url);
        }

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
}
