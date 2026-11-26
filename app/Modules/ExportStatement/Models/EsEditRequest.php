<?php

declare(strict_types=1);

namespace App\Modules\ExportStatement\Models;

use App\Modules\Approval\Contracts\Approvable;
use App\Modules\Approval\Models\ApprovalAction;
use App\Modules\ExportStatement\Support\EsApproval;
use App\Modules\Identity\Models\User;
use App\Shared\Concerns\BelongsToTenant;
use App\Shared\Models\BaseModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * An edit of a saved export statement, waiting for the Approval Center
 * (Norban: the editor's department head). `diff` holds the changed cells
 * {key: [current, new]}; approved, the new values are written onto the
 * statement; rejected, the statement keeps what it had, status included.
 */
class EsEditRequest extends BaseModel implements Approvable
{
    use BelongsToTenant;

    public const PENDING = 'PENDING';

    public const APPROVED = 'APPROVED';

    public const REJECTED = 'REJECTED';

    public const CANCELLED = 'CANCELLED';

    protected $table = 'es_edit_requests';

    protected $fillable = ['company_id', 'statement_id', 'diff', 'source', 'status', 'approval_status', 'approval_request_id', 'requested_by', 'decided_by', 'decided_at', 'decision_note'];

    protected function casts(): array
    {
        return ['diff' => 'array', 'decided_at' => 'datetime'];
    }

    public function statement(): BelongsTo
    {
        return $this->belongsTo(ExportStatement::class, 'statement_id')->withTrashed();
    }

    public function requester(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by');
    }

    /* ── Approval Center (EXPORT_STATEMENT_EDIT) ──────────────────────── */

    public function approvalContext(): array
    {
        $v = $this->statement?->values ?? [];

        return ['cost' => number_format((float) ($v['invoice_value_net'] ?? 0), 4, '.', ''), 'currency' => 'USD', 'buyer' => $v['buyer_name'] ?? null];
    }

    public function approvalSummary(): array
    {
        $v = $this->statement?->values ?? [];
        $invoice = (string) ($v['invoice_no'] ?? '—');

        return [
            'number' => $invoice,
            'title' => trim(__('exportstatement.approval.title', ['invoice' => $invoice, 'company' => $v['company_name'] ?? '—', 'n' => count($this->diff ?? [])])),
            'url' => rtrim((string) config('tenancy.frontend_url'), '/').'/export-statement/approvals',
            'factory_id' => null,
        ];
    }

    public function onApprovalDecided(string $status): void
    {
        if ($this->status !== self::PENDING) {
            return;
        }
        $by = $this->approval_request_id
            ? ApprovalAction::query()->where('approval_request_id', $this->approval_request_id)->whereIn('action', ['APPROVED', 'REJECTED', 'RETURNED'])->latest('acted_at')->value('approver_id')
            : null;
        match ($status) {
            'APPROVED', 'NOT_REQUIRED' => EsApproval::apply($this, $by),
            'REJECTED', 'RETURNED' => EsApproval::close($this, self::REJECTED, $by),
            'CANCELLED' => EsApproval::close($this, self::CANCELLED, $by),
            default => null,
        };
    }
}
