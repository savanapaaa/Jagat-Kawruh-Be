<?php
// Test PBL submit via HTTP request
require 'vendor/autoload.php';
$app = require 'bootstrap/app.php';
$kernel = $app->make(\Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use App\Http\Controllers\PBLController;
use App\Models\User;

// Get siswa (fiqa cantik)
$siswa = User::find(11);
echo "Testing with siswa: {$siswa->name} (ID: {$siswa->id})\n\n";

// Authenticate
Auth::login($siswa);

// Create fake request with file
$request = new Request();
$request->merge([
    'kelompok_id' => 'kelompok-7',
    'catatan' => 'Test submission'
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

// Parse and display
$data = json_decode($response->getContent(), true);
if ($data) {
    echo "\n=== PARSED ===\n";
    echo "Success: " . ($data['success'] ? 'YES' : 'NO') . "\n";
    echo "Message: " . $data['message'] . "\n";
    if ($data['success']) {
        echo "Submission ID: " . $data['data']['id'] . "\n";
        echo "File path: " . $data['data']['file_path'] . "\n";
    }
}
