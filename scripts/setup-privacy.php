<?php
/**
 * Creates the "Política de privacidad y cookies" CMS page (once) and stores its id in
 * NF_PRIVACY_CMS_ID, which the footer and the cookie notice link to.
 *
 * Idempotent. Usage: docker compose exec -T -u www-data prestashop php /opt/natufitness/scripts/setup-privacy.php
 * The text is a general template: the owner should review it with a lawyer before selling.
 */

if (PHP_SAPI !== 'cli') {
    exit("CLI only\n");
}

$_SERVER['REQUEST_METHOD'] = 'GET';
$_SERVER['HTTP_HOST'] = getenv('PS_DOMAIN') ?: 'localhost';
require '/var/www/html/config/config.inc.php';

const PRIVACY_SLUG = 'politica-de-privacidad-y-cookies';

$langId = (int) Configuration::get('PS_LANG_DEFAULT');
$existing = (int) Db::getInstance()->getValue(
    'SELECT id_cms FROM `' . _DB_PREFIX_ . 'cms_lang` WHERE link_rewrite = \'' . pSQL(PRIVACY_SLUG) . '\''
);

if ($existing) {
    Configuration::updateValue('NF_PRIVACY_CMS_ID', $existing);
    echo "Privacy page already exists (#{$existing})\n";
    exit(0);
}

$content = <<<'HTML'
<h2>Política de privacidad y cookies</h2>
<p>En NatuFitness (Farmacia Naturista, Riobamba, Ecuador) cuidamos tus datos personales conforme a la Ley Orgánica de Protección de Datos Personales del Ecuador.</p>
<h3>Qué datos recogemos</h3>
<p>Nombre, cédula o RUC (solo si pides factura), correo, teléfono y dirección de entrega, para procesar tus pedidos, emitir tu factura y contactarte por WhatsApp sobre tu compra.</p>
<h3>Con quién los compartimos</h3>
<p>Solo con quienes hacen posible tu pedido: el courier que lo entrega y la entidad que procesa el pago. No vendemos tus datos.</p>
<h3>Cookies</h3>
<p><strong>Necesarias:</strong> mantienen tu carrito y tu sesión. Sin ellas la tienda no funciona.</p>
<p><strong>De medición (opcionales):</strong> nos ayudan a entender cómo se usa la tienda para mejorarla. Solo se usan si las aceptas en el aviso de cookies. Puedes cambiar tu elección borrando las cookies de tu navegador.</p>
<h3>Tus derechos</h3>
<p>Puedes pedir acceso, corrección o eliminación de tus datos escribiéndonos por WhatsApp o en nuestro local.</p>
HTML;

$cms = new CMS();
$cms->id_cms_category = 1;
$cms->active = true;
$cms->indexation = true;
foreach (Language::getIDs(false) as $id) {
    $cms->meta_title[$id] = 'Política de privacidad y cookies';
    $cms->meta_description[$id] = 'Cómo NatuFitness usa tus datos personales y las cookies.';
    $cms->link_rewrite[$id] = PRIVACY_SLUG;
    $cms->content[$id] = $content;
}
$cms->add();

Configuration::updateValue('NF_PRIVACY_CMS_ID', (int) $cms->id);
Tools::clearSmartyCache();
echo "Created privacy page #{$cms->id}\n";
