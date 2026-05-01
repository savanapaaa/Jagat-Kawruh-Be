<?php
// Check siswa data
require 'vendor/autoload.php';
$app = require 'bootstrap/app.php';
$kernel = $app->make(\Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\User;
use App\Models\PBL;

echo "=== PROJECT DATA ===\n";
$project = PBL::find('pbl-7');
echo "Project: {$project->judul}\n";
echo "Status: {$project->status}\n";
echo "Jurusan ID: {$project->jurusan_id}\n";
echo "Kelas IDs: " . json_encode($project->kelasRelation->pluck('id')->toArray()) . "\n";

echo "\n=== SISWA DATA ===\n";
$siswas = User::where('role', 'siswa')->get(['id', 'name', 'kelas_id', 'jurusan_id']);
foreach ($siswas as $s) {
    echo "ID: {$s->id}, Name: {$s->name}, Kelas: {$s->kelas_id}, Jurusan: {$s->jurusan_id}\n";
}
