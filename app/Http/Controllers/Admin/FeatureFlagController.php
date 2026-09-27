<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\FeatureFlag;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/**
 * ADMIN PANEL: Feature Flag Management Controller
 * 
 * Allows admin to manage feature flags system-wide.
 */
class FeatureFlagController extends Controller
{
    /**
     * Feature flags management page (mobile-friendly admin UI)
     * GET /admin/feature-flags/manage
     */
    public function page()
    {
        return view('admin.feature_flags.index');
    }

    /**
     * Get all feature flags
     * GET /admin/feature-flags
     */
    public function index(Request $request)
    {
        $flags = FeatureFlag::orderBy('key')->get();

        if ($request->expectsJson() || $request->wantsJson() || $request->ajax()
            || str_contains((string) $request->header('Accept'), 'application/json')) {
            return response()->json([
                'data' => $flags,
            ]);
        }

        // Fallback for browsers opening the JSON route directly.
        return view('admin.feature_flags.index');
    }

    /**
     * Update a feature flag
     * PUT /admin/feature-flags/{id}
     */
    public function update(Request $request, $id)
    {
        $flag = FeatureFlag::findOrFail($id);
        
        $request->validate([
            'enabled' => ['required', 'boolean'],
        ]);
        
        $oldValue = $flag->enabled;
        $flag->update([
            'enabled' => $request->boolean('enabled'),
        ]);
        
        // Clear cache using FeatureFlagService
        \App\Services\FeatureFlagService::clearCache($flag->key);
        
        // Log the change
        Log::info('Feature flag updated', [
            'admin_id' => auth()->id(),
            'flag_key' => $flag->key,
            'old_value' => $oldValue,
            'new_value' => $flag->enabled,
            'timestamp' => now(),
        ]);
        
        return response()->json([
            'status' => 'success',
            'message' => "Feature flag {$flag->key} " . ($flag->enabled ? 'enabled' : 'disabled'),
            'data' => $flag,
        ]);
    }

    /**
     * Toggle a feature flag by key
     * POST /admin/feature-flags/{key}/toggle
     *
     * Optional JSON body: { "enabled": true|false } to set an explicit value.
     * Without a body, the current value is flipped.
     */
    public function toggle(Request $request, $key)
    {
        // Create flag if it doesn't exist
        $flag = FeatureFlag::firstOrCreate(
            ['key' => $key],
            [
                'enabled' => false,
                'platform' => 'all',
                'description' => ucfirst(str_replace('_', ' ', $key)),
            ]
        );
        
        $oldValue = $flag->enabled;
        $enabled = $request->has('enabled')
            ? $request->boolean('enabled')
            : !$flag->enabled;

        $flag->update([
            'enabled' => $enabled,
        ]);
        
        // Clear cache using FeatureFlagService
        \App\Services\FeatureFlagService::clearCache($flag->key);
        
        // Log the change
        Log::info('Feature flag toggled', [
            'admin_id' => auth()->id(),
            'flag_key' => $flag->key,
            'old_value' => $oldValue,
            'new_value' => $flag->enabled,
            'timestamp' => now(),
        ]);
        
        return response()->json([
            'status' => 'success',
            'message' => "Feature flag {$flag->key} " . ($flag->enabled ? 'enabled' : 'disabled'),
            'data' => $flag,
        ]);
    }
}

