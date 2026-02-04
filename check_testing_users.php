<?php

require __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use App\Models\User;
use App\Models\TestingMode;
use App\Services\TestingModeService;

echo "=== TESTING MODE STATUS ===\n";
echo "Testing Mode Enabled: " . (TestingModeService::isEnabled() ? "YES" : "NO") . "\n\n";

$tm = TestingMode::first();
if ($tm) {
    echo "Testing Mode User IDs: " . json_encode($tm->user_ids) . "\n\n";
    
    echo "=== USER DETAILS ===\n";
    foreach ($tm->user_ids as $userId) {
        $user = User::find($userId);
        if ($user) {
            echo "User ID {$userId}: {$user->name}, Phone: {$user->phone}, Username: " . ($user->username ?? "NULL") . "\n";
        } else {
            echo "User ID {$userId}: NOT FOUND\n";
        }
    }
} else {
    echo "No testing mode configuration found\n";
}

echo "\n=== CHECKING SPECIFIC PHONES ===\n";
$phones = ['0248229540', '0245790807', '0000000000'];
foreach ($phones as $phone) {
    $user = User::where('phone', $phone)->first();
    if ($user) {
        echo "Phone {$phone}: ID={$user->id}, Username=" . ($user->username ?? "NULL") . "\n";
    } else {
        echo "Phone {$phone}: NOT FOUND\n";
    }
}
