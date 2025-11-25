<?php

use Ngfw\Webparser\DomQuery;
use PHPUnit\Framework\TestCase;

class DomQueryTest extends TestCase
{
    protected DomQuery $domQuery;

    protected function setUp(): void
    {
        $htmlContent = file_get_contents(__DIR__ . '/static_test_page.html');

        if ($htmlContent === false) {
            throw new Exception("Unable to load test content from static_test_page.html");
        }

        $this->domQuery = DomQuery::fromHtml($htmlContent);
    }

    public function testCanInstantiateWebParser(): void
    {
        $this->assertInstanceOf(DomQuery::class, $this->domQuery);
    }

    public function testCanSelectById(): void
    {
        $results = $this->domQuery->whereId('root')->select('*')->all();
        $this->assertInstanceOf(\Illuminate\Support\Collection::class, $results);
        $this->assertNotEmpty($results->all(), 'Expected elements with ID "root" not found.');
    }

    public function testSelectTextAfterWhereId(): void
    {
        $result = $this->domQuery->whereId('root')->select('text')->first();
        $this->assertIsString($result);
        $this->assertStringContainsString('Section 1', $result);
    }

    public function testSelectTextBeforeWhereId(): void
    {
        $result = $this->domQuery->select('text')->whereId('root')->first();
        $this->assertIsString($result);
        $this->assertStringContainsString('Section 1', $result);
    }

    public function testWhereWithId(): void
    {
        $results = $this->domQuery->where('#root')->select('*')->get();
        $this->assertInstanceOf(\Illuminate\Support\Collection::class, $results);
        $this->assertNotEmpty($results, 'Expected elements with ID "root" not found.');
        $this->assertEquals('main', $results->first()['tag']);
    }

    public function testWhereWithClass(): void
    {
        $results = $this->domQuery->where('.texts')->select('text')->get();
        $this->assertInstanceOf(\Illuminate\Support\Collection::class, $results);
        $this->assertNotEmpty($results, 'Expected elements with class "texts" not found.');
        $this->assertStringContainsString('Section 1', $results->first());
    }

    public function testCanSelectByMultipleClassesInComplexHtml(): void
    {
        $results = $this->domQuery->where('.container .mx-auto .p-6 .bg-blue-500 .text-white')->select('text')->last();
        $this->assertEquals('© 2024 Test Company. All rights reserved.', $results);
    }

    public function testWhereWithTag(): void
    {
        $results = $this->domQuery->where('h2')->select('text')->get();
        $this->assertInstanceOf(\Illuminate\Support\Collection::class, $results);
        $this->assertNotEmpty($results, 'Expected elements with tag "h2" not found.');
        $this->assertEquals('Section 1', $results->first());
    }

    public function testSelectTextAfterFind(): void
    {
        $result = $this->domQuery->find('h2')->select('text')->first();
        $this->assertIsString($result);
        $this->assertEquals('Section 1', $result);
    }

    public function testSelectTextBeforeFind(): void
    {
        $result = $this->domQuery->select('text')->find('h2')->first();
        $this->assertIsString($result);
        $this->assertEquals('Section 1', $result);
    }

    public function testCanSelectByClass(): void
    {
        $results = $this->domQuery->whereClass('texts')->select('text')->all();
        $this->assertInstanceOf(\Illuminate\Support\Collection::class, $results);
        $this->assertNotEmpty($results->all(), 'Expected elements with class "texts" not found.');
    }

    public function testCanOrderByAttribute(): void
    {
        $results = $this->domQuery->whereClass('texts')->orderBy('data-order')->select('*')->all();
        $this->assertInstanceOf(\Illuminate\Support\Collection::class, $results);
        $this->assertNotEmpty($results->all(), 'Expected elements to order by "data-order" not found.');
        $this->assertEquals('1', $results->first()['attributes']['data-order']);
    }

    public function testCanSelectByTag(): void
    {
        $results = $this->domQuery->find('h2')->select('text')->all();
        $this->assertInstanceOf(\Illuminate\Support\Collection::class, $results);

        $expectedTexts = [
            'Section 1',
            'Section 2',
            'Section 3',
            'Sidebar',
        ];
        foreach ($expectedTexts as $text) {
            $this->assertContains($text, $results->all());
        }
    }

    public function testCanLimitResults(): void
    {
        $results = $this->domQuery->find('h2')->select('text')->all();
        $this->assertInstanceOf(\Illuminate\Support\Collection::class, $results);
        $this->assertNotEmpty($results->all(), 'Expected elements with tag "h2" not found.');
        $this->assertEquals('Section 1', $results->first());
    }

    public function testCanSelectTag(): void
    {
        $results = $this->domQuery->whereId('root')->select('tag')->all();
        $this->assertInstanceOf(\Illuminate\Support\Collection::class, $results);
        $this->assertEquals('main', $results->first());
    }

    public function testCanGetAllElements(): void
    {
        $results = $this->domQuery->whereClass('texts')->all();
        $this->assertInstanceOf(\Illuminate\Support\Collection::class, $results);
        $this->assertCount(2, $results->all());
    }

