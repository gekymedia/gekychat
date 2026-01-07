<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\PhaseMode;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/**
 * PHASE 2: Phase Mode Admin Controller
 * 
 * Allows admin to switch Phase Modes (server-wide).
 */
class PhaseModeController extends Controller
{
    /**
     * Get current phase mode
     * GET /admin/phase-mode
     */
    public function index()
    {
        $currentPhase = PhaseMode::where('is_active', true)->first();
        $allPhases = PhaseMode::all();

        return response()->json([
            'current' => $currentPhase,
            'available' => $allPhases,
        ]);
    }

    /**
     * Switch to a different phase mode
     * POST /admin/phase-mode/switch
     */
    public function switch(Request $request)
    {
        $request->validate([
            'phase_name' => ['required', 'in:basic,essential,comfort'],
        ]);

        // Deactivate all phases
        PhaseMode::query()->update(['is_active' => false]);

        // Activate selected phase
        $phase = PhaseMode::where('name', $request->input('phase_name'))->firstOrFail();
        $phase->update(['is_active' => true]);

        // Clear cache
        Cache::forget('phase_mode');

        // Log the change
        Log::info('Phase mode switched', [
            'admin_id' => auth()->id(),
            'phase' => $phase->name,
            'timestamp' => now(),
        ]);

        return response()->json([
            'status' => 'success',
            'message' => "Phase mode switched to {$phase->name}",
            'phase' => $phase,
        ]);
    }

    /**
     * Update phase mode limits (advanced)
     * PUT /admin/phase-mode/{id}
     */
    public function update(Request $request, $id)
    {
        $phase = PhaseMode::findOrFail($id);

        $request->validate([
            'limits' => ['nullable', 'array'],
        ]);

        $phase->update([
            'limits' => $request->input('limits'),
        ]);

        Cache::forget('phase_mode');

        return response()->json([
            'status' => 'success',
            'phase' => $phase,
        ]);
    }
}
