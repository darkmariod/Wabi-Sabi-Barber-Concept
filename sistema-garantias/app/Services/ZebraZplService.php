<?php

namespace App\Services;

use App\Models\Label;
use App\Models\LabelBatch;
use App\Models\ZebraPrintSetting;

class ZebraZplService
{
    protected ZebraPrintSetting $settings;

    public function __construct()
    {
        $this->settings = ZebraPrintSetting::where('active', true)->first()
            ?? new ZebraPrintSetting([
                'dpi'            => 203,
                'label_width_mm' => 100,
                'label_height_mm' => 350,
                'width_dots'     => 800,
                'height_dots'    => 2800,
                'margin_x'       => 20,
                'margin_y'       => 20,
                'qr_size'        => 6,
                'barcode_height' => 120,
            ]);
    }

    public function mmToDots(float $mm): int
    {
        $dotsPerMm = match (true) {
            $this->settings->dpi >= 600 => 24,
            $this->settings->dpi >= 300 => 12,
            default                     => 8,
        };

        return (int) round($mm * $dotsPerMm);
    }

    public function generateForLabel(Label $label): string
    {
        $label->load(['product.productModel.category', 'product.technicalComposition', 'labelBatch']);

        $product     = $label->product;
        $model       = $product->productModel;
        $composition = $product->technicalComposition;
        $batch       = $label->labelBatch;

        $pw  = $this->settings->width_dots;
        $ll  = $this->settings->height_dots;
        $mx  = $this->settings->margin_x;
        $my  = $this->settings->margin_y;
        $qrs = $this->settings->qr_size;
        $bch = $this->settings->barcode_height;

        $serial      = $label->serial;
        $qrUrl       = $label->qr_url;
        $productCode = $product->product_code;
        $productName = $product->name;
        $modelName   = $model->name ?? '';
        $measurements = $product->measurements_text ?? '';
        $batchNumber = $batch->customer_batch_number ?? '';
        $batchDate   = $batch->customer_batch_date?->format('d/m/Y') ?? '';
        $operator    = $batch->operator ?? '';
        $type        = $model->type ?? 'Colchón';
        $class       = $model->class ?? '';
        $barcode     = $product->barcode ?? '';

        $cover       = $composition->cover_material ?? '';
        $springs     = $composition->springs ?? '';
        $foam        = $composition->foam_description ?? '';
        $conservation = $composition->conservation_instructions ?? '';
        $manufacturer = $composition->manufacturer ?? '';
        $ruc         = $composition->manufacturer_ruc ?? '';
        $address     = $composition->manufacturer_address ?? '';
        $inen        = $composition->inen_standard ?? 'NTE INEN 2035';
        $website     = $composition->website ?? '';
        $legalText   = $composition->legal_text ?? '';

        // ── SECCIÓN 1: TRAZABILIDAD (aparece dos veces) ───────────────────
        $sec1_y  = $my;
        $col1_x  = $mx;
        $col2_x  = $mx + 400;

        $sec1 = $this->buildSection1(
            $serial, $productCode, $batchDate, $batchNumber,
            $type, $modelName, $measurements, $operator,
            $col1_x, $col2_x, $sec1_y
        );

        // Segunda repetición de trazabilidad
        $sec1b_y = $sec1_y + 220;
        $sec1b   = $this->buildSection1(
            $serial, $productCode, $batchDate, $batchNumber,
            $type, $modelName, $measurements, $operator,
            $col1_x, $col2_x, $sec1b_y
        );

        // ── SECCIÓN 2: COMPOSICIÓN TÉCNICA ───────────────────────────────
        $sec2_y = $sec1b_y + 240;
        $sec2   = $this->buildSection2(
            $type, $class, $measurements, $conservation,
            $batchDate, $batchNumber, $operator,
            $cover, $springs, $foam,
            $manufacturer, $ruc, $address, $inen, $website,
            $col1_x, $col2_x, $sec2_y
        );

        // ── SECCIÓN 3: PRINCIPAL CON QR ───────────────────────────────────
        $sec3_y = $sec2_y + 560;
        $sec3   = $this->buildSection3(
            $serial, $productCode, $productName, $type,
            $modelName, $measurements, $qrUrl, $barcode,
            $legalText, $col1_x, $sec3_y, $qrs, $bch
        );

        // ── ENSAMBLE FINAL ZPL ────────────────────────────────────────────
        $zpl = "^XA\n";
        $zpl .= "^PW{$pw}\n";
        $zpl .= "^LL{$ll}\n";
        $zpl .= "^LH0,0\n";
        $zpl .= "^CI28\n";
        $zpl .= $sec1;
        $zpl .= $this->separator($mx, $sec1b_y - 5, $pw - ($mx * 2));
        $zpl .= $sec1b;
        $zpl .= $this->separator($mx, $sec2_y - 5, $pw - ($mx * 2));
        $zpl .= $sec2;
        $zpl .= $this->separator($mx, $sec3_y - 5, $pw - ($mx * 2));
        $zpl .= $sec3;
        $zpl .= "^XZ\n";

        return $zpl;
    }

