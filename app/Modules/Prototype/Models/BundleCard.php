<?php

declare(strict_types=1);

namespace App\Modules\Prototype\Models;

use App\Shared\Concerns\BelongsToTenant;
use App\Shared\Models\BaseModel;

/**
 * A sample lay cut on a cutting table (SBC-2026100001), its bundles and who
 * they are issued to. The card's own status is its paperwork; Bundle Status
 * is where its bundles stand, moved by Issue, Receive, Start / Resume, Hold,
 * Return and Cancel. Issuing finalizes the card and opens it to the scan
 * desk; once a bundle has moved the card is no longer re-bundled.
 */
class BundleCard extends BaseModel
{
    use BelongsToTenant;

    public const STATUSES = ['DRAFT', 'SUBMITTED', 'IN_PROGRESS', 'SENT', 'APPROVED', 'REJECTED', 'CANCELLED'];

    public const BUNDLE_STATUSES = ['CREATED', 'ISSUED', 'RECEIVED', 'IN_PRODUCTION', 'COMPLETED', 'HOLD', 'RETURNED', 'CANCELLED'];

    /** On the floor: issued and not yet back. */
    public const ON_FLOOR = ['ISSUED', 'RECEIVED', 'IN_PRODUCTION'];

    /** Bundle Status no longer moving. */
    public const CLOSED = ['COMPLETED', 'RETURNED', 'CANCELLED'];

    public const PRIORITIES = ['LOW', 'NORMAL', 'HIGH', 'URGENT', 'CRITICAL'];

    protected $table = 'proto_bundle_cards';

    protected $fillable = [
        'company_id', 'card_no', 'sample_id', 'buyer_id', 'buyer_office_id', 'style_id', 'order_id', 'cutting_no', 'table_id', 'fabric_type_id', 'cutting_date', 'status', 'is_finalized',
        'bundle_status', 'department_id', 'assigned_team', 'operator_id', 'issue_date', 'due_date', 'priority', 'remarks', 'delivered_qty', 'rejected_qty', 'created_by',
    ];

    protected function casts(): array
    {
        return ['cutting_date' => 'date', 'issue_date' => 'date', 'due_date' => 'date', 'is_finalized' => 'boolean', 'delivered_qty' => 'integer', 'rejected_qty' => 'integer'];
    }
}
