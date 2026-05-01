<?php
require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use App\Models\User;
use App\Models\Kelas;
use App\Models\Kuis;

echo "=== Checking Siswa & Kuis Setup ===" . PHP_EOL;

// Find siswa with kelas_id
$siswa = User::where('role', 'siswa')->whereNotNull('kelas_id')->first();

if (!$siswa) {
    echo "No siswa with kelas_id. Finding any siswa..." . PHP_EOL;
    $siswa = User::where('role', 'siswa')->first();
    
    if ($siswa) {
        // Assign to first kelas
        $kelas = Kelas::first();
        if ($kelas) {
            $siswa->kelas_id = $kelas->id;
            $siswa->save();
            echo "Assigned siswa '{$siswa->name}' to kelas '{$kelas->nama}' (ID: {$kelas->id})" . PHP_EOL;
        }
    }
}

if ($siswa) {
    echo PHP_EOL . "Siswa: {$siswa->name}" . PHP_EOL;
    echo "  ID: {$siswa->id}" . PHP_EOL;
    echo "  Email: {$siswa->email}" . PHP_EOL;
    echo "  Kelas ID: {$siswa->kelas_id}" . PHP_EOL;
    
    $kelas = Kelas::find($siswa->kelas_id);
    if ($kelas) {
        echo "  Kelas: {$kelas->nama}" . PHP_EOL;
    }
    
    // Find kuis for this siswa's kelas
    echo PHP_EOL . "=== Kuis yang bisa diakses siswa ===" . PHP_EOL;
    $kuisForKelas = Kuis::whereHas('kelasRelation', function($q) use ($siswa) {
        $q->where('kelas.id', $siswa->kelas_id);
    })->where('status', 'Aktif')->get();
    
    if ($kuisForKelas->isEmpty()) {
        echo "Tidak ada kuis aktif untuk kelas siswa." . PHP_EOL;
        
        // Cari kuis aktif dan assign ke kelas siswa
        $anyKuis = Kuis::where('status', 'Aktif')->first();
        if ($anyKuis && $siswa->kelas_id) {
            // Check if pivot exists
            $exists = \DB::table('kuis_kelas')
                ->where('kuis_id', $anyKuis->id)
                ->where('kelas_id', $siswa->kelas_id)
                ->exists();
            
            if (!$exists) {
                \DB::table('kuis_kelas')->insert([
                    'kuis_id' => $anyKuis->id,
                    'kelas_id' => $siswa->kelas_id,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
                echo "Added kuis '{$anyKuis->id}' to kelas ID {$siswa->kelas_id}" . PHP_EOL;
            }
        }
    } else {
        foreach ($kuisForKelas as $k) {
            echo "  - {$k->id}: {$k->judul} (Status: {$k->status})" . PHP_EOL;
        }
    }
} else {
    echo "No siswa found in database!" . PHP_EOL;
}

echo PHP_EOL . "=== All Aktif Kuis ===" . PHP_EOL;
$allKuis = Kuis::where('status', 'Aktif')->take(5)->get();
foreach ($allKuis as $k) {
    $kelasIds = $k->kelasRelation->pluck('id')->toArray();
    echo "  {$k->id}: {$k->judul} -> Kelas: [" . implode(', ', $kelasIds) . "]" . PHP_EOL;
}
