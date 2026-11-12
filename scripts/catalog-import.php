<?php
/**
 * Imports the Canva catalog products as HIDDEN products, without prices or photos.
 * The owner sets price, photo and stock in Mi Tienda, then switches them on.
 *
 * Idempotent: products are matched by reference (CANVA-<slug>); existing ones are skipped.
 * Usage: docker compose exec -T -u www-data prestashop php /opt/natufitness/scripts/catalog-import.php
 */

if (PHP_SAPI !== 'cli') {
    exit("CLI only\n");
}

$_SERVER['REQUEST_METHOD'] = 'GET';
$_SERVER['HTTP_HOST'] = getenv('PS_DOMAIN') ?: 'localhost';
require '/var/www/html/config/config.inc.php';

// [name, weight/presentation, category, category id]
$catalog = [
    ['BCAA XS', '', 'Aminoácidos y BCAA', 14],
    ['Creatina XS', '', 'Creatinas', 12],
    ['Glutamina XS', '', 'Ganadores de masa', 15],
    ['King Mass XL', 'Vainilla / Chocolate', 'Ganadores de masa', 15],
    ['Carnivor Mass 6 lb', 'Cookies & cream / Vainilla / Chocolate', 'Ganadores de masa', 15],
    ['Carnivor Mass 10 lb', 'Chocolate / Vainilla', 'Ganadores de masa', 15],
    ['Carnivor Mass 15 lb', 'Chocolate', 'Ganadores de masa', 15],
    ['Carnivor Isolate', '', 'Proteínas', 11],
    ['Creatina Carnivor', '60 servicios', 'Creatinas', 12],
    ['Crea 10', '30 servicios', 'Creatinas', 12],
    ['Pre-entreno Shaboom Pump', 'Ice pump / Fruit punch', 'Pre-entrenos', 13],
    ['Pure Whey', '', 'Proteínas', 11],
    ['Pure Whey Vegan', 'Chocolate & cookies', 'Proteínas', 11],
    ['Delux 100% Whey', 'Vainilla / Fresa', 'Proteínas', 11],
    ['Creatina Creapure', '', 'Creatinas', 12],
    ['Creatina Extreme Tank', '', 'Creatinas', 12],
    ['Burner Extreme Tank', '', 'Quemadores', 18],
    ['Define Body 12 Women', '60 cápsulas', 'Quemadores', 18],
    ['Energy Storm', '30 servicios', 'Pre-entrenos', 13],
    ['Mantequilla de maní', '', 'Salud y vitaminas', 17],
    ['Anabolic Iso Whey 2 lb', 'Vainilla', 'Proteínas', 11],
    ['Levrone Whey Supreme 10 lb', 'Vainilla / Chocolate', 'Proteínas', 11],
    ['Levro Whey 4.4 lb', 'Vainilla / Chocolate', 'Proteínas', 11],
    ['Anabolic PM Protein 1 lb', 'Vainilla', 'Proteínas', 11],
    ['Anabolic Iso Whey 5 lb', 'Vainilla / Chocolate', 'Proteínas', 11],
    ['Levro Legendary Mass 8 lb', 'Vainilla / Chocolate', 'Ganadores de masa', 15],
    ['Anabolic Mass 6 lb', 'Vainilla', 'Ganadores de masa', 15],
    ['Vitargo 4.4 lb', 'Revisar sabor', 'Pre-entrenos', 13],
    ['BCAA 5000', '30 servicios', 'Aminoácidos y BCAA', 14],
    ['Creatina 5000', '60 servicios', 'Creatinas', 12],
    ['BCAA Energy', '30 servicios', 'Aminoácidos y BCAA', 14],
    ['Glutamina 5000', '60 servicios', 'Ganadores de masa', 15],
    ['L-Arginina 1500', '100 cápsulas', 'Salud y vitaminas', 17],
    ['Whey Protein Evlution 5 lb', 'Chocolate / Vainilla', 'Proteínas', 11],
    ['L-Carnitina 1500', '', 'Quemadores', 18],
    ['Multivitamínico Vita XS', '', 'Salud y vitaminas', 17],
];

$langId = (int) Configuration::get('PS_LANG_DEFAULT');
$shopId = (int) Configuration::get('PS_SHOP_DEFAULT');
$taxGroupId = (int) TaxRulesGroup::getIdByName('EC IVA 15%');
$created = 0;
$skipped = 0;

foreach ($catalog as [$name, $presentation, $categoryName, $categoryId]) {
    $reference = 'CANVA-' . Tools::str2url($name);
    if (Product::getIdByReference($reference)) {
        $skipped++;
        continue;
    }

    $product = new Product();
    $product->reference = $reference;
    $product->price = 0;
    $product->id_tax_rules_group = $taxGroupId;
    $product->id_category_default = $categoryId;
    $product->active = false; // hidden until the owner sets price, photo and stock
    $product->visibility = 'none';
    $product->redirect_type = '404';
    $product->condition = 'new';
    $product->show_price = false;
    foreach (Language::getIDs(false) as $id) {
        $product->name[$id] = $name;
        $product->link_rewrite[$id] = Tools::str2url($name);
        $product->description_short[$id] = $presentation !== '' ? 'Presentación / sabor: ' . $presentation : '';
    }
    $product->add();
    $product->updateCategories([$categoryId]);
    StockAvailable::setQuantity((int) $product->id, 0, 0, $shopId);
    $created++;
    echo "Created hidden product: {$name} ({$categoryName})\n";
}

Tools::clearSmartyCache();
echo "Done: created {$created}, already existed {$skipped}\n";
