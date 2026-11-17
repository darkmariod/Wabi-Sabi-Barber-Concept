<?php
/**
 * Subcategories per the 2026 catalog, so every top menu item opens a submenu (like
 * Nutrición deportiva) and every category has products:
 *  - Medicina natural: Windmill and Sascha natural line (was empty).
 *  - Salud y vitaminas, Quemadores, Implementos, Belleza: grouped by product type.
 * Also fixes products the automatic import placed in Quemadores by mistake.
 *
 * Rules are matched by product name, only among products whose default category is in
 * the rule's scope (so "Lipo 6 Pre Entreno" stays in Pre-entrenos). First match wins.
 * Products get [parent, subcategory] with the subcategory as default.
 *
 * Idempotent. Usage: docker compose exec -T -u www-data prestashop php /opt/natufitness/scripts/setup-subcategories.php
 * Then run setup-filters.php and clear the cache (the menu is cached).
 */

if (PHP_SAPI !== 'cli') {
    exit("CLI only\n");
}

$_SERVER['REQUEST_METHOD'] = 'GET';
$_SERVER['HTTP_HOST'] = getenv('PS_DOMAIN') ?: 'localhost';
require '/var/www/html/config/config.inc.php';

const MEDICINA = 16;
const SALUD = 17;
const QUEMADORES = 18;
const IMPLEMENTOS = 20;
const BELLEZA = 22;
const PROTEINAS = 11;
const GANADORES = 15;

// [regex on the product name, scope (current default categories, children included), parent, subcategory or null]
$rules = [
    // Misplaced by the import ("carni" matched Carnivor): proteins and gainers.
    ['/^(Carnivor Isolate|Sascha Fitness Isolate|BUM Itholate)/u', [QUEMADORES], PROTEINAS, null],
    ['/^Carnivor Mass/u', [QUEMADORES], GANADORES, null],

    ['/^Windmill (Vitamina C|K2|Cal-mag|Magnesium|Glicinato|Picolinato)/iu', [SALUD, MEDICINA], MEDICINA, 'Vitaminas y minerales'],
    ['/^Windmill (Omega|CO-Q10|Ácido Alfa)/iu', [SALUD, QUEMADORES, MEDICINA], MEDICINA, 'Antioxidantes y omega 3'],
    ['/(Ashwagandha|Sascha Fitness (Restore|Collagen))/iu', [SALUD, MEDICINA], MEDICINA, 'Bienestar y colágeno'],

    ['/(Vita XS|Vitamina T|Dragon Pharma Omega)/iu', [SALUD], SALUD, 'Multivitamínicos'],
    ['/(Mens|Testrol|T-up|Tribulus)/iu', [SALUD], SALUD, 'Salud masculina'],
    ['/(Mantequilla|Crema de Arroz)/iu', [SALUD], SALUD, 'Snacks saludables'],
    ['/(Bebida|Gel)/iu', [SALUD], SALUD, 'Bebidas y geles energéticos'],

    ['/(carnitin|L-CARN|Carni Liquid|CLA)/iu', [QUEMADORES], QUEMADORES, 'L-Carnitina y CLA'],
    ['/(Burner|Define Body|RAW|Shred|Black Viper|Cafeína|Lipo 6|Fit 9|Dry Up)/iu', [QUEMADORES], QUEMADORES, 'Termogénicos'],

    ['/(Guante|Muñequera|Strap)/iu', [IMPLEMENTOS], IMPLEMENTOS, 'Guantes y muñequeras'],
    ['/(Rodillera|Cinturón)/iu', [IMPLEMENTOS], IMPLEMENTOS, 'Rodilleras y cinturones'],
    ['/(Shaker|Mezcladores)/iu', [IMPLEMENTOS], IMPLEMENTOS, 'Shakers'],
    ['/Cubo/iu', [IMPLEMENTOS], IMPLEMENTOS, 'Accesorios'],

    ['/Cubre Canas/iu', [BELLEZA], BELLEZA, 'Cubre canas'],
    ['/^Tinte/iu', [BELLEZA], BELLEZA, 'Tintes'],
    ['/./u', [BELLEZA], BELLEZA, 'Shampoos y acondicionadores'],
];

$db = Db::getInstance();
$langIds = Language::getIDs(false);
$langId = (int) Configuration::get('PS_LANG_DEFAULT');

function subcategoryId(int $parentId, string $name, array $langIds): int
{
    $slug = Tools::str2url($name);
    $id = (int) Db::getInstance()->getValue(
        'SELECT c.id_category FROM `' . _DB_PREFIX_ . 'category` c JOIN `' . _DB_PREFIX_ . 'category_lang` cl ON cl.id_category = c.id_category
         WHERE c.id_parent = ' . $parentId . ' AND cl.link_rewrite = \'' . pSQL($slug) . '\''
    );
    if ($id) {
        return $id;
    }
    $category = new Category();
    $category->id_parent = $parentId;
    $category->active = true;
    foreach ($langIds as $lang) {
        $category->name[$lang] = $name;
        $category->link_rewrite[$lang] = $slug;
    }
    $category->add();
    echo "Created subcategory {$name} (#{$category->id})\n";

    return (int) $category->id;
}

function withChildren(array $ids): array
{
    $all = $ids;
    foreach ($ids as $id) {
        foreach (Db::getInstance()->executeS('SELECT id_category FROM `' . _DB_PREFIX_ . 'category` WHERE id_parent = ' . (int) $id) as $row) {
            $all[] = (int) $row['id_category'];
        }
    }

    return $all;
}

$products = $db->executeS(
    'SELECT p.id_product, p.id_category_default, pl.name FROM `' . _DB_PREFIX_ . 'product` p
     JOIN `' . _DB_PREFIX_ . 'product_shop` ps ON ps.id_product = p.id_product AND ps.active = 1
     JOIN `' . _DB_PREFIX_ . 'product_lang` pl ON pl.id_product = p.id_product AND pl.id_lang = ' . $langId
);

$moved = 0;
$unmatched = [];
$watched = withChildren([MEDICINA, SALUD, QUEMADORES, IMPLEMENTOS, BELLEZA]);
foreach ($products as $row) {
    $current = (int) $row['id_category_default'];
    $target = null;
    foreach ($rules as [$regex, $scope, $parent, $sub]) {
        if (in_array($current, withChildren($scope), true) && preg_match($regex, $row['name'])) {
            $target = $sub === null ? [$parent] : [$parent, subcategoryId($parent, $sub, $langIds)];
            break;
        }
    }
    if ($target === null) {
        if (in_array($current, $watched, true)) {
            $unmatched[] = $row['name'];
        }
        continue;
    }
    $default = end($target);
    $product = new Product((int) $row['id_product']);
    $assigned = array_map('intval', $product->getCategories());
    sort($assigned);
    $wanted = $target;
    sort($wanted);
    if ($assigned === $wanted && (int) $product->id_category_default === $default) {
        continue;
    }
    $product->updateCategories($target);
    $product->id_category_default = $default;
    $product->update();
    $moved++;
}

Tools::clearSmartyCache();
echo "Products placed: {$moved}\n";
echo $unmatched ? 'Without subcategory: ' . implode(' | ', $unmatched) . "\n" : "Every product has its subcategory\n";
