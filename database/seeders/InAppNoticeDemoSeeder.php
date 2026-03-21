<?php

namespace Database\Seeders;

use App\Models\InAppNotice;
use Illuminate\Database\Seeder;

/**
 * Demo row for in-app notices (chat list banners). Run:
 *   php artisan db:seed --class=InAppNoticeDemoSeeder
 * Then set is_active = 1 in DB or via tinker to show it.
 */
class InAppNoticeDemoSeeder extends Seeder
{
    public function run(): void
    {
        InAppNotice::query()->updateOrCreate(
            ['notice_key' => 'demo_welcome_v1'],
            [
                'title' => 'In-app notices',
                'body' => 'Manage banners in the in_app_notices table. Use for birthdays, storage warnings, or promos.',
                'style' => 'info',
                'action_label' => null,
                'action_url' => null,
                'is_active' => false,
                'sort_order' => 0,
            ]
        );
    }
}
