<?php

require __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use App\Http\Controllers\PBLNilaiIndividuController;
use App\Models\Kelompok;
use App\Models\PBL;
use App\Models\User;
use Illuminate\Http\Request;

// Find a kelompok that has anggota, then pick a siswa from anggota
$kelompok = Kelompok::whereNotNull('anggota')->whereRaw("JSON_LENGTH(anggota) > 0")->first();
if (!$kelompok) {
    echo "No kelompok with anggota found\n";
    exit(1);
}

$anggota = is_array($kelompok->anggota) ? $kelompok->anggota : [];
// pick the first anggota and normalize to user id
$raw = $anggota[0] ?? null;
if ($raw === null) {
    echo "Kelompok has no anggota\n";
    exit(1);
}

// raw can be 'siswa-12' or numeric id
$uid = null;
if (is_string($raw) && str_starts_with($raw, 'siswa-')) {
    $uid = (int) substr($raw, strlen('siswa-'));
} elseif (is_numeric($raw)) {
    $uid = (int) $raw;
}

if (!$uid) {
    echo "Could not determine siswa id from anggota: "; var_export($raw); echo PHP_EOL; exit(1);
}

$student = User::find($uid);
if (!$student) {
    echo "User with id $uid not found\n";
    exit(1);
}

$pblId = $kelompok->pbl_id;

$controller = new PBLNilaiIndividuController();
$reqShow = new Request();
$reqShow->setUserResolver(fn () => $student);

// Ensure auth() returns the student in this test environment
auth()->login($student);


$res = $controller->show($reqShow, $pblId, $kelompok->id);

echo "Status: {$res->getStatusCode()}\n";
echo json_encode(json_decode($res->getContent()), JSON_PRETTY_PRINT) . "\n";
