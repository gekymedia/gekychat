<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\CallSession;
use App\Models\Contact;
use App\Models\User;
use Illuminate\Http\Request;

class CallLogController extends Controller
{
    /**
     * GET /api/v1/calls
     * Get call logs for the authenticated user
     */
    public function index(Request $request)
    {
        $user = $request->user();
        
        // Get all calls where user is either caller or callee
        $calls = CallSession::where(function($query) use ($user) {
                $query->where('caller_id', $user->id)
                      ->orWhere('callee_id', $user->id);
            })
            ->whereNull('group_id') // Only direct calls for now
            ->with(['caller:id,name,phone,avatar_path', 'callee:id,name,phone,avatar_path'])
            ->orderBy('created_at', 'desc')
            ->paginate(20);
        
        // Eager load contacts for phone fallback
        $otherUserIds = $calls->getCollection()->map(function($call) use ($user) {
            return $call->caller_id === $user->id ? $call->callee_id : $call->caller_id;
        })->filter()->unique()->values()->toArray();
        
        $contacts = Contact::where('user_id', $user->id)
            ->where('is_deleted', false)
            ->whereIn('contact_user_id', $otherUserIds)
            ->get()
            ->keyBy('contact_user_id');
        
        // Transform calls
        $calls->getCollection()->transform(function($call) use ($user, $contacts) {
            $duration = null;
            if ($call->started_at && $call->ended_at) {
                $duration = $call->started_at->diffInSeconds($call->ended_at);
            }
            
            $isMissed = !$call->started_at || ($duration !== null && $duration < 2);
            $isOutgoing = $call->caller_id === $user->id;
            
            // Determine other user - for outgoing calls it's the callee, for incoming it's the caller
            $otherUser = $isOutgoing ? $call->callee : $call->caller;
            $otherUserId = $isOutgoing ? $call->callee_id : $call->caller_id;
            
            // If other user is null (e.g., callee_id was null), try to load directly
            if (!$otherUser && $otherUserId) {
                $otherUser = User::find($otherUserId);
            }
            
            // Get contact for phone fallback
            $contact = $otherUserId ? $contacts->get($otherUserId) : null;
            
            // Build other_user data - always try to provide meaningful data
            $otherUserData = null;
            if ($otherUser) {
                // Resolve phone: user's phone -> contact's phone -> null
                $phone = $otherUser->phone;
                if (empty($phone) && $contact && !empty($contact->phone)) {
                    $phone = $contact->phone;
                }
                
                // Resolve name: contact display_name -> user's name -> phone -> placeholder
                $name = $otherUser->name;
                if ($contact && !empty($contact->display_name)) {
                    $name = $contact->display_name;
                } elseif (empty($name) || $name === 'Unknown') {
                    $name = $phone ?? 'User ' . $otherUser->id;
                }
                
                $otherUserData = [
                    'id' => $otherUser->id,
                    'name' => $name,
                    'phone' => $phone,
                    'avatar_url' => $otherUser->avatar_path ? asset('storage/'.$otherUser->avatar_path) : null,
                ];
            } elseif ($otherUserId) {
                // Fallback: provide minimal data with the ID and contact info if available
                $phone = $contact ? $contact->phone : null;
                $name = $contact && !empty($contact->display_name) 
                    ? $contact->display_name 
                    : ($phone ?? 'User ' . $otherUserId);
                
                $otherUserData = [
                    'id' => $otherUserId,
                    'name' => $name,
                    'phone' => $phone,
                    'avatar_url' => null,
                ];
            }
            
            return [
                'id' => $call->id,
                'type' => $call->type,
                'duration' => $duration,
                'is_missed' => $isMissed,
                'is_outgoing' => $isOutgoing,
                'caller_id' => $call->caller_id,
                'callee_id' => $call->callee_id,
                'other_user' => $otherUserData,
                'started_at' => $call->started_at?->toIso8601String(),
                'ended_at' => $call->ended_at?->toIso8601String(),
                'created_at' => $call->created_at->toIso8601String(),
            ];
        });
        
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


