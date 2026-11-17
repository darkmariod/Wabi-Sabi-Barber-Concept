<?php
/**
 * NatuFitness owner-editable homepage content.
 *
 * - Generates the default hero slide images (1920x600) and promo banner image (1200x600)
 *   with GD into themes/natufitness/assets/img/defaults/ (only when missing) and copies
 *   them into the ps_imageslider / ps_banner upload directories.
 * - ps_imageslider: removes the PrestaShop demo slides (sample-1/2/3) and creates the three
 *   NatuFitness slides only when the shop has no other slides (owner edits are kept).
 * - ps_banner: sets image, link and description only while they are still at module defaults.
 * - Manufacturers: creates five placeholder brands when the shop has no brands other than the
 *   PrestaShop demo ones (which are then deactivated, not deleted).
 * - Clears caches when something changed.
 *
 * Everything stays editable in the back office afterwards (see README "Owner-editable content").
 * Idempotent: safe to run multiple times (prints "unchanged" when nothing to do).
 * Usage: docker compose exec -u www-data prestashop php /opt/natufitness/scripts/setup-content.php
 */

if (PHP_SAPI !== 'cli') {
    exit("CLI only\n");
}

$_SERVER['REQUEST_METHOD'] = 'GET';
$_SERVER['HTTP_HOST'] = getenv('PS_DOMAIN') ?: 'localhost';
require '/var/www/html/config/config.inc.php';

const WEB_USER = 'www-data';
const DEFAULTS_DIR = _PS_ALL_THEMES_DIR_ . 'natufitness/assets/img/defaults/';
const SLIDER_IMG_DIR = _PS_MODULE_DIR_ . 'ps_imageslider/images/';
const BANNER_IMG_DIR = _PS_MODULE_DIR_ . 'ps_banner/img/';

const SLIDE_SIZE = [1920, 600];
const PROMO_SIZE = [1200, 600];
const PROMO_FILE = 'natufitness-promo.jpg';

// Jar colors per slide; text lives in the slide fields, never in the image.
const SLIDES = [
    [
        'file' => 'natufitness-slide-1.jpg',
        'jar' => '#17507d',
        'title' => 'SALUD, FUERZA Y VITALIDAD',
        'legend' => 'Distribuidores autorizados',
        'category' => 21,
    ],
    [
        'file' => 'natufitness-slide-2.jpg',
        'jar' => '#4e9a26',
        'title' => 'NUTRICIÓN DEPORTIVA ORIGINAL',
        'legend' => 'Proteínas, creatinas y más',
        'category' => 10,
    ],
    [
        'file' => 'natufitness-slide-3.jpg',
        'jar' => '#0f3a5c',
        'title' => 'MEDICINA NATURAL',
        'legend' => 'Lo mejor de la naturaleza',
        'category' => 16,
    ],
];
// Categories from scripts/seed.php: 10 Nutrición deportiva, 16 Medicina natural, 21 Combos.
const PROMO_CATEGORY = 10;
const PROMO_DESC = 'Proteínas, creatinas, quemadores, vitaminas y productos naturistas originales. Asesoría personalizada en nuestro local de Riobamba o por WhatsApp.';
const BANNER_DEFAULT_IMAGES = ['', 'sale70.png'];

const PLACEHOLDER_BRANDS = ['ON', 'BSN', 'MuscleTech', 'Universal', 'Dymatize'];
// Installed by PrestaShop with the demo catalog.
const DEMO_BRANDS = ['Studio Design', 'Graphic Corner'];

function out(string $msg): void
{
    echo $msg . PHP_EOL;
}

function fail(string $msg): void
{
    fwrite(STDERR, $msg . PHP_EOL);
    exit(1);
}

/** @return int[] [r, g, b] */
function hexToRgb(string $hex): array
{
    $hex = ltrim($hex, '#');

    return [hexdec(substr($hex, 0, 2)), hexdec(substr($hex, 2, 2)), hexdec(substr($hex, 4, 2))];
}

function allocate($img, string $hex, int $alpha = 0): int
{
    [$r, $g, $b] = hexToRgb($hex);

    return imagecolorallocatealpha($img, $r, $g, $b, $alpha);
}

