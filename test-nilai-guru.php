<?php

require __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use App\Http\Controllers\NilaiController;
use Illuminate\Http\Request;
use App\Models\User;

// Login as guru
$user = User::where('role', 'guru')->first();
if (!$user) {
    echo "No guru found!\n";
    exit;
}

echo "=== GURU INFO ===\n";
echo "ID: {$user->id}\n";
echo "Name: {$user->name}\n";
echo "Role: {$user->role}\n\n";

auth()->login($user);

$request = new Request();
$request->setUserResolver(function () use ($user) {
    return $user;
});

$controller = new NilaiController();

try {
    $response = $controller->index($request);
    echo "Response status: " . $response->getStatusCode() . "\n";
    echo "Response body:\n";
    echo $response->getContent() . "\n";
} catch (\Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
    echo "File: " . $e->getFile() . ":" . $e->getLine() . "\n";
    echo "\nStack trace:\n";
    echo $e->getTraceAsString() . "\n";
}
