<?php
require_once __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use App\Models\User;
use App\Models\Kelas;
use App\Models\Kuis;

// Assign siswa ke kelas
$kelas = Kelas::first();
$siswa = User::where('role', 'siswa')->first();

if ($kelas && $siswa) {
    $siswa->kelas_id = $kelas->id;
    $siswa->save();
    echo "Siswa {$siswa->name} assigned to kelas ID: {$siswa->kelas_id} ({$kelas->nama})\n";
} else {
    echo "No siswa or kelas found\n";
}

// Cek kuis aktif
$kuis = Kuis::where('status', 'Aktif')->with('kelasRelation')->first();
if ($kuis) {
    echo "\nKuis Aktif: {$kuis->judul} (ID: {$kuis->id})\n";
    echo "Status: {$kuis->status}\n";
    echo "Kelas IDs: " . implode(', ', $kuis->kelasRelation->pluck('id')->toArray()) . "\n";
    
    // Cek apakah siswa punya akses
    $kelasIds = $kuis->kelasRelation->pluck('id')->toArray();
    if (in_array($siswa->kelas_id, $kelasIds)) {
        echo "✓ Siswa punya akses ke kuis ini\n";
    } else {
        echo "✗ Siswa TIDAK punya akses ke kuis ini\n";
        echo "  - Siswa kelas_id: {$siswa->kelas_id}\n";
        echo "  - Kuis kelas_ids: " . implode(', ', $kelasIds) . "\n";
        
        // Auto-add siswa kelas to kuis
        echo "\nAuto-adding siswa kelas to kuis...\n";
        $kuis->kelasRelation()->syncWithoutDetaching([$siswa->kelas_id]);
        echo "Done!\n";
    }
} else {
    echo "\nNo active kuis found. Creating one...\n";
    
    $kuis = Kuis::create([
        'judul' => 'Kuis Test Attempt',
        'deskripsi' => 'Untuk testing attempt system',
        'status' => 'Aktif',
        'kelas' => $kelas->tingkat,
        'batas_waktu' => 30,
    ]);
    
    // Attach kelas
    $kuis->kelasRelation()->attach($kelas->id);
    
    // Create sample soal
    \App\Models\Soal::create([
        'kuis_id' => $kuis->id,
        'pertanyaan' => 'Apa ibu kota Indonesia?',
        'pilihan' => ['Jakarta', 'Bandung', 'Surabaya', 'Medan'],
        'jawaban' => 'Jakarta',
        'urutan' => 1,
    ]);
    
    \App\Models\Soal::create([
        'kuis_id' => $kuis->id,
        'pertanyaan' => '1 + 1 = ?',
        'pilihan' => ['1', '2', '3', '4'],
        'jawaban' => '2',
        'urutan' => 2,
    ]);
    
    echo "Created kuis ID: {$kuis->id} with 2 soal\n";
}

echo "\n=== Test Info ===\n";
echo "Siswa email: {$siswa->email}\n";
echo "Siswa kelas_id: {$siswa->kelas_id}\n";
echo "Kuis ID to test: {$kuis->id}\n";
