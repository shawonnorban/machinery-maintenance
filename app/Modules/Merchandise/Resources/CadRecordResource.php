<?php

declare(strict_types=1);

namespace App\Modules\Merchandise\Resources;

use App\Modules\Erp\Support\ResourceDefinition;
use App\Modules\Merchandise\Models\BuyerOffice;
use App\Modules\Merchandise\Models\CadRecord;
use App\Modules\Merchandise\Models\CadSize;
use App\Modules\Merchandise\Models\Order;
use App\Modules\Merchandise\Models\Style;
use App\Shared\Tenancy\TenantContext;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\Rule;

/**
 * Costing & Budget → CAD Consumption Register → Records, New and Edit CAD
 * record (AnnotechERP 06/09). A record names the job / work style it was cut
 * for and its date; its sizes and their average consumption come from the
 * Consumption tab.
 */
class CadRecordResource extends ResourceDefinition
{
    public function key(): string
    {
        return 'merch-cad-records';
    }

    public function model(): string
    {
        return CadRecord::class;
    }

    public function viewPermission(): string
    {
        return 'merchandise.costing.view';
    }

    public function managePermission(): string
    {
        return 'merchandise.costing.manage';
    }

    public function columns(): array
    {
        return ['buyer_office_id', 'style_id', 'order_id', 'category', 'job_no', 'record_date', 'status'];
    }

    public function defaults(): array
    {
        return ['status' => 'DRAFT'];
    }

    public function query(): Builder
    {
        return CadRecord::query()->latest('record_date')->latest();
    }

    public function deletable(): bool
    {
        return true;
    }

    public function rules(?Model $row): array
    {
        $required = $row ? 'sometimes' : 'required';
        $company = app(TenantContext::class)->companyId();

        return [
            'buyer_office_id' => ['nullable', Rule::exists('buyer_offices', 'id')->where('company_id', $company)],
            'style_id' => ['nullable', Rule::exists('merch_styles', 'id')->where('company_id', $company)],
            'order_id' => ['nullable', Rule::exists('merch_orders', 'id')->where('company_id', $company)],
            'category' => ['nullable', Rule::in(CadRecord::CATEGORIES)],
            'job_no' => [$required, 'string', 'max:96'],
            'record_date' => [$required, 'date'],
            'status' => ['nullable', Rule::in(CadRecord::STATUSES)],
        ];
    }

    public function messages(): array
    {
        return [
            'job_no.required' => __('merchandise.cad.job_required'),
            'record_date.required' => __('merchandise.cad.date_required'),
        ];
    }

    public function beforeSave(array $data, ?Model $row): array
    {
        if (array_key_exists('job_no', $data) && is_string($data['job_no'])) {
            $data['job_no'] = trim($data['job_no']);
        }
        if ($row === null) {
            $data['created_by'] = $this->actingUserId();
        }

        return $data;
    }

    public function present(Model $row): array
    {
        $out = parent::present($row);
        $sizes = CadSize::query()->where('cad_id', $row->getKey())->pluck('consumption');
        $out['sizes'] = $sizes->count();
        $out['avg_consumption'] = $sizes->isEmpty() ? null : round((float) $sizes->avg(), 4);
        $out['buyer_ac'] = $row->getAttribute('buyer_office_id') ? BuyerOffice::query()->whereKey($row->getAttribute('buyer_office_id'))->value('name') : null;
        $out['order_no'] = $row->getAttribute('order_id') ? Order::query()->whereKey($row->getAttribute('order_id'))->value('order_no') : null;
        $style = $row->getAttribute('style_id') ? Style::query()->whereKey($row->getAttribute('style_id'))->first() : null;
        $out['style_no'] = $style?->styleNo();

        return $out;
    }
}
