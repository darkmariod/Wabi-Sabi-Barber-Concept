<?php
/**
 * NatuFitness branding setup.
 *
 * - Renames the shop entity (installer default "PrestaShop") to PS_SHOP_NAME.
 * - Installs the NatuFitness logo (PS_LOGO, used by emails, invoices and the back office)
 *   and favicon from themes/natufitness/assets/img/.
 * - Replaces the English customer-facing texts of ps_dataprivacy, psgdpr and
 *   ps_emailsubscription with Spanish ones.
 * - Replaces the blockreassurance placeholders ("Security policy (edit with the Customer
 *   Reassurance module)") with the three NatuFitness reassurance items shown in checkout
 *   and on product pages.
 *
 * Texts are written for every language: the store is Spanish-only.
 * Idempotent: safe to run multiple times (prints "unchanged" when nothing to do).
 * Usage: docker compose exec -u www-data prestashop php /opt/natufitness/scripts/setup-branding.php
 */

if (PHP_SAPI !== 'cli') {
    exit("CLI only\n");
}

$_SERVER['REQUEST_METHOD'] = 'GET';
$_SERVER['HTTP_HOST'] = getenv('PS_DOMAIN') ?: 'localhost';
require '/var/www/html/config/config.inc.php';

const WEB_USER = 'www-data';
const THEME_IMG_DIR = _PS_ALL_THEMES_DIR_ . 'natufitness/assets/img/';
const LOGO_FILE = 'logo-natufitness.png';
const FAVICON_FILE = 'favicon-natufitness.ico';

const LANG_TEXTS = [
    // ps_dataprivacy: "Privacidad de los datos del cliente" block on the customer/guest form.
    'CUSTPRIV_MSG_AUTH' => 'Los datos personales que nos proporcionas se usan para procesar tus pedidos, responder tus consultas y emitir tu factura. Puedes modificarlos o eliminarlos en cualquier momento desde «Mi cuenta».',
    // psgdpr: consent checkboxes on the customer form (checkout) and account creation.
    'PSGDPR_CUSTOMER_FORM' => 'Acepto los términos y condiciones y la política de privacidad',
    'PSGDPR_CREATION_FORM' => 'Acepto los términos y condiciones y la política de privacidad',
    // ps_emailsubscription: newsletter footnote.
    'NW_CONDITIONS' => 'Puedes darte de baja en cualquier momento. Encuentra nuestros datos de contacto en el aviso legal.',
];

const REASSURANCE_ICON_DIR = '/modules/blockreassurance/views/img/reassurance/pack2/';
const REASSURANCE_ITEMS = [
    ['icon' => 'security.svg', 'title' => 'Pago seguro', 'description' => 'DeUna o transferencia bancaria'],
    ['icon' => 'carrier.svg', 'title' => 'Envío a todo Ecuador', 'description' => 'Gratis desde $35 · Retiro en Riobamba'],
    ['icon' => 'trust.svg', 'title' => 'Productos originales', 'description' => 'Distribuidores autorizados'],
];
const REASSURANCE_CONFIG = [
    'PSR_HOOK_CHECKOUT' => '1',
    'PSR_HOOK_PRODUCT' => '1',
    'PSR_ICON_COLOR' => '#17507d',
    'PSR_TEXT_COLOR' => '#222222',
];

function out(string $msg): void
{
    echo $msg . PHP_EOL;
}

function setConfig(string $key, string $value): bool
{
    if (Configuration::hasKey($key) && (string) Configuration::get($key) === $value) {
        return false;
    }
    Configuration::updateValue($key, $value);

    return true;
}

/** Copies a theme image into /img when missing or different. Returns true when copied. */
function installImage(string $file): bool
{
    $source = THEME_IMG_DIR . $file;
    $target = _PS_IMG_DIR_ . $file;
    if (!is_file($source)) {
        fwrite(STDERR, "Missing {$source} (is the theme bind mount active?)\n");
        exit(1);
    }
    if (is_file($target) && sha1_file($source) === sha1_file($target)) {
        return false;
    }
    if (!copy($source, $target)) {
        fwrite(STDERR, "Could not copy {$source} to {$target}\n");
        exit(1);
    }

    return true;
}

