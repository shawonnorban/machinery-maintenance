<?php

declare(strict_types=1);

namespace App\Modules\Merchandise\Resources;

use App\Modules\Erp\Support\ResourceDefinition;
use App\Modules\Identity\Models\User;
use App\Modules\MaterialProcurement\Models\MaterialSupplier;
use App\Modules\Merchandise\Models\Buyer;
use App\Modules\Merchandise\Models\BuyerOffice;
use App\Modules\Merchandise\Models\LabdipProgram;
use App\Modules\Merchandise\Models\LabdipShade;
use App\Modules\Tenancy\Models\Section;
use App\Shared\Tenancy\TenantContext;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

/**
 * Lab Dip & Sample → Lab Dip Programme → Records, New and Edit lab dip
 * program (AnnotechERP 06/10). A program names the buyer and buyer account,
 * when it was requested, the lab it goes to and who asked; it is numbered
 * LD-YYYY-000001 as it is raised. The lab is an internal section or a
 * nominated house, picked as one `request_to` value (`SECTION:<id>` or
 * `SUPPLIER:<id>`). Its shades come from the Labdip Specification panel.
 */
class LabdipProgramResource extends ResourceDefinition
{
    public function key(): string
    {
        return 'merch-labdip-programs';
    }

    public function model(): string
    {
        return LabdipProgram::class;
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
        return ['program_no', 'buyer_id', 'buyer_office_id', 'request_date', 'request_section_id', 'request_supplier_id', 'request_by', 'attn_user_id', 'remarks', 'status'];
    }

    public function defaults(): array
    {
        return ['status' => 'DRAFT'];
    }

    public function query(): Builder
    {
        return LabdipProgram::query()->latest('request_date')->latest('program_no');
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
            'buyer_id' => [$required, Rule::exists('buyers', 'id')->where('company_id', $company)],
            'buyer_office_id' => [$required, Rule::exists('buyer_offices', 'id')->where('company_id', $company)],
            'request_date' => [$required, 'date'],
            'request_to' => [$required, 'string', function (string $attribute, mixed $value, \Closure $fail) use ($company): void {
                if (! $this->labOf($value, $company)) {
                    $fail(__('merchandise.labdip.request_to_unknown'));
                }
            }],
            'request_by' => [$required, Rule::exists('company_users', 'user_id')->where('company_id', $company)],
            'attn_user_id' => ['nullable', Rule::exists('company_users', 'user_id')->where('company_id', $company)],
            'remarks' => ['nullable', 'string', 'max:2000'],
            'status' => ['nullable', Rule::in(LabdipProgram::STATUSES)],
        ];
    }

    public function messages(): array
    {
        return [
            'buyer_id.required' => __('merchandise.labdip.buyer_required'),
            'buyer_office_id.required' => __('merchandise.labdip.buyer_ac_required'),
            'request_date.required' => __('merchandise.labdip.date_required'),
            'request_to.required' => __('merchandise.labdip.request_to_required'),
            'request_by.required' => __('merchandise.labdip.request_by_required'),
        ];
    }

    public function beforeSave(array $data, ?Model $row): array
    {
        if (array_key_exists('request_to', $data)) {
            [$type, $id] = $this->labOf($data['request_to'], app(TenantContext::class)->companyId());
            $data['request_section_id'] = $type === 'SECTION' ? $id : null;
            $data['request_supplier_id'] = $type === 'SUPPLIER' ? $id : null;
            unset($data['request_to']);
        }
        if (array_key_exists('remarks', $data)) {
            $data['remarks'] = is_string($data['remarks']) && trim($data['remarks']) !== '' ? trim($data['remarks']) : null;
        }
        if ($row === null) {
            $data['program_no'] = $this->nextNumber();
            $data['created_by'] = $this->actingUserId();
        }

        return $data;
    }

    public function present(Model $row): array
    {
        $out = parent::present($row);
        $section = $row->getAttribute('request_section_id');
        $supplier = $row->getAttribute('request_supplier_id');
        $out['request_to'] = $section ? "SECTION:{$section}" : ($supplier ? "SUPPLIER:{$supplier}" : null);
        $out['request_to_name'] = $section ? Section::query()->whereKey($section)->value('name') : ($supplier ? MaterialSupplier::query()->whereKey($supplier)->value('name') : null);
        $buyer = Buyer::query()->whereKey($row->getAttribute('buyer_id'))->first(['name', 'short_name', 'code']);
        $out['buyer'] = $buyer ? ($buyer->short_name ?: $buyer->name) : null;
        $out['buyer_ac'] = BuyerOffice::query()->whereKey($row->getAttribute('buyer_office_id'))->value('name');
        $users = User::query()->whereIn('id', array_filter([$row->getAttribute('request_by'), $row->getAttribute('attn_user_id')]))->pluck('name', 'id');
        $out['request_by_name'] = $users[$row->getAttribute('request_by')] ?? null;
        $out['attn_user_name'] = $row->getAttribute('attn_user_id') ? ($users[$row->getAttribute('attn_user_id')] ?? null) : null;
        $out['shades'] = LabdipShade::query()->where('program_id', $row->getKey())->count();

        return $out;
    }

    /**
     * The lab a `request_to` value names in this company — an internal
     * section or a nominated house — or null.
     *
     * @return array{0: string, 1: string}|null
     */
    private function labOf(mixed $value, ?string $company): ?array
    {
        if (! is_string($value) || ! preg_match('/^(SECTION|SUPPLIER):([0-9A-Za-z]{26})$/', $value, $m)) {
            return null;
        }
        $id = strtolower($m[2]);
        $exists = $m[1] === 'SECTION'
            ? Section::query()->where('company_id', $company)->whereKey($id)->exists()
            : MaterialSupplier::query()->where('company_id', $company)->whereKey($id)->exists();

        return $exists ? [$m[1], $id] : null;
    }

    /** LD-2026-000001: the year and the company's next program; held to the end of the save. */
    private function nextNumber(): string
    {
        $head = 'LD-'.now()->format('Y').'-';
        DB::select('select pg_advisory_xact_lock(hashtext(?))', ['merch_labdip_programs:'.app(TenantContext::class)->companyId()]);
        $n = 1 + (int) LabdipProgram::query()->where('program_no', 'like', $head.'%')->pluck('program_no')->map(fn (string $v): int => (int) substr($v, strlen($head)))->max();

        return $head.str_pad((string) $n, 6, '0', STR_PAD_LEFT);
    }
}
