<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ZebraPrintSetting extends Model
{
    protected $fillable = [
        'name',
        'printer_model',
        'dpi',
        'label_width_mm',
        'label_height_mm',
        'label_gap_mm',
        'width_dots',
        'height_dots',
        'margin_x',
        'margin_y',
        'qr_size',
        'barcode_height',
        'active',
    ];

    protected function casts(): array
    {
        return [
            'active'         => 'boolean',
            'dpi'            => 'integer',
            'width_dots'     => 'integer',
            'height_dots'    => 'integer',
            'margin_x'       => 'integer',
            'margin_y'       => 'integer',
            'qr_size'        => 'integer',
            'barcode_height' => 'integer',
        ];
    }
}