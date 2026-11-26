<?php

declare(strict_types=1);

namespace App\Modules\QualityTally\Models;

use App\Shared\Concerns\BelongsToTenant;
use App\Shared\Models\BaseModel;

/**
 * A QC: the user who signs in and taps, tied to their HR employee, with the lines they may tap on. Quality Tally (AnnotechERP 36).
 */
class QtInspector extends BaseModel
{
    use BelongsToTenant;

    protected $table = 'qt_inspectors';

    protected $fillable = ['company_id', 'user_id', 'hr_employee_id', 'active', 'created_by', 'updated_by'];

    protected $hidden = ['pin_hash'];

    protected function casts(): array
    {
        return ['active' => 'boolean', 'pin_changed_at' => 'datetime'];
    }

    public function lines(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(QtInspectorLine::class, 'inspector_id');
    }
}