function roundedRect($img, int $x1, int $y1, int $x2, int $y2, int $radius, int $color): void
{
    imagefilledrectangle($img, $x1 + $radius, $y1, $x2 - $radius, $y2, $color);
    imagefilledrectangle($img, $x1, $y1 + $radius, $x2, $y2 - $radius, $color);
    $d = $radius * 2;
    imagefilledellipse($img, $x1 + $radius, $y1 + $radius, $d, $d, $color);
    imagefilledellipse($img, $x2 - $radius, $y1 + $radius, $d, $d, $color);
    imagefilledellipse($img, $x1 + $radius, $y2 - $radius, $d, $d, $color);
    imagefilledellipse($img, $x2 - $radius, $y2 - $radius, $d, $d, $color);
}

/** Fills the image with a linear gradient through the brand stops along the given angle. */
function brandGradient($img, int $w, int $h, float $angleDeg): void
{
    $stops = [[0.0, hexToRgb('#0b2a44')], [0.55, hexToRgb('#17507d')], [1.0, hexToRgb('#2f7a4a')]];
    $rad = deg2rad($angleDeg);
    $dx = cos($rad);
    $dy = sin($rad);
    $len = abs($w * $dx) + abs($h * $dy);
    // Gradient is computed per 4px block and stretched; smooth enough after JPEG encoding.
    $step = 4;
    for ($y = 0; $y < $h; $y += $step) {
        for ($x = 0; $x < $w; $x += $step) {
            $t = (($x - $w / 2) * $dx + ($y - $h / 2) * $dy) / $len + 0.5;
            $t = max(0.0, min(1.0, $t));
            for ($i = 1; $i < count($stops); ++$i) {
                if ($t <= $stops[$i][0]) {
                    break;
                }
            }
            [$t0, $c0] = $stops[$i - 1];
            [$t1, $c1] = $stops[$i];
            $k = ($t - $t0) / max(0.0001, $t1 - $t0);
            $color = imagecolorallocate(
                $img,
                (int) round($c0[0] + ($c1[0] - $c0[0]) * $k),
                (int) round($c0[1] + ($c1[1] - $c0[1]) * $k),
                (int) round($c0[2] + ($c1[2] - $c0[2]) * $k)
            );
            imagefilledrectangle($img, $x, $y, $x + $step - 1, $y + $step - 1, $color);
        }
    }
}

/** Draws the supplement jar (same proportions as the former SVG symbol, 120x150 units). */
function drawJar($img, int $x, int $y, float $scale, string $bodyHex): void
{
    $u = static function (float $v) use ($scale): int {
        return (int) round($v * $scale);
    };
    $glow = allocate($img, '#ffffff', 112);
    imagefilledellipse($img, $x + $u(60), $y + $u(80), $u(230), $u(230), $glow);
    $shadow = allocate($img, '#000000', 90);
    imagefilledellipse($img, $x + $u(60), $y + $u(147), $u(110), $u(12), $shadow);

    roundedRect($img, $x + $u(22), $y + $u(6), $x + $u(98), $y + $u(28), $u(4), allocate($img, '#111111'));
    roundedRect($img, $x + $u(14), $y + $u(24), $x + $u(106), $y + $u(144), $u(10), allocate($img, $bodyHex));
    imagefilledrectangle($img, $x + $u(14), $y + $u(58), $x + $u(106), $y + $u(110), allocate($img, '#ffffff', 8));
    roundedRect($img, $x + $u(24), $y + $u(68), $x + $u(84), $y + $u(77), $u(2), allocate($img, $bodyHex));
    roundedRect($img, $x + $u(24), $y + $u(83), $x + $u(66), $y + $u(89), $u(2), allocate($img, '#999999'));
    roundedRect($img, $x + $u(24), $y + $u(94), $x + $u(74), $y + $u(100), $u(2), allocate($img, '#999999'));
}

/**
 * Renders at 2x and downsamples (cheap anti-aliasing) then saves a JPEG.
 *
 * @param callable $draw function ($img, int $w, int $h): void, receives the 2x canvas
 */
function renderJpeg(string $path, int $w, int $h, callable $draw): void
{
    $big = imagecreatetruecolor($w * 2, $h * 2);
    imagealphablending($big, true);
    $draw($big, $w * 2, $h * 2);
    $img = imagecreatetruecolor($w, $h);
    imagecopyresampled($img, $big, 0, 0, 0, 0, $w, $h, $w * 2, $h * 2);
    imageinterlace($img, true);
    if (!imagejpeg($img, $path, 82)) {
        fail("Could not write {$path}");
    }
    imagedestroy($big);
    imagedestroy($img);
}

