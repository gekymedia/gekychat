<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\WorldFeedPost;
use App\Models\WorldFeedLike;
use App\Models\WorldFeedComment;
use App\Models\WorldFeedFollow;
use App\Services\FeatureFlagService;
use App\Services\Audio\AudioService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;

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

        // PHASE 2: Check username requirement
        if (!$user->username) {
            return response()->json([
                'message' => 'Username is required to access World Feed',
                'requires_username' => true,
            ], 403);
        }

        if (!FeatureFlagService::isEnabled('world_feed', $user)) {
            return response()->json(['message' => 'World feed feature is not available'], 403);
        }

        $perPage = $request->input('per_page', 10);
        $userId = $request->user()->id;
        $creatorId = $request->input('creator_id'); // Filter by creator if provided
        $searchQuery = $request->input('q'); // Search query

        // Get public posts, ordered by engagement (likes + comments + views)
        $query = WorldFeedPost::where('is_public', true)
            ->with(['creator:id,name,avatar_path,username', 'audio.audio']);
        
        // Filter by creator_id if provided
        if ($creatorId) {
            $query->where('creator_id', $creatorId);
        }
        
        // Search by query if provided (search in caption and tags)
        if ($searchQuery) {
            $query->where(function($q) use ($searchQuery) {
                $q->where('caption', 'like', "%{$searchQuery}%")
                  ->orWhereJsonContains('tags', $searchQuery);
            });
        }
        
        $posts = $query->orderByRaw('(likes_count + comments_count + views_count) DESC')
            ->orderBy('created_at', 'desc')
            ->paginate($perPage);

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
            
            return [
                'id' => $post->id,
                'share_code' => $post->share_code,
                'type' => $post->type ?? 'image', // Default to image instead of text
                'media_type' => $post->type ?? 'image', // Also include as media_type for compatibility
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

        // PHASE 2: Check username requirement
        if (!$user->username) {
            return response()->json([
                'message' => 'Username is required to create posts',
                'requires_username' => true,
            ], 403);
        }

        if (!FeatureFlagService::isEnabled('world_feed', $user)) {
            return response()->json(['message' => 'World feed feature is not available'], 403);
        }

        $request->validate([
            'caption' => 'nullable|string|max:500',
            'media' => 'required|file|mimes:jpeg,png,jpg,gif,mp4,mov,avi|max:100000', // 100MB max, required like TikTok
            'tags' => 'nullable|array',
            'tags.*' => 'string|max:50',
            'audio_id' => 'nullable|integer|exists:audio_library,id',
            'audio_volume' => 'nullable|integer|min:0|max:100',
            'audio_loop' => 'nullable|boolean',
        ]);

        // Media is required - World feed is like TikTok (no text-only posts)
        if (!$request->hasFile('media')) {
            return response()->json([
                'message' => 'Media is required. World feed only supports image or video posts.',
            ], 422);
        }

        $file = $request->file('media');
        $filename = 'worldfeed_' . uniqid() . '.' . $file->getClientOriginalExtension();
        $path = $file->storeAs('world-feed', $filename, 'public');

        // Auto-detect type from file MIME type
        $mimeType = $file->getMimeType();
        $type = str_starts_with($mimeType, 'video/') ? 'video' : 'image';

        $data = [
            'creator_id' => $request->user()->id,
            'type' => $type,
            'caption' => $request->caption,
            'media_url' => $path,
            'is_public' => true,
            'tags' => $request->tags ?? [],
        ];

        // TODO: Generate thumbnail for videos
        // TODO: Extract duration for videos

        $post = WorldFeedPost::create($data);
        
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
     * Check if user is following a creator
     */
    private function isFollowingCreator(int $followerId, int $creatorId): bool
    {
        return WorldFeedFollow::where('follower_id', $followerId)
            ->where('creator_id', $creatorId)
            ->exists();
    }
}
