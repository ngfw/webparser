<?php
namespace Ngfw\Webparser\Facades;

use Illuminate\Support\Facades\Facade;
use Ngfw\Webparser\DomQuery;

class WebParser extends Facade
{
    protected static function getFacadeAccessor()
    {
        return 'webparser';
    }

    public static function fromUrl(string $url)
    {
        return app()->makeWith(DomQuery::class, [
            'document' => DomQuery::fromUrl($url)->getDocument(),
        ]);
    }

    public static function fromHtml(string $html)
    {
        return app()->makeWith(DomQuery::class, [
            'document' => DomQuery::fromHtml($html)->getDocument(),
        ]);
    }
}