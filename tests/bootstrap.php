<?php
declare(strict_types=1);

use Cake\Cache\Cache;
use Cake\Core\Configure;
use Cake\Datasource\ConnectionManager;
use Cake\Datasource\FactoryLocator;
use Cake\ORM\Locator\TableLocator;

require dirname(__DIR__) . '/vendor/autoload.php';

if (!defined('DS')) {
    define('DS', DIRECTORY_SEPARATOR);
}
define('ROOT', dirname(__DIR__));
define('APP_DIR', 'src');
define('APP', ROOT . DS . APP_DIR . DS);
define('WWW_ROOT', ROOT . DS . 'webroot' . DS);
define('TMP', sys_get_temp_dir() . DS . 'cakephp-datatables' . DS);
define('CONFIG', ROOT . DS . 'config' . DS);
define('CAKE', ROOT . DS . 'vendor' . DS . 'cakephp' . DS . 'cakephp' . DS . 'src' . DS);

require ROOT . '/vendor/cakephp/cakephp/config/bootstrap.php';
date_default_timezone_set('UTC');
Configure::write('App', [
    'namespace' => 'DataTables',
    'encoding' => 'UTF-8',
    'fullBaseUrl' => 'http://localhost',
    'paths' => ['templates' => [], 'locales' => [APP . 'Locale' . DS]],
]);
foreach (['_cake_core_', '_cake_model_', '_cake_translations_'] as $name) {
    Cache::setConfig($name, ['className' => 'Array']);
}
ConnectionManager::setConfig('default', ['url' => 'sqlite:///:memory:']);
FactoryLocator::add('Table', new TableLocator());
