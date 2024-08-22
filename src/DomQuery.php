<?php

namespace Ngfw\Webparser;

use DOMXPath;
use Exception;
use DOMDocument;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;
use Illuminate\Support\Collection;

class DomQuery
{
    /**
     * The DOMDocument instance.
     *
     * @var \DOMDocument
     */
    protected $document;

    /**
     * The DOMXPath instance.
     *
     * @var \DOMXPath
     */
    protected $xpath;

    /**
     * The current XPath query.
     *
     * @var string|null
     */
    protected $query;

    /**
     * The selected elements.
     *
     * @var \ArrayIterator
     */
    protected $elements;

    /**
     * The selection type for the current query.
     *
     * @var string
     */
    protected $selectionType;

    /**
     * Create a new WebParser instance.
     *
     * @param  \DOMDocument  $document
     * @param  mixed  $elements
     * @return void
     */
    public function __construct(DOMDocument $document, $elements = null)
    {
        $this->document = $document;
        $this->xpath = new DOMXPath($document);
        $this->elements = $elements ?? $this->xpath->query('//*');
    }

    /**
     * Create a new WebParser instance from a URL using Guzzle.
     *
     * @param  string  $url
     * @return self
     *
     * @throws \Exception
     */
    public static function fromUrl(string $url): self
    {
        $client = new Client();

        try {
            $response = $client->get($url);
            $html = (string) $response->getBody();
        } catch (RequestException $e) {
            throw new Exception("Unable to load content from the URL: $url. Error: " . $e->getMessage());
        }

        $document = new DOMDocument();
        @$document->loadHTML($html);

        return new self($document);
    }

    /**
     * Create a new WebParser instance from HTML content.
     *
     * @param  string  $html
     * @return self
     */
    public static function fromHtml(string $html): self
    {
        $document = new DOMDocument();
        @$document->loadHTML($html);
        return new self($document);
    }

    /**
     * Get the DOMDocument instance.
     *
     * @return \DOMDocument
     */
    public function getDocument(): DOMDocument
    {
        return $this->document;
    }

    /**
     * Select elements by a CSS-like selector.
     *
     * @param  string  $selector
     * @return self
     */
    public function where($selector): self
    {
        if (str_starts_with($selector, '#')) {
            return $this->whereId(ltrim($selector, '#'));
        } elseif (str_starts_with($selector, '.')) {
            $classes = array_filter(explode(' ', $selector), fn($class) => !empty($class));
            $xpathQuery = "//*[" . implode(' and ', array_map(fn($class) => "contains(concat(' ', normalize-space(@class), ' '), ' " . trim($class, '.') . " ')", $classes)) . "]";
            $this->elements = $this->xpath->query($xpathQuery);
            return $this;
        } else {
            return $this->whereTag($selector);
        }
    }


    /**
     * Select elements by their ID.
     *
     * @param  string  $id
     * @return self
     */
    public function whereId($id): self
    {
        $this->elements = $this->xpath->query("//*[@id='$id']");
        return $this;
    }

    /**
     * Select elements by their class.
     *
     * @param  string  $class
     * @return self
     */
    public function whereClass($class): self
    {
        $this->elements = $this->xpath->query("//*[contains(concat(' ', normalize-space(@class), ' '), ' $class ')]");
        return $this;
    }

    /**
     * Select elements by their tag name.
     *
     * @param  string  $tag
     * @return self
     */
    public function whereTag($tag): self
    {
        $this->elements = $this->xpath->query("//{$tag}");
        return $this;
    }

    /**
     * Select elements by a specific attribute and value.
     *
     * @param  string  $attribute
     * @param  string  $value
     * @return self
     */
    public function whereAttribute($attribute, $value): self
    {
        $this->elements = $this->xpath->query("//*[@$attribute='$value']");
        return $this;
    }

    /**
     * Find elements within the current selection by their tag name.
     *
     * @param  string  $tag
     * @return self
     */
    public function find($tag): self
    {
        $filteredElements = [];

        foreach ($this->elements as $element) {
            $nodes = $this->xpath->query(".//{$tag}", $element);

            if ($nodes !== false && $nodes->length > 0) {
                foreach ($nodes as $node) {
                    $filteredElements[] = $node;
                }
            }
        }

        $this->elements = new \ArrayIterator($filteredElements);
        return $this;
    }

    /**
     * Apply a selection type (e.g., 'text', 'tag', 'domelement') to the elements.
     *
     * @param  string  $select
     * @return self
     */
    public function select($select = '*'): self
    {
        $this->selectionType = $select;
        return $this;
    }

    /**
     * Apply the selection type to a given element.
     *
     * @param  \DOMElement  $element
     * @return mixed
     */
    protected function applySelection($element)
    {
        if ($this->selectionType === 'text') {
            return trim($element->textContent);
        } elseif ($this->selectionType === 'tag') {
            return $element->nodeName;
        } elseif ($this->selectionType === 'domelement') {
            return $element;
        } else {
            return $this->elementToArray($element);
        }
    }

