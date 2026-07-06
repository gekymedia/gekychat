<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Contact;
use App\Support\CallLogPresenter;
use Illuminate\Http\Request;

class CallLogController extends Controller
{
    /**
     * GET /api/v1/calls
     * Get call logs for the authenticated user (1:1, group, and meeting calls).
     */
    public function index(Request $request)
    {
        $user = $request->user();

        $calls = CallLogPresenter::queryForUser($user)->paginate(20);

        $otherUserIds = $calls->getCollection()
            ->filter(fn ($call) => $call->group_id === null && ! $call->is_meeting)
            ->map(function ($call) use ($user) {
                return $call->caller_id === $user->id ? $call->callee_id : $call->caller_id;
            })
            ->filter()
            ->unique()
            ->values()
            ->toArray();

        $contacts = Contact::where('user_id', $user->id)
            ->where('is_deleted', false)
            ->whereIn('contact_user_id', $otherUserIds)
            ->get()
            ->keyBy('contact_user_id');

        $calls->getCollection()->transform(
            fn ($call) => CallLogPresenter::toApiArray($call, $user, $contacts)
        );

        return response()->json([
            'data' => $calls->items(),
            'pagination' => [
                'current_page' => $calls->currentPage(),
                'last_page' => $calls->lastPage(),
                'per_page' => $calls->perPage(),
                'total' => $calls->total(),
            ],
        ]);
    }
}
