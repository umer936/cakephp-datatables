<?php
namespace DataTables\Controller\Component;

use Cake\Collection\Collection;
use Cake\Controller\Component;
use Cake\Core\Configure;
use Cake\Database\Driver\Postgres;
use Cake\Http\Exception\BadRequestException;
use Cake\ORM\Locator\LocatorAwareTrait;
use Cake\ORM\Query;
use Cake\ORM\Table;
use DataTables\Lib\ColumnDefinitions;

/**
 * DataTables component
 */
class DataTablesComponent extends Component
{
    use LocatorAwareTrait;

    protected array $_defaultConfig = [
        'start' => 0,
        'length' => 10,
        'order' => [],
        'prefixSearch' => true, // use "LIKE …%" instead of "LIKE %…%" conditions
        'conditionsOr' => [],  // table-wide search conditions
        'conditionsAnd' => [], // column search conditions
        'matching' => [],      // column search conditions for foreign tables
        'comparison' => [], // per-column comparison definition
    ];

    protected array $_defaultComparison = [
        'string' => 'LIKE',
        'text' => 'LIKE',
        'uuid' => 'LIKE',
        'integer' => '=',
        'biginteger' => '=',
        'float' => '=',
        'decimal' => '=',
        'boolean' => '=',
        'binary' => 'LIKE',
        'date' => 'LIKE',
        'datetime' => 'LIKE',
        'timestamp' => 'LIKE',
        'time' => 'LIKE',
        'json' => 'LIKE',
    ];

    protected array $_viewVars = [
        'recordsTotal' => 0,
        'recordsFiltered' => 0,
        'draw' => 0
    ];

    protected Table $_table;

    protected ColumnDefinitions $_columns;

    public function initialize(array $config): void
    {
        parent::initialize($config);

        // Set custom comparison operators if configured
        if (Configure::check('DataTables.ComparisonOperators')) {
            $operators = Configure::read('DataTables.ComparisonOperators');
            $this->_defaultComparison = array_merge($this->_defaultComparison, $operators);
        }

        // Setup column definitions
        $this->_columns = new ColumnDefinitions();
    }

    public function columns(): ColumnDefinitions
    {
        return $this->_columns;
    }

    /**
     * Process draw option (pass-through)
     */
    private function _draw(): void
    {
        $drawParam = $this->getController()->getRequest()->getQuery('draw');
        if ($drawParam) {
            $this->_viewVars['draw'] = (int)$drawParam;
        }
    }

    /**
     * Process query data of ajax request regarding order
     * Alters $options if delegateOrder is set
     * @param array $options Query options
     * @param ColumnDefinitions|array $columns Column definitions
     */
    private function _order(array &$options, &$columns): void
    {
        $queryParams = $this->getController()->getRequest()->getQueryParams();

        if (empty($queryParams['order'])) {
            return;
        }

        $order = $this->getConfig('order');
        /* extract custom ordering from request */
        foreach ($queryParams['order'] as $item) {
            // note: empty() does not work on objects
            if (!count($columns)) {
                throw new \InvalidArgumentException('Column ordering requested, but no column definitions provided.');
            }

            $dir = strtoupper($item['dir']);
            if (!in_array($dir, ['ASC', 'DESC'], true)) {
                throw new BadRequestException('Malformed order direction.');
            }

            $c = $columns[$item['column']] ?? null;
            if (!$c || !($c['orderable'] ?? true)) {
                throw new BadRequestException('Illegal column ordering.');
            }

            if (empty($c['field'])) {
                throw new \InvalidArgumentException('Column description misses field name.');
            }

            $order[$c['field']] = $dir;
        }

        if (!empty($options['delegateOrder'])) {
            $options['customOrder'] = $order;
        } else {
            $this->setConfig('order', $order);
        }

        unset($options['order']);
    }

    /**
     * Process query data of ajax request regarding filtering
     * Alters $options if delegateSearch is set
     * @param array $options Query options
     * @param array|ColumnDefinitions $columns Column definitions
     * @return bool True if additional filtering takes place
     */
    private function _filter(array &$options, $columns): bool
    {
        $queryParams = $this->getController()->getRequest()->getQueryParams();
        $haveFilters = false;
        $delegateSearch = $options['delegateSearch'] ?? false;

        // Handle global filter (search value)
        $globalSearch = $queryParams['search']['value'] ?? '';
        if ($globalSearch) {
            if (empty($columns)) {
                throw new \InvalidArgumentException('Filtering requested, but no column definitions provided.');
            }

            if ($delegateSearch) {
                $options['globalSearch'] = $globalSearch;
                $haveFilters = true;
            } else {
                foreach ($columns as $c) {
                    // searchable is true by default
                    if (!($c['searchable'] ?? true)) {
                        continue;
                    }

                    if (empty($c['field'])) {
                        throw new \InvalidArgumentException('Column description misses field name.');
                    }

                    $this->_addCondition($c['field'], $globalSearch, 'or');
                    $haveFilters = true;
                }
            }
        }

        // Handle local filters (column specific search)
        foreach ($queryParams['columns'] ?? [] as $index => $column) {
            $localSearch = $column['search']['value'] ?? '';
            if ($localSearch !== '') {
                // note: empty() does not work on objects
                if (!count($columns)) {
                    throw new \InvalidArgumentException('Filtering requested, but no column definitions provided.');
                }

                $c = $columns[$index] ?? null;
                // searchable is true by default
                if (!$c || !($c['searchable'] ?? true)) {
                    throw new BadRequestException('Illegal filter request.');
                }

                if (empty($c['field'])) {
                    throw new \InvalidArgumentException('Column description misses field name.');
                }

                if ($delegateSearch) {
                    $options['localSearch'][$c['field']] = $localSearch;
                } else {
                    $this->_addCondition($c['field'], $localSearch);
                }
                $haveFilters = true;
            }
        }

        return $haveFilters;
    }

