<?php

require __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use App\Models\User;

$updates = [
    '0248229540' => 'user9540',
    '0245790807' => 'user0807',
    '0000000000' => 'johnopoku',
];

foreach ($updates as $phone => $username) {
    $user = User::where('phone', $phone)->first();
    
    if (!$user) {
        echo "❌ User with phone {$phone} not found\n";
        continue;
    }
    
    // Check if username is already taken
    $existing = User::where('username', $username)->where('id', '!=', $user->id)->first();
    if ($existing) {
        echo "❌ Username '{$username}' is already taken by user {$existing->id}\n";
        continue;
    }
    
    $user->username = $username;
    $user->save();
    
    echo "✅ Set username for {$user->name} ({$phone}) to: {$username}\n";
}

echo "\n=== VERIFICATION ===\n";
foreach ($updates as $phone => $username) {
    $user = User::where('phone', $phone)->first();
    if ($user) {
        echo "Phone {$phone}: Username=" . ($user->username ?? "NULL") . "\n";
    }
}
