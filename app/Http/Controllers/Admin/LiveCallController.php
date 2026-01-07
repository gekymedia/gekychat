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
     */
    public function stats()
    {
        $activeCalls = CallSession::where('status', 'ongoing')
            ->orWhere('status', 'calling')
            ->count();
            
        $activeGroupCalls = CallSession::whereNotNull('group_id')
            ->where(function ($q) {
                $q->where('status', 'ongoing')->orWhere('status', 'calling');
            })
            ->count();
            
        $activeLives = LiveBroadcast::where('status', 'live')->count();
        
        // Get all active calls with details
        $calls = CallSession::with(['caller', 'callee', 'group', 'activeParticipants'])
            ->where(function ($q) {
                $q->where('status', 'ongoing')->orWhere('status', 'calling');
            })
            ->get();
            
        // Get all active live broadcasts
        $broadcasts = LiveBroadcast::with('broadcaster')
            ->where('status', 'live')
            ->orderBy('started_at', 'desc')
            ->get();
        
        return response()->json([
            'stats' => [
                'active_calls' => $activeCalls,
                'active_group_calls' => $activeGroupCalls,
                'active_lives' => $activeLives,
            ],
            'calls' => $calls,
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

