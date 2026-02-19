<?php

namespace App\Console\Commands;

use App\Models\Message;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Move messages older than 1 year into message_archives (Phase 9).
 * Run via: php artisan messages:archive
 */
class ArchiveOldMessages extends Command
{
    protected $signature = 'messages:archive {--years=1 : Archive messages older than this many years}';
    protected $description = 'Move messages older than retention period to message_archives table';

    public function handle(): int
    {
        $years = (float) $this->option('years');
        $before = now()->subYears($years);

        if (!\Schema::hasTable('message_archives')) {
            $this->error('Run migration: php artisan migrate');
            return 1;
        }

        $count = Message::where('created_at', '<', $before)->count();
        if ($count === 0) {
            $this->info('No messages to archive.');
            return 0;
        }

        $this->info("Archiving {$count} messages older than {$before->toDateString()}...");

        DB::transaction(function () use ($before) {
            $chunk = Message::where('created_at', '<', $before)->limit(5000)->get();
            while ($chunk->isNotEmpty()) {
                $rows = $chunk->map(fn ($m) => [
                    'original_message_id' => $m->id,
                    'conversation_id' => $m->conversation_id,
                    'sender_id' => $m->sender_id,
                    'body' => $m->body,
                    'read_at' => $m->read_at,
                    'created_at' => $m->created_at,
                    'updated_at' => $m->updated_at,
                ])->values()->all();
                DB::table('message_archives')->insert($rows);
                Message::whereIn('id', $chunk->pluck('id'))->delete();
                $chunk = Message::where('created_at', '<', $before)->limit(5000)->get();
            }
        });

        $this->info('Done.');
        return 0;
    }
}
