<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        try { DB::statement('ALTER TABLE messages ADD FULLTEXT messages_body_ft (body)'); } catch (\Throwable $e) {}
        try { DB::statement('ALTER TABLE group_messages ADD FULLTEXT group_messages_body_ft (body)'); } catch (\Throwable $e) {}
    }
    public function down(): void
    {
        // Some MySQLs need DROP INDEX name, not DROP FULLTEXT INDEX
        try { DB::statement('DROP INDEX messages_body_ft ON messages'); } catch (\Throwable $e) {}
        try { DB::statement('DROP INDEX group_messages_body_ft ON group_messages'); } catch (\Throwable $e) {}
    }
};
