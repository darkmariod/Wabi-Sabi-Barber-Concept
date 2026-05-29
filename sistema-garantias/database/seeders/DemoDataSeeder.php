<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Category;
use App\Models\ProductModel;
use App\Models\Product;
use App\Models\TechnicalComposition;
use App\Models\ZebraPrintSetting;

class DemoDataSeeder extends Seeder
{
    public function run(): void
    {
        // ── CATEGORÍA ──────────────────────────────────────────────────────
        $categoria = Category::firstOrCreate(
            ['code' => 'RES'],
            [
                'name'        => 'Resortes',
                'description' => 'Colchones de resortes de alta calidad',
                'active'      => true,
            ]
        );

        // ── MODELO ─────────────────────────────────────────────────────────
        $modelo = ProductModel::firstOrCreate(
            ['code' => 'SEN'],
            [
                'category_id'    => $categoria->id,
                'name'           => 'Señorial',
                'type'           => 'Colchón de Resortes',
                'class'          => 'Especial',
                'warranty_years' => 10,
                'active'         => true,
            ]
        );

        // ── PRODUCTO ───────────────────────────────────────────────────────
        $producto = Product::firstOrCreate(
            ['product_code' => 'A50135'],
            [
                'product_model_id' => $modelo->id,
                'name'             => 'CR SE ESP',
                'barcode'          => '7861191227279',
                'width_cm'         => 80.00,
                'length_cm'        => 190.00,
                'height_cm'        => 23.00,
                'measurements_text'=> '080 x 190 cm',
                'description'      => 'Colchón de Resortes Señorial Especial 080x190',
                'active'           => true,
            ]
        );

        // ── COMPOSICIÓN TÉCNICA ────────────────────────────────────────────
        TechnicalComposition::firstOrCreate(
            ['product_id' => $producto->id],
            [
                'commercial_name'           => 'CR SE ESP',
                'product_family'            => 'Colchón de Resortes',
                'cover_material'            => '100% Poliéster',
                'springs'                   => '187',
                'foam_description'          => 'Espuma Poliuretano 23 kg/m3 - 2,0 cm',
                'support_material'          => 'Resortes Bonell templados',
                'general_composition'       => 'Forro: 100% Poliéster. Resortes: 187. Espuma Poliuretano: 23 kg/m3 - 2,0 cm',
                'conservation_instructions' => 'LIMPIEZA SOLO CON ASPIRADORA',
                'legal_text'                => 'Etiqueta elaborada 100% con material reciclado post-consumo. COMPROMETIDOS CON EL MEDIO AMBIENTE.',
                'inen_standard'             => 'NTE INEN 2035',
                'manufacturing_country'     => 'Ecuador',
                'manufacturer'             => 'PRODUCTOS PARAÍSO DEL ECUADOR C.L',
                'manufacturer_ruc'          => '1790098230001',
                'manufacturer_address'      => 'AV. PANAMERICANA SUR KM 25 TAMBILLO-ECUADOR',
                'website'                   => 'www.paraiso.com.ec',
                'active'                    => true,
            ]
        );

        // ── CONFIGURACIÓN ZEBRA ZT411 ──────────────────────────────────────
        ZebraPrintSetting::firstOrCreate(
            ['name' => 'Zebra ZT411 - Etiqueta Colchón'],
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

        $this->command->info('Datos demo de Productos Paraíso creados correctamente.');
    }
}