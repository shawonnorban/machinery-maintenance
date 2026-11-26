<?php

declare(strict_types=1);

namespace App\Modules\Identity\Resources;

use App\Modules\Erp\Support\ResourceDefinition;
use App\Modules\Identity\Models\DeletionRequest;
use App\Modules\Identity\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\Rule;

/**
 * Access Control → Document Deletion Console (AnnotechERP 02/04): the register
 * of requests to delete a posted PR, PO, MRR, GRN, requisition, invoice or
 * challan, with the reason and the decision. The deletion itself is wired to
 * each document's own module as those modules are built; until then a request
 * is recorded and decided here.
 */
class DeletionRequestResource extends ResourceDefinition
{
    public function key(): string
    {
        return 'deletion-requests';
    }

    public function model(): string
    {
        return DeletionRequest::class;
    }

    public function viewPermission(): string
    {
        return 'settings.company.manage';
    }

    public function columns(): array
    {
        return ['document_type', 'document_no', 'reason', 'status', 'approval_remarks', 'requested_by'];
    }

    public function defaults(): array
    {
        return ['status' => 'DRAFT'];
    }

    public function rules(?Model $row): array
    {
        $required = $row ? 'sometimes' : 'required';

        return [
            'document_type' => [$required, Rule::in(DeletionRequest::DOCUMENT_TYPES)],
            'document_no' => [$required, 'string', 'max:64'],
            'reason' => [$required, 'string', 'max:4000'],
            'status' => ['sometimes', Rule::in(DeletionRequest::STATUSES)],
            'approval_remarks' => ['nullable', 'string', 'max:4000'],
        ];
    }

    public function messages(): array
    {
        return ['document_no.required' => __('access.config.document_no_required')];
    }

    public function beforeSave(array $data, ?Model $row): array
    {
        if ($row === null) {
            $data['requested_by'] = $this->actingUserId();
        }

        return $data;
    }

    public function present(Model $row): array
    {
        $out = parent::present($row);
        $out['requested_by_name'] = $row->requested_by ? User::query()->whereKey($row->requested_by)->value('name') : null;

        return $out;
    }
}
