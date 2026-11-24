<?php
/**
 * Moves the store to another domain (and turns HTTPS on or off).
 *
 * Updates the shop domain settings and shop_url, replaces the old domain in banner links
 * and in configuration values that contain it, regenerates .htaccess and clears caches.
 * Running it again with the same domain changes nothing.
 *
 * Options:
 *   --domain=tienda.com      new domain, without http:// (a port is allowed: localhost:8099)
 *   --ssl=1|0                1 = HTTPS everywhere (default), 0 = plain HTTP (local tests)
 *
 * Usage (always as www-data):
 *   docker compose exec -u www-data prestashop php /opt/natufitness/scripts/change-domain.php --domain=tienda.com
 */
if (PHP_SAPI !== 'cli') {
    exit("CLI only\n");
}

$options = getopt('', ['domain:', 'ssl::', 'help']);
if (isset($options['help']) || empty($options['domain'])) {
    $doc = file_get_contents(__FILE__);
    preg_match('#/\*\*(.*?)\*/#s', $doc, $match);
    echo preg_replace('/^\s*\* ?/m', '', $match[1] ?? '') . PHP_EOL;
    exit(isset($options['help']) ? 0 : 2);
}

$domain = strtolower(trim((string) $options['domain']));
$domain = preg_replace('#^https?://#', '', rtrim($domain, '/'));
if (!preg_match('/^[a-z0-9]([a-z0-9-]*[a-z0-9])?(\.[a-z0-9]([a-z0-9-]*[a-z0-9])?)*(:\d{1,5})?$/', $domain)) {
    fwrite(STDERR, "Invalid domain: $domain\n");
    exit(2);
}
$ssl = !isset($options['ssl']) || $options['ssl'] === false || (string) $options['ssl'] !== '0';

$_SERVER['REQUEST_METHOD'] = 'GET';
$_SERVER['HTTP_HOST'] = $domain;
require '/var/www/html/config/config.inc.php';

$db = Db::getInstance();
$oldDomain = (string) Configuration::get('PS_SHOP_DOMAIN');
$oldSsl = (bool) Configuration::get('PS_SSL_ENABLED');
echo "Domain: $oldDomain -> $domain | HTTPS: " . ($oldSsl ? 'on' : 'off') . ' -> ' . ($ssl ? 'on' : 'off') . PHP_EOL;

// Domain-wide settings (global and every shop-specific copy).
foreach (['PS_SHOP_DOMAIN' => $domain, 'PS_SHOP_DOMAIN_SSL' => $domain, 'PS_SSL_ENABLED' => (int) $ssl, 'PS_SSL_ENABLED_EVERYWHERE' => (int) $ssl] as $key => $value) {
    $db->update('configuration', ['value' => pSQL((string) $value)], "name = '" . pSQL($key) . "'");
    Configuration::updateGlobalValue($key, $value);
}
$db->update('shop_url', ['domain' => pSQL($domain), 'domain_ssl' => pSQL($domain)], 'main = 1');

$replaced = 0;
if ($oldDomain !== '' && $oldDomain !== $domain) {
    // Absolute links typed by hand (banners, texts) still point at the old domain.
    foreach ([['homeslider_slides_lang', 'url'], ['configuration', 'value'], ['configuration_lang', 'value']] as [$table, $column]) {
        $db->execute(
            'UPDATE `' . _DB_PREFIX_ . $table . '` SET `' . $column . '` = REPLACE(`' . $column . '`, \'' . pSQL($oldDomain) . '\', \'' . pSQL($domain) . '\')'
            . ' WHERE `' . $column . '` LIKE \'%' . pSQL($oldDomain) . '%\''
        );
        $replaced += (int) $db->Affected_Rows();
    }
}
echo "Links updated: $replaced" . PHP_EOL;

Configuration::clearConfigurationCacheForTesting();
Tools::generateHtaccess();
Tools::clearSmartyCache();
Tools::clearSf2Cache();
Media::clearCache();
echo "OK .htaccess regenerated, caches cleared." . PHP_EOL;
echo 'Store: ' . ($ssl ? 'https' : 'http') . "://$domain/" . PHP_EOL;
