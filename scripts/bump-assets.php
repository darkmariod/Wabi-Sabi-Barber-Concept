<?php
/**
 * Run after every theme CSS/JS change: bumps the CCC asset versions so bundles get a new
 * file name (browsers fetch the new code instead of a cached copy) and clears caches.
 *
 * Usage: docker compose exec -T -u www-data prestashop php /opt/natufitness/scripts/bump-assets.php
 */

if (PHP_SAPI !== 'cli') {
    exit("CLI only\n");
}

$_SERVER['REQUEST_METHOD'] = 'GET';
$_SERVER['HTTP_HOST'] = getenv('PS_DOMAIN') ?: 'localhost';
require '/var/www/html/config/config.inc.php';

foreach (['PS_CCCJS_VERSION', 'PS_CCCCSS_VERSION'] as $key) {
    Configuration::updateValue($key, (int) Configuration::get($key) + 1);
}

foreach (glob(_PS_ALL_THEMES_DIR_ . '*/assets/cache/*.{js,css}', GLOB_BRACE) ?: [] as $file) {
    @unlink($file);
}
Tools::clearSmartyCache();

echo 'js=' . Configuration::get('PS_CCCJS_VERSION') . ' css=' . Configuration::get('PS_CCCCSS_VERSION') . "\n";
