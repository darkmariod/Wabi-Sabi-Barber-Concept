<?php
/**
 * Installs the WhatsApp sales alerts (modules/nf_orderalerts) and makes the phone
 * mandatory at checkout, so every alert carries a number to coordinate the dispatch.
 * The owner then pastes his CallMeBot API key in Módulos > Alertas de venta por WhatsApp.
 *
 * Install the module first (needs the Symfony kernel, so not from this script):
 *   docker compose exec -T -u www-data prestashop php bin/console prestashop:module install nf_orderalerts
 * Idempotent. Usage: docker compose exec -T -u www-data prestashop php /opt/natufitness/scripts/setup-order-alerts.php
 * Preview an alert without sending: add an order id, e.g. setup-order-alerts.php 14
 */

if (PHP_SAPI !== 'cli') {
    exit("CLI only\n");
}

$_SERVER['REQUEST_METHOD'] = 'GET';
$_SERVER['HTTP_HOST'] = getenv('PS_DOMAIN') ?: 'localhost';
require '/var/www/html/config/config.inc.php';

if (!Module::isInstalled('nf_orderalerts')) {
    exit("Install it first: php bin/console prestashop:module install nf_orderalerts\n");
}

// Phone required in the address form (ps_required_field).
$address = new Address();
$required = $address->getFieldsRequiredDatabase();
$fields = array_column($required ?: [], 'field_name');
if (!in_array('phone', $fields, true)) {
    $address->addFieldsRequiredDatabase(array_merge($fields, ['phone']));
    echo "Phone is now required at checkout\n";
}

$orderId = (int) ($argv[1] ?? 0);
if ($orderId) {
    require_once _PS_MODULE_DIR_ . 'nf_orderalerts/classes/NfOrderAlertMessage.php';
    $order = new Order($orderId);
    echo "\n--- preview (not sent) ---\n" . NfOrderAlertMessage::compose(NfOrderAlertMessage::KIND_NEW, NfOrderAlertMessage::fromOrder($order)) . "\n";
}

echo 'Alerts ' . (Configuration::get('NF_ALERTS_CALLMEBOT_KEY') ? 'ready' : 'installed, waiting for the CallMeBot API key') . "\n";
