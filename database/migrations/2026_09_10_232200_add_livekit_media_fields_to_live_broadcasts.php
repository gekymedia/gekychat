<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('live_broadcasts', function (Blueprint $table) {
            $table->string('egress_id')->nullable()->after('replay_url');
            $table->string('rtmp_egress_id')->nullable()->after('egress_id');
            $table->string('ingress_id')->nullable()->after('rtmp_egress_id');
            $table->string('ingress_url')->nullable()->after('ingress_id');
            $table->string('whip_url')->nullable()->after('ingress_url');
        });
    }

    public function down(): void
    {
        Schema::table('live_broadcasts', function (Blueprint $table) {
            $table->dropColumn([
                'egress_id',
                'rtmp_egress_id',
                'ingress_id',
                'ingress_url',
                'whip_url',
            ]);
        });
    }
};
