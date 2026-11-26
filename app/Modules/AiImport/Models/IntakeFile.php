<?php

declare(strict_types=1);

namespace App\Modules\AiImport\Models;

use App\Modules\Approval\Contracts\Approvable;
use App\Shared\Concerns\BelongsToTenant;
use App\Shared\Models\BaseModel;
use Illuminate\Support\Facades\DB;

/**
 * A file that came in to be read, and how far it has got. AI File & PO Import
 * → Universal File Intake (AnnotechERP 26/01).
 *
 * Once read and checked it waits on the Approval Center (entity IMPORT_FILE):
 * approved — or nothing to sign — it is posted; rejected, it fails; sent back
 * or withdrawn, it returns to validating for a person to look at.
 */
class IntakeFile extends BaseModel implements Approvable
{
    use BelongsToTenant;

    protected $table = 'ai_intake_files';

    protected $fillable = ['company_id', 'file', 'arrived_by', 'family', 'buyer_id', 'received_on', 'size_mb', 'confidence', 'stage', 'read_by', 'read_at', 'summary', 'read_note', 'approval_status', 'approval_request_id'];

    protected function casts(): array
    {
        return ['received_on' => 'date', 'size_mb' => 'float', 'confidence' => 'float', 'read_at' => 'datetime'];
    }

    /** The file's own name: an upload's original name, or the name typed in. */
    public function displayName(): string
    {
        if (preg_match('#^/api/files/([0-9A-Za-z]{26})/download$#', (string) $this->file, $m)) {
            $name = DB::table('file_attachments')->whereRaw('lower(id) = ?', [strtolower($m[1])])->where('company_id', $this->company_id)->value('original_name');
            if ($name) {
                return (string) $name;
            }
        }

        return (string) $this->file;
    }

    public function approvalContext(): array
    {
        return ['cost' => '0.0000', 'currency' => 'USD', 'family' => $this->family, 'confidence' => (string) $this->confidence, 'buyer_id' => $this->buyer_id];
    }

    public function approvalSummary(): array
    {
        $family = $this->family ? __("aiimport.family.{$this->family}") : '—';

        return [
            'number' => mb_strimwidth($this->displayName(), 0, 60, '…'),
            'title' => __('aiimport.approval_title', ['file' => $this->displayName(), 'family' => $family, 'confidence' => number_format((float) $this->confidence, 1)]),
            'url' => rtrim((string) config('tenancy.frontend_url'), '/').'/ai-file-import/omni-file-intelligence',
            'factory_id' => null,
        ];
    }

    public function onApprovalDecided(string $status): void
    {
        if ($this->stage !== 'AWAITING_APPROVAL') {
            return;
        }
        $stage = match ($status) {
            'APPROVED', 'NOT_REQUIRED' => 'POSTED',
            'REJECTED' => 'FAILED',
            'RETURNED', 'CANCELLED' => 'VALIDATING',
            default => null,
        };
        if ($stage !== null) {
            $this->forceFill(['stage' => $stage])->save();
        }
    }
}
