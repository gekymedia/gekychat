<?php

namespace Database\Seeders;

use App\Models\InAppNotice;
use Illuminate\Database\Seeder;

/**
 * Seed default in-app notice templates for admin activation.
 * Run:
 *   php artisan db:seed --class=InAppNoticeDemoSeeder
 */
class InAppNoticeDemoSeeder extends Seeder
{
    public function run(): void
    {
        InAppNotice::query()->updateOrCreate(
            ['notice_key' => 'invite_friends_v1'],
            [
                'is_system_notice' => true,
                'title' => 'Invite friends to GekyChat',
                'body' => 'Grow your circle. Invite your friends to join you on GekyChat.',
                'style' => 'promo',
                'action_label' => 'Invite now',
                'action_url' => null,
                'is_active' => false,
                'sort_order' => 30,
                'condition_type' => 'always',
                'condition_value' => null,
            ]
        );

        InAppNotice::query()->updateOrCreate(
            ['notice_key' => 'storage_full_alert_v1'],
            [
                'is_system_notice' => true,
                'title' => 'Storage almost full',
                'body' => 'Your device storage is nearly full. Clear old media to keep messages flowing smoothly.',
                'style' => 'warning',
                'action_label' => 'Open storage',
                'action_url' => null,
                'is_active' => false,
                'sort_order' => 20,
                'condition_type' => 'device_storage_low',
                'condition_value' => null,
            ]
        );

        InAppNotice::query()->updateOrCreate(
            ['notice_key' => 'birthday_alert_v1'],
            [
                'is_system_notice' => true,
                'title' => 'Birthday reminder',
                'body' => 'A friend has a birthday today. Send a wish and make their day special.',
                'style' => 'info',
                'action_label' => 'Send wishes',
                'action_url' => null,
                'is_active' => false,
                'sort_order' => 10,
                'condition_type' => 'birthday_contact_today',
                'condition_value' => null,
            ]
        );
    }
}
