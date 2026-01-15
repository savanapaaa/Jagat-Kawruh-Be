<?php

require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$latest = App\Models\Kuis::query()->orderByDesc('created_at')->first();
if (!$latest) {
    echo "No kuis found\n";
    exit(0);
}

echo "Latest kuis: {$latest->id} | {$latest->judul}\n";
echo "Soal count: " . App\Models\Soal::query()->where('kuis_id', $latest->id)->count() . "\n\n";

$ids = App\Models\Kuis::query()->orderByDesc('created_at')->limit(5)->pluck('id');
foreach ($ids as $id) {
    $count = App\Models\Soal::query()->where('kuis_id', $id)->count();
    echo "$id => $count\n";
}
