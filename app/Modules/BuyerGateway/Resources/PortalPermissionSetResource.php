<?php

declare(strict_types=1);

namespace App\Modules\BuyerGateway\Resources;

use App\Modules\BuyerGateway\Models\PortalPermissionSet;
use App\Modules\BuyerGateway\Support\PortalBook;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\Rule;

/**
 * Portal Access Permissions (AnnotechERP 25/01): what a portal role may open,
 * download and act on for a buyer — orders, samples, documents, commercial —
 * and whether it may approve. Granted per role per buyer, never per person:
 * one set for each.
 */
class PortalPermissionSetResource extends BuyerGatewayResource
{
    public function key(): string
    {
        return 'bg-permission-sets';
    }

    public function model(): string
    {
        return PortalPermissionSet::class;
    }

    public function columns(): array
    {
        return ['role', 'buyer_id', 'orders', 'samples', 'documents', 'commercial', 'may_approve', 'set_on'];
    }

    public function booleans(): array
    {
        return ['may_approve'];
    }

    public function defaults(): array
    {
        return ['orders' => 'READ_ONLY', 'samples' => 'READ_ONLY', 'documents' => 'VIEW_ONLY', 'commercial' => 'HIDDEN', 'may_approve' => false];
    }

    public function rules(?Model $row): array
    {
        $required = $row ? 'sometimes' : 'required';

        return [
            'role' => [$required, Rule::in(PortalBook::ROLES), $this->unique($row, ['buyer_id', 'role'], 'buyergateway.v.set_taken')],
            'buyer_id' => $this->buyerRule(! $row),
            'orders' => ['sometimes', Rule::in(PortalBook::ACCESS)],
            'samples' => ['sometimes', Rule::in(PortalBook::ACCESS)],
            'documents' => ['sometimes', Rule::in(PortalBook::DOCUMENTS)],
            'commercial' => ['sometimes', Rule::in(PortalBook::ACCESS)],
            'may_approve' => ['sometimes', 'boolean'],
            'set_on' => ['nullable', 'date_format:Y-m-d'],
        ];
    }

    public function messages(): array
    {
        return ['role.required' => __('buyergateway.v.role'), 'buyer_id.required' => __('buyergateway.v.buyer')];
    }

    public function beforeSave(array $data, ?Model $row): array
    {
        if (! $row && empty($data['set_on'])) {
            $data['set_on'] = CarbonImmutable::now('Asia/Dhaka')->toDateString();
        }

        return $data;
    }
}
