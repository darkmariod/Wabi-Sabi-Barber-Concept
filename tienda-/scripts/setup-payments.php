<?php
/**
 * NatuFitness payment methods setup: DeUna static QR (nf_deuna) + bank transfer (ps_wirepayment).
 *
 * - Installs/enables nf_deuna (restricted to Ecuador/USD).
 * - Fills ps_wirepayment settings with PENDIENTE placeholders only when they are empty,
 *   so real bank details entered in the back office are never overwritten.
 * - Restricts ps_wirepayment to USD.
 * - Disables (does not uninstall) cheque and cash on delivery.
 *
 * Idempotent: safe to run multiple times.
 * Usage: docker compose exec prestashop php /opt/natufitness/scripts/setup-payments.php
 */

if (PHP_SAPI !== 'cli') {
    exit("CLI only\n");
}

$_SERVER['REQUEST_METHOD'] = 'GET';
$_SERVER['HTTP_HOST'] = getenv('PS_DOMAIN') ?: 'localhost';
require '/var/www/html/config/config.inc.php';

const WEB_USER = 'www-data';
const DISABLED_PAYMENT_MODULES = ['ps_checkpayment', 'ps_cashondelivery'];
const BANK_WIRE_PLACEHOLDERS = [
    'BANK_WIRE_OWNER' => 'PENDIENTE: titular de la cuenta',
    'BANK_WIRE_DETAILS' => "Banco Pichincha - Cta. Ahorros PENDIENTE\nCédula/RUC del titular: PENDIENTE",
    'BANK_WIRE_ADDRESS' => 'Banco Pichincha, Riobamba - Ecuador',
];
const BANK_WIRE_CUSTOM_TEXT = 'Realiza la transferencia por el monto exacto e indica tu número de pedido. Luego envíanos el comprobante por WhatsApp para procesar tu pedido.';

function out(string $msg): void
{
    echo $msg . PHP_EOL;
}

$context = Context::getContext();
$context->shop = new Shop((int) Configuration::get('PS_SHOP_DEFAULT'));
Shop::setContext(Shop::CONTEXT_SHOP, (int) $context->shop->id);
$context->language = new Language((int) Configuration::get('PS_LANG_DEFAULT'));
if (!Validate::isLoadedObject($context->employee ?? null)) {
    // Module::install() and enable() check employee permissions in some code paths.
    $context->employee = new Employee((int) Db::getInstance()->getValue('SELECT id_employee FROM `' . _DB_PREFIX_ . 'employee` WHERE id_profile = ' . (int) _PS_ADMIN_PROFILE_ . ' ORDER BY id_employee'));
}

$usdId = (int) Currency::getIdByIsoCode('USD');
if (!$usdId) {
    fwrite(STDERR, "USD currency not found (run seed.php first)\n");
    exit(1);
}

// --- DeUna (nf_deuna) ------------------------------------------------------

if (!is_file(_PS_MODULE_DIR_ . 'nf_deuna/nf_deuna.php')) {
    fwrite(STDERR, 'nf_deuna not found at ' . _PS_MODULE_DIR_ . "nf_deuna (is the bind mount in docker-compose.yml active?)\n");
    exit(1);
}

/**
 * Runs a module action through the Symfony console: Module::install() needs the
 * Symfony container (translator), which this legacy CLI bootstrap does not boot.
 */
function moduleConsole(string $action, string $moduleName): void
{
    $cmd = 'php ' . escapeshellarg(_PS_ROOT_DIR_ . '/bin/console') . ' prestashop:module ' . escapeshellarg($action) . ' ' . escapeshellarg($moduleName) . ' 2>&1';
    exec($cmd, $output, $code);
    if ($code !== 0) {
        fwrite(STDERR, "{$action} {$moduleName} failed:\n" . implode("\n", $output) . "\n");
        exit(1);
    }
}

