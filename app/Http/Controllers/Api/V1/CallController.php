<?php

namespace App\Http\Controllers\Api\V1;

use App\Events\CallSignal;
use App\Http\Controllers\Controller;
use App\Models\CallSession;
use App\Models\Group;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class CallController extends Controller
{
    /**
     * POST /api/v1/calls/start
     * Body: { "callee_id": 2, "group_id": null, "type": "video" }
     * Creates a new call session and notifies the callee(s).
     */
    public function start(Request $request)
    {
        $user = $request->user();
        $data = $request->validate([
            'callee_id' => ['nullable', 'numeric', 'exists:users,id'],
            'group_id'  => ['nullable', 'numeric', 'exists:groups,id'],
            'type'      => ['required', 'in:voice,video'],
        ]);
        if (!$data['callee_id'] && !$data['group_id']) {
            return response()->json([
                'status' => 'error',
                'message' => 'callee_id or group_id is required.',
            ], 422);
        }
        if ($data['callee_id'] && $data['group_id']) {
            return response()->json([
                'status' => 'error',
                'message' => 'Provide either callee_id or group_id, not both.',
            ], 422);
        }
        // Create call session
        $call = CallSession::create([
            'caller_id' => $user->id,
            'callee_id' => $data['callee_id'] ?? null,
            'group_id'  => $data['group_id'] ?? null,
            'type'      => $data['type'],
            'status'    => 'pending',
        ]);
        $payload = json_encode([
            'session_id' => $call->id,
            'type'       => $call->type,
            'caller'     => [
                'id'   => $user->id,
                'name' => $user->name ?? $user->phone,
            ],
            'action'     => 'invite',
        ]);
        broadcast(new CallSignal($call, $payload))->toOthers();
        return response()->json([
            'status'     => 'success',
            'session_id' => $call->id,
        ]);
    }

    /**
     * POST /api/v1/calls/{session}/signal
     * Body: { "payload": "..." }
     * Forwards signalling data (offer/answer/ICE) to the other party.
     */
    public function signal(Request $request, CallSession $session)
    {
        $user = $request->user();
        // Authorize: either caller or callee or group member
        if ($session->group_id) {
            $group = $session->group;
            Gate::authorize('manage-group', $group);
        } else {
            if ($user->id !== $session->caller_id && $user->id !== $session->callee_id) {
                return response()->json(['status' => 'error', 'message' => 'Not authorized'], 403);
            }
        }
        $data = $request->validate([
            'payload' => ['required', 'string'],
        ]);
        broadcast(new CallSignal($session, $data['payload']))->toOthers();
        return response()->json(['status' => 'success']);
    }

    /**
     * POST /api/v1/calls/{session}/end
     * Marks the call as ended and notifies participants.
     */
    public function end(Request $request, CallSession $session)
    {
        $user = $request->user();
        // Authorize
        if ($session->group_id) {
            Gate::authorize('manage-group', $session->group);
        } else {
            if ($user->id !== $session->caller_id && $user->id !== $session->callee_id) {
                return response()->json(['status' => 'error', 'message' => 'Not authorized'], 403);
            }
        }
        $session->update([
            'status'    => 'ended',
            'ended_at'  => now(),
        ]);
        $payload = json_encode([
            'session_id' => $session->id,
            'action'     => 'ended',
        ]);
        broadcast(new CallSignal($session, $payload))->toOthers();
        return response()->json(['status' => 'success']);
    }
}