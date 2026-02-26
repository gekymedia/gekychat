<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        // Insert default broadcast settings
        $settings = [
            [
                'key' => 'broadcast_attachments_enabled',
                'value' => '1',
                'type' => 'boolean',
                'description' => 'Allow attachments (images, videos, documents) in broadcast messages',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'key' => 'broadcast_max_attachments',
                'value' => '10',
                'type' => 'integer',
                'description' => 'Maximum number of attachments per broadcast message',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'key' => 'broadcast_max_recipients',
                'value' => '256',
                'type' => 'integer',
                'description' => 'Maximum number of recipients per broadcast list',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'key' => 'broadcast_max_messages_per_day',
                'value' => '50',
                'type' => 'integer',
                'description' => 'Maximum broadcast messages a user can send per day (0 = unlimited)',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'key' => 'broadcast_admin_only',
                'value' => '0',
                'type' => 'boolean',
                'description' => 'Restrict broadcast feature to admin users only',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'key' => 'broadcast_max_file_size',
                'value' => '16777216',
                'type' => 'integer',
                'description' => 'Maximum file size for broadcast attachments (in bytes, default 16MB)',
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ];

        foreach ($settings as $setting) {
            DB::table('upload_settings')->updateOrInsert(
                ['key' => $setting['key']],
                $setting
            );
        }
    }

    public function down(): void
    {
        DB::table('upload_settings')->whereIn('key', [
            'broadcast_attachments_enabled',
            'broadcast_max_attachments',
            'broadcast_max_recipients',
            'broadcast_max_messages_per_day',
            'broadcast_admin_only',
            'broadcast_max_file_size',
        ])->delete();
    }
};