    /**
     * Get the results of the current selection.
     *
     * @return \Illuminate\Support\Collection
     */
    public function get(): Collection
    {
        $results = [];
        foreach ($this->elements as $element) {
            $results[] = $this->applySelection($element);
        }

        return collect($results);
    }

    /**
     * Get all elements in the current selection.
     *
     * @return \Illuminate\Support\Collection
     */
    public function all(): Collection
    {
        return $this->get();
    }

    /**
     * Get the first element in the current selection.
     *
     * @return mixed
     */
    public function first()
    {
        $firstElement = collect(iterator_to_array($this->elements))->first();
        return $firstElement ? $this->applySelection($firstElement) : null;
    }

    /**
     * Get the last element in the current selection.
     *
     * @return mixed
     */
    public function last()
    {
        $lastElement = collect(iterator_to_array($this->elements))->last();
        return $lastElement ? $this->applySelection($lastElement) : null;
    }

    /**
     * Find the first element by tag name or throw an exception.
     *
     * @param  string  $tag
     * @return mixed
     *
     * @throws \Exception
     */
    public function findOrFail($tag)
    {
        $results = $this->find($tag)->get();
        if ($results->isEmpty()) {
            throw new Exception("Element with tag '$tag' not found.");
        }
        return $results->first();
    }

    /**
     * Get the latest element based on a specific attribute.
     *
     * @param  string  $attribute
     * @return mixed
     */
    public function latest($attribute = 'data-order')
    {
        return $this->orderByDesc($attribute)->first();
    }

    /**
     * Filter elements that contain the specified text.
     *
     * @param  string  $text
     * @return self
     */
    public function contains($text): self
    {
        $this->elements = $this->xpath->query("//*[contains(text(), '$text')]");
        return $this;
    }

    /**
     * Find elements within a given element by their tag name.
     *
     * @param  \DOMElement  $element
     * @param  string  $tag
     * @return self
     */
    public function findWithin($element, $tag): self
    {
        $subXpath = new DOMXPath($element->ownerDocument);
        $nodes = $subXpath->query(".//{$tag}", $element);

        return new self($element->ownerDocument, iterator_to_array($nodes));
    }

    /**
     * Order elements by a specific attribute.
     *
     * @param  string  $attribute
     * @param  string  $direction
     * @return self
     */
    public function orderBy($attribute, $direction = 'asc'): self
    {
        $this->elements = collect(iterator_to_array($this->elements))
            ->sortBy(fn($element) => $element->getAttribute($attribute), SORT_REGULAR, $direction === 'desc')
            ->all();
        return $this;
    }

    /**
     * Order elements in descending order by a specific attribute.
     *
     * @param  string  $attribute
     * @return self
     */
    public function orderByDesc($attribute): self
    {
        return $this->orderBy($attribute, 'desc');
    }

    /**
     * Limit the number of results returned.
     *
     * @param  int  $count
     * @return \Illuminate\Support\Collection
     */
    public function limit(int $count): Collection
    {
        return $this->get()->take($count);
    }

    /**
     * Convert a DOMElement to an array representation.
     *
     * @param  \DOMElement  $element
     * @return array
     */
    protected function elementToArray($element): array
    {
        $node = ['tag' => $element->nodeName, 'attributes' => []];

        foreach ($element->attributes as $attr) {
            $node['attributes'][$attr->nodeName] = $attr->nodeValue;
        }

        $node['children'] = [];
        foreach ($element->childNodes as $child) {
            $node['children'][] = $child instanceof \DOMElement ? $this->elementToArray($child) : trim($child->textContent);
        }

        return $node;
    }

    /**
     * Extract the values of a specific attribute from the elements.
     *
     * @param  string  $attribute
     * @return \Illuminate\Support\Collection
     */
    public function pluck($attribute): Collection
    {
        return collect(iterator_to_array($this->elements))
            ->map(function ($element) use ($attribute) {
                if ($element instanceof \DOMElement) {
                    return $element->getAttribute($attribute) ?: null;
                }
                return null;
            })
            ->filter(); // Filter out null values if needed
    }

    /**
     * Count the number of elements in the current selection.
     *
     * @return int
     */
    public function count(): int
    {
        return $this->get()->count();
    }

    /**
     * Determine if any elements exist in the current selection.
     *
     * @return bool
     */
    public function exists(): bool
    {
        return $this->get()->isNotEmpty();
    }

    /**
     * Get the value of a specific attribute from the first element.
     *
     * @param  string  $attribute
     * @return mixed
     */
    public function value($attribute)
    {
        $firstElement = $this->first();
        return $firstElement['attributes'][$attribute] ?? null;
    }

    /**
     * Take a limited number of elements from the selection.
     *
     * @param  int  $limit
     * @return \Illuminate\Support\Collection
     */
    public function take(int $limit): Collection
    {
        return $this->get()->take($limit);
    }

    /**
     * Chunk the results of the selection and pass each chunk to a callback.
     *
     * @param  int  $size
     * @param  callable  $callback
     * @return self
     */
    public function chunk(int $size, callable $callback): self
    {
        $this->get()->chunk($size)->each($callback);
        return $this;
    }
}
