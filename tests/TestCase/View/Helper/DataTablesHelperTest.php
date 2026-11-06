<?php
declare(strict_types=1);

namespace DataTables\Test\TestCase\View\Helper;

use Cake\View\View;
use DataTables\Lib\ColumnDefinitions;
use DataTables\View\Helper\DataTablesHelper;
use PHPUnit\Framework\TestCase;

class DataTablesHelperTest extends TestCase
{
    protected DataTablesHelper $helper;

    protected function setUp(): void
    {
        parent::setUp();

        $this->helper = new DataTablesHelper(new View());
    }

    public function testDrawCreatesVanillaDataTablesInitialization(): void
    {
        $result = $this->helper->draw('#users', [
            'columns' => [
                ['data' => 'id', 'field' => 'Users.id'],
                ['data' => 'name', 'field' => 'Users.name'],
            ],
            'language' => ['url' => 'https://cdn.datatables.net/plug-ins/1.13.6/i18n/en-GB.json'],
        ]);

        $this->assertStringContainsString('dt.initDataTables', $result);
        $this->assertStringContainsString('"#users"', $result);
        $this->assertStringContainsString('https:\/\/cdn.datatables.net\/plug-ins\/1.13.6\/i18n\/en-GB.json', $result);
    }

    public function testTranslateOrderUsesColumnIndexes(): void
    {
        $order = ['name' => 'asc'];
        $columns = [
            ['data' => 'id', 'field' => 'Users.id'],
            ['data' => 'name', 'field' => 'Users.name'],
        ];

        $translated = $this->helper->translateOrder($order, $columns);

        $this->assertSame([[1, 'asc']], $translated);
    }

    public function testModernOrderObjectsArePreserved(): void
    {
        foreach ([['name' => 'display_title', 'dir' => 'desc'], ['idx' => 0, 'dir' => 'asc']] as $order) {
            $original = $order;
            $this->assertSame($original, $this->helper->translateOrder($order, [['data' => 'display_title']]));
        }
    }

    public function testVirtualColumnCollectionIsNotMutated(): void
    {
        $columns = new ColumnDefinitions();
        $columns->add('display_title', 'display_title');
        $script = $this->helper->draw('#users', ['columns' => $columns]);
        $this->assertStringNotContainsString('"field"', $script);
        $this->assertStringContainsString('"data":"display_title"', $script);
        $this->assertSame('display_title', $columns[0]['field']);
    }

    public function testCallbacksAndScriptSafeOptions(): void
    {
        $script = $this->helper->draw('#users', [
            'init' => [$this->helper->callback('dt.init.fitIntoWindow', [10])],
            'columns' => [['data' => 'display_title', 'title' => '</script><script>alert(1)</script>']],
        ]);
        $this->assertStringContainsString('dt.init.fitIntoWindow.apply', $script);
        $this->assertStringNotContainsString('</script>', $script);
    }
}
