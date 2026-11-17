<?php
/**
 * Builds the WhatsApp text the owner receives for an order.
 * compose() is pure (plain arrays in, text out) so it is testable without PrestaShop;
 * fromOrder() gathers those arrays from a PrestaShop order.
 */
class NfOrderAlertMessage
{
    const KIND_NEW = 'new';
    const KIND_PAID = 'paid';

    /**
     * @param array{reference:string,customer:string,phone:string,products:array<array{name:string,quantity:int}>,
     *              total:string,payment:string,carrier:string,address:string,pickup:bool} $order
     */
    public static function compose(string $kind, array $order): string
    {
        $lines = [];
        if ($kind === self::KIND_PAID) {
            $lines[] = '✅ *Pago confirmado — listo para despachar*';
            $lines[] = 'Pedido ' . $order['reference'];
        } else {
            $lines[] = '🛒 *Nuevo pedido ' . $order['reference'] . '*';
        }
        $lines[] = '';
        foreach ($order['products'] as $product) {
            $lines[] = '• ' . (int) $product['quantity'] . ' x ' . $product['name'];
        }
        $lines[] = '';
        $lines[] = '💵 Total: *' . $order['total'] . '*';
        $lines[] = '💳 Pago: ' . $order['payment'];
        if ($order['pickup']) {
            $lines[] = '🏪 Entrega: ' . $order['carrier'] . ' (el cliente retira en el local)';
        } else {
            $lines[] = '🚚 Entrega: ' . $order['carrier'];
            $lines[] = '📍 ' . $order['address'];
        }
        $lines[] = '';
        $lines[] = '👤 ' . $order['customer'];
        $wa = self::waNumber($order['phone']);
        if ($wa !== '') {
            $lines[] = '📱 ' . $order['phone'] . ' — https://wa.me/' . $wa;
        }
        if ($kind === self::KIND_NEW) {
            $lines[] = '';
            $lines[] = 'Verifica el pago (comprobante del cliente) antes de despachar. Luego pulsa "Pago recibido" en Mi Tienda > Pedidos.';
        }

        return implode("\n", $lines);
    }

    /**
     * Ecuadorian phone to wa.me format: 0983168337 / +593 98 316 8337 -> 593983168337.
     */
    public static function waNumber(string $phone): string
    {
        $digits = preg_replace('/\D+/', '', $phone);
        if (preg_match('/^0(9\d{8})$/', $digits, $m)) {
            return '593' . $m[1];
        }
        if (preg_match('/^593\d{8,9}$/', $digits)) {
            return $digits;
        }

        return '';
    }

    public static function fromOrder(Order $order): array
    {
        $customer = new Customer((int) $order->id_customer);
        $address = new Address((int) $order->id_address_delivery);
        $carrier = new Carrier((int) $order->id_carrier, (int) $order->id_lang);

        $products = [];
        // Raw order lines: getProducts() recomputes prices and needs a full context.
        foreach ($order->getProductsDetail() as $line) {
            $products[] = ['name' => (string) $line['product_name'], 'quantity' => (int) $line['product_quantity']];
        }

        return [
            'reference' => (string) $order->reference,
            'customer' => trim($customer->firstname . ' ' . $customer->lastname),
            'phone' => (string) ($address->phone_mobile ?: $address->phone),
            'products' => $products,
            // Plain format: the locale formatter needs the Symfony kernel, missing in CLI and some hooks.
            'total' => '$' . number_format((float) $order->total_paid, 2, '.', ','),
            'payment' => (string) $order->payment,
            'carrier' => (string) $carrier->name,
            'address' => trim(implode(', ', array_filter([$address->address1, $address->address2, $address->city]))),
            // The store pickup carrier is "Retiro en local Riobamba" (scripts/seed.php).
            'pickup' => stripos((string) $carrier->name, 'retiro') !== false,
        ];
    }
}
