<?php

declare(strict_types=1);

namespace App\Modules\Merchandise\Resources;

use App\Modules\Erp\Support\ResourceDefinition;
use App\Modules\Merchandise\Models\BuyerOffice;
use App\Modules\Merchandise\Models\SampleChallan;
use App\Modules\Merchandise\Models\SampleChallanItem;
use App\Shared\Tenancy\TenantContext;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

/**
 * Lab Dip & Sample → Sample Delivery Challan → Records, New challan and Edit
 * (AnnotechERP 06/10): the challan date, buyer office, vehicle and carrier;
 * numbered SDC-00001 as it is raised, as Brickland does. What is on the
 * challan comes from the Challan Items panel.
 */
class SampleChallanResource extends ResourceDefinition
{
    public function key(): string
    {
        return 'merch-sample-challans';
    }

    public function model(): string
    {
        return SampleChallan::class;
    }

    public function viewPermission(): string
    {
        return 'merchandise.sample.view';
    }

    public function managePermission(): string
    {
        return 'merchandise.sample.manage';
    }

    public function columns(): array
    {
        return ['challan_no', 'challan_date', 'buyer_office_id', 'vehicle_no', 'carrier', 'status', 'remarks'];
    }

    public function defaults(): array
    {
        return ['status' => 'DRAFT'];
    }

    public function query(): Builder
    {
        return SampleChallan::query()->latest('challan_date')->latest('challan_no');
    }

    public function deletable(): bool
    {
        return true;
    }

    public function rules(?Model $row): array
    {
        $required = $row ? 'sometimes' : 'required';

        return [
            'challan_date' => [$required, 'date'],
            'buyer_office_id' => ['nullable', Rule::exists('buyer_offices', 'id')->where('company_id', app(TenantContext::class)->companyId())],
            'vehicle_no' => ['nullable', 'string', 'max:32'],
            'carrier' => ['nullable', 'string', 'max:64'],
            'status' => ['nullable', Rule::in(SampleChallan::STATUSES)],
            'remarks' => ['nullable', 'string', 'max:2000'],
        ];
    }

    public function messages(): array
    {
        return ['challan_date.required' => __('merchandise.challan.date_required')];
    }

    public function beforeSave(array $data, ?Model $row): array
    {
        foreach (['vehicle_no', 'carrier', 'remarks'] as $text) {
            if (array_key_exists($text, $data)) {
                $data[$text] = is_string($data[$text]) && trim($data[$text]) !== '' ? trim($data[$text]) : null;
            }
        }
        if ($row === null) {
            $data['challan_no'] = $this->nextNumber();
            $data['created_by'] = $this->actingUserId();
        }

        return $data;
    }

    public function present(Model $row): array
    {
        $out = parent::present($row);
        $out['buyer_office'] = $row->getAttribute('buyer_office_id') ? BuyerOffice::query()->whereKey($row->getAttribute('buyer_office_id'))->value('name') : null;
        $out['created_on'] = $row->created_at?->toDateString();
        $out['items'] = SampleChallanItem::query()->where('challan_id', $row->getKey())->count();

        return $out;
    }

    /** SDC-00001: the company's next challan, as Brickland numbers them; held to the end of the save. */
    private function nextNumber(): string
    {
        DB::select('select pg_advisory_xact_lock(hashtext(?))', ['merch_sample_challans:'.app(TenantContext::class)->companyId()]);
        $n = 1 + (int) SampleChallan::query()->pluck('challan_no')->map(fn (string $v): int => (int) substr($v, 4))->max();

        return 'SDC-'.str_pad((string) $n, 5, '0', STR_PAD_LEFT);
    }
}
