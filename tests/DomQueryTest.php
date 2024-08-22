<?php

use Ngfw\Webparser\DomQuery;
use PHPUnit\Framework\TestCase;

class DomQueryTest extends TestCase
{
    protected $domQuery;

    protected function setUp(): void
    {
        $htmlContent = file_get_contents(__DIR__ . '/static_test_page.html');

        if ($htmlContent === false) {
            throw new Exception("Unable to load test content from static_test_page.html");
        }

        $document = new DOMDocument();
        @$document->loadHTML($htmlContent);
        $this->domQuery = new DomQuery($document);
    }

    public function testCanInstantiateWebParser()
    {
        $this->assertInstanceOf(DomQuery::class, $this->domQuery);
    }

    public function testCanSelectById()
    {
        $results = $this->domQuery->whereId('root')->select('*')->all();
        $this->assertInstanceOf(\Illuminate\Support\Collection::class, $results);
        $this->assertNotEmpty($results->all(), 'Expected elements with ID "root" not found.');
    }

    public function testSelectTextAfterWhereId()
    {
        $result = $this->domQuery->whereId('root')->select('text')->first();
        $this->assertIsString($result);
        $this->assertStringContainsString('Section 1', $result);
    }

    public function testSelectTextBeforeWhereId()
    {
        $result = $this->domQuery->select('text')->whereId('root')->first();
        $this->assertIsString($result);
        $this->assertStringContainsString('Section 1', $result);
    }

    public function testWhereWithId()
    {
        $results = $this->domQuery->where('#root')->select('*')->get();
        $this->assertInstanceOf(\Illuminate\Support\Collection::class, $results);
        $this->assertNotEmpty($results, 'Expected elements with ID "root" not found.');
        $this->assertEquals('main', $results->first()['tag']);
    }

    public function testWhereWithClass()
    {
        $results = $this->domQuery->where('.texts')->select('text')->get();
        $this->assertInstanceOf(\Illuminate\Support\Collection::class, $results);
        $this->assertNotEmpty($results, 'Expected elements with class "texts" not found.');
        $this->assertStringContainsString('Section 1', $results->first());
    }

    public function testCanSelectByMultipleClassesInComplexHtml()
{
    $results = $this->domQuery->where('.container .mx-auto .p-6 .bg-blue-500 .text-white')->select('text')->last();
    $this->assertEquals('© 2024 Test Company. All rights reserved.', $results);
}


    public function testWhereWithTag()
    {
        $results = $this->domQuery->where('h2')->select('text')->get();
        $this->assertInstanceOf(\Illuminate\Support\Collection::class, $results);
        $this->assertNotEmpty($results, 'Expected elements with tag "h2" not found.');
        $this->assertEquals('Section 1', $results->first());
    }

    public function testSelectTextAfterFind()
    {
        $result = $this->domQuery->find('h2')->select('text')->first();
        $this->assertIsString($result);
        $this->assertEquals('Section 1', $result);
    }

    public function testSelectTextBeforeFind()
    {
        $result = $this->domQuery->select('text')->find('h2')->first();
        $this->assertIsString($result);
        $this->assertEquals('Section 1', $result);
    }

    public function testCanSelectByClass()
    {
        $results = $this->domQuery->whereClass('texts')->select('text')->all();
        $this->assertInstanceOf(\Illuminate\Support\Collection::class, $results);
        $this->assertNotEmpty($results->all(), 'Expected elements with class "texts" not found.');

    }

    public function testCanOrderByAttribute()
    {
        $results = $this->domQuery->whereClass('texts')->orderBy('data-order')->select('*')->all();
        $this->assertInstanceOf(\Illuminate\Support\Collection::class, $results);
        $this->assertNotEmpty($results->all(), 'Expected elements to order by "data-order" not found.');
        $this->assertEquals('1', $results->first()['attributes']['data-order']);
    }

    public function testCanSelectByTag()
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

    public function testCanLimitResults()
    {
        $results = $this->domQuery->find('h2')->select('text')->all();
        $this->assertInstanceOf(\Illuminate\Support\Collection::class, $results);
        $this->assertNotEmpty($results->all(), 'Expected elements with tag "h2" not found.');
        $this->assertEquals('Section 1', $results->first());
    }

