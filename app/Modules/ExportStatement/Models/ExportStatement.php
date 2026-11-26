<?php

declare(strict_types=1);

namespace App\Modules\ExportStatement\Models;

use App\Modules\Identity\Models\User;
use App\Shared\Concerns\BelongsToTenant;
use App\Shared\Models\BaseModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * One export invoice of the Export Statement (AnnotechERP 35, Norban's "MD
 * Sir Export Status"): every column's value lives in `values`, as the
 * company's Column Setup makes the sheet. A new statement is saved at once;
 * an edit waits in EsEditRequest for the Approval Center.
 */
class ExportStatement extends BaseModel
{
    use BelongsToTenant;
    use SoftDeletes;

    public const CREATED = 'CREATED';

    public const PENDING = 'PENDING_APPROVAL';

    public const APPROVED = 'APPROVED';

    public const STATUSES = [self::CREATED, self::PENDING, self::APPROVED];

    protected $table = 'es_statements';

    protected $fillable = ['company_id', 'serial_no', 'status', 'buyer_id', 'values', 'version', 'approved_by', 'approved_at', 'created_by', 'updated_by'];

    protected function casts(): array
    {
        return ['serial_no' => 'integer', 'values' => 'array', 'version' => 'integer', 'approved_at' => 'datetime'];
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updater(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    public function edits(): HasMany
    {
        return $this->hasMany(EsEditRequest::class, 'statement_id');
    }

    /** The edit waiting for approval, if any (one at a time, as Norban). */
    public function pendingEdit(): ?EsEditRequest
    {
        return $this->edits()->where('status', EsEditRequest::PENDING)->latest('created_at')->first();
    }
}
