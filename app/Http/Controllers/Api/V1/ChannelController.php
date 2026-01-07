<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\ChannelPost;
use App\Models\ChannelFollower;
use App\Models\Group;
use App\Services\FeatureFlagService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

/**
 * PHASE 2: Channel Controller
 * 
 * Channels are one-way broadcasts. Admins post, followers view.
 */
class ChannelController extends Controller
{
    /**
     * Get all channels (feature flag protected)
     * GET /api/v1/channels
     */
    public function index(Request $request)
    {
        if (!FeatureFlagService::isEnabled('channels_enabled', $request->user())) {
            return response()->json(['message' => 'Channels feature is not available'], 403);
        }

        $channels = Group::channels()
            ->with(['owner:id,name,avatar_path'])
            ->withCount(['channelFollowers as followers_count'])
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->json([
            'data' => $channels->map(function ($channel) use ($request) {
                return [
                    'id' => $channel->id,
                    'name' => $channel->name,
                    'description' => $channel->description,
                    'avatar_url' => $channel->avatar_url,
                    'owner' => [
                        'id' => $channel->owner->id,
                        'name' => $channel->owner->name,
                        'avatar_url' => $channel->owner->avatar_url,
                    ],
                    'followers_count' => $channel->followers_count ?? 0,
                    'is_following' => $channel->isFollowedBy($request->user()->id),
                    'created_at' => $channel->created_at->toIso8601String(),
                ];
            }),
        ]);
    }

    /**
     * Follow a channel
     * POST /api/v1/channels/{id}/follow
     */
    public function follow(Request $request, $channelId)
    {
        if (!FeatureFlagService::isEnabled('channels_enabled', $request->user())) {
            return response()->json(['message' => 'Channels feature is not available'], 403);
        }

        $channel = Group::channels()->findOrFail($channelId);
        
        if (!$channel->isFollowedBy($request->user()->id)) {
            ChannelFollower::create([
                'channel_id' => $channel->id,
                'user_id' => $request->user()->id,
                'followed_at' => now(),
            ]);
        }

        return response()->json(['message' => 'Channel followed', 'success' => true]);
    }

    /**
     * Unfollow a channel
     * POST /api/v1/channels/{id}/unfollow
     */
    public function unfollow(Request $request, $channelId)
    {
        $channel = Group::channels()->findOrFail($channelId);
        ChannelFollower::where('channel_id', $channel->id)
            ->where('user_id', $request->user()->id)
            ->delete();

        return response()->json(['message' => 'Channel unfollowed', 'success' => true]);
    }

    /**
     * Get channel posts
     * GET /api/v1/channels/{id}/posts
     */
    public function posts(Request $request, $channelId)
    {
        if (!FeatureFlagService::isEnabled('channels_enabled', $request->user())) {
            return response()->json(['message' => 'Channels feature is not available'], 403);
        }

        $channel = Group::channels()->findOrFail($channelId);
        
        // Only followers can view posts
        if (!$channel->isFollowedBy($request->user()->id) && !$channel->isAdmin($request->user())) {
            return response()->json(['message' => 'You must follow this channel to view posts'], 403);
        }

        $posts = $channel->channelPosts()
            ->with(['poster:id,name,avatar_path'])
            ->orderBy('created_at', 'desc')
            ->paginate($request->input('per_page', 20));

        return response()->json([
            'data' => $posts->map(function ($post) use ($request) {
                $post->markAsViewed($request->user()->id);
                return [
                    'id' => $post->id,
                    'type' => $post->type,
                    'body' => $post->body,
                    'media_url' => $post->media_url,
                    'thumbnail_url' => $post->thumbnail_url,
                    'views_count' => $post->views_count,
                    'reactions_count' => $post->reactions_count,
                    'posted_by' => [
                        'id' => $post->poster->id,
                        'name' => $post->poster->name,
                        'avatar_url' => $post->poster->avatar_url,
                    ],
                    'created_at' => $post->created_at->toIso8601String(),
                ];
            }),
            'pagination' => [
                'current_page' => $posts->currentPage(),
                'last_page' => $posts->lastPage(),
                'per_page' => $posts->perPage(),
                'total' => $posts->total(),
            ],
        ]);
    }

    /**
     * Create a channel post (admin only)
     * POST /api/v1/channels/{id}/posts
     */
    public function createPost(Request $request, $channelId)
    {
        if (!FeatureFlagService::isEnabled('channels_enabled', $request->user())) {
            return response()->json(['message' => 'Channels feature is not available'], 403);
        }

        $channel = Group::channels()->findOrFail($channelId);
        
        // Only admins can post
        if (!$channel->isAdmin($request->user())) {
            return response()->json(['message' => 'Only channel admins can post'], 403);
        }

        $request->validate([
            'type' => 'required|in:text,image,video',
            'body' => 'nullable|string|max:5000',
            'media' => 'nullable|file|mimes:jpeg,png,jpg,gif,mp4,mov,avi|max:50000', // 50MB max
        ]);

        $data = [
            'channel_id' => $channel->id,
            'posted_by' => $request->user()->id,
            'type' => $request->type,
            'body' => $request->body,
        ];

        // Handle media upload
        if ($request->hasFile('media')) {
            $file = $request->file('media');
            $filename = 'channel_' . uniqid() . '.' . $file->getClientOriginalExtension();
            $path = $file->storeAs('channels', $filename, 'public');
            $data['media_url'] = $path;
        }

        $post = ChannelPost::create($data);

        // TODO: Broadcast new post to followers

        return response()->json([
            'message' => 'Post created',
            'data' => $post->load('poster'),
        ], 201);
    }

    /**
     * React to a channel post
     * POST /api/v1/channels/posts/{postId}/react
     */
    public function react(Request $request, $postId)
    {
        $post = ChannelPost::findOrFail($postId);
        $channel = $post->channel;

        // Only followers can react
        if (!$channel->isFollowedBy($request->user()->id)) {
            return response()->json(['message' => 'You must follow this channel to react'], 403);
        }

        $emoji = $request->input('emoji', '👍');

        // Toggle reaction (if exists, delete; if not, create)
        $existing = $post->reactions()->where('user_id', $request->user()->id)->first();
        
        if ($existing) {
            $existing->delete();
            $post->decrement('reactions_count');
        } else {
            $post->reactions()->create([
                'user_id' => $request->user()->id,
                'emoji' => $emoji,
            ]);
            $post->increment('reactions_count');
        }

        return response()->json([
            'message' => 'Reaction updated',
            'reactions_count' => $post->fresh()->reactions_count,
        ]);
    }
}
