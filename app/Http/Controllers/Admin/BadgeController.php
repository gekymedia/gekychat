<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\UserBadge;
use App\Models\AuditLog;
use Illuminate\Http\Request;

class BadgeController extends Controller
{
    /**
     * Get all badges
     */
    public function index()
    {
        $badges = UserBadge::active()->get();
        return response()->json(['data' => $badges]);
    }
    
    /**
     * Get user's badges
     */
    public function userBadges(User $user)
    {
        $badges = $user->badges;
        return response()->json(['data' => $badges]);
    }
    
    /**
     * Assign badge to user
     */
    public function assign(Request $request, User $user)
    {
        if (!$request->user()->is_admin) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }
        
        $request->validate([
            'badge_id' => 'required|exists:user_badges,id',
            'notes' => 'nullable|string|max:500',
        ]);
        
        $badge = UserBadge::findOrFail($request->badge_id);
        
        // Check if user already has this badge
        if ($user->hasBadge($badge->name)) {
            return response()->json([
                'message' => "{$user->name} already has the '{$badge->display_name}' badge",
            ], 400);
        }
        
        $badge->assignTo($user, $request->user(), $request->notes);
        
        // Log the action
        AuditLog::log(
            'badge_assigned',
            $user,
            "Badge '{$badge->display_name}' assigned to user"
        );
        
        return response()->json([
            'message' => "Badge '{$badge->display_name}' assigned to {$user->name} successfully",
            'user' => $user->load('badges'),
        ]);
    }
    
    /**
     * Remove badge from user
     */
    public function remove(Request $request, User $user, UserBadge $badge)
    {
        if (!$request->user()->is_admin) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }
        
        $badge->removeFrom($user);
        
        // Log the action
        AuditLog::log(
            'badge_removed',
            $user,
            "Badge '{$badge->display_name}' removed from user"
        );
        
        return response()->json([
            'message' => "Badge '{$badge->display_name}' removed from {$user->name} successfully",
            'user' => $user->load('badges'),
        ]);
    }
}
