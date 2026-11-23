<?php
/**
 * Connects the shop's email to Gmail, so that on every purchase:
 *  - the owner gets an automatic "new order" email (ps_emailalerts, to the same Gmail),
 *  - the customer gets the order confirmation and status emails.
 * Orders are always in the admin panel regardless of email.
 *
 * Needs a Google "app password" (16 characters) for the Gmail account. It is typed by the
 * person running the script (hidden input), stored only in the shop database, and checked
 * by sending a test email before anything is saved.
 *
 * Usage (interactive, so the password is typed, not passed as an argument):
 *   docker compose exec -it -u www-data prestashop php /opt/natufitness/scripts/setup-mail.php [gmail]
 * Default Gmail: natufitness4@gmail.com
 */

if (PHP_SAPI !== 'cli') {
    exit("CLI only\n");
}

$_SERVER['REQUEST_METHOD'] = 'GET';
$_SERVER['HTTP_HOST'] = getenv('PS_DOMAIN') ?: 'localhost';
require '/var/www/html/config/config.inc.php';

const DEFAULT_GMAIL = 'natufitness4@gmail.com';
const SMTP_SERVER = 'smtp.gmail.com';
const SMTP_PORT = 587;
const SMTP_ENCRYPTION = 'tls';
const METHOD_SMTP = 2;

$gmail = strtolower(trim($argv[1] ?? DEFAULT_GMAIL));
if (!Validate::isEmail($gmail)) {
    exit("Correo no válido: {$gmail}\n");
}

echo "Correo de la tienda: {$gmail}\n";
echo 'Pega la contraseña de aplicación de Google (no se verá al escribir) y pulsa Enter: ';
system('stty -echo 2>/dev/null');
$password = str_replace(' ', '', trim((string) fgets(STDIN)));
system('stty echo 2>/dev/null');
echo "\n";
if (strlen($password) !== 16) {
    exit("La contraseña de aplicación de Google tiene 16 letras. No se guardó nada.\n");
}

echo "Probando: enviando un correo de prueba a {$gmail}...\n";
$result = Mail::sendMailTest(
    true,
    SMTP_SERVER,
    'Este es un correo de prueba de tu tienda NatuFitness: el envío de correos funciona.',
    'NatuFitness: correo de prueba',
    'text/plain',
    $gmail,
    $gmail,
    $gmail,
    $password,
    SMTP_PORT,
    SMTP_ENCRYPTION
);
if ($result !== true) {
    echo "No se pudo enviar el correo de prueba. Nada se guardó.\n";
    echo 'Detalle: ' . (is_string($result) ? $result : 'error desconocido') . "\n";
    echo "Revisa que la contraseña de aplicación sea la correcta y que la cuenta tenga la verificación en 2 pasos activa.\n";
    exit(1);
}

$settings = [
    'PS_MAIL_METHOD' => METHOD_SMTP,
    'PS_MAIL_SERVER' => SMTP_SERVER,
    'PS_MAIL_SMTP_PORT' => SMTP_PORT,
    'PS_MAIL_SMTP_ENCRYPTION' => SMTP_ENCRYPTION,
    'PS_MAIL_USER' => $gmail,
    'PS_MAIL_PASSWD' => $password,
    'PS_MAIL_DOMAIN' => '',
    'PS_SHOP_EMAIL' => $gmail,
    // ps_emailalerts: email the owner on every new order (MA_MERCHANT_ORDER_EMAILS is the
    // new-order recipient list; MA_MERCHANT_MAILS is only for stock alerts).
    'MA_MERCHANT_ORDER' => 1,
    'MA_MERCHANT_ORDER_EMAILS' => $gmail,
    'MA_MERCHANT_MAILS' => $gmail,
];
foreach ($settings as $key => $value) {
    Configuration::updateValue($key, $value);
}

$alerts = Module::getInstanceByName('ps_emailalerts');
if ($alerts && Module::isInstalled('ps_emailalerts') && !$alerts->active) {
    $alerts->enable();
}

echo "Listo: correo de prueba enviado y configuración guardada.\n";
echo "Desde ahora, cada compra le avisa a {$gmail} y el cliente recibe su confirmación.\n";
