#!/usr/bin/env php
<?php

/**
 * Download all Laravel attributes documentation from GitHub
 *
 * Usage: php .ai/docs/download-attributes.php
 */

$baseUrl = 'https://raw.githubusercontent.com/MrPunyapal/laravel-attributes-list/main/attributes';
$baseDir = __DIR__ . '/attributes';

$files = [
    // Eloquent
    'eloquent/Appends.md',
    'eloquent/Boot.md',
    'eloquent/CollectedBy.md',
    'eloquent/Connection.md',
    'eloquent/DateFormat.md',
    'eloquent/Guarded.md',
    'eloquent/Initialize.md',
    'eloquent/Touches.md',
    'eloquent/Unguarded.md',
    'eloquent/UseEloquentBuilder.md',
    'eloquent/UseFactory.md',
    'eloquent/UsePolicy.md',
    'eloquent/UseResource.md',
    'eloquent/UseResourceCollection.md',
    'eloquent/Visible.md',
    'eloquent/WithoutIncrementing.md',
    'eloquent/WithoutTimestamps.md',

    // Queue
    'queue/Backoff.md',
    'queue/DebounceFor.md',
    'queue/Delay.md',
    'queue/DeleteWhenMissingModels.md',
    'queue/FailOnTimeout.md',
    'queue/MaxExceptions.md',
    'queue/Queue.md',
    'queue/UniqueFor.md',
    'queue/WithoutRelations.md',

    // Console
    'console/Aliases.md',
    'console/Description.md',
    'console/Help.md',
    'console/Hidden.md',
    'console/Signature.md',
    'console/Usage.md',

    // Controllers
    'controllers/Authorize.md',
    'controllers/Middleware.md',

    // Form Requests
    'form-requests/ErrorBag.md',
    'form-requests/FailOnUnknownFields.md',
    'form-requests/RedirectTo.md',
    'form-requests/RedirectToRoute.md',
    'form-requests/StopOnFirstFailure.md',

    // Dependency Injection
    'di/Auth.md',
    'di/Authenticated.md',
    'di/Bind.md',
    'di/Cache.md',
    'di/Config.md',
    'di/Context.md',
    'di/CurrentUser.md',
    'di/DB.md',
    'di/Database.md',
    'di/Give.md',
    'di/Log.md',
    'di/RouteParameter.md',
    'di/Scoped.md',
    'di/Storage.md',
    'di/Tag.md',

    // Testing
    'testing/Seed.md',
    'testing/Seeder.md',
    'testing/SetUp.md',
    'testing/TearDown.md',
    'testing/UnitTest.md',

    // Factories
    'factories/UseModel.md',

    // API Resources
    'api-resources/Collects.md',
    'api-resources/PreserveKeys.md',

    // AI (Agents)
    'ai/MaxSteps.md',
    'ai/MaxTokens.md',
    'ai/Model.md',
    'ai/Provider.md',
    'ai/Temperature.md',
    'ai/Timeout.md',
    'ai/UseCheapestModel.md',
    'ai/UseSmartestModel.md',

    // PHP Built-in
    'php/AllowDynamicProperties.md',
    'php/Attribute.md',
    'php/Deprecated.md',
    'php/NoDiscard.md',
    'php/Override.md',
    'php/ReturnTypeWillChange.md',
    'php/SensitiveParameter.md',
];

echo "Downloading Laravel Attributes documentation...\n\n";

$downloaded = 0;
$skipped = 0;
$failed = 0;

foreach ($files as $file) {
    $url = "$baseUrl/$file";
    $localPath = "$baseDir/$file";

    // Skip if already exists
    if (file_exists($localPath)) {
        echo "⏭️  Skipped: $file (already exists)\n";
        $skipped++;
        continue;
    }

    echo "⬇️  Downloading: $file ... ";

    $content = @file_get_contents($url);

    if ($content === false) {
        echo "❌ FAILED\n";
        $failed++;
        continue;
    }

    // Ensure directory exists
    $dir = dirname($localPath);
    if (!is_dir($dir)) {
        mkdir($dir, 0755, true);
    }

    file_put_contents($localPath, $content);
    echo "✅\n";
    $downloaded++;
}

echo "\n";
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
echo "✅ Downloaded: $downloaded files\n";
echo "⏭️  Skipped: $skipped files\n";
if ($failed > 0) {
    echo "❌ Failed: $failed files\n";
}
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
echo "\n✨ Done! All attributes documentation is now available offline.\n";
