<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\WorldFeedFollow;
use App\Models\WorldFeedPost;
use App\Services\FeatureFlagService;
use App\Http\Controllers\Traits\HasSidebarData;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;

class WorldFeedController extends Controller
{
    use HasSidebarData;

    public function index()
    {
        $user = Auth::user();
        
        if (!FeatureFlagService::isEnabled('world_feed', $user, 'web')) {
            abort(403, 'World Feed feature is not available');
        }
        
        if (!$user->username) {
            return redirect()->route('settings.index')
                ->with('error', 'Username is required to access World Feed. Please set your username in Settings.');
        }
        
        $sidebarData = $this->getSidebarData();
        
        return view('world_feed.index', $sidebarData);
    }

    /**
     * Activity feed page (Instagram/TikTok-style: likes, comments, follows, live).
     */
    public function activity()
    {
        $user = Auth::user();

        if (!FeatureFlagService::isEnabled('world_feed', $user, 'web')) {
            abort(403, 'World Feed feature is not available');
        }

        if (!$user->username) {
            return redirect()->route('settings.index')
                ->with('error', 'Username is required. Please set your username in Settings.');
        }

        $sidebarData = $this->getSidebarData();

        return view('world_feed.activity', $sidebarData);
    }

    /**
     * User profile page - shows user info and their posts
     */
    public function userProfile(Request $request, $userId)
    {
        $currentUser = Auth::user();
        
        if (!FeatureFlagService::isEnabled('world_feed', $currentUser, 'web')) {
            abort(403, 'World Feed feature is not available');
        }

        $profileUser = User::find($userId);
        
        if (!$profileUser) {
            abort(404, 'User not found');
        }

        // Check if current user is viewing their own profile
        $isOwnProfile = $currentUser->id === $profileUser->id;

        // Get avatar URL
        $avatarUrl = $profileUser->avatar_path
            ? Storage::disk('public')->url($profileUser->avatar_path)
            : ($profileUser->avatar_url ?? asset('icons/icon-192x192.png'));

        // Get follower/following counts
        $followersCount = WorldFeedFollow::where('creator_id', $profileUser->id)->count();
        $followingCount = WorldFeedFollow::where('follower_id', $profileUser->id)->count();

        // Check if current user is following this profile
        $isFollowing = !$isOwnProfile && WorldFeedFollow::where('follower_id', $currentUser->id)
            ->where('creator_id', $profileUser->id)
            ->exists();

        // Get posts count
        $postsCount = WorldFeedPost::where('creator_id', $profileUser->id)
            ->where('is_public', true)
            ->count();

        // Get total likes received
        $totalLikes = WorldFeedPost::where('creator_id', $profileUser->id)
            ->where('is_public', true)
            ->sum('likes_count');

        $sidebarData = $this->getSidebarData();

        return view('world_feed.user_profile', array_merge($sidebarData, [
            'profileUser' => $profileUser,
            'avatarUrl' => $avatarUrl,
            'followersCount' => $followersCount,
            'followingCount' => $followingCount,
            'isFollowing' => $isFollowing,
            'isOwnProfile' => $isOwnProfile,
            'postsCount' => $postsCount,
            'totalLikes' => $totalLikes,
        ]));
    }
}

