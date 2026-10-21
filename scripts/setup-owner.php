<?php
/**
 * NatuFitness owner access setup for the "Mi Tienda" panel (module nf_panel).
 *
 * Without arguments: installs/enables nf_panel and makes sure the "Dueño" profile
 * exists (access only to Mi Tienda, landing page Mi Tienda). Idempotent.
 *
 * Create or update the owner's employee (profile Dueño, landing page Mi Tienda):
 *   --email=... --firstname=... --lastname=... --password=...
 *   (--password is required to create the employee; when updating it is optional.)
 *
 * Remove demo/test orders and customers (local cleanup, only with the explicit flag):
 *   --purge-demo-orders [--dry-run]
 *   Deletes ALL orders, plus the demo customer pub@prestashop.com and the guest test
 *   customers (emails ending in @natufitness.test or test-t4-*@example.com), with their
 *   carts and addresses. --dry-run only prints what would be deleted.
 *
 * Usage (always as www-data):
 *   docker compose exec -u www-data prestashop php /opt/natufitness/scripts/setup-owner.php
 *   docker compose exec -u www-data prestashop php /opt/natufitness/scripts/setup-owner.php \
 *     --email=dueno@example.com --firstname=Nombre --lastname=Apellido --password="$OWNER_PASSWORD"
 *
 * Passwords are never printed.
 */
if (PHP_SAPI !== 'cli') {
    exit("CLI only\n");
}

$options = getopt('', ['email:', 'firstname:', 'lastname:', 'password:', 'purge-demo-orders', 'dry-run', 'help']);
if (isset($options['help'])) {
    $doc = file_get_contents(__FILE__);
    preg_match('#/\*\*(.*?)\*/#s', $doc, $match);
    echo preg_replace('/^\s*\* ?/m', '', $match[1] ?? '') . PHP_EOL;
    exit(0);
}

$_SERVER['REQUEST_METHOD'] = 'GET';
$_SERVER['HTTP_HOST'] = getenv('PS_DOMAIN') ?: 'localhost';
require '/var/www/html/config/config.inc.php';

const DEMO_CUSTOMER_EMAIL = 'pub@prestashop.com';
const TEST_GUEST_EMAIL_PATTERNS = ['%@natufitness.test', 'test-t4-%@example.com'];

function out(string $msg): void
{
    echo $msg . PHP_EOL;
}

function fail(string $msg): void
{
    fwrite(STDERR, $msg . PHP_EOL);
    exit(1);
}

$context = Context::getContext();
$context->shop = new Shop((int) Configuration::get('PS_SHOP_DEFAULT'));
Shop::setContext(Shop::CONTEXT_SHOP, (int) $context->shop->id);
$context->language = new Language((int) Configuration::get('PS_LANG_DEFAULT'));
if (!Validate::isLoadedObject($context->employee ?? null)) {
    $context->employee = new Employee((int) Db::getInstance()->getValue('SELECT id_employee FROM `' . _DB_PREFIX_ . 'employee` WHERE id_profile = ' . (int) _PS_ADMIN_PROFILE_ . ' ORDER BY id_employee'));
}

// --- Module ----------------------------------------------------------------

if (!is_file(_PS_MODULE_DIR_ . 'nf_panel/nf_panel.php')) {
    fail('nf_panel not found at ' . _PS_MODULE_DIR_ . 'nf_panel (is the bind mount in docker-compose.yml active?)');
}

/**
 * Module::install() needs the Symfony container, so it runs through bin/console.
 */
function moduleConsole(string $action, string $moduleName): void
{
    $cmd = 'php ' . escapeshellarg(_PS_ROOT_DIR_ . '/bin/console') . ' prestashop:module ' . escapeshellarg($action) . ' ' . escapeshellarg($moduleName) . ' 2>&1';
    exec($cmd, $output, $code);
    if ($code !== 0) {
        fail("{$action} {$moduleName} failed:\n" . implode("\n", $output));
    }
}

if (!Module::isInstalled('nf_panel')) {
    moduleConsole('install', 'nf_panel');
    out('Installed nf_panel');
} elseif (!Module::isEnabled('nf_panel')) {
    moduleConsole('enable', 'nf_panel');
    out('Enabled nf_panel');
} else {
    out('nf_panel already installed and enabled');
}

require_once _PS_MODULE_DIR_ . 'nf_panel/nf_panel.php';

$hadTab = (int) Tab::getIdFromClassName(Nf_Panel::TAB_CLASS);
$hadProfile = Nf_Panel::findOwnerProfileId();
$tabId = Nf_Panel::ensureTab();
$profileId = Nf_Panel::ensureOwnerProfile();
if (!$tabId || !$profileId) {
    fail('Could not create the Mi Tienda tab or the Dueño profile');
}
out(($hadTab ? 'Mi Tienda tab ok' : 'Created Mi Tienda tab') . " (#{$tabId})");
out(($hadProfile ? 'Profile Dueño ok' : 'Created profile Dueño') . " (#{$profileId})");