$context = Context::getContext();
$context->shop = new Shop((int) Configuration::get('PS_SHOP_DEFAULT'));
Shop::setContext(Shop::CONTEXT_SHOP, (int) $context->shop->id);
$context->language = new Language((int) Configuration::get('PS_LANG_DEFAULT'));
$languageIds = array_map('intval', array_column(Language::getLanguages(false), 'id_lang'));
$changed = false;

// --- Shop name -----------------------------------------------------------------
// The installer leaves the shop entity named "PrestaShop"; some modules (e.g. bank
// transfer confirmation "Su pedido en %s se ha completado") print that name.

$shopName = (string) Configuration::get('PS_SHOP_NAME');
if ($shopName !== '' && $context->shop->name !== $shopName) {
    $context->shop->name = $shopName;
    if (!$context->shop->update()) {
        fwrite(STDERR, "Could not rename shop to {$shopName}\n");
        exit(1);
    }
    out("Shop renamed to {$shopName}");
    $changed = true;
} else {
    out('Shop name unchanged');
}

// --- Logo and favicon --------------------------------------------------------

// The default logo/favicon are applied once. After that the owner manages them in
// Diseño > Tema y logotipo, and re-running this script must not overwrite them.
const LOGO_APPLIED_FLAG = 'NF_BRANDING_LOGO_APPLIED';

function applyDefaultLogo(): bool
{
    $imagesChanged = installImage(LOGO_FILE);
    $imagesChanged = installImage(FAVICON_FILE) || $imagesChanged;

    [$logoWidth, $logoHeight] = getimagesize(_PS_IMG_DIR_ . LOGO_FILE);
    $logoKeys = ['PS_LOGO'];
    // Mail/invoice logos fall back to PS_LOGO; only override them when they were set before.
    foreach (['PS_LOGO_MAIL', 'PS_LOGO_INVOICE'] as $key) {
        if (Configuration::get($key)) {
            $logoKeys[] = $key;
        }
    }
    $configChanged = false;
    foreach ($logoKeys as $key) {
        $configChanged = setConfig($key, LOGO_FILE) || $configChanged;
    }
    $configChanged = setConfig('SHOP_LOGO_WIDTH', (string) $logoWidth) || $configChanged;
    $configChanged = setConfig('SHOP_LOGO_HEIGHT', (string) $logoHeight) || $configChanged;
    $configChanged = setConfig('PS_FAVICON', FAVICON_FILE) || $configChanged;

    if (!$imagesChanged && !$configChanged) {
        out('Logo and favicon unchanged');

        return false;
    }
    // Busts browser caches of the logo/favicon URLs.
    Configuration::updateValue('PS_IMG_UPDATE_TIME', time());
    out('Logo and favicon installed (' . implode(', ', $logoKeys) . ', PS_FAVICON)');

    return true;
}

$currentLogo = (string) Configuration::get('PS_LOGO');
$ownerLogoSet = $currentLogo !== '' && !in_array($currentLogo, ['logo.png', LOGO_FILE], true);

if (Configuration::get(LOGO_APPLIED_FLAG) || $ownerLogoSet) {
    out('Logo and favicon managed in the back office, left as is');
} else {
    $changed = applyDefaultLogo() || $changed;
}
if (!Configuration::get(LOGO_APPLIED_FLAG)) {
    Configuration::updateValue(LOGO_APPLIED_FLAG, 1);
}

// --- Customer-facing module texts ------------------------------------------

foreach (LANG_TEXTS as $key => $text) {
    $values = [];
    $differs = false;
    foreach ($languageIds as $idLang) {
        $values[$idLang] = $text;
        if ((string) Configuration::get($key, $idLang) !== $text) {
            $differs = true;
        }
    }
    if ($differs) {
        Configuration::updateValue($key, $values, true);
        out("{$key} set in Spanish");
        $changed = true;
    } else {
        out("{$key} unchanged");
    }
}

