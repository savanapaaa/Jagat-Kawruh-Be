<?php
echo "Starting...\n";

require __DIR__ . '/vendor/autoload.php';

try {
    echo "Loading bootstrap...\n";
    $app = require __DIR__ . '/bootstrap/app.php';
    echo "Bootstrap loaded\n";
    
    $kernel = $app->make(\Illuminate\Contracts\Http\Kernel::class);
    echo "HTTP Kernel loaded\n";
    
    // Create a test request
    $request = \Illuminate\Http\Request::create('/', 'GET');
    echo "Request created\n";
    
    $response = $kernel->handle($request);
    echo "Request handled\n";
    echo "Status: " . $response->getStatusCode() . "\n";
    echo "Body: " . $response->getContent() . "\n";
    
} catch (\Exception $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
    echo $e->getTraceAsString() . "\n";
}