// --- Owner employee ----------------------------------------------------------

$employeeArgs = array_intersect_key($options, array_flip(['email', 'firstname', 'lastname', 'password']));
if ($employeeArgs) {
    $email = trim((string) ($options['email'] ?? ''));
    $firstname = trim((string) ($options['firstname'] ?? ''));
    $lastname = trim((string) ($options['lastname'] ?? ''));
    $password = (string) ($options['password'] ?? '');

    if (!Validate::isEmail($email)) {
        fail('--email is missing or invalid');
    }
    $idEmployee = (int) Db::getInstance()->getValue('SELECT id_employee FROM `' . _DB_PREFIX_ . 'employee` WHERE email = "' . pSQL($email) . '"');
    $employee = new Employee($idEmployee ?: null);
    if ($idEmployee && (int) $employee->id_profile === (int) _PS_ADMIN_PROFILE_) {
        fail('That email belongs to a SuperAdmin employee; refusing to downgrade it to Dueño');
    }
    if (!$idEmployee && ($firstname === '' || $lastname === '' || $password === '')) {
        fail('Creating the owner needs --email, --firstname, --lastname and --password');
    }
    if ($firstname !== '') {
        if (!Validate::isName($firstname)) {
            fail('--firstname is invalid');
        }
        $employee->firstname = $firstname;
    }
    if ($lastname !== '') {
        if (!Validate::isName($lastname)) {
            fail('--lastname is invalid');
        }
        $employee->lastname = $lastname;
    }
    if ($password !== '') {
        if (Tools::strlen($password) < 8 || !Validate::isAcceptablePasswordLength($password)) {
            fail('--password is too short (minimum 8 characters)');
        }
        /** @var \PrestaShop\PrestaShop\Core\Crypto\Hashing $hashing */
        $hashing = \PrestaShop\PrestaShop\Adapter\ServiceLocator::get('\\PrestaShop\\PrestaShop\\Core\\Crypto\\Hashing');
        $employee->passwd = $hashing->hash($password);
    }
    $employee->email = $email;
    $employee->id_profile = $profileId;
    $employee->default_tab = $tabId;
    $employee->id_lang = (int) Configuration::get('PS_LANG_DEFAULT');
    $employee->active = true;
    $saved = $idEmployee ? $employee->update() : $employee->add();
    if (!$saved) {
        fail('Could not save the owner employee');
    }
    $employee->associateTo([(int) $context->shop->id]);
    out(($idEmployee ? 'Updated' : 'Created') . " owner employee #{$employee->id} ({$email}), profile #{$profileId}, landing tab #{$tabId}" . ($password !== '' ? ', password set' : ''));
}

// --- Demo / test data purge -------------------------------------------------

if (!isset($options['purge-demo-orders'])) {
    exit(0);
}

$db = Db::getInstance();
$dryRun = isset($options['dry-run']);
$orders = $db->executeS('SELECT id_order, reference, id_customer, total_paid, id_currency FROM `' . _DB_PREFIX_ . 'orders` ORDER BY id_order') ?: [];
$guestConditions = [];
foreach (TEST_GUEST_EMAIL_PATTERNS as $pattern) {
    $guestConditions[] = 'email LIKE "' . pSQL($pattern) . '"';
}
$customers = $db->executeS(
    'SELECT id_customer, email, is_guest FROM `' . _DB_PREFIX_ . 'customer`'
    . ' WHERE email = "' . pSQL(DEMO_CUSTOMER_EMAIL) . '"'
    . ' OR (is_guest = 1 AND (' . implode(' OR ', $guestConditions) . '))'
    . ' ORDER BY id_customer'
) ?: [];
$customerIds = array_map('intval', array_column($customers, 'id_customer'));
$addressCount = $customerIds ? (int) $db->getValue('SELECT COUNT(*) FROM `' . _DB_PREFIX_ . 'address` WHERE id_customer IN (' . implode(',', $customerIds) . ')') : 0;
$cartCount = $customerIds ? (int) $db->getValue('SELECT COUNT(*) FROM `' . _DB_PREFIX_ . 'cart` WHERE id_customer IN (' . implode(',', $customerIds) . ')') : 0;

out('Purge plan' . ($dryRun ? ' (dry run)' : '') . ':');
out('  orders: ' . count($orders) . ($orders ? ' [' . implode(', ', array_map(function ($o) {
    return '#' . $o['id_order'] . ' ' . $o['reference'];
}, $orders)) . ']' : ''));
out('  customers: ' . count($customers) . ($customers ? ' [' . implode(', ', array_column($customers, 'email')) . ']' : ''));
out('  addresses: ' . $addressCount . ', carts: ' . $cartCount);
if ($dryRun) {
    exit(0);
}

