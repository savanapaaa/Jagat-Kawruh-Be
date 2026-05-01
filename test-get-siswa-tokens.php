<?php
// Quick test to get siswa member of pbl-7 kelompok
require 'vendor/autoload.php';
$app = require 'bootstrap/app.php';
$kernel = $app->make(\Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\User;
use App\Models\Kelompok;
use App\Models\PBL;

// Find kelompok in pbl-7
$kelompok = Kelompok::where('pbl_id', 'pbl-7')->first();
echo "Kelompok: {$kelompok->nama_kelompok}\n";
echo "Anggota (raw): " . json_encode($kelompok->anggota) . "\n";

// Extract siswa IDs from anggota
$siswaIds = [];
if (is_array($kelompok->anggota)) {
    foreach ($kelompok->anggota as $anggota) {
        $anggotaId = str_replace('siswa-', '', $anggota);
        $siswaIds[] = (int)$anggotaId;
    }
}
echo "Extracted siswa IDs: " . json_encode($siswaIds) . "\n";

// Get siswa details
$siswas = User::whereIn('id', $siswaIds)->get(['id', 'name', 'email']);
echo "\nSiswa members:\n";
foreach ($siswas as $siswa) {
    echo "  - ID: {$siswa->id}, Name: {$siswa->name}, Email: {$siswa->email}\n";
}

// Get tokens
echo "\nTokens:\n";
foreach ($siswas as $siswa) {
    $tokens = $siswa->tokens()->latest()->first();
    if ($tokens) {
        echo "  - {$siswa->name}: {$tokens->id}|{$tokens->token}\n";
    }
}
