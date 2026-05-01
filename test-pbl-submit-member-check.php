<?php
// Test siswa yang akses project OK tapi bukan anggota kelompok
require 'vendor/autoload.php';
$app = require 'bootstrap/app.php';
$kernel = $app->make(\Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use App\Http\Controllers\PBLController;
use App\Models\User;

// Get siswa 12 (sapp) - has kelas=1 but not in kelompok-7
$siswa = User::find(12);
echo "Testing with siswa: {$siswa->name} (ID: {$siswa->id})\n";
echo "Kelas: {$siswa->kelas_id}, Jurusan: {$siswa->jurusan_id}\n";
echo "Expected: Should be denied (not in kelompok-7 anggota)\n\n";

// Authenticate
Auth::login($siswa);

// Create request
$request = new Request();
$request->merge([
    'kelompok_id' => 'kelompok-7',
    'catatan' => 'Test submission member check'
]);

$file = UploadedFile::fake()->create('test-submission.pdf', 100);
$request->files->set('file', $file);

// Call submit
$controller = new PBLController();
$response = $controller->submit($request, 'pbl-7');

echo "=== RESPONSE ===\n";
echo "Status Code: " . $response->getStatusCode() . "\n";
$data = json_decode($response->getContent(), true);
echo "Message: " . $data['message'] . "\n";
echo "Result: " . ($response->getStatusCode() === 403 ? "✓ CORRECT (403 Forbidden)" : "✗ WRONG ({$response->getStatusCode()})") . "\n";
