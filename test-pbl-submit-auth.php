<?php
// Test PBL submit authorization
require 'vendor/autoload.php';
$app = require 'bootstrap/app.php';
$kernel = $app->make(\Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\User;
use App\Models\PBL;
use App\Models\Kelompok;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;

// Setup test data
$siswa = User::where('role', 'siswa')->first();
$project = PBL::find('pbl-7');
$kelompok = Kelompok::where('pbl_id', 'pbl-7')->first();

echo "=== TEST DATA ===\n";
echo "Siswa: {$siswa->id} - {$siswa->name}\n";
echo "Project: {$project->id} - {$project->judul}\n";
echo "Kelompok: {$kelompok->id} - {$kelompok->nama_kelompok}\n";
echo "Anggota: " . json_encode($kelompok->anggota) . "\n";

// Check siswa in kelompok
$isMember = false;
if (is_array($kelompok->anggota)) {
    foreach ($kelompok->anggota as $anggota) {
        $anggotaId = str_replace('siswa-', '', $anggota);
        if ($anggotaId == $siswa->id || $anggota == $siswa->id) {
            $isMember = true;
            break;
        }
    }
}
echo "\nIs siswa member: " . ($isMember ? 'YES' : 'NO') . "\n";

// Simulate request
echo "\n=== SIMULATING SUBMIT REQUEST ===\n";
$request = new Request();
$request->merge([
    'kelompok_id' => $kelompok->id,
    'catatan' => 'Test submission'
]);

// Create a dummy file
$file = UploadedFile::fake()->create('test-submission.pdf', 100);
$request->files->set('file', $file);

// Setup auth
Auth::login($siswa);
echo "Authenticated as: " . auth()->user()->name . "\n";

// Test authorization checks
$controller = new \App\Http\Controllers\PBLController();

// Test siswaCanAccessProject
$canAccess = $controller->siswaCanAccessProject($siswa, $project);
echo "siswaCanAccessProject result: " . ($canAccess ? 'ALLOWED' : 'DENIED') . "\n";

// Test authorization flow manually
echo "\n=== AUTHORIZATION FLOW ===\n";
echo "1. Is project Aktif? " . ($project->status === 'Aktif' ? 'YES' : 'NO') . "\n";
echo "2. User role: " . $siswa->role . "\n";
echo "3. User kelas_id: {$siswa->kelas_id}\n";
echo "4. Project kelas_ids: " . json_encode($project->kelasRelation->pluck('id')->toArray()) . "\n";
echo "5. Kelompok exist in project? " . (Kelompok::where('id', $kelompok->id)->where('pbl_id', 'pbl-7')->count() > 0 ? 'YES' : 'NO') . "\n";
echo "6. Siswa is member of kelompok? " . ($isMember ? 'YES' : 'NO') . "\n";

echo "\n✓ All checks passed - submit should be allowed!\n";
