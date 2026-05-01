<?php

require __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use App\Http\Controllers\PBLKontribusiController;
use App\Models\PBLKontribusi;
use App\Models\Kelompok;
use App\Models\PBLSintaks;
use App\Models\User;
use Illuminate\Http\Request;

$kelompok = Kelompok::whereNotNull('anggota')->whereRaw('JSON_LENGTH(anggota) > 0')->first();
if (!$kelompok) {
    echo "No kelompok with anggota found\n";
    exit(1);
}

$anggota = is_array($kelompok->anggota) ? $kelompok->anggota : [];
$raw = $anggota[0] ?? null;
$uid = null;

if (is_string($raw) && str_starts_with($raw, 'siswa-')) {
    $uid = (int) substr($raw, strlen('siswa-'));
} elseif (is_numeric($raw)) {
    $uid = (int) $raw;
} elseif (is_array($raw)) {
    $fromArray = $raw['id'] ?? $raw['user_id'] ?? null;
    if (is_string($fromArray) && str_starts_with($fromArray, 'siswa-')) {
        $uid = (int) substr($fromArray, strlen('siswa-'));
    } elseif (is_numeric($fromArray)) {
        $uid = (int) $fromArray;
    }
}

if (!$uid) {
    echo "Could not determine siswa id\n";
    exit(1);
}

$student = User::find($uid);
if (!$student || $student->role !== 'siswa') {
    echo "Siswa not found\n";
    exit(1);
}

$sintaksList = PBLSintaks::where('pbl_id', $kelompok->pbl_id)->orderBy('urutan')->get();
$sintaks = null;

foreach ($sintaksList as $candidate) {
    $exists = PBLKontribusi::where('pbl_id', $kelompok->pbl_id)
        ->where('kelompok_id', $kelompok->id)
        ->where('sintaks_id', $candidate->id)
        ->where('siswa_id', $student->id)
        ->exists();

    if (!$exists) {
        $sintaks = $candidate;
        break;
    }
}

if (!$sintaks) {
    $sintaks = $sintaksList->first();
}

if (!$sintaks) {
    echo "No sintaks found for pbl {$kelompok->pbl_id}\n";
    exit(1);
}

$alreadyExists = PBLKontribusi::where('pbl_id', $kelompok->pbl_id)
    ->where('kelompok_id', $kelompok->id)
    ->where('sintaks_id', $sintaks->id)
    ->where('siswa_id', $student->id)
    ->exists();

echo "Using sintaks {$sintaks->id}; existing record: " . ($alreadyExists ? 'yes' : 'no') . "\n";

$controller = new PBLKontribusiController();
$request = new Request([
    'catatan' => 'Tes kontribusi tanpa file ' . date('Y-m-d H:i:s'),
]);
$request->setUserResolver(fn () => $student);
auth()->login($student);

$response = $controller->storeMine($request, (string) $kelompok->pbl_id, (string) $sintaks->id);

echo "Status: {$response->getStatusCode()}\n";
echo json_encode(json_decode($response->getContent(), true), JSON_PRETTY_PRINT) . "\n";
