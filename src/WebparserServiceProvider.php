<?php

namespace Ngfw\Webparser;

use Illuminate\Support\ServiceProvider;

class WebparserServiceProvider extends ServiceProvider
{
    /**
     * Bootstrap the application services.
     *
     * @return void
     */
    public function boot(): void
    {
        //
    }

    /**
     * Register the application services.
     *
     * @return void
     */
    public function register(): void
    {
        // Bind DomQuery class for dependency injection
        $this->app->bind(DomQuery::class, function ($app, array $params = []) {
            if (isset($params['document'])) {
                return new DomQuery($params['document'], $params['elements'] ?? null);
            }

            // Return a placeholder that can be initialized later via fromUrl() or fromHtml()
            return new class {
                public function fromUrl(string $url, array $options = []): DomQuery
                {
                    return DomQuery::fromUrl($url, $options);
                }

                public function fromHtml(string $html): DomQuery
                {
                    return DomQuery::fromHtml($html);
                }
            };
        });

        // Bind 'webparser' alias for the facade
        $this->app->alias(DomQuery::class, 'webparser');
    }
}
