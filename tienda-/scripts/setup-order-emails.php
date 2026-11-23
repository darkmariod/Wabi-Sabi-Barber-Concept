<?php
/**
 * Customer emails for every order step (sent once the shop email works, see setup-mail.php):
 *   new order (order_conf), Pago aceptado (payment), Listo para retirar (preparation),
 *   Enviado (shipped), Entregado (delivered, template in themes/natufitness/mails/es),
 *   Devolución / Reembolsado (refund).
 * PrestaShop does not email on "Entregado" by default: this turns it on with our template.
 *
 * Idempotent. Usage: docker compose exec -T -u www-data prestashop php /opt/natufitness/scripts/setup-order-emails.php
 */

if (PHP_SAPI !== 'cli') {
    exit("CLI only\n");
}

$_SERVER['REQUEST_METHOD'] = 'GET';
$_SERVER['HTTP_HOST'] = getenv('PS_DOMAIN') ?: 'localhost';
require '/var/www/html/config/config.inc.php';

// state config => template that must be sent to the customer
const STATE_TEMPLATES = [
    'PS_OS_PAYMENT' => 'payment',
    'PS_OS_PREPARATION' => 'preparation',
    'PS_OS_SHIPPING' => 'shipped',
    'PS_OS_DELIVERED' => 'delivered',
    'PS_OS_REFUND' => 'refund',
];

foreach (STATE_TEMPLATES as $config => $template) {
    $state = new OrderState((int) Configuration::get($config));
    if (!Validate::isLoadedObject($state)) {
        echo "Missing state {$config}\n";
        continue;
    }
    $state->send_email = true;
    foreach (Language::getIDs(false) as $lang) {
        $state->template[$lang] = $template;
    }
    $state->update();
    echo "{$config}: emails customer with '{$template}'\n";
}