    protected function buildSection1(
        string $serial, string $productCode,
        string $batchDate, string $batchNumber,
        string $type, string $modelName, string $measurements,
        string $operator,
        int $col1_x, int $col2_x, int $y
    ): string {
        $zpl  = '';
        $zpl .= "^FO{$col1_x},{$y}^A0N,18,18^FDN: {$serial}^FS\n";
        $zpl .= "^FO{$col1_x}," . ($y + 22) . "^A0N,16,16^FD{$productCode}^FS\n";
        $zpl .= "^FO{$col1_x}," . ($y + 42) . "^A0N,16,16^FDFecha: {$batchDate}^FS\n";
        $zpl .= "^FO{$col1_x}," . ($y + 62) . "^A0N,16,16^FDLote: {$batchNumber}^FS\n";
        $zpl .= "^FO{$col2_x},{$y}^A0N,16,16^FDCONTROL DE CALIDAD^FS\n";
        $zpl .= "^FO{$col2_x}," . ($y + 22) . "^A0N,16,16^FD{$type}^FS\n";
        $zpl .= "^FO{$col2_x}," . ($y + 42) . "^A0N,18,18^FD{$modelName}^FS\n";
        $zpl .= "^FO{$col2_x}," . ($y + 62) . "^A0N,16,16^FD({$measurements})^FS\n";
        $zpl .= "^FO{$col2_x}," . ($y + 90) . "^A0N,14,14^FDOperador: ________________^FS\n";
        $zpl .= "^FO{$col2_x}," . ($y + 110) . "^A0N,14,14^FDEnsamble: ________________^FS\n";
        $zpl .= "^FO{$col2_x}," . ($y + 130) . "^A0N,14,14^FDCerrador: ________________^FS\n";
        $zpl .= "^FO{$col2_x}," . ($y + 160) . "^A0N,14,14^FDTrazabilidad^FS\n";

        return $zpl;
    }

    protected function buildSection2(
        string $type, string $class, string $measurements,
        string $conservation,
        string $batchDate, string $batchNumber, string $operator,
        string $cover, string $springs, string $foam,
        string $manufacturer, string $ruc, string $address,
        string $inen, string $website,
        int $col1_x, int $col2_x, int $y
    ): string {
        $zpl  = '';

        $zpl .= "^FO{$col1_x},{$y}^A0N,20,20^FDInformacion de Composicion^FS\n";

        $y1 = $y + 28;
        $zpl .= "^FO{$col1_x},{$y1}^A0N,16,16^FDTipo IV: {$type}^FS\n";
        $zpl .= "^FO{$col1_x}," . ($y1 + 20) . "^A0N,16,16^FDClase {$class}: {$measurements}^FS\n";
        $zpl .= "^FO{$col1_x}," . ($y1 + 40) . "^A0N,14,14^FDCONDICIONES PARA SU CONSERVACION^FS\n";
        $zpl .= "^FO{$col1_x}," . ($y1 + 58) . "^A0N,14,14^FD{$conservation}^FS\n";

        $zpl .= "^FO{$col1_x}," . ($y1 + 100) . "^A0N,16,16^FDFecha: {$batchDate}^FS\n";
        $zpl .= "^FO{$col1_x}," . ($y1 + 120) . "^A0N,16,16^FDLote: {$batchNumber}^FS\n";
        $zpl .= "^FO{$col1_x}," . ($y1 + 140) . "^A0N,28,28^FD{$operator}^FS\n";
        $zpl .= "^FO{$col1_x}," . ($y1 + 172) . "^A0N,14,14^FDOperador^FS\n";

        $zpl .= "^FO{$col2_x},{$y1}^A0N,16,16^FDForro: {$cover}^FS\n";
        $zpl .= "^FO{$col2_x}," . ($y1 + 22) . "^A0N,16,16^FDResortes: {$springs}^FS\n";
        $zpl .= "^FO{$col2_x}," . ($y1 + 44) . "^A0N,16,16^FD{$foam}^FS\n";
        $zpl .= "^FO{$col2_x}," . ($y1 + 76) . "^A0N,18,18^FDHECHO EN ECUADOR^FS\n";
        $zpl .= "^FO{$col2_x}," . ($y1 + 100) . "^A0N,13,13^FDFABRICADO POR:^FS\n";
        $zpl .= "^FO{$col2_x}," . ($y1 + 116) . "^A0N,13,13^FD{$manufacturer}^FS\n";
        $zpl .= "^FO{$col2_x}," . ($y1 + 132) . "^A0N,13,13^FDRUC: {$ruc}^FS\n";
        $zpl .= "^FO{$col2_x}," . ($y1 + 148) . "^A0N,13,13^FD{$address}^FS\n";
        $zpl .= "^FO{$col2_x}," . ($y1 + 176) . "^A0N,14,14^FD{$inen}^FS\n";
        $zpl .= "^FO{$col2_x}," . ($y1 + 194) . "^A0N,14,14^FD{$website}^FS\n";

        return $zpl;
    }

