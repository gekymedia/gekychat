<?php

namespace App\Http\Controllers;

use App\Models\Status;
use App\Models\StatusView;
use App\Models\User;
use App\Models\Contact;
use App\Notifications\StatusPosted;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;

class StatusController extends Controller
{
    public function getStatuses(Request $request)
    {
        $user = Auth::user();

        $statuses = Status::with([
            'user',
            'views' => function ($q) use ($user) {
                // Only current user's views for this endpoint
                $q->where('user_id', $user->id);
            }
        ])

            ->whereHas('user.contacts', function ($query) use ($user) {
                $query->where('user_id', $user->id);
            })
            ->where('created_at', '>=', now()->subDay())
            ->where(function ($query) use ($user) {
                $query->whereDoesntHave('views', function ($q) use ($user) {
                    $q->where('user_id', $user->id);
                })
                    ->orWhereHas('views', function ($q) use ($user) {
                        $q->where('user_id', $user->id)
                            ->where('viewed_at', '>=', now()->subDay());
                    });
            })
            ->orderBy('created_at', 'desc')
            ->get()
            ->groupBy('user_id');

        $formattedStatuses = [];
        foreach ($statuses as $userId => $userStatuses) {
            $user = $userStatuses->first()->user;
            $unviewedCount = $userStatuses
                ->filter(function ($status) {
                    // views is now a collection of ONLY the current user's views
                    return $status->views->isEmpty();
                })
                ->count();


            $formattedStatuses[] = [
                'user' => [
                    'id' => $user->id,
                    'name' => $user->name,
                    'avatar_url' => $user->avatar_url,
                    'initial' => $user->initial,
                ],
                'statuses' => $userStatuses->map(function ($status) use ($user) {
                    return [
                        'id' => $status->id,
                        'type' => $status->type,
                        'content' => $status->content,
                        'background_color' => $status->background_color,
                        'text_color' => $status->text_color,
                        'font_size' => $status->font_size,
                        'duration' => $status->duration,
                        'is_viewed' => $status->views->isNotEmpty(),
                        'created_at' => $status->created_at,
                        'expires_at' => $status->created_at->addSeconds($status->duration),
                        'time_ago' => $status->created_at->diffForHumans(),
                    ];
                }),
                'unviewed_count' => $unviewedCount,
                'last_updated' => $userStatuses->max('created_at'),
            ];
        }

        usort($formattedStatuses, function ($a, $b) {
            return $b['last_updated'] <=> $a['last_updated'];
        });

        return response()->json([
            'success' => true,
            'statuses' => $formattedStatuses,
            'my_status' => $this->getMyCurrentStatus()
        ]);
    }

    public function createStatus(Request $request)
    {
        // Validate input first
        $request->validate([
            'content' => 'nullable|string|max:500',
            'background_color' => 'nullable|string|max:7',
            'text_color' => 'nullable|string|max:7',
            'font_size' => 'nullable|integer|min:12|max:72',
            'media' => 'nullable|array',
            'media.*' => 'file|mimes:jpeg,jpg,png,webp,gif,mp4,webm|max:10240',
        ]);

        // Check if media is uploaded
        $hasMedia = $request->hasFile('media') && $request->file('media');
        $type = 'text'; // Default to text
        
        // Auto-detect type based on uploaded media
        if ($hasMedia) {
            $files = $request->file('media');
            $firstFile = is_array($files) ? $files[0] : $files;
            
            if ($firstFile && $firstFile->isValid()) {
                $mimeType = $firstFile->getMimeType();
                if ($mimeType && str_starts_with($mimeType, 'image/')) {
                    $type = 'image';
                } elseif ($mimeType && str_starts_with($mimeType, 'video/')) {
                    $type = 'video';
                }
            }
        }

        // Validation: Must have either text or media
        $hasContent = !empty(trim($request->input('content', '')));
        if (!$hasMedia && !$hasContent) {
            return response()->json([
                'success' => false,
                'message' => 'Please enter some text or upload a media file for your status.'
            ], 422);
        }

        Status::where('user_id', Auth::id())
            ->where('created_at', '<', now()->subDay())
            ->delete();

        // Duration is fixed at 24 hours (86400 seconds)
        $duration = 86400;

        $statusData = [
            'user_id' => Auth::id(),
            'type' => $type,
            'text' => $request->input('content') ?? ($request->input('text') ?? ''),
            'background_color' => $request->input('background_color') ?? '#000000',
            'text_color' => $request->input('text_color') ?? '#FFFFFF',
            'font_size' => $request->input('font_size') ?? 24,
            'duration' => $duration,
            'expires_at' => now()->addSeconds($duration),
        ];

        // Handle media upload (use first file if multiple selected)
        if ($hasMedia && in_array($type, ['image', 'video'])) {
            $files = $request->file('media');
            $firstFile = is_array($files) ? $files[0] : $files;
            if ($firstFile && $firstFile->isValid()) {
                $path = $firstFile->store('statuses', 'public');
                $statusData['media_url'] = $path;
            }
        }

        $status = Status::create($statusData);
        $status->load('user');

        // Broadcast status creation - use broadcast() instead of toOthers() so sender also gets it for real-time UI update
        broadcast(new \App\Events\StatusCreated($status));

        // Send notifications to all users who have the status creator as a contact
        $contacts = Contact::where('contact_user_id', Auth::id())
            ->where('is_deleted', false)
            ->with('user:id,name,email')
            ->get();

        foreach ($contacts as $contact) {
            if ($contact->user) {
                $contact->user->notify(new StatusPosted($status));
            }
        }

        return response()->json([
            'success' => true,
            'message' => 'Status updated successfully',
            'status' => $status
        ]);
    }

