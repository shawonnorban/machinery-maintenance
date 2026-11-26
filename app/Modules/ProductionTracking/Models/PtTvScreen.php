<?php

declare(strict_types=1);

namespace App\Modules\ProductionTracking\Models;

use App\Shared\Concerns\BelongsToTenant;
use App\Shared\Models\BaseModel;

/**
 * A factory-floor TV: its saved selection of sewing or finishing lines, its layout and refresh, and its display link (TV Dashboard Show, 30/04). Only the link's hash is kept. Production Tracking (AnnotechERP 30).
 */
class PtTvScreen extends BaseModel
{
    use BelongsToTenant;

    protected $table = 'pt_tv_screens';

    protected $hidden = ['token_hash'];

    protected $fillable = ['company_id', 'name', 'section', 'line_ids', 'layout', 'refresh_seconds', 'show_defects', 'active', 'token_hash', 'token_hint', 'issued_at', 'last_seen_at', 'updated_by'];

    protected function casts(): array
    {
        return ['line_ids' => 'array', 'refresh_seconds' => 'integer', 'show_defects' => 'boolean', 'active' => 'boolean', 'issued_at' => 'datetime', 'last_seen_at' => 'datetime'];
    }
}
