<?php
// Test PBL submit with wrong siswa (not in kelompok)
require 'vendor/autoload.php';
$app = require 'bootstrap/app.php';
$kernel = $app->make(\Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use App\Http\Controllers\PBLController;
use App\Models\User;

// Get a different siswa (not in kelompok-7)
$siswa = User::where('role', 'siswa')->where('id', '!=', 11)->first();
echo "Testing with siswa: {$siswa->name} (ID: {$siswa->id})\n";
echo "Expected: Should be denied (not in kelompok-7)\n\n";

// Authenticate
Auth::login($siswa);

// Create fake request
$request = new Request();
$request->merge([
    'kelompok_id' => 'kelompok-7',
    'catatan' => 'Unauthorized submission'
]);

// Create fake file
$file = UploadedFile::fake()->create('test-submission.pdf', 100);
$request->files->set('file', $file);

// Call submit method
$controller = new PBLController();
$response = $controller->submit($request, 'pbl-7');

echo "=== RESPONSE ===\n";
echo "Status Code: " . $response->getStatusCode() . "\n";
echo "Body:\n";
echo $response->getContent() . "\n";

// Parse
$data = json_decode($response->getContent(), true);
echo "\n=== RESULT ===\n";
echo "Success: " . ($data['success'] ? 'YES (WRONG!)' : 'NO (CORRECT!)') . "\n";
echo "Message: " . $data['message'] . "\n";
