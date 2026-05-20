<?php
$_SERVER['REMOTE_ADDR'] = '127.0.0.1';
$_SERVER['HTTP_HOST'] = 'localhost:8001';
$_SERVER['SERVER_NAME'] = 'localhost';
$_SERVER['SERVER_PORT'] = 8001;

require_once __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Http\Kernel::class);
$request = Illuminate\Http\Request::capture();
$response = $kernel->handle($request);

$request->session()->start();

echo "=== FLUJO DE COMPRA COMPLETO ===\n\n";

use Webkul\Checkout\Facades\Cart;
use Webkul\Product\Models\Product;

$product = Product::find(2);
if (!$product) {
    die("ERROR: Producto no encontrado\n");
}

echo "1. Agregando {$product->name} al carrito...\n";
try {
    Cart::addProduct($product, [
        'product_id' => $product->id,
        'quantity' => 1,
    ]);
    echo "   ✓ Producto agregado\n";
} catch (Exception $e) {
    echo "   ✗ Error: " . $e->getMessage() . "\n";
}

$cart = Cart::getCart();
if ($cart) {
    echo "   Items en carrito: " . $cart->items->count() . "\n";
    foreach ($cart->items as $item) {
        echo "   - {$item->product->name} x{$item->quantity} = {$item->total}\n";
    }
}

echo "\n2. Guardando direcciones...\n";
$addressData = [
    'billing' => [
        'first_name' => 'Mario',
        'last_name' => 'Test',
        'email' => 'mario@test.com',
        'address' => ['Calle Principal 123'],
        'city' => 'Riobamba',
        'state' => 'RIOBAMBA',
        'postcode' => '060150',
        'country' => 'EC',
        'phone' => '0999999999',
        'use_for_shipping' => true,
    ],
];
Cart::saveAddresses($addressData);
echo "   ✓ Direcciones guardadas\n";

Cart::collectTotals();

echo "\n3. Seleccionando método de envío...\n";
$shipping = app('Webkul\Shipping\Shipping');
$rates = $shipping->collectRates();
echo "   Métodos disponibles:\n";
foreach ($rates['shippingMethods'] as $code => $method) {
    foreach ($method['rates'] as $rate) {
        echo "   - {$rate->method_code}: {$rate->method_title} ({$rate->currency_price})\n";
    }
}

$shippingMethod = 'flatrate_flatrate';
Cart::saveShippingMethod($shippingMethod);
echo "   ✓ Método seleccionado: {$shippingMethod}\n";
Cart::collectTotals();

echo "\n4. Seleccionando método de pago...\n";
$paymentData = ['method' => 'moneytransfer'];
Cart::savePaymentMethod($paymentData);
echo "   ✓ Método de pago: moneytransfer\n";
Cart::collectTotals();

$cart = Cart::getCart();

echo "\n5. Creando orden...\n";
try {
    $orderRepository = app('Webkul\Sales\Repositories\OrderRepository');
    
    $data = (new Webkul\Sales\Transformers\OrderResource($cart))->jsonSerialize();
    $order = $orderRepository->create($data);
    
    echo "   ✓ ORDEN CREADA EXITOSAMENTE!\n";
    echo "     ID: {$order->id}\n";
    echo "     Estado: {$order->status}\n";
    echo "     Total: \${$order->grand_total}\n";
    echo "     Email: {$order->customer_email}\n";
    
    Cart::deActivateCart();
    
    echo "\n=== RESUMEN DE COMPRA ===\n";
    echo "Producto: {$order->items->first()->name}\n";
    echo "Cantidad: {$order->items->first()->qty_ordered}\n";
    echo "Subtotal: \${$order->sub_total}\n";
    echo "Gran Total: \${$order->grand_total}\n";
    echo "Estado: {$order->status}\n";
    echo "========================\n";
} catch (Exception $e) {
    echo "   ✗ Error al crear orden: " . $e->getMessage() . "\n";
    echo "   Trace: " . $e->getTraceAsString() . "\n";
}

$kernel->terminate($request, $response);
