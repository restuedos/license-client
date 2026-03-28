<?php

namespace Edzero\LicenseClient;

use Edzero\LicenseClient\Commands\LicenseActivateCommand;
use Edzero\LicenseClient\Http\Middleware\EnsureLicenseIsValid;
use Edzero\LicenseClient\Services\LicenseService;
use Illuminate\Routing\Router;
use Illuminate\Support\ServiceProvider;

class LicenseClientServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/license-client.php', 'license-client');

        $this->app->singleton(LicenseService::class);
    }

    public function boot(Router $router): void
    {
        $router->aliasMiddleware('license.valid', EnsureLicenseIsValid::class);

        $this->loadRoutesFrom(__DIR__.'/../routes/web.php');
        $this->loadViewsFrom(__DIR__.'/../resources/views', 'license-client');

        $this->publishes([
            __DIR__.'/../config/license-client.php' => config_path('license-client.php'),
        ], 'license-client-config');

        $this->publishes([
            __DIR__.'/../resources/views' => resource_path('views/vendor/license-client'),
        ], 'license-client-views');

        if ($this->app->runningInConsole()) {
            $this->commands([
                LicenseActivateCommand::class,
            ]);
        }
    }
}
