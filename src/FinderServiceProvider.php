<?php

namespace Feelri\Finder;

use Illuminate\Support\ServiceProvider;

class FinderServiceProvider extends ServiceProvider
{
    public function boot()
    {
        $this->loadRoutesFrom(__DIR__ . '/routes/web.php');
        $this->loadViewsFrom(__DIR__ . '/views', 'finder');

        $this->publishes([
            __DIR__ . '/config/finder.php' => config_path('finder.php'),
        ], 'finder-config');
    }

    public function register()
    {
        $this->mergeConfigFrom(
            __DIR__ . '/config/finder.php', 'finder'
        );
    }
}
