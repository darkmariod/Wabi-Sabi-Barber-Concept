<?php
/**
 * Enables the NatuFitness child theme for the default shop and clears caches.
 *
 * Idempotent: safe to run multiple times.
 * Usage: docker compose exec prestashop php /opt/natufitness/scripts/activate-theme.php
 */

if (PHP_SAPI !== 'cli') {
    exit("CLI only\n");
}

$_SERVER['REQUEST_METHOD'] = 'GET';
$_SERVER['HTTP_HOST'] = getenv('PS_DOMAIN') ?: 'localhost';
require '/var/www/html/config/config.inc.php';

use PrestaShop\PrestaShop\Core\Addon\Theme\ThemeManagerBuilder;

const THEME_NAME = 'natufitness';
const WEB_USER = 'www-data';

function out(string $msg): void
{
    echo $msg . PHP_EOL;
}

if (!is_file(_PS_ALL_THEMES_DIR_ . THEME_NAME . '/config/theme.yml')) {
    fwrite(STDERR, 'Theme not found at ' . _PS_ALL_THEMES_DIR_ . THEME_NAME . " (is the bind mount in docker-compose.yml active?)\n");
    exit(1);
}

$context = Context::getContext();
$context->shop = new Shop((int) Configuration::get('PS_SHOP_DEFAULT'));
Shop::setContext(Shop::CONTEXT_SHOP, (int) $context->shop->id);

// --- Spanish translations --------------------------------------------------
// The install ships es-ES catalogue files; download them only when missing.

$language = new Language((int) Configuration::get('PS_LANG_DEFAULT'));
$catalogueDir = _PS_ROOT_DIR_ . '/translations/' . $language->locale;
if (!is_dir($catalogueDir) || count(glob($catalogueDir . '/*.xlf')) === 0) {
    $errors = [];
    if (Language::downloadAndInstallLanguagePack($language->iso_code, _PS_VERSION_, $errors)) {
        out("Installed {$language->locale} language pack");
    } else {
        out("WARNING: could not install {$language->locale} language pack: " . implode('; ', array_map('strval', $errors)));
    }
} else {
    out("Translations for {$language->locale} already present");
}

// --- Enable theme ----------------------------------------------------------
// Enabling also applies config/theme.yml hooks: displayHome keeps only
// ps_featuredproducts. The hero (ps_imageslider) and promo block (ps_banner)
// are rendered as widgets by templates/index.tpl instead of on displayHome.

$themeManager = (new ThemeManagerBuilder($context, Db::getInstance()))->build();
if (!$themeManager->enable(THEME_NAME, true)) {
    fwrite(STDERR, 'Theme "' . THEME_NAME . "\" failed validation\n");
    exit(1);
}
out('Theme "' . THEME_NAME . '" enabled for shop ' . (int) $context->shop->id);

// Demo homepage modules stay installed but must not render on displayHome
// even if a later theme reset re-hooks them.
foreach (['ps_imageslider', 'ps_banner', 'ps_customtext', 'ps_specials', 'ps_newproducts', 'ps_bestsellers'] as $moduleName) {
    $module = Module::getInstanceByName($moduleName);
    if ($module && $module->isRegisteredInHook('displayHome')) {
        $module->unregisterHook('displayHome');
        out("Unhooked {$moduleName} from displayHome");
    }
}

// The theme renders ps_imageslider with its own markup and script (assets/js/custom.js),
// so the module's responsiveslides CSS/JS loaded on displayHeader is not needed.
$slider = Module::getInstanceByName('ps_imageslider');
if ($slider && $slider->isRegisteredInHook('displayHeader')) {
    $slider->unregisterHook('displayHeader');
    out('Unhooked ps_imageslider from displayHeader');
}

// --- Caches ----------------------------------------------------------------

Tools::clearSf2Cache();
Tools::clearSmartyCache();
Tools::clearXMLCache();
Media::clearCache();
out('Caches cleared');

// Files generated while running as root must stay writable by Apache.
if (function_exists('posix_geteuid') && posix_geteuid() === 0) {
    foreach ([_PS_ROOT_DIR_ . '/var/cache', _PS_ROOT_DIR_ . '/config/themes', _PS_ROOT_DIR_ . '/img/tmp'] as $path) {
        if (file_exists($path)) {
            exec('chown -R ' . WEB_USER . ':' . WEB_USER . ' ' . escapeshellarg($path));
        }
    }
    out('Ownership restored to ' . WEB_USER);
}

out('Done');
