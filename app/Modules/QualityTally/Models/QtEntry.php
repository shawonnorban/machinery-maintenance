<?php

declare(strict_types=1);

namespace App\Modules\QualityTally\Models;

use App\Shared\Concerns\BelongsToTenant;
use App\Shared\Models\BaseModel;

/**
 * One tap on one garment: RFT, DEFECT, RECTIFY or REJECT. Quality Tally (AnnotechERP 36).
 */
class QtEntry extends BaseModel
{
    use BelongsToTenant;

    protected $table = 'qt_entries';

    protected $fillable = ['company_id', 'client_uuid', 'section', 'line_id', 'job_id', 'inspector_id', 'user_id', 'shift_id', 'panel_id', 'cut_bundle_id', 'garment_type', 'cutting_no', 'batch_no', 'cut_qty', 'bundle_no', 'bundle_serial', 'bundle_qty', 'size', 'action', 'occurred_at', 'production_date', 'hour', 'synced_late', 'review', 'phone_id', 'session_id'];

    protected function casts(): array
    {
        return ['occurred_at' => 'datetime', 'production_date' => 'date', 'hour' => 'integer', 'synced_late' => 'boolean', 'cut_qty' => 'integer', 'bundle_qty' => 'integer'];
    }

    public function defects(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(QtEntryDefect::class, 'entry_id');
    }
}
