<?php
/**
 * Sidebar filters like syf.ec: Categorías, Precio (slider) and Marca, on every category page.
 * Rewrites the single ps_facetedsearch filter template so it covers all active categories
 * (new categories included), then rebuilds the filter and price indexes.
 *
 * Idempotent: run again after adding categories or bulk-changing prices.
 * Usage: docker compose exec -T -u www-data prestashop php /opt/natufitness/scripts/setup-filters.php
 */

if (PHP_SAPI !== 'cli') {
    exit("CLI only\n");
}

$_SERVER['REQUEST_METHOD'] = 'GET';
$_SERVER['HTTP_HOST'] = getenv('PS_DOMAIN') ?: 'localhost';
require '/var/www/html/config/config.inc.php';

const TEMPLATE_NAME = 'NatuFitness: categorías, precio y marca';
const CHECKBOX = 0;

$module = Module::getInstanceByName('ps_facetedsearch');
if (!$module || !$module->active) {
    exit("ps_facetedsearch is not installed or active\n");
}

$db = Db::getInstance();
$categories = array_map('intval', array_column($db->executeS(
    'SELECT id_category FROM `' . _DB_PREFIX_ . 'category` WHERE active = 1 AND id_parent > 0 ORDER BY id_category'
), 'id_category'));

$facet = ['filter_type' => CHECKBOX, 'filter_show_limit' => 0];
$filters = [
    'categories' => $categories,
    'controllers' => ['category', 'manufacturer', 'supplier', 'new-products', 'best-sales', 'prices-drop'],
    'shop_list' => [(int) Configuration::get('PS_SHOP_DEFAULT')],
    // Order here is the order shown in the sidebar.
    'layered_selection_subcategories' => $facet,
    'layered_selection_price_slider' => $facet,
    'layered_selection_manufacturer' => $facet,
];

$db->execute('DELETE FROM `' . _DB_PREFIX_ . 'layered_filter`');
$db->insert('layered_filter', [
    'name' => pSQL(TEMPLATE_NAME),
    'filters' => pSQL(serialize($filters)),
    'n_categories' => count($categories),
    'date_add' => date('Y-m-d H:i:s'),
]);

$module->buildLayeredCategories();
$module->fullPricesIndexProcess(0, false, false);
$module->invalidateLayeredFilterBlockCache();
Tools::clearSmartyCache();

echo 'Filters ready on ' . count($categories) . " categories\n";