$productIds = [];
$deletedOrders = 0;
foreach ($orders as $row) {
    $idOrder = (int) $row['id_order'];
    $order = new Order($idOrder);

    foreach ($db->executeS('SELECT id_order_detail, product_id FROM `' . _DB_PREFIX_ . 'order_detail` WHERE id_order = ' . $idOrder) ?: [] as $detail) {
        $productIds[(int) $detail['product_id']] = true;
        (new OrderDetail((int) $detail['id_order_detail']))->delete();
    }
    $invoiceIds = array_map('intval', array_column($db->executeS('SELECT id_order_invoice FROM `' . _DB_PREFIX_ . 'order_invoice` WHERE id_order = ' . $idOrder) ?: [], 'id_order_invoice'));
    if ($invoiceIds) {
        $db->delete('order_invoice_payment', 'id_order_invoice IN (' . implode(',', $invoiceIds) . ')');
        $db->delete('order_invoice_tax', 'id_order_invoice IN (' . implode(',', $invoiceIds) . ')');
    }
    $slipIds = array_map('intval', array_column($db->executeS('SELECT id_order_slip FROM `' . _DB_PREFIX_ . 'order_slip` WHERE id_order = ' . $idOrder) ?: [], 'id_order_slip'));
    if ($slipIds) {
        $db->delete('order_slip_detail', 'id_order_slip IN (' . implode(',', $slipIds) . ')');
    }
    $returnIds = array_map('intval', array_column($db->executeS('SELECT id_order_return FROM `' . _DB_PREFIX_ . 'order_return` WHERE id_order = ' . $idOrder) ?: [], 'id_order_return'));
    if ($returnIds) {
        $db->delete('order_return_detail', 'id_order_return IN (' . implode(',', $returnIds) . ')');
    }
    foreach (['order_history', 'order_carrier', 'order_cart_rule', 'order_invoice', 'order_slip', 'order_return', 'message'] as $table) {
        $db->delete($table, 'id_order = ' . $idOrder);
    }
    foreach ($db->executeS('SELECT id_customer_thread FROM `' . _DB_PREFIX_ . 'customer_thread` WHERE id_order = ' . $idOrder) ?: [] as $thread) {
        (new CustomerThread((int) $thread['id_customer_thread']))->delete();
    }
    $otherOrdersWithReference = (int) $db->getValue('SELECT COUNT(*) FROM `' . _DB_PREFIX_ . 'orders` WHERE reference = "' . pSQL($order->reference) . '" AND id_order <> ' . $idOrder);
    if (!$otherOrdersWithReference) {
        $db->delete('order_payment', 'order_reference = "' . pSQL($order->reference) . '"');
    }

    if ($order->delete()) {
        ++$deletedOrders;
    } else {
        fwrite(STDERR, "Could not delete order #{$idOrder}\n");
    }
}

$deletedCarts = 0;
foreach ($customerIds as $idCustomer) {
    foreach ($db->executeS('SELECT id_cart FROM `' . _DB_PREFIX_ . 'cart` WHERE id_customer = ' . $idCustomer) ?: [] as $cartRow) {
        $cart = new Cart((int) $cartRow['id_cart']);
        if (Validate::isLoadedObject($cart) && $cart->delete()) {
            ++$deletedCarts;
        }
    }
}

$deletedAddresses = 0;
$deletedCustomers = 0;
foreach ($customerIds as $idCustomer) {
    foreach ($db->executeS('SELECT id_address FROM `' . _DB_PREFIX_ . 'address` WHERE id_customer = ' . $idCustomer) ?: [] as $addressRow) {
        $address = new Address((int) $addressRow['id_address']);
        if (Validate::isLoadedObject($address) && $address->delete()) {
            ++$deletedAddresses;
        }
    }
    $customer = new Customer($idCustomer);
    if (Validate::isLoadedObject($customer) && $customer->delete()) {
        ++$deletedCustomers;
    }
}

// Reserved quantities are derived from orders: recompute them for the affected products.
$stockManager = new \PrestaShop\PrestaShop\Adapter\StockManager();
foreach (array_keys($productIds) as $idProduct) {
    if ($idProduct && Validate::isLoadedObject(new Product($idProduct))) {
        $stockManager->updatePhysicalProductQuantity(
            (int) $context->shop->id,
            (int) Configuration::get('PS_OS_ERROR'),
            (int) Configuration::get('PS_OS_CANCELED'),
            $idProduct
        );
    }
}

out("Deleted: orders {$deletedOrders}, customers {$deletedCustomers}, addresses {$deletedAddresses}, carts {$deletedCarts}");
