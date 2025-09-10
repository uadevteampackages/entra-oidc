<?php

namespace UaDevTeamPackages\EntraOidc\Tests;

use Laravel\Socialite\SocialiteServiceProvider;
use Orchestra\Testbench\TestCase as BaseTestCase;
use UaDevTeamPackages\EntraOidc\EntraOidcServiceProvider;

class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutExceptionHandling();
    }

    protected function getPackageProviders($app)
    {
        return [
            EntraOidcServiceProvider::class,
            SocialiteServiceProvider::class,
        ];
    }

    protected function defineEnvironment($app): void
    {
        $app['config']->set('app.key', 'base64:' . base64_encode(random_bytes(32)));
        $app['config']->set('app.debug', true);
        $app['config']->set('ms-graph-api.azure.tenant', 'common');
        $app['config']->set('ms-graph-api.azure.client_id', 'test-client');
        $app['config']->set('ms-graph-api.azure.client_secret', 'test-secret');

        $app['config']->set('database.default', 'testing');
        $app['config']->set('database.connections.testing', [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
        ]);
    }

    protected function defineDatabaseMigrations(): void
    {
        $this->loadMigrationsFrom(__DIR__ . '/../database/test-migrations');
        $this->artisan('migrate', ['--database' => 'testing'])->run();
    }

    protected function defineRoutes($router): void
    {
        $router->middleware('web')->group(function () use ($router) {
            $router->get('/protected', fn() => 'ok')->middleware('oidc-auth');
        });
    }
}
