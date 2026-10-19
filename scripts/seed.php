<?php
/**
 * NatuFitness base configuration seed.
 *
 * Idempotent: safe to run multiple times.
 * Usage: docker compose exec prestashop php /opt/natufitness/scripts/seed.php
 */

if (PHP_SAPI !== 'cli') {
    exit("CLI only\n");
}

$_SERVER['REQUEST_METHOD'] = 'GET';
$_SERVER['HTTP_HOST'] = getenv('PS_DOMAIN') ?: 'localhost';
require '/var/www/html/config/config.inc.php';

const SHIPPING_FREE_PRICE = 35;
const HOME_DELIVERY_PRICE = 4.00; // Placeholder until the client confirms courier rates
const DEMO_CATEGORY_NAMES = ['Clothes', 'Men', 'Women', 'Accessories', 'Accesorios', 'Stationery', 'Home Accessories', 'Art'];

$langId = (int) Configuration::get('PS_LANG_DEFAULT');
$homeId = (int) Configuration::get('PS_HOME_CATEGORY');

function out(string $msg): void
{
    echo $msg . PHP_EOL;
}

// --- Locale: Ecuador, USD, timezone ---------------------------------------

$usdId = (int) Currency::getIdByIsoCode('USD');
if (!$usdId) {
    exit("USD currency not found\n");
}
$usd = new Currency($usdId);
$usd->active = true;
$usd->conversion_rate = 1;
$usd->save();
Configuration::updateValue('PS_CURRENCY_DEFAULT', $usdId);

foreach (Currency::getCurrencies(true, false) as $currency) {
    if ((int) $currency->id !== $usdId && $currency->active) {
        $currency->active = false;
        $currency->save();
        out("Disabled currency {$currency->iso_code}");
    }
}

$ecId = (int) Country::getByIso('EC');
$ecuador = new Country($ecId);
$ecuador->active = true;
$ecuador->contains_states = false;
$ecuador->need_identification_number = false; // ID/RUC stays optional (invoice on request)
$ecuador->save();
Configuration::updateValue('PS_COUNTRY_DEFAULT', $ecId);
Db::getInstance()->execute('UPDATE `' . _DB_PREFIX_ . 'country` SET active = 0 WHERE id_country != ' . $ecId);

$addressFormat = "firstname lastname\ndni\ncompany\naddress1\naddress2\ncity\nCountry:name\nphone";
$format = new AddressFormat($ecId);
$format->id_country = $ecId;
$format->format = $addressFormat;
$format->id ? $format->update() : $format->add();

foreach (Language::getLanguages(true) as $language) {
    if ((int) $language['id_lang'] !== $langId) {
        $lang = new Language((int) $language['id_lang']);
        $lang->active = false;
        $lang->save();
        out("Disabled language {$lang->iso_code}");
    }
}

Configuration::updateValue('PS_TIMEZONE', 'America/Guayaquil');
Configuration::updateValue('PS_SHOP_NAME', 'NatuFitness');
Configuration::updateValue('PS_SHOP_COUNTRY_ID', $ecId);
Configuration::updateValue('PS_SHOP_CITY', 'Riobamba');
Configuration::updateValue('PS_SHIPPING_FREE_PRICE', SHIPPING_FREE_PRICE);
out("Locale: Ecuador, USD, free shipping from \$" . SHIPPING_FREE_PRICE);

Configuration::updateValue('PS_SHIPPING_HANDLING', 0);

// --- Taxes: Ecuador IVA groups (assigned per product) -------------------------

function ensureTaxRulesGroup(string $name, float $rate, int $countryId, int $langId): int
{
    $groupId = (int) TaxRulesGroup::getIdByName($name);
    if ($groupId) {
        return $groupId;
    }

    $tax = new Tax();
    $tax->rate = $rate;
    $tax->active = true;
    foreach (Language::getIDs(false) as $id) {
        $tax->name[$id] = $name;
    }
    $tax->add();

    $group = new TaxRulesGroup();
    $group->name = $name;
    $group->active = true;
    $group->add();

    $rule = new TaxRule();
    $rule->id_tax_rules_group = (int) $group->id;
    $rule->id_country = $countryId;
    $rule->id_tax = (int) $tax->id;
    $rule->behavior = 0;
    $rule->zipcode_from = 0;
    $rule->zipcode_to = 0;
    $rule->add();

    out("Created tax rules group {$name}");

    return (int) $group->id;
}

ensureTaxRulesGroup('EC IVA 15%', 15.0, $ecId, $langId);
ensureTaxRulesGroup('EC IVA 0%', 0.0, $ecId, $langId);

// --- Carriers (reuse demo carriers by reference, serve Ecuador's zone) ------

