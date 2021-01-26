<?php

namespace hotelsforhope\ArnLaravelApi;

use Illuminate\Support\ServiceProvider;

class ArnLaravelApiServiceProvider extends ServiceProvider
{
    /**
     * Perform post-registration booting of services.
     *
     * @return void
     */
    public function boot()
    {
        // $this->loadTranslationsFrom(__DIR__.'/../resources/lang', 'nateritter');
        // $this->loadViewsFrom(__DIR__.'/../resources/views', 'nateritter');
        // $this->loadMigrationsFrom(__DIR__.'/../database/migrations');
        // $this->loadRoutesFrom(__DIR__.'/routes.php');

        // Publishing is only necessary when using the CLI.
        if ($this->app->runningInConsole()) {
            $this->bootForConsole();
        }
    }

    /**
     * Register any package services.
     *
     * @return void
     */
    public function register()
    {
        $this->mergeConfigFrom(__DIR__.'/../config/arnlaravelapi.php', 'arnlaravelapi');

        // Register the service the package provides.
        $this->app->singleton('arnlaravelapi', function ($app) {
            return new ArnLaravelApi;
        });
    }

    /**
     * Get the services provided by the provider.
     *
     * @return array
     */
    public function provides()
    {
        return ['arnlaravelapi'];
    }

    /**
     * Console-specific booting.
     *
     * @return void
     */
    protected function bootForConsole()
    {
        // Publishing the configuration file.
        $this->publishes([
            __DIR__.'/../config/arnlaravelapi.php' => config_path('arnlaravelapi.php'),
        ], 'arnlaravelapi.config');

        // Publishing the views.
        /*$this->publishes([
            __DIR__.'/../resources/views' => base_path('resources/views/vendor/nateritter'),
        ], 'arnlaravelapi.views');*/

        // Publishing assets.
        /*$this->publishes([
            __DIR__.'/../resources/assets' => public_path('vendor/nateritter'),
        ], 'arnlaravelapi.views');*/

        // Publishing the translation files.
        /*$this->publishes([
            __DIR__.'/../resources/lang' => resource_path('lang/vendor/nateritter'),
        ], 'arnlaravelapi.views');*/

        // Registering package commands.
        // $this->commands([]);
    }
}
