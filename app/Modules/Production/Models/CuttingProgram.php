<?php

declare(strict_types=1);

namespace App\Modules\Production\Models;

use App\Modules\Identity\Models\User;
use App\Modules\Tenancy\Models\Factory;
use App\Shared\Casts\MoneyCast;
use App\Shared\Concerns\BelongsToTenant;
use App\Shared\Models\BaseModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A batch of a style to be cut on a date (Master Data spec §6).
 *
 * This is the unit material is actually issued against: a style of 20,000
 * pieces is cut in programmes of two or three thousand, and the store issues
 * fabric programme by programme. It is therefore also the unit the BOM
 * variance is measured on — what was issued for this cut against what the
 * bill of materials said it would take (Inventory spec §9).
 */
class CuttingProgram extends BaseModel
{
    use BelongsToTenant;

    public const STATUSES = ['CONFIRMED', 'IN_PROGRESS', 'CLOSED', 'CANCELLED'];

    /**
     * @var array<string, list<string>>
     */
    public const TRANSITIONS = [
        'CONFIRMED' => ['IN_PROGRESS', 'CLOSED', 'CANCELLED'],
        // Issuing moves it to IN_PROGRESS; it stays there until the cut is
        // done, because a second issue against the same programme is normal.
        'IN_PROGRESS' => ['CLOSED', 'CANCELLED'],
        'CLOSED' => [],
        'CANCELLED' => [],
    ];

    protected $table = 'production_cutting_programs';

    protected $fillable = [
        'company_id', 'factory_id', 'style_id', 'program_no', 'program_qty',
        'cutting_date', 'status', 'notes', 'created_by',
    ];

    protected function casts(): array
    {
        return [
            'program_qty' => MoneyCast::class,
            'cutting_date' => 'date',
        ];
    }

    public function factory(): BelongsTo
    {
        return $this->belongsTo(Factory::class);
    }

    public function style(): BelongsTo
    {
        return $this->belongsTo(Style::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function canTransitionTo(string $status): bool
    {
        return in_array($status, self::TRANSITIONS[$this->status] ?? [], true);
    }

    /** Material may still be issued against it. */
    public function isOpen(): bool
    {
        return in_array($this->status, ['CONFIRMED', 'IN_PROGRESS'], true);
    }
}
