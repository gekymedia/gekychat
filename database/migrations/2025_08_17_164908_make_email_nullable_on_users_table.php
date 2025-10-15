<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Use raw SQL so you don't need doctrine/dbal
        if (Schema::hasColumn('users', 'email')) {
            DB::statement("ALTER TABLE `users` MODIFY `email` VARCHAR(255) NULL");
        }
    }

    public function down(): void
    {
        // Revert back to NOT NULL (adjust default if you had one)
        if (Schema::hasColumn('users', 'email')) {
            DB::statement("ALTER TABLE `users` MODIFY `email` VARCHAR(255) NOT NULL");
        }
    }
};
