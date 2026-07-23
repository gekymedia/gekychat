<?php
require __DIR__ . '/vendor/autoload.php';
$app = require __DIR__ . '/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\InboundMailLog;
use App\Models\Admission;

echo 'inbound_today=' . InboundMailLog::whereDate('created_at', today())->count() . PHP_EOL;
foreach (InboundMailLog::orderByDesc('id')->limit(10)->get(['id','subject','status','academic_year','matched_app_id','created_at']) as $x) {
    echo $x->id . '|' . $x->created_at . '|' . $x->status . '|' . $x->academic_year . '|' . $x->matched_app_id . '|' . substr((string)$x->subject, 0, 60) . PHP_EOL;
}
echo 'years=' . Admission::select('academic_year')->distinct()->orderByDesc('academic_year')->pluck('academic_year')->implode(', ') . PHP_EOL;
echo 'adm_2026_2027=' . Admission::where('academic_year', '2026/2027')->count() . PHP_EOL;
echo 'adm_today=' . Admission::whereDate('created_at', today())->count() . PHP_EOL;
echo 'failed_jobs=' . DB::table('failed_jobs')->count() . PHP_EOL;
