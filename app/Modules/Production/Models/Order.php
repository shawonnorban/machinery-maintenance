<?php

declare(strict_types=1);

namespace App\Modules\Production\Models;

use App\Modules\Identity\Models\User;
use App\Modules\Tenancy\Models\Factory;
use App\Shared\Casts\MoneyCast;
use App\Shared\Concerns\BelongsToTenant;
use App\Shared\Models\BaseModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A buyer's order (Master Data spec §6).
 *
 * The spec is explicit that this master belongs to a Merchandising module
 * when one exists, and that this scope builds a minimal version when it does
 * not. This is that minimal version: enough to hang a style, a bill of
 * materials and a cutting programme off, and no more. It deliberately does
 * not model costing, sampling, shipment or anything else a real
 * merchandising system would — if one arrives, this is the table that gets
 * replaced by a reference to it.
 */
class Order extends BaseModel
{
    use BelongsToTenant;

    public const STATUSES = ['CONFIRMED', 'IN_PROGRESS', 'CLOSED', 'CANCELLED'];

    /**
     * @var array<string, list<string>>
     */
    public const TRANSITIONS = [
        'CONFIRMED' => ['IN_PROGRESS', 'CLOSED', 'CANCELLED'],
        'IN_PROGRESS' => ['CLOSED', 'CANCELLED'],
        'CLOSED' => [],
        'CANCELLED' => [],
    ];

    protected $table = 'production_orders';

    protected $fillable = [
        'company_id', 'factory_id', 'order_no', 'buyer', 'order_qty',
        'order_date', 'ship_date', 'status', 'notes', 'created_by',
    ];

    protected function casts(): array
    {
        return [
            'order_qty' => MoneyCast::class,
            'order_date' => 'date',
            'ship_date' => 'date',
        ];
    }

    public function factory(): BelongsTo
    {
        return $this->belongsTo(Factory::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function styles(): HasMany
    {
        return $this->hasMany(Style::class, 'production_order_id')->orderBy('style_no');
    }

    public function canTransitionTo(string $status): bool
    {
        return in_array($status, self::TRANSITIONS[$this->status] ?? [], true);
    }

    /** Still being worked on — bookings and programmes may be raised against it. */
    public function isOpen(): bool
    {
        return in_array($this->status, ['CONFIRMED', 'IN_PROGRESS'], true);
    }
}
