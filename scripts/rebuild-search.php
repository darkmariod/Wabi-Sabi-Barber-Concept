<?php
/**
 * Rebuilds the product search index. Products created by scripts are not indexed
 * automatically, so the store search returns nothing for them until this runs.
 * The import scripts call it at the end; run it by hand after bulk changes.
 *
 * Usage: docker compose exec -T -u www-data prestashop php /opt/natufitness/scripts/rebuild-search.php
 */

if (PHP_SAPI !== 'cli') {
    exit("CLI only\n");
}

$_SERVER['REQUEST_METHOD'] = 'GET';
$_SERVER['HTTP_HOST'] = getenv('PS_DOMAIN') ?: 'localhost';
require '/var/www/html/config/config.inc.php';

Search::indexation(true);
Tools::clearSmartyCache();
echo 'Search index rebuilt: ' . (int) Db::getInstance()->getValue('SELECT COUNT(*) FROM `' . _DB_PREFIX_ . 'product` WHERE indexed = 1') . " products\n";
