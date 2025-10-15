<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class BotUserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
   public function run()
{
    \App\Models\User::firstOrCreate([
        'phone' => '0000000000',
    ], [
        'name' => 'GekyBot',
        'email' => 'gekybot@gekychat.com',
        'password' => bcrypt('botpass'),
    ]);
}

}
