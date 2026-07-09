<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\ProductAnalyticsIngestService;
use App\Services\ProductAnalyticsReportService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ProductAnalyticsAdminController extends Controller
{
    public function __construct(
        private ProductAnalyticsReportService $reports,
        private ProductAnalyticsIngestService $ingest,
    ) {}

    public function index(Request $request)
    {
        $period = $request->get('period', '7d');
        $searchQuery = $request->get('q', '');
        $searchResults = $searchQuery !== ''
            ? $this->reports->searchUsers($searchQuery)
            : [];

        return view('admin.product_analytics.index', [
            'period' => $period,
            'features' => ProductAnalyticsIngestService::FEATURES,
            'overview' => $this->reports->overview($period),
            'featureUsage' => $this->reports->featureUsage($period),
            'platformBreakdown' => $this->reports->platformBreakdown($period),
            'sessionsOverTime' => $this->reports->sessionsOverTime($period),
            'topActions' => $this->reports->topActions($period),
            'retention' => $this->reports->retentionCohorts(6),
            'durationBuckets' => $this->reports->sessionDurationBuckets($period),
            'realtime' => $this->reports->realtime(),
            'funnel' => $this->reports->acquisitionFunnel(30),
            'searchQuery' => $searchQuery,
            'searchResults' => $searchResults,
            'bridgeEnabled' => config('services.product_analytics.amplitude.enabled')
                || config('services.product_analytics.firebase.enabled'),
        ]);
    }

    public function user(Request $request, int $userId)
    {
        $period = $request->get('period', '30d');
        $detail = $this->reports->userDetail($userId, $period);

        return view('admin.product_analytics.user', [
            'period' => $period,
            'detail' => $detail,
            'features' => ProductAnalyticsIngestService::FEATURES,
        ]);
    }

    public function export(Request $request, string $type): StreamedResponse
    {
        $this->authorizeAdmin($request);
        $period = $request->get('period', '7d');
        $rows = $this->reports->exportRows($type, $period);
        $filename = "product-analytics-{$type}-{$period}-" . now()->format('Y-m-d') . '.csv';

        return response()->streamDownload(function () use ($rows) {
            $out = fopen('php://output', 'w');
            foreach ($rows as $row) {
                fputcsv($out, $row);
            }
            fclose($out);
        }, $filename, [
            'Content-Type' => 'text/csv',
        ]);
    }

    public function apiOverview(Request $request): JsonResponse
    {
        $this->authorizeAdmin($request);
        return response()->json($this->reports->overview($request->get('period', '7d')));
    }

    public function apiFeatures(Request $request): JsonResponse
    {
        $this->authorizeAdmin($request);
        return response()->json($this->reports->featureUsage($request->get('period', '7d')));
    }

    public function apiRealtime(Request $request): JsonResponse
    {
        $this->authorizeAdmin($request);
        return response()->json($this->reports->realtime());
    }

    public function apiFunnel(Request $request): JsonResponse
    {
        $this->authorizeAdmin($request);
        $days = (int) $request->get('days', 30);

        return response()->json($this->reports->acquisitionFunnel(max(7, min(90, $days))));
    }

    public function apiUser(Request $request, int $userId): JsonResponse
    {
        $this->authorizeAdmin($request);

        return response()->json($this->reports->userDetail($userId, $request->get('period', '30d')));
    }

    public function apiSearchUsers(Request $request): JsonResponse
    {
        $this->authorizeAdmin($request);

        return response()->json($this->reports->searchUsers((string) $request->get('q', '')));
    }

    public function apiFull(Request $request): JsonResponse
    {
        $this->authorizeAdmin($request);
        $period = $request->get('period', '7d');

        return response()->json([
            'overview' => $this->reports->overview($period),
            'features' => $this->reports->featureUsage($period),
            'platforms' => $this->reports->platformBreakdown($period),
            'sessions_over_time' => $this->reports->sessionsOverTime($period),
            'top_actions' => $this->reports->topActions($period),
            'retention' => $this->reports->retentionCohorts(6),
            'duration_buckets' => $this->reports->sessionDurationBuckets($period),
            'realtime' => $this->reports->realtime(),
            'funnel' => $this->reports->acquisitionFunnel(30),
        ]);
    }

    private function authorizeAdmin(Request $request): void
    {
        $user = $request->user();
        if (!$user || !$user->is_admin) {
            abort(403);
        }
    }
}
