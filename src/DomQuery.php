<?php

namespace Ngfw\Webparser;

use ArrayIterator;
use DOMElement;
use DOMNodeList;
use DOMXPath;
use Exception;
use DOMDocument;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;
use Illuminate\Support\Collection;
use InvalidArgumentException;

class DomQuery
{
    /**
     * The DOMDocument instance.
     */
    protected DOMDocument $document;

    /**
     * The DOMXPath instance.
     */
    protected DOMXPath $xpath;

    /**
     * The current XPath query.
     */
    protected ?string $query = null;

    /**
     * The selected elements.
     *
     * @var ArrayIterator|DOMNodeList|array
     */
    protected ArrayIterator|DOMNodeList|array $elements;

    /**
     * The selection type for the current query.
     */
    protected string $selectionType = '*';

    /**
     * Create a new WebParser instance.
     *
     * @param DOMDocument $document
     * @param ArrayIterator|DOMNodeList|array|null $elements
     */
    public function __construct(DOMDocument $document, ArrayIterator|DOMNodeList|array|null $elements = null)
    {
        $this->document = $document;
        $this->xpath = new DOMXPath($document);
        $this->elements = $elements ?? $this->xpath->query('//*') ?? new ArrayIterator([]);
    }

    /**
     * Create a new WebParser instance from a URL using Guzzle.
     *
     * @param string $url
     * @param array $options Guzzle request options
     * @return self
     *
     * @throws Exception
     */
    public static function fromUrl(string $url, array $options = []): self
    {
        if (!filter_var($url, FILTER_VALIDATE_URL)) {
            throw new InvalidArgumentException("Invalid URL provided: $url");
        }

        $client = new Client();

        try {
            $response = $client->get($url, $options);
            $html = (string) $response->getBody();
        } catch (RequestException $e) {
            throw new Exception("Unable to load content from the URL: $url. Error: " . $e->getMessage());
        }

        return self::fromHtml($html);
    }

    /**
     * Create a new WebParser instance from HTML content.
     *
     * @param string $html
     * @return self
     */
    public static function fromHtml(string $html): self
    {
        $document = new DOMDocument();

        // Handle empty HTML gracefully
        if (trim($html) === '') {
            return new self($document, new ArrayIterator([]));
        }

        // Use internal errors to avoid suppressing with @ operator
        $previousUseErrors = libxml_use_internal_errors(true);
        $document->loadHTML($html, LIBXML_NOERROR | LIBXML_NOWARNING);
        libxml_clear_errors();
        libxml_use_internal_errors($previousUseErrors);

        return new self($document);
    }

    /**
     * Escape a string for use in XPath queries to prevent XPath injection.
     *
     * @param string $value The value to escape
     * @return string The escaped value safe for XPath
     */
    protected static function escapeXPathValue(string $value): string
    {
        // If the value contains no single quotes, wrap in single quotes
        if (strpos($value, "'") === false) {
            return "'" . $value . "'";
        }

        // If the value contains no double quotes, wrap in double quotes
        if (strpos($value, '"') === false) {
            return '"' . $value . '"';
        }

        // If the value contains both, use concat() to safely construct the string
        $parts = [];
        $current = '';

        for ($i = 0; $i < strlen($value); $i++) {
            $char = $value[$i];
            if ($char === "'") {
                if ($current !== '') {
                    $parts[] = "'" . $current . "'";
                    $current = '';
                }
                $parts[] = '"\'"';
            } else {
                $current .= $char;
            }
        }

        if ($current !== '') {
            $parts[] = "'" . $current . "'";
        }

        return 'concat(' . implode(',', $parts) . ')';
    }

    /**
     * Validate and sanitize a tag/element name for XPath.
     *
     * @param string $name
     * @return string
     * @throws InvalidArgumentException
     */
    protected static function sanitizeElementName(string $name): string
    {
        // Element names must start with a letter or underscore and contain only valid characters
        if (!preg_match('/^[a-zA-Z_][a-zA-Z0-9_\-]*$/', $name)) {
            throw new InvalidArgumentException("Invalid element name: $name");
        }
        return $name;
    }

    /**
     * Validate and sanitize an attribute name for XPath.
     *
     * @param string $name
     * @return string
     * @throws InvalidArgumentException
     */
    protected static function sanitizeAttributeName(string $name): string
    {
        // Attribute names follow similar rules to element names, but can include colons for namespaces
        if (!preg_match('/^[a-zA-Z_][a-zA-Z0-9_\-:]*$/', $name)) {
            throw new InvalidArgumentException("Invalid attribute name: $name");
        }
        return $name;
    }

