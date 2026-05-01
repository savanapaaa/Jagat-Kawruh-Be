<?php
require 'vendor/autoload.php';
$app = require 'bootstrap/app.php';
$kernel = $app->make(\Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\User;
use App\Models\Kuis;
use App\Models\Soal;
use App\Models\KuisAttempt;
use App\Http\Controllers\KuisAttemptController;
use Illuminate\Http\Request;

$guru = User::where('role', 'guru')->first();
$siswa = User::where('role', 'siswa')->whereNotNull('kelas_id')->first();
$kuis = Kuis::where('status', 'Aktif')->with('kelasRelation')->first();

if (!$guru || !$siswa || !$kuis) {
    echo "Missing guru/siswa/kuis data\n";
    exit(1);
}

// Ensure siswa class can access kuis
$kelasIds = $kuis->kelasRelation->pluck('id')->toArray();
if (!in_array($siswa->kelas_id, $kelasIds)) {
    $kuis->kelasRelation()->syncWithoutDetaching([$siswa->kelas_id]);
}

// Ensure question exists
if (Soal::where('kuis_id', $kuis->id)->count() === 0) {
    Soal::create([
        'kuis_id' => $kuis->id,
        'pertanyaan' => 'Test soal retake?',
        'pilihan' => ['A','B','C','D'],
        'jawaban' => 'A',
        'urutan' => 1,
    ]);
}

// Cleanup old attempts to make test deterministic
KuisAttempt::where('kuis_id', $kuis->id)->where('siswa_id', $siswa->id)->delete();

$controller = new KuisAttemptController();

// 1) Start first attempt (should succeed)
auth()->login($siswa);
$startReq1 = Request::create('/api/kuis/' . $kuis->id . '/attempts/start', 'POST');
$startReq1->setUserResolver(fn() => $siswa);
$resp1 = $controller->start($startReq1, $kuis->id);

echo "Start #1: " . $resp1->getStatusCode() . "\n";
$body1 = json_decode($resp1->getContent(), true);
$attemptId = $body1['data']['attempt_id'] ?? null;

echo "  message: " . ($body1['message'] ?? '-') . "\n";

auth()->logout();

// 2) Start again without approval (should 409)
auth()->login($siswa);
$startReq2 = Request::create('/api/kuis/' . $kuis->id . '/attempts/start', 'POST');
$startReq2->setUserResolver(fn() => $siswa);
$resp2 = $controller->start($startReq2, $kuis->id);

echo "Start #2 (without approval): " . $resp2->getStatusCode() . "\n";
$body2 = json_decode($resp2->getContent(), true);
echo "  message: " . ($body2['message'] ?? '-') . "\n";

auth()->logout();

// 3) Approve retake by guru (should 200)
auth()->login($guru);
$approveReq = Request::create('/api/kuis/' . $kuis->id . '/attempts/' . $attemptId . '/approve-retake', 'POST');
$approveReq->setUserResolver(fn() => $guru);
$resp3 = $controller->approveRetake($approveReq, $kuis->id, $attemptId);

echo "Approve retake: " . $resp3->getStatusCode() . "\n";
$body3 = json_decode($resp3->getContent(), true);
echo "  message: " . ($body3['message'] ?? '-') . "\n";

auth()->logout();

// 4) Start again after approval (should succeed)
auth()->login($siswa);
$startReq3 = Request::create('/api/kuis/' . $kuis->id . '/attempts/start', 'POST');
$startReq3->setUserResolver(fn() => $siswa);
$resp4 = $controller->start($startReq3, $kuis->id);

echo "Start #3 (after approval): " . $resp4->getStatusCode() . "\n";
$body4 = json_decode($resp4->getContent(), true);
echo "  message: " . ($body4['message'] ?? '-') . "\n";

auth()->logout();
