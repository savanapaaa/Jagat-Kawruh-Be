<?php
require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use App\Models\Kuis;
use App\Models\KuisAttempt;
use App\Models\User;

// Login as siswa
$siswa = User::where('email', 'siswa@example.com')->first();
echo "Siswa: {$siswa->name} (ID: {$siswa->id}, Kelas ID: {$siswa->kelas_id})\n";

// Test normalize function
function normalizeKuisId(string $kuisId): string {
    if (preg_match('/^kuis-\d+$/i', $kuisId)) {
        return strtolower($kuisId);
    }
    if (preg_match('/^\d+$/', $kuisId)) {
        return 'kuis-' . $kuisId;
    }
    return $kuisId;
}

$testIds = ['kuis-1', '1', 'KUIS-1', 'kuis-26', '26'];
echo "\n=== ID Normalization Test ===\n";
foreach ($testIds as $id) {
    echo "  '{$id}' -> '" . normalizeKuisId($id) . "'\n";
}

// Find kuis
$kuisId = 'kuis-1';
$normalized = normalizeKuisId($kuisId);
echo "\n=== Testing kuis lookup ===\n";
echo "Looking for: {$normalized}\n";

$kuis = Kuis::with('kelasRelation')->find($normalized);
if ($kuis) {
    echo "Kuis found: {$kuis->judul} (Status: {$kuis->status})\n";
    
    $kelasIds = $kuis->kelasRelation->pluck('id')->toArray();
    echo "Kelas IDs: [" . implode(', ', $kelasIds) . "]\n";
    
    if (in_array($siswa->kelas_id, $kelasIds)) {
        echo "✓ Siswa punya akses ke kuis ini\n";
    } else {
        echo "✗ Siswa TIDAK punya akses (kelas_id {$siswa->kelas_id} not in kelas)\n";
    }
    
    // Check soal
    $soalCount = \App\Models\Soal::where('kuis_id', $normalized)->count();
    echo "Jumlah soal: {$soalCount}\n";
    
    // Check existing attempt
    $existingAttempt = KuisAttempt::where('kuis_id', $normalized)
        ->where('siswa_id', $siswa->id)
        ->where('status', 'in_progress')
        ->first();
    
    if ($existingAttempt) {
        echo "Ada attempt aktif: ID {$existingAttempt->id}\n";
    } else {
        echo "Tidak ada attempt aktif\n";
        
        // Try create attempt
        try {
            $attempt = KuisAttempt::create([
                'kuis_id' => $normalized,
                'siswa_id' => $siswa->id,
                'started_at' => now(),
                'ends_at' => now()->addMinutes(60),
                'status' => 'in_progress',
                'total_soal' => $soalCount,
                'answers' => [],
            ]);
            echo "✓ Attempt created: ID {$attempt->id}, Token: {$attempt->token}\n";
        } catch (Exception $e) {
            echo "✗ Error creating attempt: {$e->getMessage()}\n";
        }
    }
} else {
    echo "Kuis NOT FOUND!\n";
}
