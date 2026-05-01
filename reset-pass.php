<?php
require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

// Check specific user
$email = 'savana@guru.com';
$u = App\Models\User::where('email', $email)->first();

if ($u) {
    echo "Found: {$u->email} ({$u->role})\n";
    echo "Password check (password123): " . (Hash::check('password123', $u->password) ? 'OK' : 'FAIL') . "\n";
    
    // Reset password
    $u->password = Hash::make('password123');
    $u->save();
    echo "Password reset to: password123\n";
} else {
    echo "User not found: $email\n";
    
    // List all users
    echo "\nAll users:\n";
    $users = App\Models\User::all();
    foreach ($users as $user) {
        echo "  - {$user->email} ({$user->role})\n";
    }
}
