<?php

$autoload = __DIR__ . '/../vendor/autoload.php';
if (! file_exists($autoload)) {
    $autoload = __DIR__ . '/vendor/autoload.php';
}
require $autoload;

$app = require_once __DIR__ . '/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

// Create a fake HTTP request and set it on the auth guard
$request = Request::create('/company/1/inventory/inventory-items/1/edit', 'GET');
Auth::guard()->setRequest($request);

// Login as user ID 1
Auth::guard()->loginUsingId(1);

// Set tenant on filament helper
filament()->setTenant(App\Models\Company::find(1));

// Try to resolve the Filament page class for edit route
try {
    $resource = App\Filament\Company\Resources\Inventory\InventoryItemResource::class;
    $pageRegistration = $resource::getPages()['edit'];

    if (is_string($pageRegistration)) {
        $pageClass = $pageRegistration;
    } elseif (is_array($pageRegistration) && isset($pageRegistration['class'])) {
        $pageClass = $pageRegistration['class'];
    } elseif (is_object($pageRegistration)) {
        // Use reflection to access protected 'page' property
        try {
            $ref = new ReflectionObject($pageRegistration);
            if ($ref->hasProperty('page')) {
                $prop = $ref->getProperty('page');
                $prop->setAccessible(true);
                $pageClass = $prop->getValue($pageRegistration);
            } else {
                throw new Exception('Page property not found');
            }
        } catch (Throwable $e) {
            echo 'Reflection error: ' . $e->getMessage() . PHP_EOL;

            throw $e;
        }
    } else {
        // Dump structure for debugging
        echo 'Page registration type: ' . gettype($pageRegistration) . PHP_EOL;
        var_export($pageRegistration);
        echo PHP_EOL;
        echo "Methods on PageRegistration:\n";
        var_export(get_class_methods($pageRegistration));
        echo PHP_EOL;

        throw new Exception('Unable to determine page class from registration');
    }

    echo 'Page class: ' . $pageClass . PHP_EOL;

    // Instantiate the page and call mount
    $page = new $pageClass;
    if (method_exists($page, 'mount')) {
        $page->mount(1);
        echo 'Mounted successfully' . PHP_EOL;
    } else {
        echo 'No mount method to call' . PHP_EOL;
    }
} catch (Throwable $e) {
    echo 'Exception: ' . get_class($e) . ': ' . $e->getMessage() . PHP_EOL;
    echo $e->getTraceAsString() . PHP_EOL;
}
