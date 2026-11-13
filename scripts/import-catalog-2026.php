<?php
/**
 * Loads "Catálogo NATUFITNESS 2026" (assets/) as the store's supplement catalog:
 * 203 products with brand, presentation and a 1080x1080 black-background photo
 * (scripts/catalog-2026/catalog.json + photos/, generated from the PDF).
 *
 * - The PDF has no prices: products get the $1 demo price (IVA included) and stock 10.
 *   The owner sets real prices in "Mi Tienda".
 * - The older Canva import (references CANVA-*) covers the same products without photos,
 *   so those are deactivated (not deleted) to avoid duplicates.
 * - Eight products with photo go to the home "Promociones Exclusivas" with a 15% offer.
 *
 * Idempotent by reference (C26-<slug>). Usage:
 * docker compose exec -T -u www-data prestashop php /opt/natufitness/scripts/import-catalog-2026.php
 */

if (PHP_SAPI !== 'cli') {
    exit("CLI only\n");
}

$_SERVER['REQUEST_METHOD'] = 'GET';
$_SERVER['HTTP_HOST'] = getenv('PS_DOMAIN') ?: 'localhost';
require '/var/www/html/config/config.inc.php';

const DATA_DIR = '/opt/natufitness/scripts/catalog-2026/';
const HOME_CATEGORY_ID = 2;
const DEMO_STOCK = 10;
const PROMO_NAMES = [
    'Dymatize Whey Gold Standard', 'Dymatize ISO 100', 'Carnivor Isolate', 'Insane Labz Psychotic Negro',
    'Nutrex Lipo 6 Black', 'Dymatize Isopure', 'Dragon Pharma Mass Phorm', 'Ronnie Coleman Creatina XS',
];

$catalog = json_decode((string) file_get_contents(DATA_DIR . 'catalog.json'), true);
if (!is_array($catalog)) {
    exit("Cannot read catalog.json\n");
}

$db = Db::getInstance();
$langIds = Language::getIDs(false);
$shopId = (int) Configuration::get('PS_SHOP_DEFAULT');
$shopGroupId = (int) Shop::getGroupFromShop($shopId);
$taxGroupId = (int) TaxRulesGroup::getIdByName('EC IVA 15%');
$demoPrice = round(1 / 1.15, 6);

function manufacturerId(string $name, array $langIds): int
{
    if ($name === '') {
        return 0;
    }
    $id = (int) Db::getInstance()->getValue(
        'SELECT id_manufacturer FROM `' . _DB_PREFIX_ . 'manufacturer` WHERE name = \'' . pSQL($name) . '\''
    );
    if ($id) {
        return $id;
    }
    $m = new Manufacturer();
    $m->name = $name;
    $m->active = true;
    foreach ($langIds as $lang) {
        $m->description[$lang] = '';
        $m->short_description[$lang] = '';
    }
    $m->add();
    echo "Created brand {$name}\n";

    return (int) $m->id;
}

function attachCover(int $productId, string $source, array $langIds, string $legend): void
{
    $cover = Image::getCover($productId);
    if ($cover) {
        // Refresh the photo when the source changed (e.g. re-exported in higher resolution).
        $existing = new Image((int) $cover['id_image']);
        $base = _PS_PRODUCT_IMG_DIR_ . $existing->getImgPath();
        if (file_exists($base . '.jpg') && md5_file($base . '.jpg') === md5_file($source)) {
            return;
        }
        copy($source, $base . '.jpg');
        foreach (ImageType::getImagesTypes('products') as $type) {
            ImageManager::resize($source, $base . '-' . $type['name'] . '.jpg', (int) $type['width'], (int) $type['height']);
        }

        return;
    }
    $image = new Image();
    $image->id_product = $productId;
    $image->position = Image::getHighestPosition($productId) + 1;
    $image->cover = true;
    foreach ($langIds as $lang) {
        $image->legend[$lang] = $legend;
    }
    $image->add();
    $image->createImgFolder();
    $base = $image->getPathForCreation();
    copy($source, $base . '.jpg');
    foreach (ImageType::getImagesTypes('products') as $type) {
        ImageManager::resize($source, $base . '-' . $type['name'] . '.jpg', (int) $type['width'], (int) $type['height']);
    }
}

