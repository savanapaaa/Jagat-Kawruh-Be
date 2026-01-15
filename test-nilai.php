<?php

use App\Models\User;
use App\Models\HasilKuis;

require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make('Illuminate\Contracts\Console\Kernel');
$kernel->bootstrap();

// Find siswa user
$siswa = User::where('email', 'siswa@example.com')->first();

echo "=== SISWA INFO ===\n";
echo "ID: " . $siswa->id . "\n";
echo "Name: " . $siswa->name . "\n";
echo "Role: " . $siswa->role . "\n";
echo "Kelas: " . ($siswa->kelas ?? 'NULL') . "\n\n";

// Check hasil kuis
echo "=== HASIL KUIS ===\n";
$hasilKuis = HasilKuis::where('siswa_id', $siswa->id)->get();
echo "Total: " . $hasilKuis->count() . "\n";
foreach ($hasilKuis as $hasil) {
    echo "- ID: {$hasil->id}, Kuis: {$hasil->kuis_id}, Nilai: {$hasil->nilai}\n";
}

echo "\n=== TESTING NILAI CONTROLLER LOGIC ===\n";
try {
    $query = HasilKuis::with(['kuis:id,judul', 'siswa:id,name,kelas'])
        ->where('siswa_id', $siswa->id)
        ->orderBy('created_at', 'desc');
    
    $results = $query->get();
    echo "Query Success! Found: " . $results->count() . " results\n";
    
    foreach ($results as $result) {
        $mapped = [
            'id' => 'nilai-kuis-' . $result->id,
            'kuis_id' => $result->kuis_id,
            'kuis_judul' => $result->kuis ? $result->kuis->judul : '-',
            'siswa_id' => $result->siswa_id,
            'siswa_nama' => $result->siswa ? $result->siswa->name : '-',
            'nilai' => $result->nilai,
        ];
        print_r($mapped);
    }
} catch (\Exception $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
    echo "Trace: " . $e->getTraceAsString() . "\n";
}
