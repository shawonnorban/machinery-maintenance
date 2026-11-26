<?php

declare(strict_types=1);

namespace App\Modules\Finishing\Models;

use App\Shared\Concerns\BelongsToTenant;
use App\Shared\Models\BaseModel;

/**
 * Passed batches handed to the finished fabric store on one note, all of one order; a draft until sent from the register (the batches leave the floor), then received by the store (only then does the stock exist). Finishing Line → Fabric Store (AnnotechERP 18/05).
 */
class FinStoreDelivery extends BaseModel
{
    use BelongsToTenant;

    public const STATUSES = ['DRAFT', 'SENT', 'RECEIVED'];

    /** Brickland's fabric types, as the F/T picker offers them. */
    public const FABRIC_TYPES = ['1x1 Rib', '2x2 Rib', 'Fleece', 'French Terry', 'Interlock', 'Lycra Single Jersey', 'Mesh', 'Pique / Lacoste', 'Single Jersey'];

    protected $table = 'fin_store_deliveries';

    protected $fillable = ['company_id', 'delivery_no', 'receive_date', 'store_id', 'order_id', 'remarks', 'status', 'sent_at', 'received_at', 'received_by', 'created_by'];

    protected function casts(): array
    {
        return ['receive_date' => 'date', 'sent_at' => 'datetime', 'received_at' => 'datetime'];
    }

    public function lines(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(FinStoreDeliveryLine::class, 'delivery_id')->orderBy('sort_order');
    }
}
