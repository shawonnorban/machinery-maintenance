<?php

declare(strict_types=1);

namespace App\Modules\MaterialProcurement\Http\Controllers\Api;

use App\Modules\MaterialProcurement\Models\AuditEntry;
use App\Modules\MaterialProcurement\Support\Procurement;
use App\Modules\Notification\Models\Notification;
use App\Shared\Http\Api\ApiController;
use App\Shared\Http\Api\ApiException;
use App\Shared\Http\Api\ApiResponse;
use App\Shared\Http\Api\ErrorCode;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;

/**
 * Material Procurement → Control (AnnotechERP 08/07). Notifications: this
 * module's notifications addressed to the caller — new requirements,
 * quotations and revisions, decisions, handovers and receipts — newest
 * first, read one by one or all at once. Audit Trail: every critical
 * transaction in the module, permanent and read-only.
 */
class ProcurementControlApiController extends ApiController
{
    /** Who works in the module (a supplier's portal login has its own screens). */
    public const STAFF = ['material.requisition.view', 'material.price.approve', 'material.followup.view', 'material.portal_access.manage'];

    /** The module's notifications to the caller. */
    public function notifications(): JsonResponse
    {
        $this->allowStaff();
        $rows = $this->mine()->orderByDesc('created_at')->orderByDesc('id')->limit(200)->get();

        return ApiResponse::ok(['rows' => $rows->map(fn (Notification $n): array => [
            'id' => $n->id, 'event' => $n->event_type, 'title' => $n->title, 'body' => $n->body,
            'at' => $n->created_at?->toIso8601String(), 'read' => $n->read_at !== null, 'url' => $n->action_url,
        ])->values()->all()]);
    }

    /** Opening one marks it read. */
    public function read(string $notification): JsonResponse
    {
        $this->allowStaff();
        $row = $this->mine()->findOrFail($notification);
        if ($row->read_at === null) {
            $row->update(['read_at' => now()]);
        }

        return ApiResponse::ok(['id' => $row->id, 'read' => true]);
    }

    /** Mark all read: this module's only — the bell keeps the rest. */
    public function readAll(): JsonResponse
    {
        $this->allowStaff();

        return ApiResponse::ok(['marked' => $this->mine()->whereNull('read_at')->update(['read_at' => now()])]);
    }

    /** Audit Trail: newest first. */
    public function auditTrail(): JsonResponse
    {
        $this->allow('material.audit.view');
        $rows = AuditEntry::query()->orderByDesc('created_at')->orderByDesc('id')->limit(1000)->get();
        $names = Procurement::names($rows->pluck('user_id')->all());

        return ApiResponse::ok(['rows' => $rows->map(fn (AuditEntry $e): array => [
            'id' => $e->id, 'at' => $e->created_at->toIso8601String(), 'user' => $names[$e->user_id] ?? null,
            'action' => $e->action, 'screen' => $e->screen, 'reference' => $e->reference, 'summary' => $e->summary,
        ])->values()->all()]);
    }

    /** @return Builder<Notification> */
    private function mine(): Builder
    {
        return Notification::query()->where('user_id', $this->caller()->auditUserId())->where('event_type', 'like', 'MP\\_%');
    }

    private function allowStaff(): void
    {
        foreach (self::STAFF as $permission) {
            if ($this->caller()->can($permission)) {
                return;
            }
        }
        throw ApiException::of(ErrorCode::FORBIDDEN);
    }
}
