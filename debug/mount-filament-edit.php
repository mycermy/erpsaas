<?php

<?php

require __DIR__ . '/../../vendor/autoload.php';

// Bootstrap application
/** @var \Illuminate\Foundation\Application $app */
$app = require_once __DIR__ . '/../../bootstrap/app.php';

// Authenticate as user 1 if possible
try {
    \Illuminate\Support\Facades\Auth::loginUsingId(1);
} catch (Throwable $e) {
    // ignore
}

// Instantiate the Filament EditInventoryItem page component
$class = \App\Filament\Company\Resources\Inventory\InventoryItemResource\Pages\EditInventoryItem::class;

try {
    $page = new $class();

    if (method_exists($page, 'mount')) {
        // Simulate Livewire mount parameters for tenant id and record id
        $page->mount('1', '1');
    }

    echo "Mounted successfully\n";
} catch (Throwable $e) {
    echo 'EXCEPTION: ' . get_class($e) . ': ' . $e->getMessage() . "\n";
    echo $e->getTraceAsString();
}
