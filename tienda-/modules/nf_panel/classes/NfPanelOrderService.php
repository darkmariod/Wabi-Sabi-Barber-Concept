<?php
/**
 * Order operations for the "Mi Tienda" panel: filtered order cards and
 * one-tap state changes through the core OrderHistory flow.
 *
 * Button → native order state:
 *   paid      "Pago recibido"        → PS_OS_PAYMENT     (Pago aceptado)
 *   ready     "Listo para retirar"   → PS_OS_PREPARATION (Preparación en curso), store pickup only
 *   shipped   "Enviado"              → PS_OS_SHIPPING    (Enviado), home delivery only
 *   delivered "Entregado"            → PS_OS_DELIVERED   (Entregado)
 *   cancel    "Cancelar"             → PS_OS_CANCELED    (Cancelado)
 */
if (!defined('_PS_VERSION_')) {
    exit;
}

class NfPanelOrderService
{
    const PAGE_SIZE = 20;
    const FILTERS = ['pending', 'all', 'delivered', 'cancelled'];
    const PICKUP_CARRIER_KEYWORD = 'retiro';
    const ACTION_STATE_CONFIG = [
        'paid' => 'PS_OS_PAYMENT',
        'ready' => 'PS_OS_PREPARATION',
        'shipped' => 'PS_OS_SHIPPING',
        'delivered' => 'PS_OS_DELIVERED',
        'cancel' => 'PS_OS_CANCELED',
        'refund' => 'PS_OS_REFUND',
    ];

    /** @var Context */
    private $context;

    /** @var int */
    private $idLang;

    /** @var bool true when the last state change was saved but its customer email failed */
    public $lastEmailFailed = false;

    public function __construct(Context $context)
    {
        $this->context = $context;
        $this->idLang = (int) $context->language->id;
    }

    /**
     * @return array{items: array, total: int, page: int, pages: int}
     */
    public function listOrders(string $filter, int $page): array
    {
        $delivered = (int) Configuration::get('PS_OS_DELIVERED');
        $cancelled = (int) Configuration::get('PS_OS_CANCELED');
        switch ($filter) {
            case 'delivered':
                $where = 'o.current_state = ' . $delivered;
                break;
            case 'cancelled':
                $where = 'o.current_state = ' . $cancelled;
                break;
            case 'all':
                $where = '1';
                break;
            default:
                $refunded = (int) Configuration::get('PS_OS_REFUND');
                $where = 'o.current_state NOT IN (' . $delivered . ', ' . $cancelled . ', ' . $refunded . ')';
        }
        $where .= Shop::addSqlRestriction(false, 'o');

        $db = Db::getInstance();
        $total = (int) $db->getValue('SELECT COUNT(*) FROM `' . _DB_PREFIX_ . 'orders` o WHERE ' . $where);
        $pages = max(1, (int) ceil($total / self::PAGE_SIZE));
        $page = min(max(1, $page), $pages);
        $ids = $db->executeS(
            'SELECT o.id_order FROM `' . _DB_PREFIX_ . 'orders` o WHERE ' . $where
            . ' ORDER BY o.date_add DESC, o.id_order DESC'
            . ' LIMIT ' . (int) (($page - 1) * self::PAGE_SIZE) . ', ' . (int) self::PAGE_SIZE
        ) ?: [];

        $items = [];
        foreach ($ids as $row) {
            $card = $this->buildCard(new Order((int) $row['id_order']));
            if ($card) {
                $items[] = $card;
            }
        }

        return ['items' => $items, 'total' => $total, 'page' => $page, 'pages' => $pages];
    }

    /**
     * Changes the order state the same way the core order page does.
     *
     * @return string|null user-facing error, null on success
     */
    public function changeState(int $idOrder, string $action): ?string
    {
        $translator = $this->context->getTranslator();
        $order = new Order($idOrder);
        if (!Validate::isLoadedObject($order)) {
            return $translator->trans('El pedido ya no existe.', [], 'Modules.Nfpanel.Admin');
        }
        if (!in_array($action, $this->availableActions($order), true)) {
            return $translator->trans('Esta acción ya no está disponible para el pedido %ref%.', ['%ref%' => $order->reference], 'Modules.Nfpanel.Admin');
        }
        $stateId = (int) Configuration::get(self::ACTION_STATE_CONFIG[$action]);
        $orderState = new OrderState($stateId);
        if (!Validate::isLoadedObject($orderState)) {
            return $translator->trans('No se encontró el estado de pedido.', [], 'Modules.Nfpanel.Admin');
        }

        $history = new OrderHistory();
        $history->id_order = (int) $order->id;
        $history->id_employee = (int) $this->context->employee->id;
        $history->changeIdOrderState($stateId, $order, !$order->hasInvoice());

        $templateVars = [];
        if ($stateId === (int) Configuration::get('PS_OS_SHIPPING') && $order->getShippingNumber()) {
            $carrier = new Carrier((int) $order->id_carrier, (int) $order->id_lang);
            $templateVars = ['{followup}' => str_replace('@', $order->getShippingNumber(), (string) $carrier->url)];
        }

        $this->lastEmailFailed = false;
        if (!$history->addWithemail(true, $templateVars)) {
            // addWithemail() returns false both when the history row was not saved and
            // when only the customer email failed (e.g. SMTP not configured).
            if (!(int) $history->id) {
                return $translator->trans('No se pudo cambiar el estado del pedido %ref%.', ['%ref%' => $order->reference], 'Modules.Nfpanel.Admin');
            }
            $this->lastEmailFailed = (bool) $orderState->send_email;
        }

        if (Configuration::get('PS_ADVANCED_STOCK_MANAGEMENT')) {
            foreach ($order->getProducts() as $product) {
                if (StockAvailable::dependsOnStock($product['product_id'])) {
                    StockAvailable::synchronize($product['product_id'], (int) $product['id_shop']);
                }
            }
        }

        return null;
    }