    protected function buildSection3(
        string $serial, string $productCode,
        string $productName, string $type,
        string $modelName, string $measurements,
        string $qrUrl, string $barcode,
        string $legalText,
        int $x, int $y, int $qrSize, int $barcodeHeight
    ): string {
        $zpl = '';

        $qr_x      = $x;
        $qr_y      = $y;
        $text_x    = $x + 180;
        $barcode_x = $x;

        $zpl .= "^FO{$qr_x},{$qr_y}^BQN,2,{$qrSize}^FDQA,{$qrUrl}^FS\n";

        $zpl .= "^FO{$text_x},{$y}^A0N,36,36^FDPARAISO^FS\n";
        $zpl .= "^FO{$text_x}," . ($y + 42) . "^A0N,14,14^FDDONDE EMPIEZAN TUS SUENOS^FS\n";
        $zpl .= "^FO{$text_x}," . ($y + 64) . "^A0N,16,16^FDCONTROL DE CALIDAD^FS\n";
        $zpl .= "^FO{$text_x}," . ($y + 84) . "^A0N,20,20^FDN: {$serial}^FS\n";
        $zpl .= "^FO{$text_x}," . ($y + 108) . "^A0N,18,18^FD{$productCode}^FS\n";
        $zpl .= "^FO{$text_x}," . ($y + 130) . "^A0N,16,16^FD{$type}^FS\n";
        $zpl .= "^FO{$text_x}," . ($y + 152) . "^A0N,26,26^FD{$modelName}^FS\n";
        $zpl .= "^FO{$text_x}," . ($y + 184) . "^A0N,18,18^FD({$measurements})^FS\n";

        $bar_y = $y + 220;
        if (!empty($barcode)) {
            $zpl .= "^FO{$barcode_x},{$bar_y}^BCN,{$barcodeHeight},Y,N,N^FD{$barcode}^FS\n";
        }

        $legal_y = $bar_y + $barcodeHeight + 20;
        $zpl .= "^FO{$x},{$legal_y}^A0N,13,13^FD{$legalText}^FS\n";

        $nodesp_x = $x + $this->settings->width_dots - 30;
        $nodesp_y = $y + 50;
        $zpl .= "^FO{$nodesp_x},{$nodesp_y}^A0R,14,14^FDNO DESPRENDER LA ETIQUETA^FS\n";

        return $zpl;
    }

    protected function separator(int $x, int $y, int $width): string
    {
        return "^FO{$x},{$y}^GB{$width},2,2^FS\n";
    }

    public function generateForBatch(LabelBatch $batch): string
    {
        $labels = $batch->labels()->where('status', '!=', 'cancelled')->get();

        $zplFull = '';

        foreach ($labels as $label) {
            $zplFull .= $this->generateForLabel($label);
            $zplFull .= "\n";
        }

        return $zplFull;
    }

    public function getFilenameForBatch(LabelBatch $batch): string
    {
        return 'etiquetas-' . $batch->internal_batch_code . '.zpl';
    }

    public function getFilenameForLabel(Label $label): string
    {
        return 'etiqueta-' . $label->serial . '.zpl';
    }
}
