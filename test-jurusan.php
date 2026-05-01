<?php

require __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use App\Models\Jurusan;

echo "=== CEK DATA JURUSAN ===\n\n";

$jurusans = Jurusan::all();
foreach ($jurusans as $jurusan) {
    echo "ID: {$jurusan->id} - Nama: {$jurusan->nama}\n";
}
