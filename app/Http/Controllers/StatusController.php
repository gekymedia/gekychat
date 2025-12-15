<?php

namespace App\Http\Controllers;

use App\Models\Status;
use App\Models\StatusView;
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
        $request->validate([
            'type' => 'required|in:text,image,video',
            'content' => 'required_if:type,text|nullable|string',
            'background_color' => 'nullable|string|max:7',
            'text_color' => 'nullable|string|max:7',
            'font_size' => 'nullable|integer|min:12|max:72',
            'duration' => 'required|integer|in:3600,21600,43200,86400',

        ]);

        Status::where('user_id', Auth::id())
            ->where('created_at', '<', now()->subDay())
            ->delete();

        $status = Status::create([
            'user_id' => Auth::id(),
            'type' => $request->type,
            'content' => $request->content ?? '',
            'background_color' => $request->background_color ?? '#000000',
            'text_color' => $request->text_color ?? '#FFFFFF',
            'font_size' => $request->font_size ?? 24,
            'duration' => (int) $request->duration,
        ]);

        if ($request->hasFile('media') && in_array($request->type, ['image', 'video'])) {
            $file = $request->file('media');
            $path = $file->store('statuses', 'public');
            $status->update(['media_path' => $path]);
        }

        broadcast(new \App\Events\StatusCreated($status))->toOthers();

        return response()->json([
            'success' => true,
            'message' => 'Status updated successfully',
            'status' => $status->load('user')
        ]);
    }

    public function viewStatus(Request $request, $statusId)
    {
        $status = Status::findOrFail($statusId);

        $isContact = Auth::user()->contacts()
            ->where('contact_id', $status->user_id)
            ->exists();

        if (!$isContact) {
            return response()->json([
                'success' => false,
                'message' => 'Cannot view status from non-contact'
            ], 403);
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

        if ($status->media_path) {
            Storage::disk('public')->delete($status->media_path);
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

    private function getMyCurrentStatus()
    {
        return Status::with('views')
            ->where('user_id', Auth::id())
            ->where('created_at', '>=', now()->subDay())
            ->orderBy('created_at', 'desc')
            ->first();
    }
    //testing
}
