<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\UploadSetting;
use App\Models\UserUploadLimit;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class UploadSettingsController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth');
        $this->middleware('admin');
    }

    /**
     * Display upload settings management page
     */
    public function index(Request $request)
    {
        $globalSettings = [
            'world_feed_max_duration' => UploadSetting::getWorldFeedMaxDuration(),
            'status_max_duration' => UploadSetting::getStatusMaxDuration(),
            'chat_video_max_size' => UploadSetting::getChatVideoMaxSize(),
        ];

        $broadcastSettings = UploadSetting::getBroadcastSettings();

        $userOverrides = UserUploadLimit::with(['user:id,name,phone'])
            ->orderBy('created_at', 'desc')
            ->paginate(20);

        return view('admin.upload-settings.index', compact('globalSettings', 'broadcastSettings', 'userOverrides'));
    }

    /**
     * Update global upload settings
     */
    public function updateGlobalSettings(Request $request)
    {
        $request->validate([
            'world_feed_max_duration' => 'required|integer|min:1|max:600',
            'status_max_duration' => 'required|integer|min:1|max:600',
            'chat_video_max_size' => 'required|numeric|min:1|max:100', // MB
        ]);

        try {
            DB::beginTransaction();

            UploadSetting::setValue(
                'world_feed_max_duration',
                $request->world_feed_max_duration,
                'integer',
                'Maximum video duration for World Feed posts (in seconds).'
            );

            UploadSetting::setValue(
                'status_max_duration',
                $request->status_max_duration,
                'integer',
                'Maximum video duration for Status videos (in seconds).'
            );

            // Convert MB to bytes
            $chatVideoMaxSizeBytes = (int) ($request->chat_video_max_size * 1048576);
            UploadSetting::setValue(
                'chat_video_max_size',
                $chatVideoMaxSizeBytes,
                'integer',
                'Maximum file size for chat videos (in bytes).'
            );

            DB::commit();

            return redirect()->route('admin.upload-settings.index')
                ->with('success', 'Global upload settings updated successfully.');
        } catch (\Exception $e) {
            DB::rollBack();
            return redirect()->route('admin.upload-settings.index')
                ->with('error', 'Failed to update settings: ' . $e->getMessage());
        }
    }

    /**
     * Update broadcast settings
     */
    public function updateBroadcastSettings(Request $request)
    {
        $request->validate([
            'broadcast_attachments_enabled' => 'required|boolean',
            'broadcast_max_attachments' => 'required|integer|min:1|max:50',
            'broadcast_max_recipients' => 'required|integer|min:1|max:1000',
            'broadcast_max_messages_per_day' => 'required|integer|min:0|max:1000',
            'broadcast_admin_only' => 'required|boolean',
            'broadcast_max_file_size' => 'required|numeric|min:1|max:100', // MB
        ]);

        try {
            DB::beginTransaction();

            UploadSetting::setValue(
                'broadcast_attachments_enabled',
                $request->broadcast_attachments_enabled ? '1' : '0',
                'boolean',
                'Allow attachments (images, videos, documents) in broadcast messages'
            );

            UploadSetting::setValue(
                'broadcast_max_attachments',
                $request->broadcast_max_attachments,
                'integer',
                'Maximum number of attachments per broadcast message'
            );

            UploadSetting::setValue(
                'broadcast_max_recipients',
                $request->broadcast_max_recipients,
                'integer',
                'Maximum number of recipients per broadcast list'
            );

            UploadSetting::setValue(
                'broadcast_max_messages_per_day',
                $request->broadcast_max_messages_per_day,
                'integer',
                'Maximum broadcast messages a user can send per day (0 = unlimited)'
            );

            UploadSetting::setValue(
                'broadcast_admin_only',
                $request->broadcast_admin_only ? '1' : '0',
                'boolean',
                'Restrict broadcast feature to admin users only'
            );

            // Convert MB to bytes
            $maxFileSizeBytes = (int) ($request->broadcast_max_file_size * 1048576);
            UploadSetting::setValue(
                'broadcast_max_file_size',
                $maxFileSizeBytes,
                'integer',
                'Maximum file size for broadcast attachments (in bytes)'
            );

            DB::commit();

            return redirect()->route('admin.upload-settings.index')
                ->with('success', 'Broadcast settings updated successfully.');
        } catch (\Exception $e) {
            DB::rollBack();
            return redirect()->route('admin.upload-settings.index')
                ->with('error', 'Failed to update broadcast settings: ' . $e->getMessage());
        }
    }

    /**
     * Get all user overrides (API)
     */
    public function getUserOverrides(Request $request)
    {
        $overrides = UserUploadLimit::with(['user:id,name,phone', 'setByAdmin:id,name'])
            ->orderBy('created_at', 'desc')
            ->paginate(20);

        return response()->json(['data' => $overrides]);
    }

    /**
     * Create user-specific override
     */
    public function createUserOverride(Request $request)
    {
        $request->validate([
            'user_id' => 'required|exists:users,id',
            'world_feed_max_duration' => 'nullable|integer|min:1|max:600',
            'status_max_duration' => 'nullable|integer|min:1|max:600',
            'chat_video_max_size' => 'nullable|numeric|min:1|max:100', // MB
        ]);

        try {
            $override = UserUploadLimit::firstOrCreate(
                ['user_id' => $request->user_id],
                ['set_by_admin_id' => auth()->id()]
            );
            
            // Convert MB to bytes if provided
            $chatVideoMaxSizeBytes = $request->filled('chat_video_max_size') && $request->chat_video_max_size
                ? (int) ($request->chat_video_max_size * 1048576) 
                : null;
            
            $override->update([
                'world_feed_max_duration' => $request->world_feed_max_duration,
                'status_max_duration' => $request->status_max_duration,
                'chat_video_max_size' => $chatVideoMaxSizeBytes,
                'set_by_admin_id' => auth()->id(),
            ]);

            return redirect()->route('admin.upload-settings.index')
                ->with('success', 'User override created successfully.');
        } catch (\Exception $e) {
            return redirect()->route('admin.upload-settings.index')
                ->with('error', 'Failed to create override: ' . $e->getMessage());
        }
    }

    /**
     * Update user override
     */
    public function updateUserOverride(Request $request, $id)
    {
        $request->validate([
            'world_feed_max_duration' => 'nullable|integer|min:1|max:600',
            'status_max_duration' => 'nullable|integer|min:1|max:600',
            'chat_video_max_size' => 'nullable|numeric|min:1|max:100', // MB
        ]);

        try {
            $override = UserUploadLimit::findOrFail($id);
            
            // Convert MB to bytes if provided  
            $chatVideoMaxSizeBytes = $request->filled('chat_video_max_size') && $request->chat_video_max_size
                ? (int) ($request->chat_video_max_size * 1048576) 
                : null;
            
            $updateData = [];
            if ($request->filled('world_feed_max_duration')) {
                $updateData['world_feed_max_duration'] = $request->world_feed_max_duration;
            }
            if ($request->filled('status_max_duration')) {
                $updateData['status_max_duration'] = $request->status_max_duration;
            }
            if ($chatVideoMaxSizeBytes !== null) {
                $updateData['chat_video_max_size'] = $chatVideoMaxSizeBytes;
            }
            
            $override->update($updateData);

            return redirect()->route('admin.upload-settings.index')
                ->with('success', 'User override updated successfully.');
        } catch (\Exception $e) {
            return redirect()->route('admin.upload-settings.index')
                ->with('error', 'Failed to update override: ' . $e->getMessage());
        }
    }

    /**
     * Delete user override (soft delete)
     */
    public function deleteUserOverride($id)
    {
        try {
            $override = UserUploadLimit::findOrFail($id);
            $override->delete();

            return redirect()->route('admin.upload-settings.index')
                ->with('success', 'User override deleted successfully.');
        } catch (\Exception $e) {
            return redirect()->route('admin.upload-settings.index')
                ->with('error', 'Failed to delete override: ' . $e->getMessage());
        }
    }

    /**
     * Search users for override creation (API)
     */
    public function searchUsers(Request $request)
    {
        $query = $request->input('q', '');
        
        if (strlen($query) < 2) {
            return response()->json(['data' => []]);
        }

        $users = User::where('name', 'like', "%{$query}%")
            ->orWhere('phone', 'like', "%{$query}%")
            ->orWhere('email', 'like', "%{$query}%")
            ->select('id', 'name', 'phone', 'email')
            ->limit(20)
            ->get();

        return response()->json(['data' => $users]);
    }
}
