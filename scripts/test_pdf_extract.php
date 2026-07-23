<?php
require __DIR__ . '/vendor/autoload.php';
$app = require __DIR__ . '/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\InboundMailLog;
use App\Services\PdfTextExtractor;
use App\Services\AdmissionLetterParser;
use Illuminate\Support\Facades\Storage;

$extractor = app(PdfTextExtractor::class);
$parser = app(AdmissionLetterParser::class);

foreach ([693, 694] as $id) {
    $log = InboundMailLog::find($id);
    echo "==== LOG $id ====\n";
    foreach ($log->attachments ?? [] as $a) {
        $path = $a['path'] ?? null;
        $name = $a['name'] ?? '';
        echo "file=$name path=$path\n";
        if (!$path || !Storage::disk('local')->exists($path)) {
            echo "  MISSING on disk\n";
            continue;
        }
        $full = Storage::disk('local')->path($path);
        echo "  size=" . filesize($full) . "\n";
        try {
            $text = $extractor->extract($full);
            echo "  extract_len=" . strlen((string)$text) . "\n";
            echo "  preview=" . substr(preg_replace('/\s+/', ' ', (string)$text), 0, 300) . "\n";
            $parsed = $parser->parse((string)$text);
            echo "  parsed_ref=" . ($parsed['admission_ref'] ?? 'null') . " year=" . ($parsed['academic_year'] ?? 'null') . " programme=" . ($parsed['programme'] ?? 'null') . "\n";
        } catch (Throwable $e) {
            echo "  EXTRACT_FAIL " . $e->getMessage() . "\n";
        }
    }
}