$zoneId = (int) $ecuador->id_zone;
$carrierSpecs = [
    // id_reference => [name, delay, is_free]
    1 => ['Retiro en local Riobamba', 'Listo para retirar en 24 horas', true],
    2 => ['Envío a domicilio', 'Entrega de 24 a 72 horas hábiles', false],
];

foreach ($carrierSpecs as $reference => [$name, $delay, $isFree]) {
    $carrier = Carrier::getCarrierByReference($reference);
    if (!$carrier) {
        out("Carrier reference {$reference} not found, skipped");
        continue;
    }
    $carrier->name = $name;
    foreach (Language::getIDs(false) as $id) {
        $carrier->delay[$id] = $delay;
    }
    $carrier->active = true;
    $carrier->is_free = $isFree;
    if (!$isFree) {
        $carrier->shipping_method = Carrier::SHIPPING_METHOD_PRICE;
        $carrier->range_behavior = 0;
    }
    $carrier->shipping_handling = false;
    $carrier->update();
    // Shipping price is the final price shown to the customer (no extra tax on top)
    $carrier->setTaxRulesGroup(0);

    if (!$carrier->getZone($zoneId)) {
        $carrier->addZone($zoneId);
    }

    if (!$isFree) {
        $ranges = RangePrice::getRanges((int) $carrier->id);
        if ($ranges) {
            $rangeId = (int) $ranges[0]['id_range_price'];
        } else {
            $range = new RangePrice();
            $range->id_carrier = (int) $carrier->id;
            $range->delimiter1 = 0;
            $range->delimiter2 = 1000000;
            $range->add();
            $rangeId = (int) $range->id;
        }
        // addZone() may already have created the row with price 0, so update first
        $where = 'id_carrier = ' . (int) $carrier->id . ' AND id_range_price = ' . $rangeId . ' AND id_zone = ' . $zoneId;
        $hasRow = (bool) Db::getInstance()->getValue('SELECT 1 FROM `' . _DB_PREFIX_ . 'delivery` WHERE ' . $where);
        if ($hasRow) {
            Db::getInstance()->update('delivery', ['price' => HOME_DELIVERY_PRICE], $where);
        } else {
            Db::getInstance()->insert('delivery', [
                'id_carrier' => (int) $carrier->id,
                'id_range_price' => $rangeId,
                'id_range_weight' => null,
                'id_zone' => $zoneId,
                'price' => HOME_DELIVERY_PRICE,
            ], true);
        }
    }
    out("Carrier ready: {$name}");
}

// --- Remove demo catalog ---------------------------------------------------

$demoProducts = Db::getInstance()->executeS('SELECT id_product FROM `' . _DB_PREFIX_ . 'product` WHERE reference LIKE "demo_%"');
foreach ($demoProducts as $row) {
    (new Product((int) $row['id_product']))->delete();
}
if ($demoProducts) {
    out('Removed ' . count($demoProducts) . ' demo products');
}

foreach (DEMO_CATEGORY_NAMES as $name) {
    $found = Category::searchByName($langId, $name, true);
    if ($found && (int) $found['id_category'] !== $homeId) {
        (new Category((int) $found['id_category']))->delete();
        out("Removed demo category {$name}");
    }
}

// --- Category tree ---------------------------------------------------------

$tree = [
    'Nutrición deportiva' => ['Proteínas', 'Creatinas', 'Pre-entrenos', 'Aminoácidos y BCAA', 'Ganadores de masa'],
    'Medicina natural' => [],
    'Salud y vitaminas' => [],
    'Quemadores' => [],
    'Ropa deportiva' => [],
    'Implementos' => [],
    'Combos' => [],
];

function ensureCategory(string $name, int $parentId, int $langId): int
{
    $existing = Category::searchByNameAndParentCategoryId($langId, $name, $parentId);
    if ($existing) {
        return (int) $existing['id_category'];
    }
    $category = new Category();
    $category->id_parent = $parentId;
    $category->active = true;
    foreach (Language::getIDs(false) as $id) {
        $category->name[$id] = $name;
        $category->link_rewrite[$id] = Tools::str2url($name);
    }
    $category->add();
    out("Created category {$name}");

    return (int) $category->id;
}

$topLevelIds = [];
foreach ($tree as $parentName => $children) {
    $parentId = ensureCategory($parentName, $homeId, $langId);
    $topLevelIds[] = $parentId;
    foreach ($children as $childName) {
        ensureCategory($childName, $parentId, $langId);
    }
}
Category::regenerateEntireNtree();

// --- Main menu -------------------------------------------------------------

$menuItems = implode(',', array_map(fn ($id) => 'CAT' . $id, $topLevelIds));
Configuration::updateValue('MOD_BLOCKTOPMENU_ITEMS', $menuItems);
out("Main menu: {$menuItems}");

Tools::clearSf2Cache();
Tools::clearSmartyCache();
out('Done');
