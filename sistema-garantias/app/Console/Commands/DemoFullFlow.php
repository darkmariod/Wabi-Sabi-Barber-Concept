<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\Category;
use App\Models\Customer;
use App\Models\Label;
use App\Models\LabelBatch;
use App\Models\LabelLog;
use App\Models\Product;
use App\Models\ProductModel;
use App\Models\TechnicalComposition;
use App\Models\Warranty;
use App\Models\ZebraPrintSetting;
use App\Services\LabelPdfService;
use App\Services\SerialGeneratorService;
use App\Services\ZebraZplService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class DemoFullFlow extends Command
{
    protected $signature   = 'demo:full-flow {--fresh : Eliminar datos demo existentes antes de crear}';
    protected $description = 'Simula el flujo completo del sistema: datos → etiquetas → ZPL → PDF → rutas públicas';

    private const DEMO_CODE = 'DEMO';

    public function handle(): int
    {
        $this->line('╔══════════════════════════════════════════════════════════╗');
        $this->line('║     SISTEMA DE GARANTÍAS — FLUJO COMPLETO (DEMO)      ║');
        $this->line('╚══════════════════════════════════════════════════════════╝');
        $this->newLine();

        // ── PASO 1: Limpiar datos demo anteriores ──────────────────────────
        if ($this->option('fresh')) {
            $this->warn('  Eliminando datos demo existentes...');
            $this->cleanDemoData();
            $this->info('  ✓ OK');
            $this->newLine();
        }

        // ── PASO 2: Crear datos maestro ────────────────────────────────────
        $this->line('──────────────────────────────────────────────────────────');
        $this->info('  1. CREANDO DATOS MAESTROS');
        $this->line('──────────────────────────────────────────────────────────');

        $category = $this->createCategory();
        $this->line("     ✔ Categoría:   {$category->name} ({$category->code})");

        $model = $this->createProductModel($category);
        $this->line("     ✔ Modelo:      {$model->name} ({$model->code})");

        $product = $this->createProduct($model);
        $this->line("     ✔ Producto:    {$product->name} ({$product->product_code})");

        $composition = $this->createTechnicalComposition($product);
        $this->line("     ✔ Composición: {$composition->commercial_name}");

        $setting = $this->createZebraSetting();
        $this->line("     ✔ Impresión:   {$setting->name} ({$setting->dpi} DPI)");

        $this->newLine();

        // ── PASO 3: Generar etiquetas ───────────────────────────────────────
        $this->line('──────────────────────────────────────────────────────────');
        $this->info('  2. GENERANDO ETIQUETAS');
        $this->line('──────────────────────────────────────────────────────────');

        $serialService = app(SerialGeneratorService::class);

        $batch = LabelBatch::create([
            'product_id'            => $product->id,
            'internal_batch_code'   => 'DEMO-' . now()->format('Ymd-His'),
            'customer_batch_number' => 'DEMO-001',
            'customer_batch_date'   => now()->toDateString(),
            'quantity'              => 3,
            'generated_by_user_id'  => 1,
            'status'                => 'pending',
            'operator'              => 'OPERADOR DEMO',
        ]);

        $serialService->generateLabelsForBatch($batch, 3);

        $batch->refresh();
        $labels = $batch->labels;

        $this->line("     ✔ Lote:        {$batch->internal_batch_code}");
        $this->line("     ✔ Cantidad:    {$batch->quantity} etiquetas");
        $this->line("     ✔ Seriales:    {$batch->serial_from} → {$batch->serial_to}");

        foreach ($labels as $i => $label) {
            $num = $i + 1;
            $this->line("       {$num}. {$label->serial}  |  QR: {$label->qr_url}");
        }

        $this->newLine();

        // ── PASO 4: Generar ZPL ─────────────────────────────────────────────
        $this->line('──────────────────────────────────────────────────────────');
        $this->info('  3. GENERANDO ZPL (Zebra Programming Language)');
        $this->line('──────────────────────────────────────────────────────────');

        $zplService = app(ZebraZplService::class);

        // ZPL individual
        $firstLabel = $labels->first();
        $zplSingle  = $zplService->generateForLabel($firstLabel);
        $this->line("     ✔ ZPL individual: {$zplService->getFilenameForLabel($firstLabel)}");
        $this->line('     ── PRIMERAS 10 LÍNEAS ──');
        $lines = explode("\n", $zplSingle);
        foreach (array_slice($lines, 0, 10) as $line) {
            if (trim($line) !== '') {
                $this->line("       {$line}");
            }
        }
        $this->line('       ...');

        // ZPL batch
        $zplBatch = $zplService->generateForBatch($batch);
        $this->line("     ✔ ZPL lote:     {$zplService->getFilenameForBatch($batch)}");
        $labelCount = mb_substr_count($zplBatch, '^XA');
        $this->line("     ✔ Total ^XA:   {$labelCount} etiquetas en el batch ZPL");

        // Guardar ZPL a archivo
        Storage::disk('local')->put('demo/output.zpl', $zplBatch);
        $zplPath = Storage::disk('local')->path('demo/output.zpl');
        $this->line("     ✔ Guardado en: {$zplPath}");

        $this->newLine();

        // ── PASO 5: Generar PDF ─────────────────────────────────────────────
        $this->line('──────────────────────────────────────────────────────────');
        $this->info('  4. GENERANDO PDF DE ETIQUETAS');
        $this->line('──────────────────────────────────────────────────────────');

        $pdfService = app(LabelPdfService::class);

        // PDF individual
        $pdfSingle = $pdfService->generateForLabel($firstLabel);
        $filenameSingle = $pdfService->getFilenameForLabel($firstLabel);
        Storage::disk('local')->put("demo/{$filenameSingle}", $pdfSingle);
        $this->line("     ✔ PDF individual: {$filenameSingle}");
        $this->line("       → " . strlen($pdfSingle) . ' bytes');

        // PDF batch
        $pdfBatch = $pdfService->generateForBatch($batch);
        $filenameBatch = $pdfService->getFilenameForBatch($batch);
        Storage::disk('local')->put("demo/{$filenameBatch}", $pdfBatch);
        $this->line("     ✔ PDF lote:       {$filenameBatch}");
        $this->line("       → " . strlen($pdfBatch) . ' bytes');
        $pdfPath = Storage::disk('local')->path("demo/{$filenameBatch}");

        $this->newLine();

        // ── PASO 6: Registrar garantía (simulación) ─────────────────────────
        $this->line('──────────────────────────────────────────────────────────');
        $this->info('  5. REGISTRANDO GARANTÍA (SIMULACIÓN)');
        $this->line('──────────────────────────────────────────────────────────');

        $customer = DB::transaction(function () use ($firstLabel, $model, $batch) {
            $customer = Customer::firstOrCreate(
                ['document_number' => '0999999999'],
                [
                    'first_name'       => 'Juan',
                    'second_name'      => 'Carlos',
                    'last_name'        => 'Pérez',
                    'second_last_name' => 'González',
                    'document_type'    => 'cedula',
                    'email'            => 'juan.perez@email.com',
                    'phone'            => '0999999999',
                    'address'          => 'Av. Siempre Viva 123',
                    'province'         => 'Pichincha',
                    'city'             => 'Quito',
                    'sector'           => 'Norte',
                ]
            );

            $warranty = Warranty::create([
                'label_id'            => $firstLabel->id,
                'customer_id'         => $customer->id,
                'store_name'          => 'ALMACEN DEMO',
                'invoice_number'      => '001-001-0000001',
                'purchase_date'       => now()->subDays(5),
                'warranty_start_date' => now()->subDays(5),
                'warranty_end_date'   => now()->subDays(5)->addYears($model->warranty_years),
                'status'              => 'active',
                'terms_accepted'      => true,
            ]);

            $firstLabel->update([
                'status'        => 'registered',
                'registered_at' => now(),
            ]);

            LabelLog::create([
                'label_id'       => $firstLabel->id,
                'label_batch_id' => $batch->id,
                'user_id'        => 1,
                'action'         => 'registrar_garantia',
                'description'    => "Garantía registrada para serial {$firstLabel->serial} por cliente {$customer->first_name} {$customer->last_name}.",
                'ip'             => '127.0.0.1',
            ]);

            return $customer;
        });

        $firstLabel->refresh();
        $this->line("     ✔ Cliente:      {$customer->first_name} {$customer->last_name} ({$customer->document_number})");
        $this->line("     ✔ Garantía:     {$firstLabel->warranty->warranty_start_date->format('d/m/Y')} → {$firstLabel->warranty->warranty_end_date->format('d/m/Y')}");
        $this->line("     ✔ Estado:       {$firstLabel->status}");

        $this->newLine();

        // ── PASO 7: Generar certificado PDF ─────────────────────────────────
        $this->line('──────────────────────────────────────────────────────────');
        $this->info('  6. GENERANDO CERTIFICADO DE GARANTÍA');
        $this->line('──────────────────────────────────────────────────────────');

        $firstLabel->load([
            'product.productModel',
            'product.technicalComposition',
            'labelBatch',
            'warranty.customer',
        ]);

        $certPdf = Pdf::loadView('public.certificate-pdf', ['label' => $firstLabel])
            ->setPaper('a4', 'portrait');

        $certFilename = "certificado-{$firstLabel->serial}.pdf";
        Storage::disk('local')->put("demo/{$certFilename}", $certPdf->output());
        $certPath = Storage::disk('local')->path("demo/{$certFilename}");
        $this->line("     ✔ Certificado: {$certFilename}");
        $this->line("       → " . strlen($certPdf->output()) . ' bytes');

        $this->newLine();

        // ── RESUMEN ─────────────────────────────────────────────────────────
        $this->line('╔══════════════════════════════════════════════════════════╗');
        $this->line('║     FLUJO COMPLETO EJECUTADO EXITOSAMENTE             ║');
        $this->line('╚══════════════════════════════════════════════════════════╝');
        $this->newLine();

        $this->info('  ARCHIVOS GENERADOS:');
        $this->line("    📄 ZPL lote:       {$zplPath}");
        $this->line("    📄 PDF etiquetas:  {$pdfPath}");
        $this->line("    📄 Certificado:    {$certPath}");
        $this->newLine();

        $this->info('  RUTAS PÚBLICAS PARA PROBAR EN EL NAVEGADOR:');
        $urlBase = config('app.url');
        $this->line("    🔗 Producto:       {$urlBase}/p/{$firstLabel->serial}");
        $this->line("    🔗 Registrar garantía: {$urlBase}/garantia/{$firstLabel->serial}/registrar");
        $this->line("    🔗 Certificado:    {$urlBase}/garantia/{$firstLabel->serial}/certificado");
        $this->line("    🔗 Certificado PDF: {$urlBase}/garantia/{$firstLabel->serial}/certificado?download=1");

        $otherLabels = $labels->slice(1);
        if ($otherLabels->isNotEmpty()) {
            $this->newLine();
            $this->info('  SERIALES ADICIONALES (aún no registrados):');
            foreach ($otherLabels as $label) {
                $this->line("    🔗 {$urlBase}/p/{$label->serial}");
            }
        }

        $this->newLine();

        // ── VERIFICACIONES ──────────────────────────────────────────────────
        $this->line('──────────────────────────────────────────────────────────');
        $this->info('  VERIFICACIONES RÁPIDAS');
        $this->line('──────────────────────────────────────────────────────────');

        $checks = [
            'Etiqueta existe'         => Label::where('serial', $firstLabel->serial)->exists(),
            'Garantía registrada'     => Warranty::where('label_id', $firstLabel->id)->exists(),
            'Cliente creado'          => Customer::where('document_number', '0999999999')->exists(),
            'LabelLog creado'         => LabelLog::where('label_id', $firstLabel->id)->exists(),
            'ZPL contiene ^XA'        => str_contains($zplSingle, '^XA'),
            'ZPL contiene ^BQN'       => str_contains($zplSingle, '^BQN'),
            'ZPL contiene QR URL'     => str_contains($zplSingle, $firstLabel->qr_url),
            'ZPL contiene ^BC'        => str_contains($zplSingle, '^BC'),
            'ZPL contiene ^LH0,0'     => str_contains($zplSingle, '^LH0,0'),
            'ZPL contiene PARASIO'    => str_contains($zplSingle, 'PARAISO'),
            'PDF etiqueta es binario' => strlen($pdfSingle) > 1000,
            'PDF certificado es binario' => strlen($certPdf->output()) > 1000,
        ];

        $failures = 0;
        foreach ($checks as $check => $pass) {
            $mark = $pass ? '✔' : '✘';
            if ($pass) {
                $this->line("     {$mark} {$check}");
            } else {
                $this->warn("     {$mark} {$check}");
                $failures++;
            }
        }

        $this->newLine();

        if ($failures === 0) {
            $this->info('  ✅ TODAS LAS VERIFICACIONES PASARON');
        } else {
            $this->warn("  ⚠️  {$failures} verificación(es) fallaron — revisar");
        }

        $this->newLine();
        $this->line('  Para abrir en el navegador:');
        $this->line("    php artisan serve");
        $this->line("    Luego abrir las URLs de arriba.");

        return self::SUCCESS;
    }

    // ── HELPERS ──────────────────────────────────────────────────────────────

    private function createCategory(): Category
    {
        return Category::firstOrCreate(
            ['code' => self::DEMO_CODE],
            [
                'name'        => 'Demostración',
                'description' => 'Categoría para demostración del sistema',
                'active'      => true,
            ]
        );
    }

    private function createProductModel(Category $category): ProductModel
    {
        return ProductModel::firstOrCreate(
            ['code' => 'DM-001'],
            [
                'category_id'    => $category->id,
                'name'           => 'Modelo Demostración',
                'type'           => 'Colchón',
                'class'          => 'A',
                'warranty_years' => 5,
                'active'         => true,
            ]
        );
    }

    private function createProduct(ProductModel $model): Product
    {
        return Product::firstOrCreate(
            ['product_code' => 'DM-001-A'],
            [
                'product_model_id' => $model->id,
                'name'             => 'Colchón Demo',
                'barcode'          => '7861191227777',
                'width_cm'         => 80.00,
                'length_cm'        => 190.00,
                'height_cm'        => 23.00,
                'measurements_text'=> '080 x 190 cm',
                'description'      => 'Producto de demostración',
                'active'           => true,
            ]
        );
    }

    private function createTechnicalComposition(Product $product): TechnicalComposition
    {
        return TechnicalComposition::firstOrCreate(
            ['product_id' => $product->id],
            [
                'commercial_name'           => 'Colchón Demo',
                'product_family'            => 'Colchones',
                'cover_material'            => '100% Algodón',
                'springs'                   => 'Resortes Bonnell',
                'foam_description'          => 'Espuma de alta densidad',
                'support_material'          => 'Resortes templados',
                'general_composition'       => 'Composición demo para pruebas',
                'conservation_instructions' => 'LIMPIEZA CON PAÑO HÚMEDO',
                'legal_text'                => 'Etiqueta demostrativa. COMPROMETIDOS CON EL MEDIO AMBIENTE.',
                'inen_standard'             => 'NTE INEN 2035',
                'manufacturing_country'     => 'Ecuador',
                'manufacturer'              => 'PRODUCTOS PARAÍSO DEL ECUADOR C.L',
                'manufacturer_ruc'          => '1790098230001',
                'manufacturer_address'      => 'AV. PANAMERICANA SUR KM 25 TAMBILLO-ECUADOR',
                'website'                   => 'www.paraiso.com.ec',
                'active'                    => true,
            ]
        );
    }

    private function createZebraSetting(): ZebraPrintSetting
    {
        return ZebraPrintSetting::firstOrCreate(
            ['name' => 'Zebra ZT411 - Demo'],
            [
                'printer_model'  => 'Zebra ZT411',
                'dpi'            => 203,
                'label_width_mm' => 100.00,
                'label_height_mm'=> 350.00,
                'label_gap_mm'   => 3.00,
                'width_dots'     => 800,
                'height_dots'    => 2800,
                'margin_x'       => 20,
                'margin_y'       => 20,
                'qr_size'        => 6,
                'barcode_height' => 120,
                'active'         => true,
            ]
        );
    }

    private function cleanDemoData(): void
    {
        $batchCodes = LabelBatch::where('customer_batch_number', 'DEMO-001')
            ->orWhere('internal_batch_code', 'like', 'DEMO-%')
            ->pluck('id');

        if ($batchCodes->isNotEmpty()) {
            LabelLog::whereIn('label_batch_id', $batchCodes)->delete();
            Warranty::whereIn('label_id', Label::whereIn('label_batch_id', $batchCodes)->pluck('id'))->delete();
            Label::whereIn('label_batch_id', $batchCodes)->delete();
            LabelBatch::whereIn('id', $batchCodes)->delete();
        }

        Customer::where('document_number', '0999999999')->delete();

        // Limpiar archivos demo
        $demoDir = Storage::disk('local')->path('demo');
        if (is_dir($demoDir)) {
            array_map('unlink', glob("{$demoDir}/*"));
            rmdir($demoDir);
        }
    }
}
