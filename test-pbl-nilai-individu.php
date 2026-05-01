<?php

require __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use App\Http\Controllers\PBLNilaiIndividuController;
use App\Models\Kelompok;
use App\Models\PBL;
use App\Models\User;
use Illuminate\Http\Request;

// Pick an admin/guru user
$actor = User::whereIn('role', ['admin', 'guru'])->first();
if (!$actor) {
    echo "No admin/guru found\n";
    exit(1);
}

auth()->login($actor);

// Find or create a PBL owned by actor (if guru)
$query = PBL::query();
if ($actor->role === 'guru') {
    $query->where('created_by', $actor->id);
}
$pbl = $query->first();
if (!$pbl) {
    $pbl = PBL::create([
        'judul' => 'PBL Test Nilai Individu',
        'kelas' => 'X',
        'status' => 'Aktif',
        'created_by' => $actor->id,
    ]);
}

// Find or create a kelompok in this PBL
$kelompok = Kelompok::where('pbl_id', $pbl->id)->first();
if (!$kelompok) {
    $siswa = User::where('role', 'siswa')->limit(2)->get();
    if ($siswa->count() < 1) {
        echo "No siswa found\n";
        exit(1);
    }

    $anggota = $siswa->map(fn ($u) => 'siswa-' . $u->id)->values()->all();

    $kelompok = Kelompok::create([
        'pbl_id' => $pbl->id,
        'nama_kelompok' => 'Kelompok Test',
        'anggota' => $anggota,
    ]);
}

$controller = new PBLNilaiIndividuController();

// Update nilai individu (format normal: nilai_individu)
$payload = [];
foreach ((array) $kelompok->anggota as $idx => $sid) {
    $payload[] = [
        'siswa_id' => $sid,
        'nilai' => $idx === 0 ? 96 : 80,
    ];
}

$reqUpdate = new Request([
    'nilai_individu' => $payload,
]);
$reqUpdate->setUserResolver(fn () => $actor);

$resUpdate = $controller->update($reqUpdate, $pbl->id, $kelompok->id);

echo "UPDATE status: {$resUpdate->getStatusCode()}\n";
echo json_encode(json_decode($resUpdate->getContent()), JSON_PRETTY_PRINT) . "\n\n";

// Update nilai individu (format wrapper: data.items)
$reqUpdate2 = new Request([
    'data' => [
        'items' => $payload,
    ],
]);
$reqUpdate2->setUserResolver(fn () => $actor);
$resUpdate2 = $controller->update($reqUpdate2, $pbl->id, $kelompok->id);

echo "UPDATE2 status: {$resUpdate2->getStatusCode()}\n";
echo json_encode(json_decode($resUpdate2->getContent()), JSON_PRETTY_PRINT) . "\n\n";

// Update nilai individu (format array root)
$reqUpdate3 = new Request($payload);
$reqUpdate3->setUserResolver(fn () => $actor);
$resUpdate3 = $controller->update($reqUpdate3, $pbl->id, $kelompok->id);

echo "UPDATE3 status: {$resUpdate3->getStatusCode()}\n";
echo json_encode(json_decode($resUpdate3->getContent()), JSON_PRETTY_PRINT) . "\n\n";

// Show nilai individu
$reqShow = new Request();
$reqShow->setUserResolver(fn () => $actor);

$resShow = $controller->show($reqShow, $pbl->id, $kelompok->id);

echo "SHOW status: {$resShow->getStatusCode()}\n";
echo json_encode(json_decode($resShow->getContent()), JSON_PRETTY_PRINT) . "\n";
