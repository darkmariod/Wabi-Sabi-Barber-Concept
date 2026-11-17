<?php
/**
 * Installs the NatuFitness "no photo" images (black background, white logo) that PrestaShop
 * shows for products without a photo. Store rule: every product image sits on black.
 * Source: themes/natufitness/assets/img/placeholder/es-default-<type>.jpg
 *
 * Idempotent. Usage: docker compose exec -T -u www-data prestashop php /opt/natufitness/scripts/install-placeholders.php
 */

if (PHP_SAPI !== 'cli') {
    exit("CLI only\n");
}

$_SERVER['REQUEST_METHOD'] = 'GET';
$_SERVER['HTTP_HOST'] = getenv('PS_DOMAIN') ?: 'localhost';
require '/var/www/html/config/config.inc.php';

$source = _PS_ALL_THEMES_DIR_ . 'natufitness/assets/img/placeholder/';
foreach (glob($source . '*-default-*.jpg') ?: [] as $file) {
    copy($file, _PS_PRODUCT_IMG_DIR_ . basename($file));
    echo 'Installed ' . basename($file) . "\n";
}
Tools::clearSmartyCache();
