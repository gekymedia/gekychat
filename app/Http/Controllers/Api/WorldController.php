<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;

class WorldController extends Controller
{
    /**
     * Search world feed content
     */
    public function search(Request $request)
    {
        $request->validate([
            'query' => 'required|string|min:2|max:255',
        ]);

        $query = $request->input('query');
        $userId = Auth::id();

        // Search videos
        $videos = DB::table('world_posts')
            ->where('type', 'video')
            ->where(function ($q) use ($query) {
                $q->where('title', 'LIKE', "%{$query}%")
                  ->orWhere('description', 'LIKE', "%{$query}%")
                  ->orWhere('hashtags', 'LIKE', "%{$query}%");
            })
            ->select('id', 'title', 'thumbnail', 'views_count')
            ->orderBy('views_count', 'desc')
            ->limit(20)
            ->get()
            ->map(fn($post) => [
                'id' => $post->id,
                'type' => 'video',
                'title' => $post->title,
                'thumbnail' => $post->thumbnail,
                'views' => $post->views_count,
            ]);

        // Search users
        $users = DB::table('users')
            ->where(function ($q) use ($query) {
                $q->where('name', 'LIKE', "%{$query}%")
                  ->orWhere('username', 'LIKE', "%{$query}%");
            })
            ->select('id', 'name', 'username', 'avatar', 'followers_count')
            ->limit(20)
            ->get()
            ->map(function ($user) use ($userId) {
                return [
                    'id' => $user->id,
                    'name' => $user->name,
                    'username' => $user->username,
                    'avatar' => $user->avatar,
                    'followers_count' => $user->followers_count ?? 0,
                    'is_following' => $userId ? $this->isFollowing($userId, $user->id) : false,
                ];
            });

        // Search hashtags
        $hashtags = DB::table('world_hashtags')
            ->where('name', 'LIKE', "%{$query}%")
            ->select('name', 'posts_count')
            ->orderBy('posts_count', 'desc')
            ->limit(20)
            ->get();

        // Top results (mix of all)
        $top = $videos->take(10)->merge($users->take(5));

        return response()->json([
            'success' => true,
            'top' => $top,
            'videos' => $videos,
            'users' => $users,
            'hashtags' => $hashtags,
        ]);
    }

    /**
     * Get search suggestions
     */
    public function searchSuggestions(Request $request)
    {
        $query = $request->input('q', '');
        $userId = Auth::id();

        if (empty($query)) {
            // Return recent searches
            $recent = DB::table('world_search_history')
                ->where('user_id', $userId)
                ->orderBy('created_at', 'desc')
                ->limit(10)
                ->pluck('query')
                ->map(fn($q) => [
                    'text' => $q,
                    'type' => 'recent',
                    'icon' => 'history',
                ]);

            return response()->json(['suggestions' => $recent]);
        }

        // Get suggestions based on history and clicks
        $suggestions = DB::table('world_search_history')
            ->where('user_id', $userId)
            ->where('query', 'LIKE', "%{$query}%")
            ->select('query')
            ->groupBy('query')
            ->orderBy(DB::raw('COUNT(*)'), 'desc')
            ->limit(10)
            ->pluck('query')
            ->map(fn($q) => [
                'text' => $q,
                'type' => 'history',
                'icon' => 'search',
            ]);

        return response()->json(['suggestions' => $suggestions]);
    }

    /**
     * Get trending searches
     */
    public function searchTrending()
    {
        $trending = DB::table('world_search_history')
            ->select('query', DB::raw('COUNT(*) as count'))
            ->where('created_at', '>=', now()->subDays(7))
            ->groupBy('query')
            ->orderBy('count', 'desc')
            ->limit(10)
            ->pluck('query');

        return response()->json(['trending' => $trending]);
    }

    /**
     * Track search click
     */
    public function trackSearchClick(Request $request)
    {
        $request->validate([
            'query' => 'required|string',
            'type' => 'required|in:user,video,hashtag,post',
            'id' => 'required|integer',
        ]);

        DB::table('world_search_clicks')->insert([
            'user_id' => Auth::id(),
            'query' => $request->input('query'),
            'clicked_type' => $request->input('type'),
            'clicked_id' => $request->input('id'),
            'created_at' => now(),
        ]);

        // Also save to search history
        DB::table('world_search_history')->insert([
            'user_id' => Auth::id(),
            'query' => $request->input('query'),
            'created_at' => now(),
        ]);

        return response()->json(['success' => true]);
    }

