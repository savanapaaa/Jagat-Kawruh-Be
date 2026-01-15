<?php

use App\Models\User;
use Illuminate\Support\Facades\Hash;

require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make('Illuminate\Contracts\Console\Kernel');
$kernel->bootstrap();

// Test login dan get token
$user = User::where('email', 'guru@example.com')->first();

if (!$user) {
    echo "Guru user not found!\n";
    exit(1);
}

// Create token
$token = $user->createToken('test-token')->plainTextToken;

echo "=== GURU LOGIN ===\n";
echo "Email: guru@example.com\n";
echo "Token: $token\n\n";

// Test create materi
echo "=== TESTING CREATE MATERI ===\n";

$data = [
    'judul' => 'Test Materi',
    'deskripsi' => 'Ini test materi',
    'kelas' => json_encode(['X', 'XI']),
    'status' => 'Draft'
];

echo "Data to send:\n";
print_r($data);

echo "\n=== VALIDATION TEST ===\n";

$validator = \Illuminate\Support\Facades\Validator::make($data, [
    'judul' => 'required|string|max:255',
    'deskripsi' => 'nullable|string',
    'kelas' => 'required',
    'jurusan_id' => 'nullable|exists:jurusans,id',
    'status' => 'sometimes|in:Draft,Dipublikasikan,Archived',
], [
    'judul.required' => 'Judul materi wajib diisi',
    'kelas.required' => 'Kelas wajib dipilih',
]);

if ($validator->fails()) {
    echo "VALIDATION FAILED:\n";
    print_r($validator->errors()->toArray());
} else {
    echo "VALIDATION PASSED!\n";
    
    // Parse kelas
    $kelas = $data['kelas'];
    if (is_string($kelas)) {
        $kelas = json_decode($kelas, true);
    }
    if (!is_array($kelas)) {
        $kelas = [$kelas];
    }
    
    echo "Parsed kelas: ";
    print_r($kelas);
}

echo "\n=== CURL COMMAND ===\n";
echo "curl -X POST http://127.0.0.1:8000/api/materi \\\n";
echo "  -H 'Authorization: Bearer $token' \\\n";
echo "  -H 'Content-Type: multipart/form-data' \\\n";
echo "  -F 'judul=Test Materi' \\\n";
echo "  -F 'deskripsi=Ini test materi' \\\n";
echo "  -F 'kelas=[\"X\",\"XI\"]' \\\n";
echo "  -F 'status=Draft'\n";
