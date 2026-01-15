<?php

use Illuminate\Http\Request;

require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';

$kernel = $app->make(Illuminate\Contracts\Http\Kernel::class);

$request = Request::create('/api/profile', 'GET');
$response = $kernel->handle($request);

echo "Status: {$response->getStatusCode()}\n";
echo "Body: {$response->getContent()}\n";

$kernel->terminate($request, $response);