    /**
     * Get the DOMDocument instance.
     *
     * @return DOMDocument
     */
    public function getDocument(): DOMDocument
    {
        return $this->document;
    }

    /**
     * Select elements by a CSS-like selector.
     *
     * @param string $selector
     * @return self
     */
    public function where(string $selector): self
    {
        if (str_starts_with($selector, '#')) {
            return $this->whereId(ltrim($selector, '#'));
        } elseif (str_starts_with($selector, '.')) {
            $classes = array_filter(explode(' ', $selector), fn($class) => !empty(trim($class)));
            $conditions = array_map(function ($class) {
                $className = trim($class, '.');
                $escapedClass = self::escapeXPathValue(' ' . $className . ' ');
                return "contains(concat(' ', normalize-space(@class), ' '), $escapedClass)";
            }, $classes);

            $xpathQuery = "//*[" . implode(' and ', $conditions) . "]";
            $result = $this->xpath->query($xpathQuery);
            $this->elements = $result !== false ? $result : new ArrayIterator([]);
            return $this;
        } else {
            return $this->whereTag($selector);
        }
    }

    /**
     * Select elements by their ID.
     *
     * @param string $id
     * @return self
     */
    public function whereId(string $id): self
    {
        $escapedId = self::escapeXPathValue($id);
        $result = $this->xpath->query("//*[@id=$escapedId]");
        $this->elements = $result !== false ? $result : new ArrayIterator([]);
        return $this;
    }

    /**
     * Select elements by their class.
     *
     * @param string $class
     * @return self
     */
    public function whereClass(string $class): self
    {
        $escapedClass = self::escapeXPathValue(' ' . $class . ' ');
        $result = $this->xpath->query("//*[contains(concat(' ', normalize-space(@class), ' '), $escapedClass)]");
        $this->elements = $result !== false ? $result : new ArrayIterator([]);
        return $this;
    }

    /**
     * Select elements by their tag name.
     *
     * @param string $tag
     * @return self
     */
    public function whereTag(string $tag): self
    {
        $sanitizedTag = self::sanitizeElementName($tag);
        $result = $this->xpath->query("//{$sanitizedTag}");
        $this->elements = $result !== false ? $result : new ArrayIterator([]);
        return $this;
    }

    /**
     * Select elements by a specific attribute and value.
     *
     * @param string $attribute
     * @param string $value
     * @return self
     */
    public function whereAttribute(string $attribute, string $value): self
    {
        $sanitizedAttr = self::sanitizeAttributeName($attribute);
        $escapedValue = self::escapeXPathValue($value);
        $result = $this->xpath->query("//*[@{$sanitizedAttr}=$escapedValue]");
        $this->elements = $result !== false ? $result : new ArrayIterator([]);
        return $this;
    }

    /**
     * Find elements within the current selection by their tag name.
     *
     * @param string $tag
     * @return self
     */
    public function find(string $tag): self
    {
        $sanitizedTag = self::sanitizeElementName($tag);
        $filteredElements = [];

        foreach ($this->elements as $element) {
            $nodes = $this->xpath->query(".//{$sanitizedTag}", $element);

            if ($nodes !== false && $nodes->length > 0) {
                foreach ($nodes as $node) {
                    $filteredElements[] = $node;
                }
            }
        }

        $this->elements = new ArrayIterator($filteredElements);
        return $this;
    }

    /**
     * Apply a selection type (e.g., 'text', 'tag', 'domelement') to the elements.
     *
     * @param string $select
     * @return self
     */
    public function select(string $select = '*'): self
    {
        $this->selectionType = $select;
        return $this;
    }

    /**
     * Apply the selection type to a given element.
     *
     * @param DOMElement $element
     * @return mixed
     */
    protected function applySelection(DOMElement $element): mixed
    {
        return match ($this->selectionType) {
            'text' => trim($element->textContent),
            'tag' => $element->nodeName,
            'domelement' => $element,
            default => $this->elementToArray($element),
        };
    }

    /**
     * Get the results of the current selection.
     *
     * @return Collection
     */
    public function get(): Collection
    {
        $results = [];
        foreach ($this->elements as $element) {
            if ($element instanceof DOMElement) {
                $results[] = $this->applySelection($element);
            }
        }

        return collect($results);
    }

    /**
     * Get all elements in the current selection.
     *
     * @return Collection
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
    public function first(): mixed
    {
        $elements = $this->iterableToArray($this->elements);
        $firstElement = reset($elements);

        if ($firstElement instanceof DOMElement) {
            return $this->applySelection($firstElement);
        }

        return null;
    }

    /**
     * Get the last element in the current selection.
     *
     * @return mixed
     */
    public function last(): mixed
    {
        $elements = $this->iterableToArray($this->elements);
        $lastElement = end($elements);

        if ($lastElement instanceof DOMElement) {
            return $this->applySelection($lastElement);
        }

        return null;
    }

