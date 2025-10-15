<?php

$root = dirname(__DIR__);
require $root . '/vendor/autoload.php';

$app = require_once $root . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Http\Kernel::class);

// Create a request for the edit page
$request = Illuminate\Http\Request::create('/company/1/inventory/inventory-items/1/edit', 'GET');

// Authenticate as the first user to mimic real session
try {
    \Illuminate\Support\Facades\Auth::loginUsingId(1);
} catch (Throwable $e) {
    // ignore if Auth not available yet in this context
}

try {
    $response = $kernel->handle($request);
    echo 'HTTP Status: ' . $response->getStatusCode() . "\n";
} catch (Throwable $e) {
    echo 'EXCEPTION: ' . get_class($e) . ': ' . $e->getMessage() . "\n";
    echo $e->getTraceAsString();
}

$kernel->terminate($request, $response ?? new Illuminate\Http\Response);