    public function testCanFindOrFail(): void
    {
        $result = $this->domQuery->findOrFail('h2');
        $this->assertIsArray($result);
        $this->assertEquals('h2', $result['tag']);
    }

    public function testCanGetFirstElement(): void
    {
        $result = $this->domQuery->whereClass('texts')->find('p')->first();
        $this->assertIsArray($result);
        $this->assertEquals('This is a paragraph in section 1.', $result['children'][0]);
    }

    public function testCanGetLastElement(): void
    {
        $result = $this->domQuery->whereClass('texts')->find('p')->last();
        $this->assertIsArray($result);
        $this->assertEquals('This is a paragraph in section 2.', $result['children'][0]);
    }

    public function testCanGetLatestElement(): void
    {
        $result = $this->domQuery->whereClass('texts')->latest('data-order');
        $this->assertIsArray($result);
        $this->assertEquals('2', $result['attributes']['data-order']);
    }

    public function testCanCheckForContains(): void
    {
        $results = $this->domQuery->contains('Section 1')->select('text')->all();
        $this->assertInstanceOf(\Illuminate\Support\Collection::class, $results);
        $this->assertNotEmpty($results->all());
        $this->assertStringContainsString('Section 1', $results->first());
    }

    public function testCanPluckAttribute(): void
    {
        $results = $this->domQuery->whereClass('texts')->pluck('data-order');
        $this->assertInstanceOf(\Illuminate\Support\Collection::class, $results);
        $this->assertEquals(['1', '2'], $results->all());
    }

    public function testCanCountElements(): void
    {
        $count = $this->domQuery->whereClass('texts')->count();
        $this->assertEquals(2, $count);
    }

    public function testExistsReturnsTrueIfElementsExist(): void
    {
        $exists = $this->domQuery->whereClass('texts')->exists();
        $this->assertTrue($exists);
    }

    public function testExistsReturnsFalseIfNoElementsExist(): void
    {
        $exists = $this->domQuery->whereClass('nonexistent')->exists();
        $this->assertFalse($exists);
    }

    public function testCanGetValueOfFirstElement(): void
    {
        $value = $this->domQuery->whereClass('texts')->value('data-order');
        $this->assertEquals('1', $value);
    }

    public function testCanTakeLimitedNumberOfElements(): void
    {
        $results = $this->domQuery->whereClass('texts')->take(1);
        $this->assertInstanceOf(\Illuminate\Support\Collection::class, $results);
        $this->assertCount(1, $results->all());
    }

    public function testCanOrderByDesc(): void
    {
        $results = $this->domQuery->whereClass('texts')->orderByDesc('data-order')->select('*')->all();
        $this->assertInstanceOf(\Illuminate\Support\Collection::class, $results);
        $this->assertEquals('2', $results->first()['attributes']['data-order']);
        $this->assertEquals('1', $results->last()['attributes']['data-order']);
    }

    public function testCanSelectButtonsByDataAction(): void
    {
        $results = $this->domQuery
            ->whereClass('other')
            ->find('button')
            ->pluck('data-action');

        $this->assertInstanceOf(\Illuminate\Support\Collection::class, $results);
        $this->assertEquals(['publish'], $results->all());
    }

    public function testCanSelectLinksInSidebar(): void
    {
        $results = $this->domQuery->whereTag('aside')->find('a')->select('text')->all();
        $this->assertInstanceOf(\Illuminate\Support\Collection::class, $results);
        $this->assertEquals(['Link 1', 'Link 2', 'Link 3'], $results->take(3)->all());
    }

    // ===== SECURITY TESTS =====

    public function testXPathInjectionPreventionInWhereId(): void
    {
        // Attempt XPath injection with special characters
        $maliciousId = "' or '1'='1";
        $results = $this->domQuery->whereId($maliciousId)->get();

        // Should return empty collection, not all elements
        $this->assertEmpty($results->all());
    }

    public function testXPathInjectionPreventionInWhereClass(): void
    {
        // Attempt XPath injection with special characters
        $maliciousClass = "') or true() or ('";
        $results = $this->domQuery->whereClass($maliciousClass)->get();

        // Should return empty collection, not all elements
        $this->assertEmpty($results->all());
    }

    public function testXPathInjectionPreventionInWhereAttribute(): void
    {
        // Attempt XPath injection
        $maliciousValue = "' or '1'='1";
        $results = $this->domQuery->whereAttribute('class', $maliciousValue)->get();

        // Should return empty collection
        $this->assertEmpty($results->all());
    }

    public function testXPathInjectionPreventionInContains(): void
    {
        // Attempt XPath injection in contains
        $maliciousText = "') or true() or ('";
        $results = $this->domQuery->contains($maliciousText)->get();

        // Should return empty collection
        $this->assertEmpty($results->all());
    }

    public function testHandlesSpecialCharactersInValues(): void
    {
        // Test with single quotes
        $html = "<div id=\"test's-id\">Content</div>";
        $parser = DomQuery::fromHtml($html);
        $results = $parser->whereId("test's-id")->get();
        $this->assertCount(1, $results);

        // Test with double quotes
        $html = '<div id="test&quot;id">Content</div>';
        $parser = DomQuery::fromHtml($html);
        $results = $parser->whereId('test"id')->get();
        $this->assertCount(1, $results);
    }

