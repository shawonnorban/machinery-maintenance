<?php

declare(strict_types=1);

namespace App\Modules\Prototype\Models;

use App\Shared\Concerns\BelongsToTenant;
use App\Shared\Models\BaseModel;

/** One thing that happened to a sample — its timeline and audit trail — and the lifecycle stage and status it left the sample at. */
class SampleEvent extends BaseModel
{
    use BelongsToTenant;

    protected $table = 'proto_events';

    protected $fillable = ['company_id', 'sample_id', 'action', 'params', 'doc_no', 'stage', 'status', 'by_name', 'by_user', 'happened_at'];

    protected function casts(): array
    {
        return ['params' => 'array', 'happened_at' => 'datetime'];
    }
}
