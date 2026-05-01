<?php
require_once __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';

use App\Models\User;
use App\Models\Kelompok;
use App\Models\PBL;
use Illuminate\Http\Request;
use App\Http\Controllers\PBLNilaiIndividuController;

// Test 1: User dengan override
echo "=== TEST 1: User 12 (sapp) di Kelompok 10 PBL 8 (dengan override 96) ===\n";
$user = User::find(12);
if ($user) {
    $controller = new PBLNilaiIndividuController();
    $request = new Request();
    $request->setUserResolver(fn () => $user);
    $response = $controller->show($request, 'pbl-8', 'kelompok-10');
    $json = json_decode($response->getContent(), true);
    echo json_encode($json, JSON_PRETTY_PRINT) . "\n\n";
}

// Test 2: Find a kelompok dengan submission nilai tapi tidak ada override
echo "=== TEST 2: Cari kelompok dengan nilai submission, tanpa override ===\n";
$submission = \App\Models\PBLSubmission::whereNotNull('nilai')
    ->first();

if ($submission) {
    $kelompok = Kelompok::find($submission->kelompok_id);
    if ($kelompok) {
        echo "Submission ditemukan: PBL {$submission->pbl_id}, Kelompok {$submission->kelompok_id}, Nilai: {$submission->nilai}\n";
        echo "Kelompok nilai_individu: " . json_encode($kelompok->nilai_individu) . "\n";
        
        // Get first member of this kelompok
        if ($kelompok->anggota && is_array($kelompok->anggota) && count($kelompok->anggota) > 0) {
            $anggotaRaw = $kelompok->anggota[0];
            // Extract user ID from anggota entry
            if (is_array($anggotaRaw)) {
                $uid = $anggotaRaw['id'] ?? $anggotaRaw['user_id'] ?? null;
            } else {
                $uid = is_numeric($anggotaRaw) ? $anggotaRaw : null;
            }
            
            if ($uid) {
                $siswa = User::find($uid);
                if ($siswa) {
                    echo "\nTesting dengan siswa: $uid ({$siswa->name})\n";
                    $controller = new PBLNilaiIndividuController();
                    $request = new Request();
                    $request->setUserResolver(fn () => $siswa);
                    $response = $controller->show($request, $submission->pbl_id, $submission->kelompok_id);
                    $json = json_decode($response->getContent(), true);
                    echo json_encode($json, JSON_PRETTY_PRINT) . "\n";
                }
            }
        }
    }
}

echo "\nDone.\n";