/** Generates the default images in the theme (sources kept in the repository). */
function generateDefaults(): bool
{
    if (!is_dir(DEFAULTS_DIR) && !mkdir(DEFAULTS_DIR, 0775, true)) {
        fail('Could not create ' . DEFAULTS_DIR . ' (is the theme bind mount active?)');
    }
    $generated = false;
    foreach (SLIDES as $index => $slide) {
        $path = DEFAULTS_DIR . $slide['file'];
        if (is_file($path)) {
            continue;
        }
        renderJpeg($path, SLIDE_SIZE[0], SLIDE_SIZE[1], static function ($img, int $w, int $h) use ($slide, $index): void {
            brandGradient($img, $w, $h, 10 + $index * 8);
            // Jar on the left third; the overlay text sits on the right (centered on mobile).
            $scale = $h * 0.48 / 150;
            drawJar($img, (int) round($w * 0.2), (int) round($h * 0.2), $scale, $slide['jar']);
        });
        out("Generated {$path}");
        $generated = true;
    }
    $path = DEFAULTS_DIR . PROMO_FILE;
    if (!is_file($path)) {
        renderJpeg($path, PROMO_SIZE[0], PROMO_SIZE[1], static function ($img, int $w, int $h): void {
            brandGradient($img, $w, $h, 35);
            $scale = $h * 0.62 / 150;
            drawJar($img, (int) round($w / 2 - 60 * $scale), (int) round($h * 0.16), $scale, '#0f3a5c');
        });
        out("Generated {$path}");
        $generated = true;
    }

    return $generated;
}

/** Copies a default image into a module upload dir when missing or different. */
function installImage(string $file, string $targetDir): bool
{
    $source = DEFAULTS_DIR . $file;
    $target = $targetDir . $file;
    if (is_file($target) && sha1_file($source) === sha1_file($target)) {
        return false;
    }
    if (!copy($source, $target)) {
        fail("Could not copy {$source} to {$target}");
    }

    return true;
}

/** Shop-relative URL of a category, resolved by ps_imageslider against the current domain. */
function relativeCategoryUrl(Link $link, int $idCategory, int $idLang): string
{
    $url = $link->getCategoryLink($idCategory, null, $idLang);
    $base = $link->getBaseLink();

    return strpos($url, $base) === 0 ? substr($url, strlen($base)) : $url;
}

$context = Context::getContext();
$context->shop = new Shop((int) Configuration::get('PS_SHOP_DEFAULT'));
Shop::setContext(Shop::CONTEXT_SHOP, (int) $context->shop->id);
$context->language = new Language((int) Configuration::get('PS_LANG_DEFAULT'));
$context->link = new Link();
$idShop = (int) $context->shop->id;
$languageIds = array_map('intval', array_column(Language::getLanguages(false), 'id_lang'));
$db = Db::getInstance();
$changed = false;

if (!function_exists('imagecreatetruecolor')) {
    fail('PHP GD extension is required');
}

// --- Default images ----------------------------------------------------------

$changed = generateDefaults() || $changed;

// --- Hero slides (ps_imageslider) ---------------------------------------------

if (!Module::isInstalled('ps_imageslider')) {
    out('WARNING: ps_imageslider not installed; slides skipped');
} else {
    require_once _PS_MODULE_DIR_ . 'ps_imageslider/Ps_HomeSlide.php';

    $imagesCopied = false;
    foreach (SLIDES as $slide) {
        $imagesCopied = installImage($slide['file'], SLIDER_IMG_DIR) || $imagesCopied;
    }
    if ($imagesCopied) {
        out('Slide images copied to ' . SLIDER_IMG_DIR);
        $changed = true;
    }

    $rows = $db->executeS(
        'SELECT hs.id_homeslider_slides AS id, GROUP_CONCAT(hssl.image) AS images
        FROM `' . _DB_PREFIX_ . 'homeslider` hs
        LEFT JOIN `' . _DB_PREFIX_ . 'homeslider_slides_lang` hssl ON hssl.id_homeslider_slides = hs.id_homeslider_slides
        WHERE hs.id_shop = ' . $idShop . '
        GROUP BY hs.id_homeslider_slides'
    ) ?: [];

    $remaining = 0;
    foreach ($rows as $row) {
        if (preg_match('/(^|,)sample-\d+(_rtl)?\.jpg(,|$)/', (string) $row['images'])) {
            $demo = new Ps_HomeSlide((int) $row['id']);
            // Ps_HomeSlide::delete keeps files named sample-*, so module samples stay on disk.
            if (!$demo->delete()) {
                fail("Could not delete demo slide #{$row['id']}");
            }
            out("Demo slide #{$row['id']} removed");
            $changed = true;
        } else {
            ++$remaining;
        }
    }

    if ($remaining > 0) {
        out("Slides unchanged ({$remaining} existing slide(s) kept)");
    } else {
        foreach (SLIDES as $index => $data) {
            $slide = new Ps_HomeSlide();
            $slide->position = $index + 1;
            $slide->active = 1;
            foreach ($languageIds as $idLang) {
                $slide->title[$idLang] = $data['title'];
                $slide->legend[$idLang] = $data['legend'];
                $slide->description[$idLang] = '';
                $slide->url[$idLang] = relativeCategoryUrl($context->link, $data['category'], $idLang);
                $slide->image[$idLang] = $data['file'];
            }
            if (!$slide->add()) {
                fail("Could not create slide \"{$data['title']}\"");
            }
            out("Slide \"{$data['title']}\" created");
        }
        $changed = true;
    }
}

