<?php

require __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use App\Http\Controllers\NilaiController;
use Illuminate\Http\Request;
use App\Models\User;
use App\Models\Kelompok;

// Pick a siswa user who is member of some kelompok
$kelompok = Kelompok::whereNotNull('anggota')->whereRaw("JSON_LENGTH(anggota) > 0")->first();
if (!$kelompok) {
    echo "No kelompok with anggota found\n";
    exit(1);
}

$anggota = is_array($kelompok->anggota) ? $kelompok->anggota : [];
$raw = $anggota[0] ?? null;
if ($raw === null) {
    echo "Kelompok has empty anggota\n";
    exit(1);
}

$uid = null;
if (is_string($raw) && str_starts_with($raw, 'siswa-')) {
    $uid = (int) substr($raw, strlen('siswa-'));
} elseif (is_numeric($raw)) {
    $uid = (int) $raw;
}

if (!$uid) {
    echo "Could not determine siswa id from anggota: "; var_export($raw); echo PHP_EOL;
    exit(1);
}

$siswa = User::find($uid);
if (!$siswa) {
    echo "User $uid not found\n";
    exit(1);
}

auth()->login($siswa);

$request = new Request([
    'type' => 'pbl',
]);
$request->setUserResolver(fn () => $siswa);

$controller = new NilaiController();
$response = $controller->index($request);

echo "SISWA: {$siswa->id} {$siswa->name}\n";
echo "Status: {$response->getStatusCode()}\n";
$json = json_decode($response->getContent(), true);
echo json_encode([
    'success' => $json['success'] ?? null,
    'data' => [
        'pbl' => $json['data']['pbl'] ?? null,
        'rata_rata_pbl' => $json['data']['rata_rata_pbl'] ?? null,
    ]
], JSON_PRETTY_PRINT) . "\n";
