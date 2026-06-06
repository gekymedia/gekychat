<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class RealtimeHealthCheck extends Command
{
    protected $signature = 'realtime:health';

    protected $description = 'Verify Reverb, queue, and broadcasting configuration for production realtime delivery';

    public function handle(): int
    {
        $ok = true;

        $this->info('GekyChat realtime health check');
        $this->newLine();

        $broadcastDriver = config('broadcasting.default');
        $this->line("BROADCAST_DRIVER: {$broadcastDriver}");
        if ($broadcastDriver !== 'reverb' && $broadcastDriver !== 'pusher') {
            $this->warn('  ⚠ Expected reverb or pusher for live WebSocket delivery.');
            $ok = false;
        }

        if ($broadcastDriver === 'reverb') {
            $host = config('broadcasting.connections.reverb.options.host');
            $port = config('broadcasting.connections.reverb.options.port');
            $scheme = config('broadcasting.connections.reverb.options.scheme') ?? 'http';
            $this->line("REVERB_SERVER: {$scheme}://{$host}:{$port}");

            $errno = 0;
            $errstr = '';
            $socket = @fsockopen($host, (int) $port, $errno, $errstr, 3);
            if ($socket) {
                fclose($socket);
                $this->info('  ✓ Reverb port reachable from this host');
            } else {
                $this->error("  ✗ Cannot reach Reverb at {$host}:{$port} — {$errstr} ({$errno})");
                $this->line('  → Ensure supervisor runs: php artisan reverb:start --host=127.0.0.1 --port=8080');
                $ok = false;
            }
        }

        $queueConnection = config('queue.default');
        $this->line("QUEUE_CONNECTION: {$queueConnection}");

        if (Schema::hasTable('failed_jobs')) {
            $failed = (int) DB::table('failed_jobs')->count();
            $this->line("Failed jobs: {$failed}");
            if ($failed > 0) {
                $this->warn('  ⚠ Run: php artisan queue:failed');
            }
        }

        if (Schema::hasTable('jobs')) {
            $pending = (int) DB::table('jobs')->count();
            $this->line("Pending queued jobs: {$pending}");
            if ($pending > 50) {
                $this->warn('  ⚠ Queue backlog — verify supervisor queue workers are running.');
            }
        }

        $firebase = env('FIREBASE_CREDENTIALS') ?: env('GOOGLE_APPLICATION_CREDENTIALS');
        if (empty($firebase) || ! is_readable($firebase)) {
            $this->warn('  ⚠ FIREBASE_CREDENTIALS missing or unreadable — FCM push will not work.');
        } else {
            $this->info('  ✓ Firebase credentials file readable');
        }

        $this->newLine();
        if ($ok) {
            $this->info('Realtime stack looks healthy.');
            return self::SUCCESS;
        }

        $this->error('Realtime stack has issues — see warnings above.');
        return self::FAILURE;
    }
}