    public function viewStatus(Request $request, $statusId)
    {
        $status = Status::findOrFail($statusId);
        $currentUser = Auth::user();

        // Allow viewing own statuses or statuses from contacts
        if ($status->user_id != $currentUser->id) {
            $isContact = $currentUser->contacts()
                ->where('contact_user_id', $status->user_id)
                ->where('is_deleted', false)
                ->exists();

            if (!$isContact) {
                return response()->json([
                    'success' => false,
                    'message' => 'Cannot view status from non-contact'
                ], 403);
            }
        }

        StatusView::updateOrCreate(
            [
                'status_id' => $statusId,
                'user_id' => Auth::id()
            ],
            ['viewed_at' => now()]
        );

        broadcast(new \App\Events\StatusViewed($status->id, Auth::id()))->toOthers();

        return response()->json(['success' => true]);
    }

    public function deleteStatus($statusId)
    {
        $status = Status::where('user_id', Auth::id())
            ->findOrFail($statusId);

        if ($status->media_url) {
            // Remove 'storage/' prefix if present (Storage::url adds it)
            $path = str_replace('storage/', '', $status->media_url);
            Storage::disk('public')->delete($path);
        }

        $status->delete();

        return response()->json([
            'success' => true,
            'message' => 'Status deleted successfully'
        ]);
    }

    public function getStatusViewers($statusId)
    {
        $status = Status::where('user_id', Auth::id())
            ->findOrFail($statusId);

        $viewers = $status->views()
            ->with('user:id,name,avatar_path,phone')
            ->orderBy('viewed_at', 'desc')
            ->get()
            ->map(function ($view) {
                return [
                    'user' => [
                        'id' => $view->user->id,
                        'name' => $view->user->name,
                        'avatar_url' => $view->user->avatar_url,
                        'phone' => $view->user->phone,
                    ],
                    'viewed_at' => $view->viewed_at,
                    'time_ago' => $view->viewed_at->diffForHumans(),
                ];
            });

        return response()->json([
            'success' => true,
            'viewers' => $viewers,
            'total_views' => $viewers->count()
        ]);
    }

    /**
     * Get statuses for a specific user (Web Route for status viewer)
     */
    public function getUserStatuses(Request $request, $userId)
    {
        $user = User::findOrFail($userId);
        $currentUser = Auth::user();

        // Allow viewing own statuses or statuses from contacts
        if ($userId != $currentUser->id) {
            $isContact = $currentUser->contacts()
                ->where('contact_user_id', $userId)
                ->where('is_deleted', false)
                ->exists();

            if (!$isContact) {
                return response()->json([
                    'success' => false,
                    'message' => 'Cannot view status from non-contact'
                ], 403);
            }
        }

        // Get non-expired statuses, ordered by creation time (oldest first for proper viewing)
        $statuses = Status::where('user_id', $userId)
            ->where('expires_at', '>', now())
            ->orderBy('created_at', 'asc') // Show oldest first for proper viewing order
            ->get()
            ->map(function ($status) use ($currentUser) {
                return [
                    'id' => $status->id,
                    'type' => $status->type,
                    'text' => $status->text ?? '',
                    'content' => $status->text ?? '',
                    'media_url' => $status->media_url,
                    'background_color' => $status->background_color,
                    'text_color' => $status->text_color,
                    'font_size' => $status->font_size,
                    'duration' => $status->duration ?? 86400,
                    'created_at' => $status->created_at->toISOString(),
                    'expires_at' => $status->expires_at ? $status->expires_at->toISOString() : null,
                    'viewed' => $status->views()->where('user_id', $currentUser->id)->exists(),
                    'view_count' => $status->views()->count(),
                ];
            });

        return response()->json([
            'success' => true,
            'user_id' => $user->id,
            'user_name' => $user->name ?? $user->phone ?? 'User',
            'user_avatar' => $user->avatar_path ? Storage::disk('public')->url($user->avatar_path) : null,
            'updates' => $statuses->values()->all()
        ]);
    }

    private function getMyCurrentStatus()
    {
        return Status::with('views')
            ->where('user_id', Auth::id())
            ->where('created_at', '>=', now()->subDay())
            ->orderBy('created_at', 'desc')
            ->first();
    }
    
}
