<?php
// database/migrations/xxxx_xx_xx_xxxxxx_add_forward_fields_to_messages_table.php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('messages', function (Blueprint $table) {
            // after('reply_to') to keep it tidy; adjust if 'reply_to' doesn't exist
            $table->unsignedBigInteger('forwarded_from_id')->nullable()->after('reply_to');
            $table->json('forward_chain')->nullable()->after('forwarded_from_id');

            $table->index('forwarded_from_id', 'messages_forwarded_from_idx');
            $table->foreign('forwarded_from_id')
                  ->references('id')->on('messages')
                  ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('messages', function (Blueprint $table) {
            $table->dropForeign(['forwarded_from_id']);
            $table->dropIndex('messages_forwarded_from_idx');
            $table->dropColumn(['forwarded_from_id', 'forward_chain']);
        });
    }
};
