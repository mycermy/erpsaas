<?php

namespace Tests;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

/**
 * Lightweight base for plugin tests.
 * Does not run the project's TestDatabaseSeeder — each plugin seeder
 * creates its own fixtures on a fresh, migrated database.
 */
abstract class PluginTestCase extends BaseTestCase
{
    use CreatesApplication;
    use RefreshDatabase;

    protected bool $seed = false;
}
