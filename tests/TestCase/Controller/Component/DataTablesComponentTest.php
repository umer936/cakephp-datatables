<?php
declare(strict_types=1);

namespace DataTables\Test\TestCase\Controller\Component;

use Cake\Controller\ComponentRegistry;
use Cake\Controller\Controller;
use Cake\Datasource\ConnectionManager;
use Cake\Http\Exception\BadRequestException;
use Cake\Http\ServerRequest;
use Cake\ORM\Entity;
use Cake\ORM\Query\SelectQuery;
use Cake\ORM\Table;
use Cake\TestSuite\TestCase;
use Cake\View\JsonView;
use DataTables\Controller\Component\DataTablesComponent;
use InvalidArgumentException;

class DataTablesComponentTest extends TestCase
{
    protected DataTablesComponent $component;

    private array $columns = [
        ['data' => 'id', 'field' => 'Articles.id'],
        ['data' => 'display_title', 'field' => 'display_title'],
    ];

    protected function setUp(): void
    {
        parent::setUp();

        ConnectionManager::get('default')->execute('DROP TABLE IF EXISTS articles');
        ConnectionManager::get('default')->execute('CREATE TABLE articles (id INTEGER PRIMARY KEY AUTOINCREMENT, title VARCHAR(255) DEFAULT NULL)');
        ConnectionManager::get('default')->execute("INSERT INTO articles (title) VALUES ('First'), ('Second'), ('0')");
        $table = new class (['alias' => 'Articles', 'table' => 'articles', 'connection' => ConnectionManager::get('default')]) extends Table {
            public function findDatatables(
                SelectQuery $query,
                string $globalSearch = '',
                array $localSearch = [],
                array $customOrder = [],
            ): SelectQuery {
                if ($globalSearch !== '') {
                    $query->where(['Articles.title LIKE' => '%' . $globalSearch . '%']);
                }
                foreach ($localSearch as $field => $value) {
                    if ($field === 'display_title') {
                        $query->where(['Articles.title LIKE' => '%' . $value . '%']);
                    }
                }
                foreach ($customOrder as $field => $dir) {
                    if ($field === 'display_title') {
                        $query->orderBy(['Articles.title' => $dir]);
                    }
                }

                return $query->formatResults(fn($rows) => $rows->map(function (Entity $row): Entity {
                    $row = new class ($row->toArray()) extends Entity {
                        protected array $_virtual = ['display_title'];

                        protected array $_hidden = ['title'];

                        protected function _getDisplayTitle(): string
                        {
                            return $this->get('title') . '!';
                        }
                    };

                    return $row;
                }));
            }

            public function findComputed(SelectQuery $query, array $customOrder = []): SelectQuery
            {
                $query->select(['id', 'title', 'title_length' => $query->func()->length(['title' => 'identifier'])]);
                foreach ($customOrder as $field => $dir) {
                    if ($field === 'title_length') {
                        $query->orderBy(['title_length' => $dir]);
                    }
                }

                return $query;
            }
        };
        $this->getTableLocator()->set('Articles', $table);

        $request = new ServerRequest([
            'query' => [
                'draw' => '1',
                'start' => '0',
                'length' => '10',
                'order' => [
                    ['column' => '0', 'dir' => 'asc'],
                ],
                'search' => ['value' => ''],
                'columns' => [
                    ['search' => ['value' => '']],
                    ['search' => ['value' => '']],
                ],
            ],
        ]);

        $controller = new Controller($request);
        $registry = new ComponentRegistry($controller);
        $this->component = new DataTablesComponent($registry);
    }

    public function testFindRespectsTableNameAndBasicOrderConfiguration(): void
    {
        $query = $this->component->find('Articles', 'all', [], [
            ['data' => 'id', 'field' => 'Articles.id'],
            ['data' => 'title', 'field' => 'Articles.title'],
        ]);

        $this->assertInstanceOf(SelectQuery::class, $query);
        $this->assertSame(10, $this->component->getConfig('length'));
        $this->assertNotSame([], $this->component->getConfig('order'));
    }

    private function request(array $query): void
    {
        $controller = $this->component->getController();
        $params = array_replace($controller->getRequest()->getQueryParams(), $query);
        $controller->setRequest($controller->getRequest()->withQueryParams($params));
    }

    public function testVirtualOrderPaginationAndJsonResponse(): void
    {
        $this->request(['order' => [['column' => '1', 'dir' => 'desc']], 'length' => '1']);
        $this->component->setConfig('order', ['display_title' => 'ASC']);
        $query = $this->component->find('Articles', 'datatables', [
            'delegateSearch' => true, 'delegateOrder' => true,
        ], $this->columns);
        $controller = $this->component->getController();
        $controller->set('data', $query->all());
        $controller->viewBuilder()->setClassName(JsonView::class);
        $serialize = $controller->viewBuilder()->getOption('serialize');
        $controller->viewBuilder()->setOption('serialize', array_merge(['data'], $serialize));
        $json = json_decode($controller->createView()->render(), true, 512, JSON_THROW_ON_ERROR);
        $this->assertSame(3, $json['recordsTotal']);
        $this->assertSame(3, $json['recordsFiltered']);
        $this->assertSame(1, $json['draw']);
        $this->assertCount(1, $json['data']);
        $this->assertSame('Second!', $json['data'][0]['display_title']);
        $this->assertArrayNotHasKey('title', $json['data'][0]);
    }

    public function testVirtualGlobalSearchForZero(): void
    {
        $this->request(['search' => ['value' => '0'], 'order' => [], 'length' => '-1']);
        $query = $this->component->find('Articles', 'datatables', [
            'delegateSearch' => true, 'delegateOrder' => true,
        ], $this->columns);
        $this->assertSame('0!', $query->first()->get('display_title'));
        $this->assertSame(3, $this->component->getController()->viewBuilder()->getVar('recordsTotal'));
        $this->assertSame(1, $this->component->getController()->viewBuilder()->getVar('recordsFiltered'));
    }

    public function testVirtualColumnSearch(): void
    {
        $this->request(['order' => [], 'columns' => [[], ['search' => ['value' => 'First']]]]);
        $query = $this->component->find('Articles', 'datatables', ['delegateSearch' => true], $this->columns);
        $this->assertSame('First!', $query->first()->get('display_title'));
        $this->assertSame(1, $this->component->getController()->viewBuilder()->getVar('recordsFiltered'));
    }

    public function testVirtualSearchWithoutDelegationHasActionableError(): void
    {
        $this->request(['order' => [], 'columns' => [[], ['search' => ['value' => 'First']]]]);
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('delegateSearch');
        $this->component->find('Articles', 'all', [], $this->columns);
    }

    public function testInvalidOrderDirectionIsRejected(): void
    {
        $this->request(['order' => [['column' => '1', 'dir' => 'invalid']]]);
        $this->expectException(BadRequestException::class);
        $this->component->find('Articles', 'all', [], $this->columns);
    }

    public function testSqlComputedColumnCanBeSorted(): void
    {
        $this->request(['order' => [['column' => '0', 'dir' => 'desc']], 'length' => '1']);
        $query = $this->component->find('Articles', 'computed', ['delegateOrder' => true], [
            ['data' => 'title_length', 'field' => 'title_length'],
        ]);
        $this->assertSame('Second', $query->first()->get('title'));
        $this->assertSame(6, $query->first()->get('title_length'));
    }
}
