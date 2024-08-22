# WebParser for Laravel

[![Latest Version on Packagist](https://img.shields.io/packagist/v/ngfw/webparser.svg?style=flat-square)](https://packagist.org/packages/ngfw/webparser)
[![Total Downloads](https://img.shields.io/packagist/dt/ngfw/webparser.svg?style=flat-square)](https://packagist.org/packages/ngfw/webparser)


**WebParser** is an ORM-style web scraping package for Laravel, designed to make DOM parsing simple and intuitive. With a fluent, chainable API, WebParser lets you effortlessly navigate, select, and extract data from HTML documents, bringing the ease of database querying to web scraping.

## Installation

You can install the package via composer:

```bash
composer require ngfw/webparser
```

## Usage
Here's how you can start using WebParser:

```php
use Ngfw\Webparser\Facades\WebParser;


// Initialize WebParser with a URL
$webParser = WebParser::fromUrl('https://laravel.com/');

// Extract the page title
$pageTitle = $webParser->select('text')->where('title')->first();
echo $pageTitle;

// Extract the content of the meta description
$metaDescription = $webParser->whereAttribute('name', 'description')->pluck('content')->first();
echo $metaDescription;

// Extract the first H1 tag's text content
$firstH1 = $webParser->select('text')->where('h1')->first();
echo $firstH1;

// Find an H2 element within a complex class structure and extract its text
$complexH2 = $webParser->where('.relative .max-w-screen-xl .w-full .mx-auto .xl:px-5')->find('h2')->select('text')->first();
echo $complexH2;

// Extract the text from all H2 tags on the page
$allH2Texts = $webParser->select('text')->where('h2')->get();
print_r($allH2Texts);

// Extract the sources (src) of all images on the page
$imageSources = $webParser->where('img')->pluck('src')->all();
print_r($imageSources);
```

### Testing
To run the tests, use:
```bash
vendor/bin/phpunit
```

### Changelog

Please see [CHANGELOG](CHANGELOG.md) for more information what has changed recently.

## Contributing

Please see [CONTRIBUTING](CONTRIBUTING.md) for details.

### Security

If you discover any security-related issues, please email the author instead of using the issue tracker.

## Credits

-   [Nick G](https://github.com/ngfw)
-   [All Contributors](../../contributors)

## License

The MIT License (MIT). Please see [License File](LICENSE.md) for more information.

## Hire Me

Looking for a Laravel expert to help with your next project? I'm available for freelance work! Feel free to contact me to discuss how I can assist you.