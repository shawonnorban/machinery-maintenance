<?php

declare(strict_types=1);

namespace App\Modules\OrderSummary\Models;

use App\Shared\Concerns\BelongsToTenant;
use App\Shared\Models\BaseModel;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * One order row of the sheet; every column's value lives in `values`. Order Summary (AnnotechERP 32).
 */
class OsOrder extends BaseModel
{
    use BelongsToTenant;
    use SoftDeletes;

    protected $table = 'os_orders';

    protected $fillable = ['company_id', 'serial_no', 'buyer_id', 'values', 'artwork_file_id', 'version', 'created_by', 'updated_by'];

    protected function casts(): array
    {
        return ['serial_no' => 'integer', 'values' => 'array', 'version' => 'integer'];
    }

    public function creator(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(\App\Modules\Identity\Models\User::class, 'created_by');
    }

    public function updater(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(\App\Modules\Identity\Models\User::class, 'updated_by');
    }
}
