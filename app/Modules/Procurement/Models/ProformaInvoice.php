<?php

declare(strict_types=1);

namespace App\Modules\Procurement\Models;

use App\Modules\Identity\Models\User;
use App\Modules\Vendor\Models\Vendor;
use App\Shared\Casts\MoneyCast;
use App\Shared\Concerns\BelongsToTenant;
use App\Shared\Models\BaseModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * The supplier's proforma invoice (Procurement spec §10).
 *
 * On an import the PI is what the bank opens a letter of credit against, so
 * it is the document everything downstream quotes: the LC matches its
 * amount, the shipment ships against it, and customs reads it.
 *
 * It is versioned rather than edited. A mill sends a revised PI when the
 * price or quantity moves, and the bank's LC was opened against a specific
 * one — overwriting it would leave the LC matching a document that no
 * longer says what it said. So a revision is a new row superseding the old,
 * and the old stays exactly as the bank saw it.
 */
class ProformaInvoice extends BaseModel
{
    use BelongsToTenant;

    public const STATUSES = ['DRAFT', 'CONFIRMED', 'SUPERSEDED', 'CANCELLED'];

    /**
     * @var array<string, list<string>>
     */
    public const TRANSITIONS = [
        'DRAFT' => ['CONFIRMED', 'CANCELLED'],
        // Superseded by a revision, never edited back into shape.
        'CONFIRMED' => ['SUPERSEDED', 'CANCELLED'],
        'SUPERSEDED' => [],
        'CANCELLED' => [],
    ];

    protected $table = 'proforma_invoices';

    protected $fillable = [
        'company_id', 'purchase_order_id', 'vendor_id', 'pi_no', 'pi_date',
        'version', 'supersedes_id', 'amount', 'currency', 'status',
        'incoterm', 'port_of_loading', 'port_of_discharge', 'notes',
        'created_by', 'confirmed_at', 'superseded_at', 'cancelled_at',
    ];

    protected function casts(): array
    {
        return [
            'pi_date' => 'date',
            'version' => 'integer',
            'amount' => MoneyCast::class,
            'confirmed_at' => 'datetime',
            'superseded_at' => 'datetime',
            'cancelled_at' => 'datetime',
        ];
    }

    public function purchaseOrder(): BelongsTo
    {
        return $this->belongsTo(PurchaseOrder::class);
    }

    public function vendor(): BelongsTo
    {
        return $this->belongsTo(Vendor::class);
    }

    public function supersedes(): BelongsTo
    {
        return $this->belongsTo(self::class, 'supersedes_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function creditLetters(): HasMany
    {
        return $this->hasMany(LetterOfCredit::class);
    }

    public function shipments(): HasMany
    {
        return $this->hasMany(Shipment::class);
    }

    public function canTransitionTo(string $status): bool
    {
        return in_array($status, self::TRANSITIONS[$this->status] ?? [], true);
    }

    public function isLive(): bool
    {
        return in_array($this->status, ['DRAFT', 'CONFIRMED'], true);
    }
}
