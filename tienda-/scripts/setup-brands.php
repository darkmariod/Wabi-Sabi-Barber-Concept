<?php
/**
 * Loads the brands NatuFitness carries, with logo, and hides the demo placeholders.
 * Logos come from themes/natufitness/assets/img/brands/<slug>.jpg (400x200, normalized).
 * After this, brands are managed in Catálogo > Marcas y proveedores.
 *
 * Idempotent: an existing brand keeps its logo unless it has none.
 * Usage: docker compose exec -T -u www-data prestashop php /opt/natufitness/scripts/setup-brands.php
 */

if (PHP_SAPI !== 'cli') {
    exit("CLI only\n");
}

$_SERVER['REQUEST_METHOD'] = 'GET';
$_SERVER['HTTP_HOST'] = getenv('PS_DOMAIN') ?: 'localhost';
require '/var/www/html/config/config.inc.php';

const LOGO_DIR = '/var/www/html/themes/natufitness/assets/img/brands/';

$brands = [
    'Beverly Nutrition' => 'beverly-nutrition',
    'Optimum Nutrition' => 'optimum-nutrition',
    'BPI Sports' => 'bpi-sports',
    'PhytoChemie' => 'phytochemie',
    "Nature's Garden" => 'natures-garden',
    'Omnilife' => 'omnilife',
    'Only Natural' => 'only-natural',
    'Mason Natural' => 'mason-natural',
    "Nature's Herbalmax" => 'natures-herbalmax',
    'NOW Foods' => 'now-foods',
    'Mutant' => 'mutant',
    'Sascha Fitness' => 'sascha-fitness',
    // 2026 catalog brands (logos from tools/catalog/brand_logos_2026.py)
    'Allmax' => 'allmax',
    'Beast Bites' => 'beast-bites',
    'BioTechUSA' => 'biotechusa',
    'BUM' => 'bum',
    'Carnivor' => 'carnivor',
    'Dorian Yates' => 'dorian-yates',
    'Dragon Pharma' => 'dragon-pharma',
    'Dymatize' => 'dymatize',
    'EVL Nutrition' => 'evl-nutrition',
    'FA Nutrition' => 'fa-nutrition',
    'GAT Sport' => 'gat-sport',
    'Ghost' => 'ghost',
    'Insane Labz' => 'insane-labz',
    'Integralmedica' => 'integralmedica',
    'Kevin Levrone' => 'kevin-levrone',
    'Maximum' => 'maximum',
    'MuscleTech' => 'muscletech',
    'Nutrex' => 'nutrex',
    'ProSupps' => 'prosupps',
    'Quest' => 'quest',
    'RedCon1' => 'redcon1',
    'Ronnie Coleman' => 'ronnie-coleman',
    'Windmill' => 'windmill',
];
// Brands that still carry the demo placeholder logo: replaced by the real one.
$replaceDemoLogo = ['Dymatize', 'MuscleTech'];
// Demo placeholders created earlier: "ON" is Optimum Nutrition; BSN and Universal are not carried.
$renames = ['ON' => 'Optimum Nutrition'];
$hide = ['BSN', 'Universal']; // Dymatize and MuscleTech are carried since the 2026 catalog

function findManufacturer(string $name): int
{
    return (int) Db::getInstance()->getValue(
        'SELECT id_manufacturer FROM `' . _DB_PREFIX_ . 'manufacturer` WHERE name = \'' . pSQL($name) . '\''
    );
}

foreach ($renames as $from => $to) {
    $id = findManufacturer($from);
    if ($id && !findManufacturer($to)) {
        $m = new Manufacturer($id);
        $m->name = $to;
        $m->update();
        echo "Renamed {$from} -> {$to}\n";
    }
}

foreach ($hide as $name) {
    $id = findManufacturer($name);
    if ($id) {
        $m = new Manufacturer($id);
        if ($m->active) {
            $m->active = false;
            $m->update();
            echo "Hid demo brand {$name}\n";
        }
    }
}

$langIds = Language::getIDs(false);
foreach ($brands as $name => $slug) {
    $id = findManufacturer($name);
    if (!$id) {
        $m = new Manufacturer();
        $m->name = $name;
        $m->active = true;
        foreach ($langIds as $lang) {
            $m->description[$lang] = '';
            $m->short_description[$lang] = '';
        }
        $m->add();
        $id = (int) $m->id;
        echo "Created brand {$name} (#{$id})\n";
    } else {
        $m = new Manufacturer($id);
        if (!$m->active) {
            $m->active = true;
            $m->update();
        }
    }

    $target = _PS_MANU_IMG_DIR_ . $id . '.jpg';
    $source = LOGO_DIR . $slug . '.jpg';
    $stale = in_array($name, $replaceDemoLogo, true) && file_exists($target) && file_exists($source) && md5_file($target) !== md5_file($source);
    if ((!file_exists($target) || $stale) && file_exists($source)) {
        copy($source, $target);
        foreach (ImageType::getImagesTypes('manufacturers') as $type) {
            ImageManager::resize($source, _PS_MANU_IMG_DIR_ . $id . '-' . $type['name'] . '.jpg', (int) $type['width'], (int) $type['height']);
        }
        echo "Logo set for {$name}\n";
    }
}

// Link unbranded products to their brand by name prefix, so the "Marca" filter has data.
$namePrefixes = [
    'Beverly Nutrition' => 'Beverly', 'Optimum Nutrition' => 'Optimum', 'BPI Sports' => 'BPI',
    'PhytoChemie' => 'PhytoChemie', 'Omnilife' => 'Omnilife', 'Only Natural' => 'Only Natural',
    'Mason Natural' => 'Mason', "Nature's Garden" => "Nature's Garden", "Nature's Herbalmax" => 'Herbalmax',
    'NOW Foods' => 'NOW', 'Mutant' => 'Mutant', 'Sascha Fitness' => 'Sascha',
];
$langId = (int) Configuration::get('PS_LANG_DEFAULT');
foreach ($namePrefixes as $name => $prefix) {
    $id = findManufacturer($name);
    if (!$id) {
        continue;
    }
    Db::getInstance()->execute(
        'UPDATE `' . _DB_PREFIX_ . 'product` p
         JOIN `' . _DB_PREFIX_ . 'product_lang` pl ON pl.id_product = p.id_product AND pl.id_lang = ' . $langId . '
         SET p.id_manufacturer = ' . $id . '
         WHERE p.id_manufacturer = 0 AND pl.name LIKE \'' . pSQL($prefix) . ' %\''
    );
    $linked = Db::getInstance()->Affected_Rows();
    if ($linked) {
        echo "Linked {$linked} products to {$name}\n";
    }
}

Tools::clearSmartyCache();
echo "Done\n";
