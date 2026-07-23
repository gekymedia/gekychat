<?php
require __DIR__ . '/vendor/autoload.php';
$app = require __DIR__ . '/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\InboundMailLog;
use App\Models\Admission;
use App\Models\User;

$logs = InboundMailLog::whereDate('created_at', today())
    ->where(function ($q) {
        $q->where('status', 'offer_sent')
          ->orWhereNotNull('matched_app_id')
          ->orWhere('subject', 'like', '%ffer%')
          ->orWhere('subject', 'like', '%dmission%');
    })
    ->orderByDesc('id')
    ->get();

echo "relevant_today=" . $logs->count() . PHP_EOL;
foreach ($logs as $log) {
    echo "==== LOG {$log->id} ====\n";
    echo "status={$log->status} year={$log->academic_year} app={$log->matched_app_id}\n";
    echo "subject={$log->subject}\n";
    echo "ref={$log->admission_ref} period={$log->admission_period}\n";
    $pj = $log->parsed_json;
    if (is_array($pj)) {
        echo "parsed_year=" . ($pj['academic_year'] ?? 'null') . " programme=" . ($pj['programme'] ?? '') . "\n";
        echo "parsed_keys=" . implode(',', array_keys($pj)) . "\n";
    }
    if ($log->matched_app_id) {
        $adm = Admission::where('app_id', $log->matched_app_id)->orderByDesc('id')->get(['id','app_id','academic_year','source','created_at','admission_number']);
        echo "admissions_for_app=" . $adm->count() . "\n";
        foreach ($adm as $a) {
            echo "  adm {$a->id} year={$a->academic_year} source={$a->source} created={$a->created_at} num={$a->admission_number}\n";
        }
        $user = User::where('app_id', $log->matched_app_id)->with('programmeDetails')->first();
        if ($user && $user->programmeDetails) {
            $pd = $user->programmeDetails;
            echo "user_programme_year=" . ($pd->academic_year ?? 'n/a') . " period=" . ($pd->admission_period ?? 'n/a') . "\n";
        }
    }
}

echo "\nAll offer_sent today:\n";
foreach (InboundMailLog::whereDate('created_at', today())->where('status','offer_sent')->get() as $log) {
    echo "{$log->id}|{$log->matched_app_id}|{$log->academic_year}|{$log->admission_ref}\n";
}