// --- Promo banner (ps_banner) -------------------------------------------------

if (!Module::isInstalled('ps_banner')) {
    out('WARNING: ps_banner not installed; promo banner skipped');
} else {
    if (installImage(PROMO_FILE, BANNER_IMG_DIR)) {
        out('Promo image copied to ' . BANNER_IMG_DIR);
        $changed = true;
    }

    $atDefaults = true;
    foreach ($languageIds as $idLang) {
        $atDefaults = $atDefaults
            && in_array((string) Configuration::get('BANNER_IMG', $idLang), BANNER_DEFAULT_IMAGES, true)
            && (string) Configuration::get('BANNER_LINK', $idLang) === ''
            && (string) Configuration::get('BANNER_DESC', $idLang) === '';
    }
    if (!$atDefaults) {
        out('Promo banner unchanged (already configured)');
    } else {
        $values = ['BANNER_IMG' => [], 'BANNER_LINK' => [], 'BANNER_DESC' => []];
        foreach ($languageIds as $idLang) {
            $values['BANNER_IMG'][$idLang] = PROMO_FILE;
            // ps_banner prefixes non-http links with http://, so the link must be absolute.
            $values['BANNER_LINK'][$idLang] = $context->link->getCategoryLink(PROMO_CATEGORY, null, $idLang);
            $values['BANNER_DESC'][$idLang] = PROMO_DESC;
        }
        foreach ($values as $key => $value) {
            Configuration::updateValue($key, $value);
        }
        out('Promo banner configured');
        $changed = true;
    }
}

// --- Brands (manufacturers) ----------------------------------------------------

$brands = $db->executeS('SELECT id_manufacturer, name, active FROM `' . _DB_PREFIX_ . 'manufacturer`') ?: [];
$ownBrands = array_filter($brands, static function (array $row): bool {
    return !in_array($row['name'], DEMO_BRANDS, true);
});

if ($ownBrands) {
    out('Brands unchanged (' . count($ownBrands) . ' brand(s) already exist)');
} else {
    foreach ($brands as $row) {
        if ((int) $row['active']) {
            $demo = new Manufacturer((int) $row['id_manufacturer']);
            $demo->active = false;
            if (!$demo->update()) {
                fail("Could not deactivate demo brand \"{$row['name']}\"");
            }
            out("Demo brand \"{$row['name']}\" deactivated");
        }
    }
    foreach (PLACEHOLDER_BRANDS as $name) {
        $brand = new Manufacturer();
        $brand->name = $name;
        $brand->active = true;
        if (!$brand->add()) {
            fail("Could not create brand \"{$name}\"");
        }
        out("Brand \"{$name}\" created");
    }
    $changed = true;
}

// --- Caches --------------------------------------------------------------------

if ($changed) {
    // ps_imageslider, ps_banner and ps_brandlist widgets are cached by Smarty.
    Tools::clearSmartyCache();
    Media::clearCache();
    out('Caches cleared');
}

// Files generated while running as root must stay writable by Apache.
if (function_exists('posix_geteuid') && posix_geteuid() === 0) {
    $paths = [_PS_ROOT_DIR_ . '/var/cache', BANNER_IMG_DIR . PROMO_FILE];
    foreach (SLIDES as $slide) {
        $paths[] = SLIDER_IMG_DIR . $slide['file'];
    }
    foreach ($paths as $path) {
        if (file_exists($path)) {
            exec('chown -R ' . WEB_USER . ':' . WEB_USER . ' ' . escapeshellarg($path));
        }
    }
    out('Ownership restored to ' . WEB_USER);
}

out($changed ? 'Done' : 'Done (nothing to change)');
