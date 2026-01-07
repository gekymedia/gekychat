<?php

namespace App\Http\Controllers\Api\V1;

use App\Events\StatusCreated;
use App\Events\StatusViewed;
use App\Http\Controllers\Controller;
use App\Models\Contact;
use App\Models\Status;
use App\Models\StatusMute;
use App\Models\StatusPrivacySetting;
use App\Models\StatusView;
use App\Services\FeatureFlagService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Intervention\Image\ImageManager;
use Intervention\Image\Drivers\Gd\Driver;

class StatusController extends Controller
{
    /**
     * 4.1 Get All Statuses
     * GET /api/v1/statuses
     */
    public function index(Request $request)
    {
        $user = $request->user();

        // Get statuses from user's contacts (excluding muted users)
        $statuses = Status::with(['user', 'views'])
            ->notExpired()
            ->visibleTo($user->id)
            ->where('user_id', '!=', $user->id) // Exclude own statuses
            ->orderBy('created_at', 'desc')
            ->get()
            ->groupBy('user_id');

        $formattedStatuses = [];

        foreach ($statuses as $userId => $userStatuses) {
            $statusUser = $userStatuses->first()->user;
            
            // Check if user is muted
            $isMuted = StatusMute::isMuted($user->id, $userId);

            // Check if any status is unviewed
            $hasUnviewed = $userStatuses->contains(function ($status) use ($user) {
                return !$status->views()->where('user_id', $user->id)->exists();
            });

            $formattedStatuses[] = [
                'user_id' => $userId,
                'user_name' => $statusUser->name,
                'user_avatar' => $statusUser->avatar_url,
                'updates' => $userStatuses->map(function ($status) use ($user) {
                    return [
                        'id' => $status->id,
                        'user_id' => $status->user_id,
                        'type' => $status->type,
                        'text' => $status->text,
                        'media_url' => $status->media_url,
                        'thumbnail_url' => $status->thumbnail_url,
                        'background_color' => $status->background_color,
                        'font_family' => $status->font_family,
                        'created_at' => $status->created_at->toIso8601String(),
                        'expires_at' => $status->expires_at->toIso8601String(),
                        'view_count' => $status->view_count,
                        'viewed' => $status->views()->where('user_id', $user->id)->exists(),
                    ];
                })->values(),
                'last_updated_at' => $userStatuses->max('created_at')->toIso8601String(),
                'has_unviewed' => $hasUnviewed,
                'is_muted' => $isMuted,
            ];
        }

        // Sort by last_updated_at descending
        usort($formattedStatuses, function ($a, $b) {
            return strtotime($b['last_updated_at']) - strtotime($a['last_updated_at']);
        });

        return response()->json([
            'statuses' => $formattedStatuses,
        ]);
    }

    /**
     * 4.2 Get My Status
     * GET /api/v1/statuses/mine
     */
    public function mine(Request $request)
    {
        $user = $request->user();

        $statuses = Status::where('user_id', $user->id)
            ->notExpired()
            ->orderBy('created_at', 'desc')
            ->get();

        $totalViews = $statuses->sum('view_count');

        return response()->json([
            'updates' => $statuses->map(function ($status) {
                return [
                    'id' => $status->id,
                    'user_id' => $status->user_id,
                    'type' => $status->type,
                    'text' => $status->text,
                    'media_url' => $status->media_url,
                    'thumbnail_url' => $status->thumbnail_url,
                    'background_color' => $status->background_color,
                    'font_family' => $status->font_family,
                    'created_at' => $status->created_at->toIso8601String(),
                    'expires_at' => $status->expires_at->toIso8601String(),
                    'view_count' => $status->view_count,
                    'viewed' => true, // Always true for own status
                ];
            })->values(),
            'last_updated_at' => $statuses->first()?->created_at?->toIso8601String(),
            'total_views' => $totalViews,
        ]);
    }

