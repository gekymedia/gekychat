<?php
require __DIR__ . '/vendor/autoload.php';
$app = require __DIR__ . '/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\InboundMailLog;
use Illuminate\Support\Facades\Storage;
use App\Services\PdfTextExtractor;
use App\Services\AdmissionLetterParser;

foreach ([693, 694] as $id) {
    $l = InboundMailLog::find($id);
    if (!$l) {
        echo "missing $id\n";
        continue;
    }
    echo "==== $id {$l->matched_app_id} ====\n";
    echo "status={$l->status} academic_year={$l->academic_year} ref={$l->admission_ref}\n";
    echo "parsed_json=" . json_encode($l->parsed_json) . "\n";
    $attrs = $l->getAttributes();
    foreach ($attrs as $k => $v) {
        if (stripos($k, 'attach') !== false || stripos($k, 'path') !== false || stripos($k, 'file') !== false) {
            echo "$k=" . substr(is_string($v) ? $v : json_encode($v), 0, 300) . "\n";
        }
    }
    // try attachments relation/json casts
    if (isset($l->attachments)) {
        echo "attachments_cast=" . json_encode($l->attachments) . "\n";
    }
}

// Search storage for recent acceptance PDFs
$disk = Storage::disk('local');
$files = [];
foreach (['inbound/' . date('Y/m'), 'inbound/' . date('Y/m', strtotime('-1 day')), 'tmp', 'mail'] as $dir) {
    try {
        foreach ($disk->allFiles($dir) as $f) {
            if (preg_match('/accept|admission|letter|owusu|osei/i', $f)) {
                $files[] = $f;
            }
        }
    } catch (Throwable $e) {
    }
}
echo "matching_files=" . count($files) . "\n";
foreach (array_slice($files, 0, 20) as $f) {
    echo " file=$f size=" . $disk->size($f) . "\n";
}
