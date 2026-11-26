<?php

declare(strict_types=1);

namespace App\Modules\Settings\Models;

use App\Shared\Concerns\BelongsToTenant;
use App\Shared\Models\BaseModel;

/**
 * How a barcode label is laid out and printed (Settings → Barcode & Label
 * Settings, AnnotechERP 03/03).
 */
class LabelTemplate extends BaseModel
{
    use BelongsToTenant;

    public const USAGES = ['ITEM', 'ROLL', 'ASSET', 'BUNDLE', 'CARTON', 'LOCATION'];

    public const SYMBOLOGIES = ['CODE128', 'QR', 'EAN13', 'DATAMATRIX'];

    public const PRINTER_LANGUAGES = ['PDF', 'ZPL'];

    /** What may be printed beside the code. */
    public const FIELDS = ['CODE', 'NAME', 'DESCRIPTION', 'QUANTITY', 'UOM', 'LOT', 'COLOR', 'SIZE', 'BUYER', 'ORDER', 'DATE', 'LOCATION'];

    protected $table = 'label_templates';

    protected $fillable = ['company_id', 'code', 'name', 'usage', 'symbology', 'width_mm', 'height_mm', 'columns', 'fields', 'printer_language', 'active'];

    protected function casts(): array
    {
        return ['width_mm' => 'decimal:1', 'height_mm' => 'decimal:1', 'columns' => 'integer', 'fields' => 'array', 'active' => 'boolean'];
    }
}
