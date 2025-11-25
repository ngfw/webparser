<?php

namespace Ngfw\Webparser\Facades;

use Illuminate\Support\Facades\Facade;
use Ngfw\Webparser\DomQuery;

/**
 * @method static DomQuery fromUrl(string $url, array $options = [])
 * @method static DomQuery fromHtml(string $html)
 *
 * @see \Ngfw\Webparser\DomQuery
 */
class WebParser extends Facade
{
    /**
     * Get the registered name of the component.
     *
     * @return string
     */
    protected static function getFacadeAccessor(): string
    {
        return DomQuery::class;
    }

    /**
     * Create a new WebParser instance from a URL.
     *
     * @param string $url
     * @param array $options Guzzle request options
     * @return DomQuery
     */
    public static function fromUrl(string $url, array $options = []): DomQuery
    {
        return DomQuery::fromUrl($url, $options);
    }

    /**
     * Create a new WebParser instance from HTML content.
     *
     * @param string $html
     * @return DomQuery
     */
    public static function fromHtml(string $html): DomQuery
    {
        return DomQuery::fromHtml($html);
    }
}