    /**
     * 4.3 Get User Status
     * GET /api/v1/statuses/user/{userId}
     * 
     * PHASE 1: Enhanced with server-side privacy enforcement
     */
    public function userStatus(Request $request, $userId)
    {
        $currentUser = $request->user();

        // Owner can always view their own statuses
        if ($userId == $currentUser->id) {
            $user = \App\Models\User::findOrFail($userId);
            $statuses = Status::where('user_id', $userId)
                ->notExpired()
                ->orderBy('created_at', 'desc')
                ->get();
        } else {
            // PHASE 1: Check privacy settings server-side
            $user = \App\Models\User::findOrFail($userId);
            $statuses = Status::where('user_id', $userId)
                ->notExpired()
                ->orderBy('created_at', 'desc')
                ->get()
                ->filter(function ($status) use ($currentUser) {
                    return $status->canBeViewedBy($currentUser->id);
                });
            
            // If no statuses visible, return 403
            if ($statuses->isEmpty()) {
                return response()->json([
                    'message' => 'You do not have permission to view this user\'s statuses',
                ], 403);
            }
        }

        $isMuted = StatusMute::isMuted($currentUser->id, $userId);
        $hasUnviewed = $statuses->contains(function ($status) use ($currentUser) {
            return !$status->views()->where('user_id', $currentUser->id)->exists();
        });

        return response()->json([
            'user_id' => $user->id,
            'user_name' => $user->name,
            'user_avatar' => $user->avatar_url,
            'updates' => $statuses->map(function ($status) use ($currentUser) {
                // PHASE 1: Include allow_download in response
                return [
                    'id' => $status->id,
                    'user_id' => $status->user_id,
                    'type' => $status->type,
                    'text' => $status->text,
                    'media_url' => $status->media_url,
                    'thumbnail_url' => $status->thumbnail_url,
                    'background_color' => $status->background_color,
                    'font_family' => $status->font_family,
                    'created_at' => $status->created_at->toIso8601String(),
                    'expires_at' => $status->expires_at->toIso8601String(),
                    'view_count' => $status->view_count,
                    'viewed' => $status->views()->where('user_id', $currentUser->id)->exists(),
                    'allow_download' => $status->allow_download ?? true, // PHASE 1: Include download permission
                ];
            })->values(),
            'last_updated_at' => $statuses->first()?->created_at?->toIso8601String(),
            'has_unviewed' => $hasUnviewed,
            'is_muted' => $isMuted,
        ]);
    }

    /**
     * 4.4 Create Text Status
     * 4.5 Create Image Status
     * 4.6 Create Video Status
     * POST /api/v1/statuses
     */
    public function store(Request $request)
    {
        $user = $request->user();

        // Validate based on type
        $request->validate([
            'type' => 'required|in:text,image,video',
            'text' => 'nullable|string|max:700',
            'background_color' => 'nullable|string|regex:/^#[0-9A-Fa-f]{6}$/',
            'font_family' => 'nullable|string|max:50',
            'media' => 'nullable|file',
        ]);

        $data = [
            'user_id' => $user->id,
            'type' => $request->type,
            'text' => $request->text,
            'background_color' => $request->background_color ?? '#00A884',
            'font_family' => $request->font_family ?? 'default',
        ];

        // Handle media upload
        if ($request->hasFile('media')) {
            if ($request->type === 'image') {
                $data = array_merge($data, $this->handleImageUpload($request->file('media')));
            } elseif ($request->type === 'video') {
                $data = array_merge($data, $this->handleVideoUpload($request->file('media')));
            }
        }

        // PHASE 1: Set allow_download permission (default to true for backward compatibility)
        $data['allow_download'] = $request->boolean('allow_download', true);

        $status = Status::create($data);

        // Broadcast to contacts
        broadcast(new StatusCreated($status))->toOthers();

        return response()->json([
            'status' => [
                'id' => $status->id,
                'user_id' => $status->user_id,
                'type' => $status->type,
                'text' => $status->text,
                'media_url' => $status->media_url,
                'thumbnail_url' => $status->thumbnail_url,
                'background_color' => $status->background_color,
                'font_family' => $status->font_family,
                'created_at' => $status->created_at->toIso8601String(),
                'expires_at' => $status->expires_at->toIso8601String(),
                'view_count' => 0,
                'viewed' => false,
            ],
        ], 201);
    }

    /**
     * Handle image upload
     */
    private function handleImageUpload($file): array
    {
        // Validate image
        if (!in_array($file->getClientOriginalExtension(), ['jpg', 'jpeg', 'png', 'gif', 'webp'])) {
            abort(422, 'Invalid image format');
        }

        // Max 10MB
        if ($file->getSize() > 10 * 1024 * 1024) {
            abort(422, 'Image size must not exceed 10MB');
        }

        $manager = new ImageManager(new Driver());
        $image = $manager->read($file);

        // Generate filename
        $filename = 'status_' . uniqid() . '.jpg';
        $thumbFilename = 'status_' . uniqid() . '_thumb.jpg';

        // Optimize and save main image
        if ($file->getSize() > 2 * 1024 * 1024) {
            $image->scale(width: 1080);
        }
        
        $path = 'statuses/' . $filename;
        Storage::disk('public')->put($path, (string) $image->toJpeg(85));

        // Generate thumbnail
        $thumbnail = clone $image;
        $thumbnail->cover(400, 400);
        $thumbPath = 'statuses/' . $thumbFilename;
        Storage::disk('public')->put($thumbPath, (string) $thumbnail->toJpeg(80));

        return [
            'media_url' => $path,
            'thumbnail_url' => $thumbPath,
        ];
    }

