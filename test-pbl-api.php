<?php

require __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make('Illuminate\Contracts\Http\Kernel');

// Create a fake request
$request = Illuminate\Http\Request::create('/api/pbl', 'POST', [
    'judul' => 'Test Project',
    'kelas' => 'X RPL 1',
    'jurusan_id' => 'RPL',
    'status' => 'Aktif',
    'deadline' => '2026-01-17'
]);

// Add auth header - we need a valid token, so let's just test the controller directly
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use App\Http\Controllers\PBLController;
use Illuminate\Http\Request;

// Create authenticated user
$user = App\Models\User::where('role', 'guru')->first();
if (!$user) {
    echo "No guru found!\n";
    exit;
}

// Login as this user
auth()->login($user);

// Create request
$request = new Request([
    'judul' => 'Test Project',
    'kelas' => 'X RPL 1',
    'jurusan_id' => 'RPL',
    'status' => 'Aktif',
    'deadline' => '2026-01-17'
]);

// Set the user on request
$request->setUserResolver(function () use ($user) {
    return $user;
});

$controller = new PBLController();

try {
    $response = $controller->store($request);
    echo "Response status: " . $response->getStatusCode() . "\n";
    echo "Response body:\n";
    echo json_encode(json_decode($response->getContent()), JSON_PRETTY_PRINT) . "\n";
} catch (\Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
    echo "File: " . $e->getFile() . ":" . $e->getLine() . "\n";
}
