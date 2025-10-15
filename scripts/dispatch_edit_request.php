<?php

require __DIR__ . '/../vendor/autoload.php';

$app = require_once __DIR__ . '/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

// Create a request via the router
$request = Request::create('/company/1/inventory/inventory-items/1/edit', 'GET');
// Set headers
$request->headers->set('Accept', 'text/html');

// Set an authenticated user (guard expects a Request; Auth::guard()->setRequest exists in runtime)
// We'll set the user resolver on the request
$request->setUserResolver(function () {
    return App\Models\User::find(1);
});
// Also log the user into the default guard so filament()->setTenant receives a user
\Illuminate\Support\Facades\Auth::loginUsingId(1);

// Set tenant helper (Filament expects a tenant and a current user)
filament()->setTenant(App\Models\Company::find(1));

try {
    $kernel = $app->make(Illuminate\Contracts\Http\Kernel::class);
    $response = $kernel->handle($request);
    echo 'Status: ' . $response->getStatusCode() . PHP_EOL;
    $content = $response->getContent();
    echo 'Content length: ' . strlen($content) . PHP_EOL;
    echo "---- Content snippet ----\n";
    echo Str::limit(strip_tags($content), 1000);
    echo "\n---- End snippet ----\n";
    // Terminate
    $kernel->terminate($request, $response);
} catch (Throwable $e) {
    echo 'Exception: ' . get_class($e) . ': ' . $e->getMessage() . PHP_EOL;
    echo $e->getTraceAsString() . PHP_EOL;
}