    /**
     * Get post comments
     */
    public function getComments(Request $request, $postId)
    {
        $comments = DB::table('world_comments')
            ->where('post_id', $postId)
            ->whereNull('parent_id')
            ->orderBy('created_at', 'desc')
            ->get()
            ->map(function ($comment) {
                return $this->formatComment($comment);
            });

        return response()->json([
            'success' => true,
            'comments' => $comments,
        ]);
    }

    /**
     * Post a comment
     */
    public function postComment(Request $request, $postId)
    {
        $request->validate([
            'text' => 'required|string|max:1000',
            'parent_id' => 'nullable|integer|exists:world_comments,id',
        ]);

        $commentId = DB::table('world_comments')->insertGetId([
            'post_id' => $postId,
            'user_id' => Auth::id(),
            'parent_id' => $request->input('parent_id'),
            'text' => $request->input('text'),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return response()->json([
            'success' => true,
            'comment_id' => $commentId,
        ]);
    }

    /**
     * Toggle comment like
     */
    public function toggleCommentLike(Request $request, $commentId)
    {
        $userId = Auth::id();

        $exists = DB::table('world_comment_likes')
            ->where('comment_id', $commentId)
            ->where('user_id', $userId)
            ->exists();

        if ($exists) {
            DB::table('world_comment_likes')
                ->where('comment_id', $commentId)
                ->where('user_id', $userId)
                ->delete();
            $isLiked = false;
        } else {
            DB::table('world_comment_likes')->insert([
                'comment_id' => $commentId,
                'user_id' => $userId,
                'created_at' => now(),
            ]);
            $isLiked = true;
        }

        $likesCount = DB::table('world_comment_likes')
            ->where('comment_id', $commentId)
            ->count();

        return response()->json([
            'success' => true,
            'is_liked' => $isLiked,
            'likes_count' => $likesCount,
        ]);
    }

    /**
     * Follow a user
     */
    public function followUser(Request $request, $userId)
    {
        $currentUserId = Auth::id();

        $exists = DB::table('follows')
            ->where('follower_id', $currentUserId)
            ->where('following_id', $userId)
            ->exists();

        if ($exists) {
            DB::table('follows')
                ->where('follower_id', $currentUserId)
                ->where('following_id', $userId)
                ->delete();
            $isFollowing = false;
        } else {
            DB::table('follows')->insert([
                'follower_id' => $currentUserId,
                'following_id' => $userId,
                'created_at' => now(),
            ]);
            $isFollowing = true;
        }

        return response()->json([
            'success' => true,
            'is_following' => $isFollowing,
        ]);
    }

    /**
     * Get user's world posts
     */
    public function getUserPosts(Request $request, $userId)
    {
        $posts = DB::table('world_posts')
            ->where('user_id', $userId)
            ->orderBy('created_at', 'desc')
            ->paginate(20);

        return response()->json([
            'success' => true,
            'posts' => $posts,
        ]);
    }

    // Helper methods
    private function formatComment($comment)
    {
        $userId = Auth::id();

        $user = DB::table('users')
            ->where('id', $comment->user_id)
            ->first(['id', 'name', 'avatar']);

        $likesCount = DB::table('world_comment_likes')
            ->where('comment_id', $comment->id)
            ->count();

        $isLiked = $userId ? DB::table('world_comment_likes')
            ->where('comment_id', $comment->id)
            ->where('user_id', $userId)
            ->exists() : false;

        $replies = DB::table('world_comments')
            ->where('parent_id', $comment->id)
            ->orderBy('created_at', 'asc')
            ->get()
            ->map(fn($reply) => $this->formatComment($reply));

        return [
            'id' => $comment->id,
            'text' => $comment->text,
            'created_at' => $comment->created_at,
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'avatar' => $user->avatar,
            ],
            'likes_count' => $likesCount,
            'is_liked' => $isLiked,
            'replies' => $replies,
        ];
    }

    private function isFollowing($followerId, $followingId)
    {
        return DB::table('follows')
            ->where('follower_id', $followerId)
            ->where('following_id', $followingId)
            ->exists();
    }
}