if (!Module::isInstalled('nf_deuna')) {
    moduleConsole('install', 'nf_deuna');
    out('Installed nf_deuna (order state #' . (int) Db::getInstance()->getValue('SELECT value FROM `' . _DB_PREFIX_ . 'configuration` WHERE name = "NF_DEUNA_OS_WAITING"') . ')');
} elseif (!Module::isEnabled('nf_deuna')) {
    moduleConsole('enable', 'nf_deuna');
    out('Enabled nf_deuna');
} else {
    out('nf_deuna already installed and enabled');
}

// --- Bank transfer (ps_wirepayment) ------------------------------------------

$wire = Module::getInstanceByName('ps_wirepayment');
if (!$wire || !Module::isInstalled('ps_wirepayment')) {
    fwrite(STDERR, "ps_wirepayment is not installed\n");
    exit(1);
}
if (!Module::isEnabled('ps_wirepayment')) {
    $wire->enable();
    out('Enabled ps_wirepayment');
}

foreach (BANK_WIRE_PLACEHOLDERS as $key => $placeholder) {
    if (trim((string) Configuration::get($key)) === '') {
        Configuration::updateValue($key, $placeholder);
        out("Set {$key} placeholder");
    }
}

$customText = [];
$customTextChanged = false;
foreach (Language::getIDs(false) as $langId) {
    $current = (string) Configuration::get('BANK_WIRE_CUSTOM_TEXT', $langId);
    $customText[$langId] = $current;
    if (trim($current) === '') {
        $customText[$langId] = BANK_WIRE_CUSTOM_TEXT;
        $customTextChanged = true;
    }
}
if ($customTextChanged) {
    Configuration::updateValue('BANK_WIRE_CUSTOM_TEXT', $customText);
    out('Set BANK_WIRE_CUSTOM_TEXT');
}

// Currency restriction lives in module_currency (Payments > Preferences in the back office).
$db = Db::getInstance();
$wireId = (int) $wire->id;
$wireCurrencies = array_map('intval', array_column(
    $db->executeS('SELECT DISTINCT id_currency FROM `' . _DB_PREFIX_ . 'module_currency` WHERE id_module = ' . $wireId) ?: [],
    'id_currency'
));
if ($wireCurrencies !== [$usdId]) {
    $db->delete('module_currency', 'id_module = ' . $wireId);
    foreach (Shop::getShops(false, null, true) as $shopId) {
        $db->insert('module_currency', ['id_module' => $wireId, 'id_shop' => (int) $shopId, 'id_currency' => $usdId]);
    }
    out('Restricted ps_wirepayment to USD');
}
if ((string) Configuration::get('BANK_WIRE_CURRENCIES') !== (string) $usdId) {
    Configuration::updateValue('BANK_WIRE_CURRENCIES', (string) $usdId);
    out('Set BANK_WIRE_CURRENCIES to USD');
}

// --- Disable cheque and cash on delivery ---------------------------------------

foreach (DISABLED_PAYMENT_MODULES as $moduleName) {
    if (Module::isInstalled($moduleName) && Module::isEnabled($moduleName)) {
        Module::getInstanceByName($moduleName)->disable();
        out("Disabled {$moduleName}");
    }
}

// --- Caches ----------------------------------------------------------------

Tools::clearSf2Cache();
Tools::clearSmartyCache();
Tools::clearXMLCache();
Media::clearCache();
out('Caches cleared');

if (function_exists('posix_geteuid') && posix_geteuid() === 0) {
    foreach ([_PS_ROOT_DIR_ . '/var/cache', _PS_ROOT_DIR_ . '/var/logs', _PS_ROOT_DIR_ . '/img/tmp', _PS_ROOT_DIR_ . '/img/os', _PS_IMG_DIR_ . 'nf_deuna'] as $path) {
        if (file_exists($path)) {
            exec('chown -R ' . WEB_USER . ':' . WEB_USER . ' ' . escapeshellarg($path));
        }
    }
    out('Ownership restored to ' . WEB_USER);
}

out('Done');