    public function testHandlesMixedQuotesInValues(): void
    {
        // Test with both single and double quotes
        $html = "<div class=\"test'and&quot;class\">Content</div>";
        $parser = DomQuery::fromHtml($html);
        $results = $parser->whereClass("test'and\"class")->get();
        $this->assertCount(1, $results);
    }

    // ===== VALIDATION TESTS =====

    public function testInvalidElementNameThrowsException(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid element name');

        $this->domQuery->find('123invalid');
    }

    public function testInvalidAttributeNameThrowsException(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid attribute name');

        $this->domQuery->whereAttribute('123-invalid', 'value');
    }

    public function testInvalidTagNameInWhereTagThrowsException(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid element name');

        $this->domQuery->whereTag('<script>');
    }

    public function testInvalidUrlThrowsException(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid URL');

        DomQuery::fromUrl('not-a-valid-url');
    }

    // ===== EDGE CASE TESTS =====

    public function testEmptyHtmlReturnsEmptyResults(): void
    {
        $parser = DomQuery::fromHtml('');
        $results = $parser->find('div')->get();
        $this->assertEmpty($results->all());
    }

    public function testMalformedHtmlIsHandledGracefully(): void
    {
        $malformedHtml = '<div><p>Unclosed paragraph<div>Nested incorrectly</div>';
        $parser = DomQuery::fromHtml($malformedHtml);

        // Should not throw, and should still find elements
        $results = $parser->find('div')->get();
        $this->assertNotEmpty($results->all());
    }

    public function testFindOrFailThrowsExceptionForMissingElement(): void
    {
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage("Element with tag 'nonexistent' not found.");

        $this->domQuery->whereId('root')->findOrFail('nonexistent');
    }

    public function testFirstReturnsNullForEmptySelection(): void
    {
        $result = $this->domQuery->whereClass('nonexistent')->first();
        $this->assertNull($result);
    }

    public function testLastReturnsNullForEmptySelection(): void
    {
        $result = $this->domQuery->whereClass('nonexistent')->last();
        $this->assertNull($result);
    }

    public function testCountReturnsZeroForEmptySelection(): void
    {
        $count = $this->domQuery->whereClass('nonexistent')->count();
        $this->assertEquals(0, $count);
    }

    public function testValueReturnsNullForMissingAttribute(): void
    {
        $value = $this->domQuery->whereClass('texts')->value('nonexistent-attribute');
        $this->assertNull($value);
    }

    public function testChunkProcessesElementsCorrectly(): void
    {
        $chunks = [];
        $this->domQuery->whereClass('texts')->chunk(1, function ($chunk) use (&$chunks) {
            $chunks[] = $chunk->count();
        });

        $this->assertCount(2, $chunks);
        $this->assertEquals([1, 1], $chunks);
    }

    public function testSelectDomElementReturnsDomElements(): void
    {
        $results = $this->domQuery->whereId('root')->select('domelement')->get();
        $this->assertNotEmpty($results->all());
        $this->assertInstanceOf(\DOMElement::class, $results->first());
    }

    public function testCanChainMultipleMethods(): void
    {
        $results = $this->domQuery
            ->whereId('root')
            ->find('section')
            ->whereClass('texts')
            ->orderBy('data-order')
            ->select('text')
            ->take(1);

        $this->assertInstanceOf(\Illuminate\Support\Collection::class, $results);
        $this->assertCount(1, $results);
    }

    public function testGetDocumentReturnsDomDocument(): void
    {
        $document = $this->domQuery->getDocument();
        $this->assertInstanceOf(\DOMDocument::class, $document);
    }

    public function testLimitMethodWorksCorrectly(): void
    {
        $results = $this->domQuery->find('h2')->limit(2);
        $this->assertCount(2, $results);
    }

    public function testWhereAttributeFindsElements(): void
    {
        $results = $this->domQuery->whereAttribute('data-order', '1')->get();
        $this->assertNotEmpty($results->all());
    }

    // ===== TYPE CONSISTENCY TESTS =====

    public function testAllMethodReturnsCollection(): void
    {
        $results = $this->domQuery->whereClass('texts')->all();
        $this->assertInstanceOf(\Illuminate\Support\Collection::class, $results);
    }

    public function testGetMethodReturnsCollection(): void
    {
        $results = $this->domQuery->whereClass('texts')->get();
        $this->assertInstanceOf(\Illuminate\Support\Collection::class, $results);
    }

    public function testPluckMethodReturnsCollection(): void
    {
        $results = $this->domQuery->whereClass('texts')->pluck('class');
        $this->assertInstanceOf(\Illuminate\Support\Collection::class, $results);
    }

    public function testTakeMethodReturnsCollection(): void
    {
        $results = $this->domQuery->whereClass('texts')->take(1);
        $this->assertInstanceOf(\Illuminate\Support\Collection::class, $results);
    }
}
