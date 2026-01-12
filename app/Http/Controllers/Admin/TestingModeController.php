<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\TestingMode;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/**
 * PHASE 2: Testing Mode Admin Controller
 * 
 * Allows admin to manage Testing Mode (user-scoped).
 */
class TestingModeController extends Controller
{
    /**
     * Get testing mode status
     * GET /admin/testing-mode
     */
    public function index()
    {
        $testingMode = TestingMode::first();
        
        if (!$testingMode) {
            $testingMode = TestingMode::create([
                'is_enabled' => false,
                'user_ids' => [],
                'features' => [],
            ]);
        }

        // Get user details for allowlisted users
        $users = [];
        if (!empty($testingMode->user_ids)) {
            $users = User::whereIn('id', $testingMode->user_ids)
                ->select('id', 'name', 'username', 'phone')
                ->get();
        }

        return response()->json([
            'testing_mode' => $testingMode,
            'allowlisted_users' => $users,
        ]);
    }

    /**
     * Enable/disable testing mode
     * POST /admin/testing-mode/toggle
     */
    public function toggle(Request $request)
    {
        $testingMode = TestingMode::firstOrCreate([]);

        $testingMode->update([
            'is_enabled' => !$testingMode->is_enabled,
        ]);

        // Clear user caches
        if (!empty($testingMode->user_ids)) {
            foreach ($testingMode->user_ids as $userId) {
                Cache::forget("testing_mode_user_{$userId}");
            }
        }

        Log::info('Testing mode toggled', [
            'admin_id' => auth()->id(),
            'enabled' => $testingMode->is_enabled,
        ]);

        return response()->json([
            'status' => 'success',
            'testing_mode' => $testingMode,
        ]);
    }

    /**
     * Add user to testing mode allowlist
     * POST /admin/testing-mode/users
     */
    public function addUser(Request $request)
    {
        $request->validate([
            'user_id' => ['required', 'exists:users,id'],
        ]);

        $testingMode = TestingMode::firstOrCreate([]);
        $userIds = $testingMode->user_ids ?? [];
        
        if (!in_array($request->input('user_id'), $userIds)) {
            $userIds[] = $request->input('user_id');
            $testingMode->update(['user_ids' => $userIds]);
            
            Cache::forget("testing_mode_user_{$request->input('user_id')}");
            
            // Clear all feature flag caches to ensure changes are reflected
            \App\Services\FeatureFlagService::clearAllCaches();
            
            // Log the change
            Log::info('User added to testing mode allowlist', [
                'admin_id' => auth()->id(),
                'user_id' => $request->input('user_id'),
                'timestamp' => now(),
            ]);
        }

        return response()->json([
            'status' => 'success',
            'message' => 'User added to testing mode allowlist',
            'testing_mode' => $testingMode,
        ]);
    }

    /**
     * Remove user from testing mode allowlist
     * DELETE /admin/testing-mode/users/{userId}
     */
    public function removeUser($userId)
    {
        $testingMode = TestingMode::firstOrCreate([]);
        $userIds = $testingMode->user_ids ?? [];
        
        $userIds = array_values(array_filter($userIds, fn($id) => $id != $userId));
        $testingMode->update(['user_ids' => $userIds]);
        
        Cache::forget("testing_mode_user_{$userId}");
        
        // Clear all feature flag caches to ensure changes are reflected
        \App\Services\FeatureFlagService::clearAllCaches();

        return response()->json([
            'status' => 'success',
            'testing_mode' => $testingMode,
        ]);
    }

    /**
     * Update testing mode limits
     * PUT /admin/testing-mode
     */
    public function update(Request $request)
    {
        $request->validate([
            'max_lives' => ['nullable', 'integer', 'min:0'],
            'max_test_rooms' => ['nullable', 'integer', 'min:0'],
            'max_test_users' => ['nullable', 'integer', 'min:0'],
            'features' => ['nullable', 'array'],
        ]);

        $testingMode = TestingMode::firstOrCreate([]);

        $testingMode->update([
            'max_lives' => $request->input('max_lives', $testingMode->max_lives),
            'max_test_rooms' => $request->input('max_test_rooms', $testingMode->max_test_rooms),
            'max_test_users' => $request->input('max_test_users', $testingMode->max_test_users),
            'features' => $request->input('features', $testingMode->features),
        ]);

        return response()->json([
            'status' => 'success',
            'testing_mode' => $testingMode,
        ]);
    }
}
