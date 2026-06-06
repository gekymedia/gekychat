<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\DeviceToken;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class DeviceTokensController extends Controller
{
    public function index(Request $request)
    {
        $typeColumn = Schema::hasColumn('device_tokens', 'device_type')
            ? 'device_type'
            : (Schema::hasColumn('device_tokens', 'platform') ? 'platform' : null);

        $query = DeviceToken::query()
            ->with(['user:id,name,phone,username'])
            ->orderByDesc('updated_at');

        if ($typeColumn && $request->filled('device_type')) {
            $query->where($typeColumn, $request->device_type);
        }

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('device_id', 'like', "%{$search}%")
                    ->orWhereHas('user', function ($uq) use ($search) {
                        $uq->where('name', 'like', "%{$search}%")
                            ->orWhere('phone', 'like', "%{$search}%")
                            ->orWhere('username', 'like', "%{$search}%")
                            ->orWhere('id', $search);
                    });
            });
        }

        if ($request->filled('user_id')) {
            $query->where('user_id', (int) $request->user_id);
        }

        if ($request->filled('date_from')) {
            $query->where('updated_at', '>=', $request->date_from);
        }
        if ($request->filled('date_to')) {
            $query->where('updated_at', '<=', $request->date_to . ' 23:59:59');
        }

        $devices = $query->paginate(50)->withQueryString();

        $stats = $this->buildStats($typeColumn);

        return view('admin.device-tokens.index', [
            'devices' => $devices,
            'stats' => $stats,
            'typeColumn' => $typeColumn,
        ]);
    }

    protected function buildStats(?string $typeColumn): array
    {
        $base = DeviceToken::query();

        $stats = [
            'total' => (clone $base)->count(),
            'unique_users' => (clone $base)->distinct('user_id')->count('user_id'),
            'registered_today' => (clone $base)->whereDate('created_at', today())->count(),
            'updated_today' => (clone $base)->whereDate('updated_at', today())->count(),
            'updated_24h' => (clone $base)->where('updated_at', '>=', now()->subDay())->count(),
            'android' => 0,
            'ios' => 0,
            'web' => 0,
            'other' => 0,
        ];

        if ($typeColumn) {
            $byType = DeviceToken::query()
                ->select($typeColumn, DB::raw('COUNT(*) as total'))
                ->groupBy($typeColumn)
                ->pluck('total', $typeColumn);

            $stats['android'] = (int) ($byType['android'] ?? 0);
            $stats['ios'] = (int) ($byType['ios'] ?? 0);
            $stats['web'] = (int) ($byType['web'] ?? 0);
            $stats['other'] = $stats['total'] - $stats['android'] - $stats['ios'] - $stats['web'];
        }

        return $stats;
    }
}
