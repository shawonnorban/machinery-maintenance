<?php

declare(strict_types=1);

namespace App\Modules\Identity\Models;

use App\Shared\Concerns\BelongsToTenant;
use App\Shared\Models\BaseModel;

/**
 * A print or export layout a screen produces (Access Control → Report Template Setup, AnnotechERP 02/03).
 */
class ReportTemplate extends BaseModel
{
    use BelongsToTenant;

    public const FORMATS = ['PDF', 'EXCEL', 'CSV', 'HTML'];

    public const ORIENTATIONS = ['PORTRAIT', 'LANDSCAPE'];

    public const PAPER_SIZES = ['A4', 'A5', 'LETTER', 'LEGAL'];

    protected $table = 'report_templates';

    protected $fillable = ['company_id', 'code', 'name', 'report_path', 'output_format', 'orientation', 'paper_size', 'description', 'active'];

    protected function casts(): array
    {
        return ['active' => 'boolean'];
    }
}
