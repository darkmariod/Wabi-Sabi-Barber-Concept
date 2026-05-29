<?php

use Illuminate\Support\Facades\Route;
use SimpleSoftwareIO\QrCode\Facades\QrCode;

Route::get('/', function () {
    return view('welcome');
});

Route::get('/test-qr', function () {
    $qr = QrCode::size(200)->generate('https://paraiso.com.ec/test');
    return response($qr)->header('Content-Type', 'image/svg+xml');
});

Route::get('/test-pdf', function () {
    $html = '<h1>Productos Paraiso</h1><p>PDF de prueba funcionando correctamente.</p>';
    $pdf  = \Barryvdh\DomPDF\Facade\Pdf::loadHTML($html);
    return $pdf->download('prueba.pdf');
});

use App\Http\Controllers\PublicController;

Route::get('/p/{serial}', [PublicController::class, 'product'])->name('public.product');
Route::get('/garantia/{serial}/registrar', [PublicController::class, 'warrantyForm'])->name('public.warranty.form');
Route::post('/garantia/{serial}/registrar', [PublicController::class, 'warrantyStore'])->name('public.warranty.store');
Route::get('/garantia/{serial}/certificado', [PublicController::class, 'warrantyCertificate'])->name('public.warranty.certificate');

Route::get('/demo', function () {
    $batches = \App\Models\LabelBatch::with('labels.product.productModel')
        ->latest()->take(10)->get();
    $labels  = \App\Models\Label::with(['product.productModel', 'warranty'])
        ->latest()->take(50)->get();
    return view('demo.index', compact('batches', 'labels'));
})->name('demo.index');