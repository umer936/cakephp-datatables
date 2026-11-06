<?php
declare(strict_types=1);

namespace DataTables\View\Helper;

use Cake\View\Helper;
use DataTables\Lib\CallbackFunction;
use JsonSerializable;
use Traversable;
use function Cake\I18n\__d;

/**
 * DataTables Helper
 * Provides utilities for integrating DataTables with CakePHP.
 */
class DataTablesHelper extends Helper
{
    public array $helpers = ['Html'];

    protected array $_defaultConfig = [
        'searching' => true,
        'processing' => true,
        'serverSide' => true,
        'deferRender' => true,
    ];

    /**
     * Initialize the helper and set default language configuration.
     *
     * @param array $config Configuration passed during initialization.
     */
    public function initialize(array $config): void
    {
        if (empty($this->getConfig('language'))) {
            $this->setConfig('language', $this->getDefaultLanguageConfig());
        }
    }

    /**
     * Get the default language configuration for DataTables.
     *
     * @return array
     */
    protected function getDefaultLanguageConfig(): array
    {
        return [
            'emptyTable' => __d('data_tables', 'No data available in table'),
            'info' => __d('data_tables', 'Showing _START_ to _END_ of _TOTAL_ entries'),
            'infoEmpty' => __d('data_tables', 'No entries to show'),
            'infoFiltered' => __d('data_tables', '(filtered from _MAX_ total entries)'),
            'lengthMenu' => __d('data_tables', 'Show _MENU_ entries'),
            'processing' => __d('data_tables', 'Processing...'),
            'search' => __d('data_tables', 'Search:'),
            'zeroRecords' => __d('data_tables', 'No matching records found'),
            'paginate' => [
                'first' => __d('data_tables', 'First'),
                'last' => __d('data_tables', 'Last'),
                'next' => __d('data_tables', 'Next'),
                'previous' => __d('data_tables', 'Previous'),
            ],
            'aria' => [
                'orderable' => __d('data_tables', ': activate to sort'),
                'orderableReverse' => __d('data_tables', ': activate to invert sorting'),
                'orderableRemove' => __d('data_tables', ': activate to remove sorting'),
            ],
        ];
    }

    /**
     * Create a JavaScript callback function for DataTables configuration.
     *
     * @param string $name JavaScript function name.
     * @param array $args Arguments to pass to the function.
     * @return \DataTables\Lib\CallbackFunction
     */
    public function callback(string $name, array $args = []): CallbackFunction
    {
        return new CallbackFunction($name, $args);
    }

    /**
     * Render a DataTable with the specified options.
     *
     * @param string $id DOM ID of the table.
     * @param array $dtOptions DataTables options.
     * @param array $htmlOptions HTML attributes for the table.
     * @return string Rendered HTML for the table and initialization script.
     */
    public function table(string $id = 'datatable', array $dtOptions = [], array $htmlOptions = []): string
    {
        $htmlOptions += [
            'id' => $id,
            'class' => 'dataTable ' . ($htmlOptions['class'] ?? ''),
        ];

        $table = $this->Html->tag('table', '', $htmlOptions);
        $script = $this->Html->scriptBlock($this->draw("#{$id}", $dtOptions), ['block' => true]);

        return $table . $script;
    }

    /**
     * Generate JavaScript code to initialize a DataTables instance.
     *
     * @param string $selector CSS selector for the table.
     * @param array $options Additional or replacement configuration.
     * @return string JavaScript code to initialize DataTables.
     */
    public function draw(string $selector, array $options = []): string
    {
        $options = array_replace_recursive($this->getConfig(), $options);

        $columns = $options['columns'] ?? [];
        if ($columns instanceof JsonSerializable) {
            $columns = $columns->jsonSerialize();
        } elseif ($columns instanceof Traversable) {
            $columns = iterator_to_array($columns, false);
        } elseif (!is_array($columns)) {
            $columns = [];
        }
        $options['columns'] = array_map(
            static fn($column) => $column instanceof JsonSerializable ? $column->jsonSerialize() : $column,
            $columns,
        );

        // Merge language options if URL not specified
        $options['language'] = isset($options['language']) && is_array($options['language'])
            ? $options['language'] : [];
        if (isset($options['language']['url'])) {
            $options['language'] = ['url' => $options['language']['url']];
        } else {
            $options['language'] += $this->getConfig('language');
        }

        // Process and translate column order
        if (!empty($options['order']) && !empty($options['columns'])) {
            $this->translateOrder($options['order'], $options['columns']);
        }

        // Remove internal field names
        if (!empty($options['columns'])) {
            foreach ($options['columns'] as &$column) {
                unset($column['field']);
            }
            unset($column);
        }

        // Generate JSON configuration with callbacks resolved
        $flags = JSON_THROW_ON_ERROR | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT;
        $json = CallbackFunction::resolve(json_encode($options, $flags));
        $json = str_replace(['"#!!', '!!#"'], '', $json);
        $selector = json_encode($selector, $flags);

        return "dt.initDataTables({$selector}, {$json});\n";
    }

    /**
     * Translate order configuration to match column keys.
     *
     * @param array $order Order configuration.
     * @param array $columns Column definitions.
     * @return array Translated order configuration.
     */
    public function translateOrder(array &$order, array $columns): array
    {
        // DataTables 3 accepts {name, dir} and {idx, dir} ordering objects.
        if (isset($order['dir']) && (isset($order['name']) || isset($order['idx']))) {
            return $order;
        }
        $order = array_map(
            static fn($key, $value) => is_int($key) ? $value : [$key, $value],
            array_keys($order),
            $order,
        );

        if (count($order) === 2 && !is_array($order[0])) {
            $order = [$order];
        }

        foreach ($order as &$item) {
            if (!is_array($item) || !array_key_exists(0, $item)) {
                continue;
            }

            foreach ($columns as $key => $column) {
                if ($item[0] === ($column['data'] ?? null) || $item[0] === ($column['field'] ?? null)) {
                    $item[0] = $key;
                    break;
                }
            }
        }

        return $order;
    }
}
