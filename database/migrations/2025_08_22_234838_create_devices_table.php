<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('devices', function (Blueprint $t) {
            $t->id();
            $t->foreignId('user_id')->constrained()->cascadeOnDelete();
            $t->string('platform'); // android|ios
            $t->string('device_name')->nullable();
            $t->string('fcm_token')->index();
            $t->timestamps();
        });
    }
    public function down(): void { Schema::dropIfExists('devices'); }
};