    /**
     * Handle video upload
     */
    private function handleVideoUpload($file): array
    {
        // Validate video
        if (!in_array($file->getClientOriginalExtension(), ['mp4', 'mov', 'avi'])) {
            abort(422, 'Invalid video format');
        }

        // Max 50MB
        if ($file->getSize() > 50 * 1024 * 1024) {
            abort(422, 'Video size must not exceed 50MB');
        }

        // Store video
        $filename = 'status_' . uniqid() . '.' . $file->getClientOriginalExtension();
        $path = $file->storeAs('statuses', $filename, 'public');

        // TODO: Generate video thumbnail (requires FFmpeg)
        // For now, use a placeholder or skip thumbnail
        $thumbPath = null;

        return [
            'media_url' => $path,
            'thumbnail_url' => $thumbPath,
        ];
    }

    /**
     * 4.7 Mark Status as Viewed
     * POST /api/v1/statuses/{id}/view
     * 
     * PHASE 1: Implemented stealth viewing support and privacy enforcement
     */
    public function view(Request $request, $id)
    {
        $user = $request->user();
        $status = Status::findOrFail($id);

        // PHASE 1: Check privacy - enforce server-side
        if (!$status->canBeViewedBy($user->id)) {
            return response()->json([
                'success' => false,
                'message' => 'You do not have permission to view this status',
            ], 403);
        }

        // Don't count owner's views
        if ($status->user_id === $user->id) {
            return response()->json([
                'success' => true,
                'view_count' => $status->view_count,
            ]);
        }

        // PHASE 1: Check stealth mode (feature flag protected)
        $stealthView = false;
        if (FeatureFlagService::isEnabled('stealth_status_viewing', $user)) {
            // Use PrivacyService to check if user has stealth viewing enabled
            $stealthView = $request->boolean('stealth', \App\Services\PrivacyService::canStealthViewStatus($user));
        }

        // Create or update view record
        $view = StatusView::firstOrCreate(
            [
                'status_id' => $status->id,
                'user_id' => $user->id,
            ],
            [
                'viewed_at' => now(),
                'stealth_view' => $stealthView, // PHASE 1: Track stealth views
            ]
        );

        // If this is a new view, increment count
        if ($view->wasRecentlyCreated) {
            $status->incrementViewCount();
            $status->refresh();

            // PHASE 1: Only broadcast if not stealth view
            if (!$stealthView) {
                broadcast(new StatusViewed($status->id, $user->id))->toOthers();
            }
        }

        return response()->json([
            'success' => true,
            'view_count' => $status->view_count,
        ]);
    }

    /**
     * 4.8 Get Status Viewers
     * GET /api/v1/statuses/{id}/viewers
     */
    public function viewers(Request $request, $id)
    {
        $user = $request->user();
        $status = Status::findOrFail($id);

        // Only owner can view viewers
        if ($status->user_id !== $user->id) {
            return response()->json([
                'message' => 'Forbidden',
            ], 403);
        }

        // PHASE 1: Filter out stealth views from viewers list
        $viewers = StatusView::where('status_id', $status->id)
            ->where('stealth_view', false) // PHASE 1: Exclude stealth views
            ->with('user')
            ->orderBy('viewed_at', 'desc')
            ->get();

        return response()->json([
            'viewers' => $viewers->map(function ($view) {
                return [
                    'user_id' => $view->user->id,
                    'user_name' => $view->user->name,
                    'user_avatar' => $view->user->avatar_url,
                    'viewed_at' => $view->viewed_at->toIso8601String(),
                ];
            }),
        ]);
    }