    public function isPickup(Order $order): bool
    {
        $carrier = new Carrier((int) $order->id_carrier, $this->idLang);

        return Validate::isLoadedObject($carrier) && stripos((string) $carrier->name, self::PICKUP_CARRIER_KEYWORD) !== false;
    }

    /**
     * Buttons offered for the current state (cancel is listed last).
     *
     * @return string[]
     */
    public function availableActions(Order $order): array
    {
        $state = (int) $order->current_state;
        $is = function (string $config) use ($state): bool {
            return $state === (int) Configuration::get($config);
        };
        if ($is('PS_OS_DELIVERED')) {
            return ['refund']; // customer returned the product: the refund email tells them
        }
        if ($is('PS_OS_CANCELED') || $is('PS_OS_REFUND')) {
            return [];
        }
        $pickup = $this->isPickup($order);
        if ($is('PS_OS_SHIPPING')) {
            return ['delivered'];
        }
        if ($is('PS_OS_PREPARATION')) {
            return $pickup ? ['delivered', 'cancel'] : ['shipped', 'delivered', 'cancel'];
        }
        $orderState = $state ? new OrderState($state) : null;
        if ($orderState && Validate::isLoadedObject($orderState) && $orderState->paid) {
            return $pickup ? ['ready', 'delivered', 'cancel'] : ['shipped', 'delivered', 'cancel'];
        }

        return ['paid', 'cancel'];
    }

    private function buildCard(Order $order): ?array
    {
        if (!Validate::isLoadedObject($order)) {
            return null;
        }
        $customer = new Customer((int) $order->id_customer);
        $delivery = new Address((int) $order->id_address_delivery, $this->idLang);
        $invoice = new Address((int) $order->id_address_invoice, $this->idLang);
        $currency = new Currency((int) $order->id_currency);
        $carrier = new Carrier((int) $order->id_carrier, $this->idLang);
        $state = $order->current_state ? new OrderState((int) $order->current_state, $this->idLang) : null;

        $phone = '';
        foreach ([$delivery, $invoice] as $address) {
            if (Validate::isLoadedObject($address)) {
                $phone = trim((string) ($address->phone_mobile ?: $address->phone));
                if ($phone !== '') {
                    break;
                }
            }
        }

        $products = [];
        foreach ($order->getProductsDetail() as $line) {
            $products[] = ['qty' => (int) $line['product_quantity'], 'name' => (string) $line['product_name']];
        }

        $invoiceData = null;
        if (Validate::isLoadedObject($invoice) && (trim((string) $invoice->dni) !== '' || trim((string) $invoice->company) !== '')) {
            $invoiceData = ['dni' => trim((string) $invoice->dni), 'company' => trim((string) $invoice->company)];
        }

        $addressLines = [];
        if (Validate::isLoadedObject($delivery)) {
            foreach ([$delivery->address1, $delivery->address2, trim($delivery->city . ' ' . $delivery->postcode)] as $part) {
                if (trim((string) $part) !== '') {
                    $addressLines[] = trim((string) $part);
                }
            }
        }

        $customerName = trim($customer->firstname . ' ' . $customer->lastname);
        $whatsapp = self::normalizePhone($phone);
        $shopName = (string) Configuration::get('PS_SHOP_NAME');
        $greeting = $this->context->getTranslator()->trans(
            'Hola %name%, te escribimos de %shop% sobre tu pedido %ref%.',
            ['%name%' => $customer->firstname, '%shop%' => $shopName, '%ref%' => $order->reference],
            'Modules.Nfpanel.Admin'
        );

        return [
            'id' => (int) $order->id,
            'reference' => (string) $order->reference,
            'date' => Tools::displayDate($order->date_add, true),
            'customer' => $customerName,
            'phone' => $phone,
            'whatsapp_url' => $whatsapp ? 'https://wa.me/' . $whatsapp . '?text=' . rawurlencode($greeting) : null,
            'total' => Tools::getContextLocale($this->context)->formatPrice((float) $order->total_paid_tax_incl, $currency->iso_code ?: 'USD'),
            'payment' => (string) $order->payment,
            'pickup' => $this->isPickup($order),
            'carrier' => Validate::isLoadedObject($carrier) ? (string) $carrier->name : '',
            'state_name' => $state && Validate::isLoadedObject($state) ? (string) $state->name : '',
            'state_color' => $state && Validate::isLoadedObject($state) && Validate::isColor($state->color) ? (string) $state->color : '#6c757d',
            'address' => implode(', ', $addressLines),
            'products' => $products,
            'invoice' => $invoiceData,
            'actions' => $this->availableActions($order),
        ];
    }

    /**
     * Converts an Ecuadorian phone into the wa.me format (593 + number without leading 0).
     */
    public static function normalizePhone(string $phone): string
    {
        $digits = preg_replace('/\D+/', '', $phone);
        if ($digits === '' || $digits === null) {
            return '';
        }
        if (strpos($digits, '00593') === 0) {
            $digits = substr($digits, 2);
        }
        if (strpos($digits, '593') === 0) {
            return $digits;
        }
        if ($digits[0] === '0') {
            $digits = substr($digits, 1);
        }

        return strlen($digits) >= 8 ? '593' . $digits : '';
    }
}
