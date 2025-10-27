<?php
// Migration: create_google_contacts_table
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('google_contacts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->string('google_contact_id')->nullable(); // Google's contact ID
            $table->string('name')->nullable();
            $table->string('phone')->nullable();
            $table->string('email')->nullable();
            $table->text('photo_url')->nullable();
            $table->boolean('is_deleted_in_google')->default(false);
            $table->timestamp('last_synced_at')->nullable();
            $table->string('sync_status')->default('active'); // active, deleted_in_google
            $table->timestamps();

            $table->index(['user_id', 'phone']);
            $table->index(['user_id', 'google_contact_id']);
        });
    }

    public function down()
    {
        Schema::dropIfExists('google_contacts');
    }
};