$created = 0;
$skipped = 0;
$idsByName = [];
foreach ($catalog as $item) {
    $productId = (int) Product::getIdByReference($item['reference']);
    if ($productId) {
        $skipped++;
    } else {
        $product = new Product();
        $product->reference = $item['reference'];
        $product->price = $demoPrice;
        $product->id_tax_rules_group = $taxGroupId;
        $product->id_category_default = (int) $item['category_id'];
        $product->id_manufacturer = manufacturerId($item['brand'], $langIds);
        $product->active = true;
        $product->visibility = 'both';
        $product->available_for_order = true;
        $product->show_price = true;
        $product->condition = 'new';
        foreach ($langIds as $lang) {
            $product->name[$lang] = $item['name'];
            $product->link_rewrite[$lang] = Tools::str2url($item['name']);
            $product->description_short[$lang] = $item['presentation'] !== '' ? 'Presentación: ' . $item['presentation'] : '';
        }
        $product->add();
        $product->updateCategories([(int) $item['category_id']]);
        StockAvailable::setQuantity((int) $product->id, 0, DEMO_STOCK, $shopId);
        $productId = (int) $product->id;
        $created++;
    }
    if ($item['photo'] && file_exists(DATA_DIR . 'photos/' . $item['photo'])) {
        attachCover($productId, DATA_DIR . 'photos/' . $item['photo'], $langIds, $item['name']);
    }
    $idsByName[$item['name']] = $productId;
}

// The Canva import is superseded by this catalog: hide it (reversible), never delete.
$db->execute('UPDATE `' . _DB_PREFIX_ . 'product` SET active = 0 WHERE reference LIKE \'CANVA-%\'');
$db->execute('UPDATE `' . _DB_PREFIX_ . 'product_shop` ps JOIN `' . _DB_PREFIX_ . 'product` p ON p.id_product = ps.id_product
    SET ps.active = 0 WHERE p.reference LIKE \'CANVA-%\'');

// Home promotions: these eight products, 15% off.
foreach (PROMO_NAMES as $name) {
    $id = $idsByName[$name] ?? 0;
    if (!$id) {
        echo "Promo product not found: {$name}\n";
        continue;
    }
    $db->execute('INSERT IGNORE INTO `' . _DB_PREFIX_ . 'category_product` (id_category, id_product, position)
        VALUES (' . HOME_CATEGORY_ID . ', ' . $id . ', 0)');
    $hasOffer = (int) $db->getValue('SELECT id_specific_price FROM `' . _DB_PREFIX_ . 'specific_price` WHERE id_product = ' . $id);
    if (!$hasOffer) {
        $sp = new SpecificPrice();
        $sp->id_product = $id;
        $sp->id_shop = $shopId;
        $sp->id_shop_group = $shopGroupId;
        $sp->id_currency = 0;
        $sp->id_country = 0;
        $sp->id_group = 0;
        $sp->id_customer = 0;
        $sp->id_product_attribute = 0;
        $sp->price = -1;
        $sp->from_quantity = 1;
        $sp->reduction = 0.15;
        $sp->reduction_tax = 1;
        $sp->reduction_type = 'percentage';
        $sp->from = '2000-01-01 00:00:00';
        $sp->to = '2099-12-31 23:59:59';
        $sp->add();
    }
}
Configuration::updateValue('PS_SPECIFIC_PRICE_FEATURE_ACTIVE', 1);

Search::indexation(false); // new products are not indexed by add()
Tools::clearSmartyCache();
echo "Done: created {$created}, already existed {$skipped}\n";
