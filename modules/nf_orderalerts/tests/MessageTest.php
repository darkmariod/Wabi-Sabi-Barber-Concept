<?php
/**
 * Plain-PHP tests for the WhatsApp alert text (no PrestaShop needed).
 * Run: php modules/nf_orderalerts/tests/MessageTest.php
 */

require __DIR__ . '/../classes/NfOrderAlertMessage.php';

$failures = 0;
function check(string $label, bool $ok): void
{
    global $failures;
    echo ($ok ? 'PASS ' : 'FAIL ') . $label . "\n";
    if (!$ok) {
        $failures++;
    }
}

$order = [
    'reference' => 'KAZUCMNZN',
    'customer' => 'Cliente Prueba',
    'phone' => '099 000 0000',
    'products' => [['name' => 'Whey Gold Standard', 'quantity' => 2], ['name' => 'Shaker', 'quantity' => 1]],
    'total' => '$1,70',
    'payment' => 'DeUna (QR estático)',
    'carrier' => 'Envío a domicilio',
    'address' => 'Av. de Prueba 123, Riobamba',
    'pickup' => false,
];

$new = NfOrderAlertMessage::compose(NfOrderAlertMessage::KIND_NEW, $order);
check('new order title', strpos($new, 'Nuevo pedido KAZUCMNZN') !== false);
check('lists products with quantity', strpos($new, '2 x Whey Gold Standard') !== false && strpos($new, '1 x Shaker') !== false);
check('shows total and payment', strpos($new, '$1,70') !== false && strpos($new, 'DeUna') !== false);
check('asks to verify payment before dispatch', stripos($new, 'verifica el pago') !== false);
check('includes delivery address', strpos($new, 'Av. de Prueba 123') !== false);
check('links customer WhatsApp in international format', strpos($new, 'https://wa.me/593990000000') !== false);

$paid = NfOrderAlertMessage::compose(NfOrderAlertMessage::KIND_PAID, $order);
check('paid title says ready to dispatch', stripos($paid, 'listo para despachar') !== false);
check('paid does not ask to verify payment', stripos($paid, 'verifica el pago') === false);

$pickup = NfOrderAlertMessage::compose(NfOrderAlertMessage::KIND_NEW, ['pickup' => true, 'address' => 'X 1'] + $order);
check('pickup hides the home address', strpos($pickup, 'X 1') === false && stripos($pickup, 'retira en el local') !== false);

check('normalizes 09 numbers', NfOrderAlertMessage::waNumber('0983168337') === '593983168337');
check('keeps 593 numbers', NfOrderAlertMessage::waNumber('+593 98 316 8337') === '593983168337');
check('rejects junk', NfOrderAlertMessage::waNumber('123') === '');
$noPhone = NfOrderAlertMessage::compose(NfOrderAlertMessage::KIND_NEW, ['phone' => ''] + $order);
check('no phone means no wa.me link', strpos($noPhone, 'wa.me') === false);

exit($failures ? 1 : 0);
