<?php

require __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

// Test dengan kelas sebagai integer ID (seperti yang dikirim FE)
$kelas = 1;
$kelasId = null;
$jurusanId = 'JUR-1';

echo "=== INPUT ===\n";
echo "kelas: " . var_export($kelas, true) . "\n";
echo "jurusan_id: " . var_export($jurusanId, true) . "\n\n";

// Normalize - logic baru
if (is_numeric($kelas) && !in_array($kelas, ['X', 'XI', 'XII'])) {
    echo "kelas is numeric, treating as kelas_id\n";
    $kelasId = $kelas;
    $kelas = null;
}

if ($kelasId) {
    $kelasData = App\Models\Kelas::find($kelasId);
    if ($kelasData) {
        $kelas = $kelasData->tingkat;
        echo "Found kelas data: " . $kelasData->nama . " (tingkat: {$kelas})\n";
    }
}

echo "\n=== RESULT ===\n";
echo "Kelas after normalize: " . $kelas . "\n";
echo "Valid (X/XI/XII): " . (in_array($kelas, ['X', 'XI', 'XII']) ? 'YES' : 'NO') . "\n";
