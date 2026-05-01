<?php
require_once __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use App\Models\User;

// Update siswa password
$siswa = User::where('role', 'siswa')->first();
if ($siswa) {
    $siswa->password = bcrypt('password123');
    $siswa->save();
    echo "Password updated for: {$siswa->email}\n";
}