    /**
     * Find data
     * @param string $tableName ORM table name
     * @param string $finder Finder name (as in Table::find())
     * @param array $options Finder options (as in Table::find())
     * @param array $columns Column definitions needed for filter/order operations
     * @return Query Query to be evaluated
     */
    public function find(string $tableName, string $finder = 'all', array $options = [], array $columns = []): Query
    {
        $delegateSearch = $options['delegateSearch'] ?? false;
        $columns = $columns ?: $this->_columns;

        // Get table object
        $this->_table = $this->getTableLocator()->get($tableName);

        // Process draw and ordering options
        $this->_draw();
        $this->_order($options, $columns);

        // Call table's finder without filters
        $data = $this->_table->find($finder, $options);

        // Get total count
        $this->_viewVars['recordsTotal'] = $data->count();

        // Process filter options
        $haveFilters = $this->_filter($options, $columns);

        // Apply filters
        if ($haveFilters) {
            if ($delegateSearch) {
                // call finder again to process filters (provided in $options)
                $data = $this->_table->find($finder, $options);
            } else {
                $data->where($this->getConfig('conditionsAnd'));
                foreach ($this->getConfig('matching') as $association => $where) {
                    $data->matching($association, fn(Query $q) => $q->where($where));
                }
                if (!empty($this->getConfig('conditionsOr'))) {
                    $data->where(['or' => $this->getConfig('conditionsOr')]);
                }
            }
        }

        // Get filtered count
        $this->_viewVars['recordsFiltered'] = $data->count();

        // Apply limit and offset
        if ($this->getConfig('length') > 0) {
            $data->limit($this->getConfig('length'))
                ->offset($this->getConfig('start'));
        }

        // Apply sorting
        $data->orderBy($this->getConfig('order'));

        // Set view vars
        $this->_setViewVars();

        return $data;
    }

    private function _setViewVars(): void
    {
        $controller = $this->getController();
        $_serialize = $controller->viewBuilder()->getVar('_serialize') ?? [];
        $_serialize = array_merge($_serialize, array_keys($this->_viewVars));
        $controller->set($this->_viewVars);
        $controller->set('_serialize', $_serialize);
    }

    private function _addCondition(string $column, string $value, string $type = 'and'): void
    {
        $table = $this->_table;
        if (($pos = strpos($column, '.')) !== false) {
            $table = $this->getTableLocator()->get(substr($column, 0, $pos));
            $column = substr($column, $pos + 1);
        }

        $comparison = trim($this->_getComparison($table, $column));
        $columnDesc = $table->getSchema()->getColumn($column);
        $columnType = $columnDesc['type'];
        $textCast = '';

        if (strpos(strtolower($comparison), 'like') !== false) {
            $value = $this->getConfig('prefixSearch') ? "{$value}%" : "%{$value}%";

            // Handle PostgreSQL text casting
            if ($this->_table->getConnection()->getDriver() instanceof Postgres) {
                if ($columnType !== 'string' && $columnType !== 'text') {
                    $textCast = "::text";
                }
            }
        }

        settype($value, $columnType);
        $condition = ["{$table->getAlias()}.{$column}{$textCast} {$comparison}" => $value];

        if ($type === 'or') {
            $this->setConfig('conditionsOr', $condition);
        } else {
            $this->setConfig('conditionsAnd', $condition);
        }
    }

    /**
     * Get comparison operator by entity and column name.
     *
     * @param Table $table ORM table
     * @param string $column Column name
     * @return string Comparison operator
     */
    protected function _getComparison(Table $table, string $column): string
    {
        $config = new Collection($this->getConfig('comparison'));
        $userConfig = $config->filter(fn($item, $key) => strtolower($key) === strtolower("{$table->getAlias()}.{$column}"));

        if (!$userConfig->isEmpty()) {
            return $userConfig->first();
        }

        $columnDesc = $table->getSchema()->getColumn($column);
        return $this->_defaultComparison[$columnDesc['type']] ?? '=';
    }
}
