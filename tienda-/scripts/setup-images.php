<?php
/**
 * Sharp product photos: the default image sizes (cards at 250 px) look blurry on retina
 * phones and laptops. Cards get 600 px, the product page 1080 px (Full HD square), and
 * every existing product image is regenerated from its original.
 *
 * Idempotent. Usage: docker compose exec -T -u www-data prestashop php /opt/natufitness/scripts/setup-images.php
 */

if (PHP_SAPI !== 'cli') {
    exit("CLI only\n");
}

$_SERVER['REQUEST_METHOD'] = 'GET';
$_SERVER['HTTP_HOST'] = getenv('PS_DOMAIN') ?: 'localhost';
require '/var/www/html/config/config.inc.php';

const SIZES = [
    'cart_default' => 250,
    'small_default' => 200,
    'home_default' => 600,
    'medium_default' => 600,
    'large_default' => 1080,
];

$db = Db::getInstance();
foreach (SIZES as $name => $size) {
    $db->update('image_type', ['width' => $size, 'height' => $size], 'name = \'' . pSQL($name) . '\'');
}
Configuration::updateValue('PS_JPEG_QUALITY', 92);

$types = ImageType::getImagesTypes('products');
$count = 0;
foreach ($db->executeS('SELECT id_image FROM `' . _DB_PREFIX_ . 'image`') as $row) {
    $image = new Image((int) $row['id_image']);
    $base = _PS_PRODUCT_IMG_DIR_ . $image->getImgPath();
    if (!file_exists($base . '.jpg')) {
        continue;
    }
    foreach ($types as $type) {
        ImageManager::resize($base . '.jpg', $base . '-' . $type['name'] . '.jpg', (int) $type['width'], (int) $type['height']);
    }
    $count++;
}

Tools::clearSmartyCache();
echo "Image sizes updated, {$count} product images regenerated\n";
