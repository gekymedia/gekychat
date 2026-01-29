<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use Illuminate\Http\Request;

class AuditLogController extends Controller
{
    /**
     * Get user's own audit logs
     */
    public function index(Request $request)
    {
        $logs = $request->user()->auditLogs()
            ->latest()
            ->paginate(50);
        
        return response()->json($logs);
    }
    
    /**
     * Get all audit logs (admin only)
     */
    public function adminIndex(Request $request)
    {
        // Check admin permission
        if (!$request->user()->is_admin) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }
        
        $query = AuditLog::with('user')->latest();
        
        // Filter by action
        if ($request->has('action')) {
            $query->where('action', $request->action);
        }
        
        // Filter by user
        if ($request->has('user_id')) {
            $query->where('user_id', $request->user_id);
        }
        
        // Filter by date range
        if ($request->has('from_date')) {
            $query->where('created_at', '>=', $request->from_date);
        }
        
        if ($request->has('to_date')) {
            $query->where('created_at', '<=', $request->to_date);
        }
        
        $logs = $query->paginate(100);
        
        return response()->json($logs);
    }
    
    /**
     * Get audit log statistics (admin only)
     */
    public function statistics(Request $request)
    {
        if (!$request->user()->is_admin) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }
        
        $stats = [
            'total_logs' => AuditLog::count(),
            'today_logs' => AuditLog::whereDate('created_at', today())->count(),
            'this_week_logs' => AuditLog::where('created_at', '>=', now()->startOfWeek())->count(),
            'top_actions' => AuditLog::selectRaw('action, COUNT(*) as count')
                ->groupBy('action')
                ->orderByDesc('count')
                ->limit(10)
                ->get(),
            'top_users' => AuditLog::with('user')
                ->selectRaw('user_id, COUNT(*) as count')
                ->groupBy('user_id')
                ->orderByDesc('count')
                ->limit(10)
                ->get(),
        ];
        
        return response()->json($stats);
    }
}
