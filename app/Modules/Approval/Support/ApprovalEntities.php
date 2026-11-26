<?php

declare(strict_types=1);

namespace App\Modules\Approval\Support;

use App\Modules\AiImport\Models\IntakeFile;
use App\Modules\Approval\Models\ApprovalTicket;
use App\Modules\ItemMaster\Models\StockCount;
use App\Modules\PreBudget\Models\PbBudget;
use App\Modules\Merchandise\Models\FabricBooking;
use App\Modules\Procurement\Models\PurchaseOrder;
use App\Modules\Procurement\Models\PurchaseRequisition;
use App\Modules\Procurement\Models\PurchaseReturn;
use App\Modules\Production\Models\MaterialIssue;
use App\Modules\WorkOrder\Models\WorkOrder;
use Illuminate\Database\Eloquent\Model;

/**
 * Which model an approval request's `entity_type` points at.
 *
 * One place, so raising a request, applying its outcome and describing it
 * on the approvals screen cannot each keep their own list and drift.
 */
final class ApprovalEntities
{
    /** @var array<string, class-string<Model>> */
    public const MODELS = [
        'WORK_ORDER' => WorkOrder::class,
        'PURCHASE_REQUISITION' => PurchaseRequisition::class,
        'PURCHASE_ORDER' => PurchaseOrder::class,
        // Material issued past the factory's BOM variance tolerance
        // (Inventory spec §9): the stock waits for the verdict.
        'PRODUCTION_ISSUE' => MaterialIssue::class,
        // Goods going back out of the gate (Procurement spec §12):
        // nothing leaves the shelf until this is decided.
        'PURCHASE_RETURN' => PurchaseReturn::class,
        // A physical count's adjustment (Inventory spec §12): the one
        // routine way stock value moves with nothing arriving or leaving,
        // and so the easiest place to bury a loss.
        'STOCK_COUNT' => StockCount::class,
        // A request written by hand in the Approval Center (AnnotechERP 04).
        'MANUAL_REQUEST' => ApprovalTicket::class,
        // A bulk fabric booking (AnnotechERP 06/11): approved, it joins the
        // Approved Fabric Booking List and is no longer changed.
        'FABRIC_BOOKING' => FabricBooking::class,
        // A file the AI read in the AI File & PO Import intake (AnnotechERP
        // 26/01): approved, it is posted; rejected, it fails.
        'IMPORT_FILE' => IntakeFile::class,
        // A pre-budget sent by Finalize (AnnotechERP 33/34): Merchandising Head → CFO → MD;
        // approved, its requisitions lock; rejected, it goes back to the merchandiser.
        'PRE_BUDGET' => PbBudget::class,
        // An edit of a saved export statement (AnnotechERP 35): approved, it is written onto the
        // statement; rejected, the statement keeps what it had.
        'EXPORT_STATEMENT_EDIT' => \App\Modules\ExportStatement\Models\EsEditRequest::class,
    ];

    public static function find(string $entityType, string $entityId): ?Model
    {
        $model = self::MODELS[$entityType] ?? null;

        if ($model === null) {
            return null;
        }

        return $model::find($entityId);
    }

    /** The entity_type a model instance is routed under, or null if it is not routable. */
    public static function typeOf(Model $entity): ?string
    {
        foreach (self::MODELS as $type => $class) {
            if ($entity instanceof $class) {
                return $type;
            }
        }

        return null;
    }
}
