<?php

declare(strict_types=1);

namespace App\Modules\Merchandise\Models;

use App\Shared\Concerns\BelongsToTenant;
use App\Shared\Models\BaseModel;

/** One uploaded style and order spreadsheet: the batch, its file and what it made (Master Data, AnnotechERP 06/01). */
class OrderUpload extends BaseModel
{
    use BelongsToTenant;

    protected $table = 'merch_order_uploads';

    protected $fillable = ['company_id', 'batch_no', 'file_type', 'file_name', 'rows', 'styles', 'orders', 'uploaded_by'];

    protected function casts(): array
    {
        return ['rows' => 'integer', 'styles' => 'integer', 'orders' => 'integer'];
    }
}
