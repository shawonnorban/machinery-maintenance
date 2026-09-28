<?php

declare(strict_types=1);

namespace App\Modules\Production\Models;

use App\Shared\Casts\MoneyCast;
use App\Shared\Concerns\BelongsToTenant;
use App\Shared\Models\BaseModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * One style on an order, and the thing a bill of materials hangs off
 * (Master Data spec §6).
 *
 * An order is usually several styles with different consumptions, so the BOM
 * belongs here rather than on the order: "how much fabric does this order
 * need" is only answerable style by style.
 */
class Style extends BaseModel
{
    use BelongsToTenant;

    protected $table = 'production_styles';

    protected $fillable = [
        'company_id', 'production_order_id', 'style_no', 'description',
        'color', 'quantity',
    ];

    protected function casts(): array
    {
        return [
            'quantity' => MoneyCast::class,
        ];
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class, 'production_order_id');
    }

    public function bomLines(): HasMany
    {
        return $this->hasMany(StyleBomLine::class)->orderBy('created_at')->orderBy('id');
    }

    public function cuttingPrograms(): HasMany
    {
        return $this->hasMany(CuttingProgram::class);
    }
}
