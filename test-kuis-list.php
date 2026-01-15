<?php

require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$rows = App\Models\Kuis::query()
    ->orderByDesc('created_at')
    ->limit(10)
    ->get(['id', 'judul', 'status', 'created_at']);

echo $rows->toJson(JSON_PRETTY_PRINT) . PHP_EOL;
