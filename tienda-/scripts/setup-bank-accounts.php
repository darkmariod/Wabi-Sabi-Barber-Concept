<?php
/**
 * NatuFitness bank accounts for transfers (module ps_wirepayment). They appear on the
 * order confirmation page and in the footer bank logos dialog (each logo highlights the
 * line that mentions its bank). Edit here and rerun, or in Módulos > Transferencia bancaria.
 *
 * Idempotent. Usage: docker compose exec -T -u www-data prestashop php /opt/natufitness/scripts/setup-bank-accounts.php
 */

if (PHP_SAPI !== 'cli') {
    exit("CLI only\n");
}

$_SERVER['REQUEST_METHOD'] = 'GET';
$_SERVER['HTTP_HOST'] = getenv('PS_DOMAIN') ?: 'localhost';
require '/var/www/html/config/config.inc.php';

// Account holder and ID: pending from the owner (needed by customers to transfer).
const HOLDER = 'PENDIENTE: nombre del titular';
const HOLDER_ID = 'PENDIENTE';

const ACCOUNTS = [
    'Banco Pichincha - Cta. Ahorros 2204192064',
    'Banco Pacífico - Cta. Ahorros 1067346898',
    'Produbanco - Cta. Ahorros 20008779285',
];

Configuration::updateValue('BANK_WIRE_OWNER', HOLDER);
Configuration::updateValue('BANK_WIRE_DETAILS', implode("\n", ACCOUNTS) . "\nCédula/RUC del titular: " . HOLDER_ID);
Configuration::updateValue('BANK_WIRE_ADDRESS', 'Riobamba - Ecuador');
Tools::clearSmartyCache();

echo 'Bank accounts saved: ' . count(ACCOUNTS) . "\n";
