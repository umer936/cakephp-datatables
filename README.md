# cakephp-datatables

[DataTables](https://www.datatables.net) provides interactive tables and server-side processing. This plugin integrates **DataTables 3** with CakePHP 5 using a view helper and an AJAX component. No jQuery is used or required.

## Versioning

`master` targets current DataTables 3, CakePHP 5 and PHP 8.5. Older versions are
not supported here. The pre-upgrade baseline is preserved on
[`legacy/datatables-2`](https://github.com/umer936/cakephp-datatables/tree/legacy/datatables-2);
that branch is archival and does not receive the new tests or fixes.

## Requirements

* PHP **8.5+**, within PHP 8
* CakePHP **5.4.4+**, within CakePHP 5
* DataTables **3.1.5+**, within DataTables 3
* No jQuery; use only DataTables 3-compatible, jQuery-free extensions

## Installation and Usage

The historical [Documentation][doc] and [Quick Start tutorial][quickstart] cover
older releases. For this branch, use DataTables 3's constructor API and CakePHP 5
named-argument finders as shown below; do not load jQuery or old DataTables assets.

Load the plugin with `$this->addPlugin('DataTables')` in your application, the
`DataTables.DataTables` component in your controller, and the `DataTables.DataTables`
helper in your view. Load DataTables' JavaScript and matching CSS from its download
builder or npm, then `DataTables.cakephp.dataTables.js` through CakePHP's Html helper.
Render the layout's `script` block **after** these dependencies. This plugin does
not bundle DataTables or its optional extensions. Use matching major versions of
extensions and styling packages when upgrading to DataTables 3.

Modern `layout` options and ordering objects such as `['name' => 'full_name',
'dir' => 'asc']` pass through to DataTables. Give that column a matching `name`.

## Virtual fields / computed columns

DataTables can request sorting or searching on columns that are not physical database fields
(for example a computed `full_name` or `price_total`).

In this case, delegate filtering and ordering to a custom finder:

```php
// In your controller (reuse the same definitions in the view)
$columns = $this->DataTables->columns();
$columns->add('full_name', 'full_name');
$data = $this->DataTables->find(
	'Users',
	'datatables',
	[
		'delegateSearch' => true,
		'delegateOrder' => true,
	],
	$columns
);
$this->set('data', $data->all());
$this->viewBuilder()->setClassName(\Cake\View\JsonView::class)
	->setOption('serialize', ['data', 'draw', 'recordsTotal', 'recordsFiltered']);
```

```php
// In src/Model/Table/UsersTable.php
use Cake\ORM\Query\SelectQuery;

public function findDatatables(
    SelectQuery $query,
    string $globalSearch = '',
    array $localSearch = [],
    array $customOrder = [],
): SelectQuery
{
    // SQLite/PostgreSQL expression; use CONCAT() for MySQL instead.
    // Keep SQL developer-controlled and search values parameter-bound.
    $fullName = $query->newExpr("Users.first_name || ' ' || Users.last_name");
    $query->select(['full_name' => $fullName])->enableAutoFields();
    foreach ([$globalSearch, $localSearch['full_name'] ?? ''] as $search) {
        if ($search !== '') {
            $query->where($query->newExpr()->like($fullName, '%' . $search . '%'));
        }
    }
    foreach ($customOrder as $field => $dir) {
        if (in_array($field, ['full_name', 'Users.id', 'Users.created'], true)) {
            // Sort by the selected alias, not an expression object used as an array key.
            $query->orderBy([$field => $dir === 'DESC' ? 'DESC' : 'ASC']);
        }
    }

    return $query;
}
```

Use this pattern whenever a DataTables column cannot be mapped directly to a real table column.

`delegateSearch` and `delegateOrder` are component switches and are not passed to
your finder. The finder receives `globalSearch`, `localSearch` (field => value) and
`customOrder` (field => ASC/DESC) only when requested. Give these named arguments
defaults because the finder runs once without request search filters to calculate
`recordsTotal`, and again with filters for `recordsFiltered`. Application-level
constraints (authorization/tenant filters) must apply on **both** calls. Do not
paginate inside the finder; the component counts before applying pagination.

For an entity accessor such as `_getFullName()`, include `full_name` in the entity's
`$_virtual` list and select its underlying fields. CakePHP JSON serialization
honours virtual and hidden fields. PHP-only accessors cannot be sorted or searched
by the database automatically: delegate to equivalent SQL as above, or mark the
column `notSearchable()->notOrderable()` for display-only use. Never sort/filter
only the fetched page in PHP: totals and pagination would be incorrect.

## Development checks

Run `composer install` and `npm ci`, then:

* `composer check` — PHPUnit SQLite regressions and full CakePHP coding standards
* `composer cs-fix` — automatically fix coding-standard formatting
* `npm test` — real DataTables 3 DOM integration tests without jQuery
* `npm run lint` — JavaScript syntax validation

CI runs PHPUnit 13 and coding standards on PHP 8.5, plus a separate Node 24
JavaScript job, using committed lockfiles. Node 24.15+ is needed for development.
SQLite, intl and mbstring PHP extensions are needed.
Coverage reports via `composer test-coverage` additionally require Xdebug or PCOV.

[doc]: https://github.com/ypnos-web/cakephp-datatables/wiki
[quickstart]: https://github.com/ypnos-web/cakephp-datatables/wiki/Quick-Start


## Credits

This work is based on the [code by Frank Heider](https://github.com/fheider/cakephp-datatables) and incorporates [code by Xavier Zolezzi](https://github.com/x-zolezzi/cakephp-datatables).