    public function testCanSelectTag()
    {
        $results = $this->domQuery->whereId('root')->select('tag')->all();
        $this->assertInstanceOf(\Illuminate\Support\Collection::class, $results);
        $this->assertEquals('main', $results->first());
    }

    public function testCanGetAllElements()
    {
        $results = $this->domQuery->whereClass('texts')->all();
        $this->assertInstanceOf(\Illuminate\Support\Collection::class, $results);
        $this->assertCount(2, $results->all());
    }

    public function testCanFindOrFail()
    {
        $result = $this->domQuery->findOrFail('h2');
        $this->assertIsArray($result);
        $this->assertEquals('h2', $result['tag']);
    }

    public function testCanGetFirstElement()
    {
        $result = $this->domQuery->whereClass('texts')->find('p')->first();
        $this->assertIsArray($result);
        $this->assertEquals('This is a paragraph in section 1.', $result['children'][0]);
    }

    public function testCanGetLastElement()
    {
        $result = $this->domQuery->whereClass('texts')->find('p')->last();
        $this->assertIsArray($result);
        $this->assertEquals('This is a paragraph in section 2.', $result['children'][0]);
    }

    public function testCanGetLatestElement()
    {
        $result = $this->domQuery->whereClass('texts')->latest('data-order');
        $this->assertIsArray($result);
        $this->assertEquals('2', $result['attributes']['data-order']);
    }

    public function testCanCheckForContains()
    {
        $results = $this->domQuery->contains('Section 1')->select('text')->all();
        $this->assertInstanceOf(\Illuminate\Support\Collection::class, $results);
        $this->assertNotEmpty($results->all());
        $this->assertStringContainsString('Section 1', $results->first());
    }

    public function testCanPluckAttribute()
    {
        $results = $this->domQuery->whereClass('texts')->pluck('data-order');
        $this->assertInstanceOf(\Illuminate\Support\Collection::class, $results);
        $this->assertEquals(['1', '2'], $results->all());
    }

    public function testCanCountElements()
    {
        $count = $this->domQuery->whereClass('texts')->count();
        $this->assertEquals(2, $count);
    }

    public function testExistsReturnsTrueIfElementsExist()
    {
        $exists = $this->domQuery->whereClass('texts')->exists();
        $this->assertTrue($exists);
    }

    public function testExistsReturnsFalseIfNoElementsExist()
    {
        $exists = $this->domQuery->whereClass('nonexistent')->exists();
        $this->assertFalse($exists);
    }

    public function testCanGetValueOfFirstElement()
    {
        $value = $this->domQuery->whereClass('texts')->value('data-order');
        $this->assertEquals('1', $value);
    }

    public function testCanTakeLimitedNumberOfElements()
    {
        $results = $this->domQuery->whereClass('texts')->take(1);
        $this->assertInstanceOf(\Illuminate\Support\Collection::class, $results);
        $this->assertCount(1, $results->all());
    }

    public function testCanOrderByDesc()
    {
        $results = $this->domQuery->whereClass('texts')->orderByDesc('data-order')->select('*')->all();
        $this->assertInstanceOf(\Illuminate\Support\Collection::class, $results);
        $this->assertEquals('2', $results->first()['attributes']['data-order']);
        $this->assertEquals('1', $results->last()['attributes']['data-order']);
    }

    public function testCanSelectButtonsByDataAction()
    {
        $results = $this->domQuery
            ->whereClass('other')
            ->find('button')
            ->pluck('data-action');

        $this->assertInstanceOf(\Illuminate\Support\Collection::class, $results);
        $this->assertEquals(['publish'], $results->all());
    }

    public function testCanSelectLinksInSidebar()
    {
        $results = $this->domQuery->whereTag('aside')->find('a')->select('text')->all();
        $this->assertInstanceOf(\Illuminate\Support\Collection::class, $results);
        $this->assertEquals(['Link 1', 'Link 2', 'Link 3'], $results->take(3)->all());
    }
}