// --- Reassurance block (blockreassurance) ----------------------------------

$module = Module::getInstanceByName('blockreassurance');
if (!$module || !Module::isInstalled('blockreassurance')) {
    out('WARNING: blockreassurance not installed; reassurance items skipped');
} else {
    require_once _PS_MODULE_DIR_ . 'blockreassurance/classes/ReassuranceActivity.php';

    foreach (REASSURANCE_CONFIG as $key => $value) {
        if (setConfig($key, $value)) {
            out("{$key} set to {$value}");
            $changed = true;
        }
    }

    $rows = Db::getInstance()->executeS(
        'SELECT id_psreassurance FROM `' . _DB_PREFIX_ . 'psreassurance` ORDER BY position, id_psreassurance'
    ) ?: [];
    $ids = array_map('intval', array_column($rows, 'id_psreassurance'));

    foreach (REASSURANCE_ITEMS as $index => $item) {
        $block = new ReassuranceActivity($ids[$index] ?? null);
        $isNew = !Validate::isLoadedObject($block);
        $icon = REASSURANCE_ICON_DIR . $item['icon'];
        $description = htmlspecialchars($item['description'], ENT_QUOTES, 'UTF-8');

        $differs = $isNew
            || $block->icon !== $icon
            || !empty($block->custom_icon)
            || !(int) $block->status
            || (int) $block->position !== $index + 1;
        foreach ($languageIds as $idLang) {
            $differs = $differs
                || ($block->title[$idLang] ?? null) !== $item['title']
                || ($block->description[$idLang] ?? null) !== $description;
        }
        if (!$differs) {
            out("Reassurance item \"{$item['title']}\" unchanged");
            continue;
        }

        $block->icon = $icon;
        $block->custom_icon = '';
        $block->status = 1;
        $block->position = $index + 1;
        $block->type_link = 0;
        $block->id_cms = 0;
        $block->title = [];
        $block->description = [];
        $block->link = [];
        foreach ($languageIds as $idLang) {
            $block->title[$idLang] = $item['title'];
            $block->description[$idLang] = $description;
            $block->link[$idLang] = '';
        }
        if ($isNew) {
            $block->date_add = date('Y-m-d H:i:s');
        }
        $block->date_upd = date('Y-m-d H:i:s');
        if (!$block->save()) {
            fwrite(STDERR, "Could not save reassurance item \"{$item['title']}\"\n");
            exit(1);
        }
        out('Reassurance item "' . $item['title'] . '" ' . ($isNew ? 'created' : 'updated'));
        $changed = true;
    }

    // Extra items beyond the three NatuFitness ones are disabled, not deleted.
    foreach (array_slice($ids, count(REASSURANCE_ITEMS)) as $extraId) {
        $extra = new ReassuranceActivity($extraId);
        if ((int) $extra->status) {
            $extra->status = 0;
            $extra->save();
            out("Reassurance item #{$extraId} disabled");
            $changed = true;
        }
    }
}

// --- Caches ----------------------------------------------------------------

if ($changed) {
    // Module widgets (blockreassurance) are cached by Smarty.
    Tools::clearSmartyCache();
    Media::clearCache();
    out('Caches cleared');
}

// Files generated while running as root must stay writable by Apache.
if (function_exists('posix_geteuid') && posix_geteuid() === 0) {
    foreach ([_PS_ROOT_DIR_ . '/var/cache', _PS_IMG_DIR_ . LOGO_FILE, _PS_IMG_DIR_ . FAVICON_FILE] as $path) {
        if (file_exists($path)) {
            exec('chown -R ' . WEB_USER . ':' . WEB_USER . ' ' . escapeshellarg($path));
        }
    }
    out('Ownership restored to ' . WEB_USER);
}

out($changed ? 'Done' : 'Done (nothing to change)');
