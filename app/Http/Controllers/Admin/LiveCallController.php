<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\LiveBroadcast;
use App\Models\CallSession;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * ADMIN PANEL: Live & Call Management Controller
 * 
 * Allows admin to monitor and manage active calls and live broadcasts.
 */
class LiveCallController extends Controller
{
    /**
     * Get active calls and live broadcasts stats
     * GET /admin/live-calls/stats
     * Splits calls into 1:1 and group, and includes participants_joined_count per call.
     */
    public function stats()
    {
        // 1:1 calls (direct): no group_id; exclude expired (24h since last join)
        $activeDirectCalls = CallSession::whereNull('group_id')
            ->whereIn('status', ['pending', 'calling', 'ongoing'])
            ->notExpired()
            ->count();

        // Group calls
        $activeGroupCalls = CallSession::whereNotNull('group_id')
            ->whereIn('status', ['pending', 'calling', 'ongoing'])
            ->notExpired()
            ->count();
            
        $activeLives = LiveBroadcast::where('status', 'live')->count();
        
        // All active calls with details and joined count (exclude expired)
        $allCalls = CallSession::with(['caller', 'callee', 'group', 'activeParticipants'])
            ->whereIn('status', ['pending', 'calling', 'ongoing'])
            ->notExpired()
            ->orderBy('created_at', 'desc')
            ->get();

        // Add participants_joined_count and split into 1:1 vs group
        $callsDirect = [];
        $callsGroup = [];
        foreach ($allCalls as $call) {
            if ($call->group_id) {
                $call->participants_joined_count = $call->activeParticipants->count();
                $callsGroup[] = $call;
            } else {
                // 1:1: caller is in; callee joined when call has started_at
                $call->participants_joined_count = $call->started_at ? 2 : 1;
                $callsDirect[] = $call;
            }
        }
            
        $broadcasts = LiveBroadcast::with('broadcaster')
            ->where('status', 'live')
            ->orderBy('started_at', 'desc')
            ->get();
        
        return response()->json([
            'stats' => [
                'active_direct_calls' => $activeDirectCalls,
                'active_group_calls' => $activeGroupCalls,
                'active_calls' => $activeDirectCalls + $activeGroupCalls,
                'active_lives' => $activeLives,
            ],
            'calls_direct' => $callsDirect,
            'calls_group' => $callsGroup,
            'calls' => $allCalls,
            'broadcasts' => $broadcasts,
        ]);
    }

    /**
     * Force-end a live broadcast
     * POST /admin/live-calls/broadcasts/{id}/force-end
     */
    public function forceEndBroadcast(Request $request, $id)
    {
        $broadcast = LiveBroadcast::findOrFail($id);
        
        $oldStatus = $broadcast->status;
        $broadcast->update([
            'status' => 'ended',
            'ended_at' => now(),
        ]);
        
        // Log admin action
        Log::info('Admin force-ended live broadcast', [
            'admin_id' => auth()->id(),
            'broadcast_id' => $broadcast->id,
            'broadcaster_id' => $broadcast->broadcaster_id,
            'old_status' => $oldStatus,
            'timestamp' => now(),
        ]);
        
        return response()->json([
            'status' => 'success',
            'message' => 'Live broadcast ended successfully',
        ]);
    }

    /**
     * Force-end a call session
     * POST /admin/live-calls/calls/{id}/force-end
     */
    public function forceEndCall(Request $request, $id)
    {
        $call = CallSession::findOrFail($id);
        
        $oldStatus = $call->status;
        $call->update([
            'status' => 'ended',
            'ended_at' => now(),
        ]);
        
        // Log admin action
        Log::info('Admin force-ended call session', [
            'admin_id' => auth()->id(),
            'call_id' => $call->id,
            'caller_id' => $call->caller_id,
            'callee_id' => $call->callee_id,
            'group_id' => $call->group_id,
            'old_status' => $oldStatus,
            'timestamp' => now(),
        ]);
        
        return response()->json([
            'status' => 'success',
            'message' => 'Call ended successfully',
        ]);
    }
}

