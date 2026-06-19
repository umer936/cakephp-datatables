# cakephp-datatables

[DataTables](https://www.datatables.net) is a JavaScript library for intelligent HTML tables. Next to adding dynamic elements to the table, it also has great support for on-demand data fetching and server-side processing. DataTables 2 can run without jQuery, and this plugin now targets that vanilla API. The _cakephp-datatables_ plugin makes it easy to use the functionality DataTables provides in your CakePHP application. It consists of a helper to add DataTables to your view and a Component to transparently process AJAX requests made by DataTables.

## Versioning

* Versions 4.x are for users of CakePHP 4.0 and above
* Versions 3.x are for users of CakePHP 3.6 and above
* Versions 2.x are available for older CakePHP installations, but will not receive new features
* Version 1.0 is a tag available for people who let their code rot. Consider upgrading by only changing a couple of lines!
* Branch `php5` is for people without PHP 7 and currently stuck at version 1.0

## Requirements

* PHP 8.1+
* CakePHP 5.3+
* DataTables 1.x or 2.x

## Installation and Usage

Please see the [Documentation][doc], esp. the [Quick Start tutorial][quickstart]

## Virtual fields / computed columns

DataTables can request sorting or searching on columns that are not physical database fields
(for example a computed `full_name` or `price_total`).

In this case, delegate filtering and ordering to a custom finder:

```php
// In your controller
$data = $this->DataTables->find(
	'Users',
	'datatables',
	[
		'delegateSearch' => true,
		'delegateOrder' => true,
	],
	$columns
);
```

```php
// In src/Model/Table/UsersTable.php
use Cake\ORM\Query\SelectQuery;

public function findDatatables(SelectQuery $query, array $options): SelectQuery
{
	$search = $options['globalSearch'] ?? '';
	$requestedOrder = $options['customOrder'] ?? [];

	if ($search !== '') {
		$search = '%' . $search . '%';
		$query->where([
			'OR' => [
				'Users.first_name LIKE' => $search,
				'Users.last_name LIKE' => $search,
			],
		]);
	}

	// Allowlist requested sort keys and map virtual fields to SQL expressions.
	$safeOrder = [];
	foreach ($requestedOrder as $field => $dir) {
		$dir = strtoupper((string)$dir) === 'DESC' ? 'DESC' : 'ASC';

		if ($field === 'full_name') {
			$safeOrder[$query->newExpr("Users.first_name || ' ' || Users.last_name")] = $dir;
			continue;
		}

		if (in_array($field, ['Users.id', 'Users.created'], true)) {
			$safeOrder[$field] = $dir;
		}
	}

	if ($safeOrder) {
		$query->orderBy($safeOrder);
	}

	return $query;
}
```

Use this pattern whenever a DataTables column cannot be mapped directly to a real table column.

[doc]: https://github.com/ypnos-web/cakephp-datatables/wiki
[quickstart]: https://github.com/ypnos-web/cakephp-datatables/wiki/Quick-Start


## Credits

This work is based on the [code by Frank Heider](https://github.com/fheider/cakephp-datatables) and incorporates [code by Xavier Zolezzi](https://github.com/x-zolezzi/cakephp-datatables).
