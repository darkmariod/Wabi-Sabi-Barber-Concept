<?php
/**
 * Loads the products from the two PDF catalogs (Belleza and Implementos) with their
 * black-background photos (scripts/catalog-photos, generated from the PDFs).
 *
 * The PDFs carry no prices, so products get the same $1 demo price (IVA included) and
 * stock 10 as the rest of the demo catalog. The owner sets real prices in "Mi Tienda".
 *
 * Idempotent by reference (PDF-<slug>): existing products are skipped; a product without
 * a cover photo gets one. Creates the "Belleza" category and adds it to the main menu.
 *
 * Usage: docker compose exec -T -u www-data prestashop php /opt/natufitness/scripts/import-pdf-catalog.php
 */

if (PHP_SAPI !== 'cli') {
    exit("CLI only\n");
}

$_SERVER['REQUEST_METHOD'] = 'GET';
$_SERVER['HTTP_HOST'] = getenv('PS_DOMAIN') ?: 'localhost';
require '/var/www/html/config/config.inc.php';

const DATA_DIR = '/opt/natufitness/scripts/';
const IMPLEMENTOS_CATEGORY_ID = 20;
const HOME_CATEGORY_ID = 2;
const DEMO_STOCK = 10;

$catalog = json_decode((string) file_get_contents(DATA_DIR . 'pdf-catalog.json'), true);
if (!is_array($catalog)) {
    exit("Cannot read pdf-catalog.json\n");
}

$langIds = Language::getIDs(false);
$shopId = (int) Configuration::get('PS_SHOP_DEFAULT');
$taxGroupId = (int) TaxRulesGroup::getIdByName('EC IVA 15%');
$demoPrice = round(1 / 1.15, 6);

function ensureBellezaCategory(array $langIds): int
{
    $id = (int) Db::getInstance()->getValue(
        'SELECT c.id_category FROM `' . _DB_PREFIX_ . 'category` c
         JOIN `' . _DB_PREFIX_ . 'category_lang` cl ON cl.id_category = c.id_category
         WHERE cl.link_rewrite = \'belleza\''
    );
    if ($id) {
        return $id;
    }

    $category = new Category();
    $category->id_parent = HOME_CATEGORY_ID;
    $category->active = true;
    foreach ($langIds as $lang) {
        $category->name[$lang] = 'Belleza';
        $category->link_rewrite[$lang] = 'belleza';
        $category->description[$lang] = 'Shampoos, acondicionadores y tintes naturales para el cuidado del cabello.';
    }
    $category->add();
    echo "Created category Belleza (#{$category->id})\n";

    return (int) $category->id;
}

function ensureInMenu(int $categoryId): void
{
    $items = array_filter(explode(',', (string) Configuration::get('MOD_BLOCKTOPMENU_ITEMS')));
    $key = 'CAT' . $categoryId;
    if (!in_array($key, $items, true)) {
        $items[] = $key;
        Configuration::updateValue('MOD_BLOCKTOPMENU_ITEMS', implode(',', $items));
        echo "Added Belleza to the main menu\n";
    }
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

$bellezaId = ensureBellezaCategory($langIds);
ensureInMenu($bellezaId);

$created = 0;
$skipped = 0;
foreach ($catalog as $item) {
    $categoryId = $item['category'] === 'Belleza' ? $bellezaId : IMPLEMENTOS_CATEGORY_ID;
    $photo = DATA_DIR . 'catalog-photos/' . $item['photo'];
    $productId = (int) Product::getIdByReference($item['reference']);

    if ($productId) {
        $skipped++;
    } else {
        $product = new Product();
        $product->reference = $item['reference'];
        $product->price = $demoPrice;
        $product->id_tax_rules_group = $taxGroupId;
        $product->id_category_default = $categoryId;
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
        $product->updateCategories([$categoryId]);
        StockAvailable::setQuantity((int) $product->id, 0, DEMO_STOCK, $shopId);
        $productId = (int) $product->id;
        $created++;
        echo "Created {$item['name']} ({$item['category']})\n";
    }

    if (file_exists($photo)) {
        attachCover($productId, $photo, $langIds, $item['name']);
    }
}

Search::indexation(false); // new products are not indexed by add()
Tools::clearSmartyCache();
echo "Done: created {$created}, already existed {$skipped}\n";
