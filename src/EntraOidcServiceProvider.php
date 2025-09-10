<?php

namespace UaDevTeamPackages\EntraOidc;

use Illuminate\Support\ServiceProvider;
use UaDevTeamPackages\EntraOidc\Http\Middleware\MicrosoftAuthMiddleware;

class EntraOidcServiceProvider extends ServiceProvider
{
    /**
     * Bootstrap the application services.
     */
    public function boot(): void
    {
        /*
         * Optional methods to load your package assets
         */
        $this->loadViewsFrom(__DIR__ . '/../resources/views', 'entra-oidc');
        $this->loadRoutesFrom(__DIR__ . '/routes.php');
        $this->loadMigrationsFrom(__DIR__ . '/../database/migrations');


        \Illuminate\Support\Facades\Event::listen(function (\SocialiteProviders\Manager\SocialiteWasCalled $event) {
            $e = new \SocialiteProviders\Azure\AzureExtendSocialite();
            $e->handle($event);
        });

        $router = $this->app->make(\Illuminate\Routing\Router::class);
        $router->aliasMiddleware('oidc-auth', MicrosoftAuthMiddleware::class);

        // Explicitly set the azure service config so we use the package's config
        config(['services.azure' => config('entra-oidc.azure')]);

        // Register an OIDC guard and provider if the host app hasn't defined them
        $this->registerOidcGuardAndProvider();

        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__ . '/../resources/stubs/entra-oidc.php.stub' => config_path('entra-oidc.php'),
            ], 'entra-oidc-config');

            // Publishing the views.
            /*$this->publishes([
                __DIR__.'/../resources/views' => resource_path('views/vendor/ms-graph-api'),
            ], 'views');*/

            // Publishing assets.
            $this->publishes([
                __DIR__ . '/../resources/assets' => public_path('vendor/entra-oidc'),
            ], 'entra-oidc-assets');

            // Optionally publish migrations so apps can customize schema
            $this->publishes([
                __DIR__ . '/../database/test-migrations' => database_path('migrations'),
            ], 'entra-oidc-migrations');

            // Publishing the translation files.
            /*$this->publishes([
                __DIR__.'/../resources/lang' => resource_path('lang/vendor/ms-graph-api'),
            ], 'lang');*/

            // Publish a customizable OIDC user model stub
            $this->publishes([
                __DIR__ . '/../resources/stubs/OidcUser.php.stub' => app_path('Models/OidcUser.php'),
            ], 'entra-oidc-model');

            // One-tag publish: publish all package resources at once
            $this->publishes([
                __DIR__ . '/../resources/stubs/entra-oidc.php.stub' => config_path('entra-oidc.php'),
                __DIR__ . '/../resources/assets' => public_path('vendor/entra-oidc'),
                __DIR__ . '/../database/test-migrations' => database_path('migrations'),
                __DIR__ . '/../resources/stubs/OidcUser.php.stub' => app_path('Models/OidcUser.php'),
            ], 'entra-oidc-all');

            // Registering package commands.
            // $this->commands([]);
        }
    }

    /**
     * Register the application services.
     */
    public function register(): void
    {
        // Automatically apply the package configuration
        $this->mergeConfigFrom(__DIR__ . '/../config/config.php', 'entra-oidc');

        // Ensure guard/provider config are in place during registration as well
        $this->registerOidcGuardAndProvider();
    }

    protected function registerOidcGuardAndProvider(): void
    {
        // Add a session guard using the OIDC provider if not present
        if (!config()->has('auth.guards.oidc')) {
            config(['auth.guards.oidc' => [
                'driver' => 'session',
                'provider' => 'oidc_users',
            ]]);
        }

        // Add eloquent provider for OIDC users if not provided by host app
        if (!config()->has('auth.providers.oidc_users')) {
            $model = config('entra-oidc.user_model', \UaDevTeamPackages\EntraOidc\Models\OidcUser::class);
            config(['auth.providers.oidc_users' => [
                'driver' => 'eloquent',
                'model' => $model,
            ]]);
        }
    }
}
