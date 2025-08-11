<?php

namespace Joeystowe\MsGraphApi;

use Illuminate\Support\ServiceProvider;
use Joeystowe\MsGraphApi\Http\Middleware\MicrosoftAuthMiddleware;

class MsGraphApiServiceProvider extends ServiceProvider
{
    /**
     * Bootstrap the application services.
     */
    public function boot()
    {
        /*
         * Optional methods to load your package assets
         */
        $this->loadViewsFrom(__DIR__ . '/../resources/views', 'ms-graph-api');
        $this->loadRoutesFrom(__DIR__ . '/routes.php');
        $this->loadMigrationsFrom(__DIR__ . '/../database/migrations');


        \Illuminate\Support\Facades\Event::listen(function (\SocialiteProviders\Manager\SocialiteWasCalled $event) {
            $e = new \SocialiteProviders\Azure\AzureExtendSocialite();
            $e->handle($event);
        });

        $router = $this->app->make(\Illuminate\Routing\Router::class);
        $router->aliasMiddleware('ms-auth', MicrosoftAuthMiddleware::class);

        // Explicitly set the azure service config so we use the package's config
        config(['services.azure' => config('ms-graph-api.azure')]);

        // Register an OIDC guard and provider if the host app hasn't defined them
        $this->registerOidcGuardAndProvider();

        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__ . '/../config/config.php' => config_path('ms-graph-api.php'),
            ], 'ms-graph-api-config');

            // Publishing the views.
            /*$this->publishes([
                __DIR__.'/../resources/views' => resource_path('views/vendor/ms-graph-api'),
            ], 'views');*/

            // Publishing assets.
            $this->publishes([
                __DIR__ . '/../resources/assets' => public_path('vendor/ms-graph-api'),
            ], 'assets');

            // Optionally publish migrations so apps can customize schema
            $this->publishes([
                __DIR__ . '/../database/migrations' => database_path('migrations'),
            ], 'ms-graph-api-migrations');

            // Publishing the translation files.
            /*$this->publishes([
                __DIR__.'/../resources/lang' => resource_path('lang/vendor/ms-graph-api'),
            ], 'lang');*/

            // Publish a customizable OIDC user model stub
            $this->publishes([
                __DIR__ . '/../resources/stubs/OidcUser.php.stub' => app_path('Models/OidcUser.php'),
            ], 'ms-graph-api-model');

            // Registering package commands.
            // $this->commands([]);
        }
    }

    /**
     * Register the application services.
     */
    public function register()
    {
        // Automatically apply the package configuration
        $this->mergeConfigFrom(__DIR__ . '/../config/config.php', 'ms-graph-api');

        // Register the main class to use with the facade
        $this->app->singleton('ms-graph-current-user-api', function () {
            // Prefer token from the oidc guard if present
            $token = '';
            if (\Illuminate\Support\Facades\Auth::guard('oidc')->check()) {
                $token = (string)(\Illuminate\Support\Facades\Auth::guard('oidc')->user()->token ?? '');
            } else {
                $token = (string)(\Illuminate\Support\Facades\Auth::user()->token ?? '');
            }

            if (!empty($token) && !str_starts_with(strtolower($token), 'bearer ')) {
                $token = 'Bearer ' . $token;
            }

            return new MsGraphCurrentUserApi($token);
        });

        $this->app->singleton('logged-in-user', function () {
            return new \Joeystowe\MsGraphApi\LoggedInUser;
        });

        // Ensure guard/provider config are in place during registration as well
        $this->registerOidcGuardAndProvider();
    }

    protected function registerOidcGuardAndProvider(): void
    {
        // Add eloquent provider for OIDC users if not provided by host app
        if (!config()->has('auth.providers.oidc_users')) {
            $model = config('ms-graph-api.user_model', \Joeystowe\MsGraphApi\Models\OidcUser::class);
            config(['auth.providers.oidc_users' => [
                'driver' => 'eloquent',
                'model' => $model,
            ]]);
        }

        // Add a session guard using the OIDC provider if not present
        if (!config()->has('auth.guards.oidc')) {
            config(['auth.guards.oidc' => [
                'driver' => 'session',
                'provider' => 'oidc_users',
            ]]);
        }
    }
}