    /**
     * 4.9 Delete Status
     * DELETE /api/v1/statuses/{id}
     */
    public function destroy(Request $request, $id)
    {
        $user = $request->user();
        $status = Status::findOrFail($id);

        // Only owner can delete
        if ($status->user_id !== $user->id) {
            return response()->json([
                'message' => 'Forbidden',
            ], 403);
        }

        // Delete media files
        if ($status->media_url) {
            Storage::disk('public')->delete($status->media_url);
        }
        if ($status->thumbnail_url) {
            Storage::disk('public')->delete($status->thumbnail_url);
        }

        // Soft delete status
        $status->delete();

        return response()->json([
            'success' => true,
        ]);
    }

    /**
     * PHASE 1: Download Status Media
     * GET /api/v1/statuses/{id}/download
     * 
     * Downloads status media (image/video) if download is permitted.
     * Respects allow_download flag and feature flag.
     */
    public function download(Request $request, $id)
    {
        $user = $request->user();
        $status = Status::findOrFail($id);

        // PHASE 1: Check if download feature is enabled
        if (!\App\Services\FeatureFlagService::isEnabled('status_download', $user)) {
            return response()->json([
                'message' => 'Status download feature is not available',
            ], 403);
        }

        // PHASE 1: Check if user can view this status (privacy check)
        if (!$status->canBeViewedBy($user->id)) {
            return response()->json([
                'message' => 'You do not have permission to view this status',
            ], 403);
        }

        // PHASE 1: Check if download is allowed
        if (!$status->allow_download) {
            return response()->json([
                'message' => 'Download is not permitted for this status',
            ], 403);
        }

        // Only image and video statuses can be downloaded
        if (!in_array($status->type, ['image', 'video'])) {
            return response()->json([
                'message' => 'This status type cannot be downloaded',
            ], 422);
        }

        if (!$status->media_url) {
            return response()->json([
                'message' => 'Media not found',
            ], 404);
        }

        // Get the file path
        $filePath = $status->media_url;
        
        // If it's a full URL, extract path (shouldn't happen but handle it)
        if (str_starts_with($filePath, 'http')) {
            // Extract path from URL
            $pathParts = parse_url($filePath);
            $filePath = ltrim($pathParts['path'] ?? '', '/storage/');
        }

        // Check if file exists
        if (!Storage::disk('public')->exists($filePath)) {
            return response()->json([
                'message' => 'Media file not found',
            ], 404);
        }

        // Return file download response
        return Storage::disk('public')->download($filePath);
    }

    /**
     * 4.10 Get Privacy Settings
     * GET /api/v1/statuses/privacy
     */
    public function getPrivacy(Request $request)
    {
        $user = $request->user();

        $settings = StatusPrivacySetting::firstOrCreate(
            ['user_id' => $user->id],
            [
                'privacy' => 'contacts',
                'excluded_user_ids' => [],
                'included_user_ids' => [],
            ]
        );

        return response()->json([
            'privacy' => $settings->privacy,
            'excluded_user_ids' => $settings->excluded_user_ids ?? [],
            'included_user_ids' => $settings->included_user_ids ?? [],
        ]);
    }

    /**
     * 4.11 Update Privacy Settings
     * PUT /api/v1/statuses/privacy
     */
    public function updatePrivacy(Request $request)
    {
        $user = $request->user();

        $request->validate([
            'privacy' => 'required|in:everyone,contacts,contacts_except,only_share_with',
            'excluded_user_ids' => 'nullable|array',
            'excluded_user_ids.*' => 'integer|exists:users,id',
            'included_user_ids' => 'nullable|array',
            'included_user_ids.*' => 'integer|exists:users,id',
        ]);

        StatusPrivacySetting::updateOrCreate(
            ['user_id' => $user->id],
            [
                'privacy' => $request->privacy,
                'excluded_user_ids' => $request->excluded_user_ids ?? [],
                'included_user_ids' => $request->included_user_ids ?? [],
            ]
        );

        return response()->json([
            'success' => true,
        ]);
    }

    /**
     * 4.12 Mute User Status
     * POST /api/v1/statuses/user/{userId}/mute
     */
    public function muteUser(Request $request, $userId)
    {
        $user = $request->user();

        StatusMute::firstOrCreate([
            'user_id' => $user->id,
            'muted_user_id' => $userId,
        ]);

        return response()->json([
            'success' => true,
        ]);
    }

    /**
     * 4.13 Unmute User Status
     * POST /api/v1/statuses/user/{userId}/unmute
     */
    public function unmuteUser(Request $request, $userId)
    {
        $user = $request->user();

        StatusMute::where('user_id', $user->id)
            ->where('muted_user_id', $userId)
            ->delete();

        return response()->json([
            'success' => true,
        ]);
    }
}

