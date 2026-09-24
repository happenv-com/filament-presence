<?php

declare(strict_types=1);

namespace Happenv\FilamentPresence\Tests;

use ErrorException;
use Happenv\FilamentPresence\FilamentPresenceServiceProvider;
use Orchestra\Testbench\TestCase as Orchestra;

class TestCase extends Orchestra
{
    protected function setUp(): void
    {
        parent::setUp();

        // Laravel only logs deprecations. Fail the test when the package's OWN
        // code triggers one, so it is fixed before the next PHP / Laravel /
        // Filament release turns it into an error.
        $sourcePath = dirname(__DIR__) . DIRECTORY_SEPARATOR . 'src' . DIRECTORY_SEPARATOR;

        $previousHandler = set_error_handler(function (int $level, string $message, string $file = '', int $line = 0) use (&$previousHandler, $sourcePath): bool {
            if (in_array($level, [E_DEPRECATED, E_USER_DEPRECATED], true) && str_starts_with($file, $sourcePath)) {
                throw new ErrorException($message, 0, $level, $file, $line);
            }

            // Laravel's handler returns nothing once it has logged a deprecation;
            // only an explicit `false` hands the error back to PHP, which would
            // print it and make the test risky.
            return $previousHandler !== null && $previousHandler($level, $message, $file, $line) !== false;
        });
    }

    protected function tearDown(): void
    {
        restore_error_handler();

        parent::tearDown();
    }

    /** @return array<int, class-string> */
    protected function getPackageProviders($app): array
    {
        return [FilamentPresenceServiceProvider::class];
    }

    protected function defineEnvironment($app): void
    {
        $app['config']->set('database.default', 'testing');
        $app['config']->set('database.connections.testing', [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
        ]);
        $app['config']->set('filament-presence.table', 'presence_sessions');
    }

    protected function defineDatabaseMigrations(): void
    {
        $this->loadMigrationsFrom(__DIR__ . '/database/migrations');
    }
}