    /**
     * Find the first element by tag name or throw an exception.
     *
     * @param string $tag
     * @return mixed
     *
     * @throws Exception
     */
    public function findOrFail(string $tag): mixed
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
     * @param string $attribute
     * @return mixed
     */
    public function latest(string $attribute = 'data-order'): mixed
    {
        return $this->orderByDesc($attribute)->first();
    }

    /**
     * Filter elements that contain the specified text.
     *
     * @param string $text
     * @return self
     */
    public function contains(string $text): self
    {
        $escapedText = self::escapeXPathValue($text);
        $result = $this->xpath->query("//*[contains(text(), $escapedText)]");
        $this->elements = $result !== false ? $result : new ArrayIterator([]);
        return $this;
    }

    /**
     * Find elements within a given element by their tag name.
     *
     * @param DOMElement $element
     * @param string $tag
     * @return self
     */
    public function findWithin(DOMElement $element, string $tag): self
    {
        $sanitizedTag = self::sanitizeElementName($tag);
        $subXpath = new DOMXPath($element->ownerDocument);
        $nodes = $subXpath->query(".//{$sanitizedTag}", $element);

        $elements = $nodes !== false ? iterator_to_array($nodes) : [];
        return new self($element->ownerDocument, $elements);
    }

    /**
     * Order elements by a specific attribute.
     *
     * @param string $attribute
     * @param string $direction
     * @return self
     */
    public function orderBy(string $attribute, string $direction = 'asc'): self
    {
        $sanitizedAttr = self::sanitizeAttributeName($attribute);
        $elements = $this->iterableToArray($this->elements);

        $sorted = collect($elements)
            ->sortBy(fn($element) => $element instanceof DOMElement ? $element->getAttribute($sanitizedAttr) : '', SORT_REGULAR, $direction === 'desc')
            ->values()
            ->all();

        $this->elements = $sorted;
        return $this;
    }

    /**
     * Order elements in descending order by a specific attribute.
     *
     * @param string $attribute
     * @return self
     */
    public function orderByDesc(string $attribute): self
    {
        return $this->orderBy($attribute, 'desc');
    }

    /**
     * Limit the number of results returned.
     *
     * @param int $count
     * @return Collection
     */
    public function limit(int $count): Collection
    {
        return $this->get()->take($count);
    }

    /**
     * Convert a DOMElement to an array representation.
     *
     * @param DOMElement $element
     * @return array
     */
    protected function elementToArray(DOMElement $element): array
    {
        $node = ['tag' => $element->nodeName, 'attributes' => []];

        foreach ($element->attributes as $attr) {
            $node['attributes'][$attr->nodeName] = $attr->nodeValue;
        }

        $node['children'] = [];
        foreach ($element->childNodes as $child) {
            if ($child instanceof DOMElement) {
                $node['children'][] = $this->elementToArray($child);
            } else {
                $text = trim($child->textContent);
                if ($text !== '') {
                    $node['children'][] = $text;
                }
            }
        }

        return $node;
    }

    /**
     * Extract the values of a specific attribute from the elements.
     *
     * @param string $attribute
     * @return Collection
     */
    public function pluck(string $attribute): Collection
    {
        return collect($this->iterableToArray($this->elements))
            ->map(function ($element) use ($attribute) {
                if ($element instanceof DOMElement) {
                    $value = $element->getAttribute($attribute);
                    return $value !== '' ? $value : null;
                }
                return null;
            })
            ->filter();
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
     * @param string $attribute
     * @return mixed
     */
    public function value(string $attribute): mixed
    {
        $firstElement = $this->first();
        return $firstElement['attributes'][$attribute] ?? null;
    }

    /**
     * Take a limited number of elements from the selection.
     *
     * @param int $limit
     * @return Collection
     */
    public function take(int $limit): Collection
    {
        return $this->get()->take($limit);
    }

    /**
     * Chunk the results of the selection and pass each chunk to a callback.
     *
     * @param int $size
     * @param callable $callback
     * @return self
     */
    public function chunk(int $size, callable $callback): self
    {
        $this->get()->chunk($size)->each($callback);
        return $this;
    }

    /**
     * Convert various iterable types to array.
     *
     * @param ArrayIterator|DOMNodeList|array $iterable
     * @return array
     */
    protected function iterableToArray(ArrayIterator|DOMNodeList|array $iterable): array
    {
        if (is_array($iterable)) {
            return $iterable;
        }

        return iterator_to_array($iterable);
    }
}
