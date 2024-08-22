<?php

namespace Ngfw\Webparser;

use Illuminate\Support\ServiceProvider;

class WebparserServiceProvider extends ServiceProvider
{
    /**
     * Bootstrap the application services.
     */
    public function boot()
    {
    }

    /**
     * Register the application services.
     */
    public function register()
    {
        // Register the main class to use with the facade
        $this->app->bind('webparser', function ($app, $params) {
            $source = $params['source'] ?? null;
            $elements = $params['elements'] ?? null;
            
            if ($source === null) {
                throw new \InvalidArgumentException('The WebParser requires a $source argument.');
            }

            return new DomQuery($source, $elements);
        });
    }
}
