<?php

require __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use App\Models\User;
use App\Models\Kelas;

echo "=== CEK DATA GURU ===\n\n";

$gurus = User::where('role', 'guru')->get();

foreach ($gurus as $guru) {
    echo "ID: {$guru->id}\n";
    echo "Nama: {$guru->name}\n";
    echo "Email: {$guru->email}\n";
    echo "kelas_diampu: " . json_encode($guru->kelas_diampu) . "\n";
    echo "---\n";
}

echo "\n=== CEK DATA KELAS ===\n\n";

$kelasList = Kelas::all();
foreach ($kelasList as $kelas) {
    echo "ID: {$kelas->id} - {$kelas->nama} (Tingkat: {$kelas->tingkat})\n";
}
