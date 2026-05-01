<?php

require __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

// Simulate the request that frontend sends
$data = [
    'judul' => 'p',
    'kelas' => 'X RPL 1',  // Frontend mengirim ini
    'jurusan_id' => 'RPL', // Frontend mengirim ini
    'status' => 'Aktif',
    'deadline' => '2026-01-17'
];

echo "=== DATA YANG DIKIRIM FRONTEND ===\n";
print_r($data);

echo "\n=== PROSES NORMALIZE ===\n";

// Simulate normalize kelas
$kelas = $data['kelas'];
if ($kelas && !in_array($kelas, ['X', 'XI', 'XII'])) {
    if (preg_match('/^(X|XI|XII)/i', $kelas, $matches)) {
        $kelas = strtoupper($matches[1]);
    }
}
echo "Kelas after normalize: $kelas\n";

// Simulate normalize jurusan_id
$jurusanId = $data['jurusan_id'];
if ($jurusanId && !str_starts_with((string)$jurusanId, 'JUR-')) {
    $jurusan = \App\Models\Jurusan::where('nama', $jurusanId)->first();
    if ($jurusan) {
        $jurusanId = $jurusan->id;
    }
}
echo "Jurusan ID after normalize: $jurusanId\n";

// Check validation
echo "\n=== CEK VALIDASI ===\n";
echo "Kelas valid (X/XI/XII): " . (in_array($kelas, ['X', 'XI', 'XII']) ? 'YES' : 'NO') . "\n";

$jurusanExists = \App\Models\Jurusan::where('id', $jurusanId)->exists();
echo "Jurusan exists in DB: " . ($jurusanExists ? 'YES' : 'NO') . "\n";
