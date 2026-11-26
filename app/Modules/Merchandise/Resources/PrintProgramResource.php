<?php

declare(strict_types=1);

namespace App\Modules\Merchandise\Resources;

use App\Modules\Erp\Support\ResourceDefinition;
use App\Modules\Identity\Models\User;
use App\Modules\Merchandise\Models\Buyer;
use App\Modules\Merchandise\Models\BuyerOffice;
use App\Modules\Merchandise\Models\PrintLine;
use App\Modules\Merchandise\Models\PrintProgram;
use App\Shared\Tenancy\TenantContext;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

/**
 * Lab Dip & Sample → Print Design Sheet → Records, New program and Edit
 * (AnnotechERP 06/10): Brickland's Program Parameter — who asks for the
 * print design and strike-off (Program By), of whom (Request To) and on what
 * date, for which buyer and buyer account, in which company. Numbered
 * PD-YYYY00001 as it is raised. Its styles, orders and instructions come from
 * the Style & Order Lines panel.
 */
class PrintProgramResource extends ResourceDefinition
{
    public function key(): string
    {
        return 'merch-print-programs';
    }

    public function model(): string
    {
        return PrintProgram::class;
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
        return ['program_no', 'buyer_office_id', 'buyer_id', 'requisition_date', 'program_by', 'request_to'];
    }

    public function query(): Builder
    {
        return PrintProgram::query()->latest('requisition_date')->latest('program_no');
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
            'buyer_office_id' => [$required, Rule::exists('buyer_offices', 'id')->where('company_id', $company)],
            'buyer_id' => [$required, Rule::exists('buyers', 'id')->where('company_id', $company)],
            'requisition_date' => [$required, 'date'],
            'program_by' => [$required, Rule::exists('company_users', 'user_id')->where('company_id', $company)],
            'request_to' => [$required, 'string', 'max:160'],
            // The company the sheet is raised in: only the one being worked in.
            'company' => [$required, Rule::in([$company])],
        ];
    }

    public function messages(): array
    {
        return [
            'buyer_office_id.required' => __('merchandise.print.buyer_ac_required'),
            'buyer_id.required' => __('merchandise.print.buyer_required'),
            'requisition_date.required' => __('merchandise.print.date_required'),
            'program_by.required' => __('merchandise.print.program_by_required'),
            'request_to.required' => __('merchandise.print.request_to_required'),
            'company.required' => __('merchandise.print.company_required'),
        ];
    }

    public function beforeSave(array $data, ?Model $row): array
    {
        unset($data['company']);
        if (array_key_exists('request_to', $data) && is_string($data['request_to'])) {
            $data['request_to'] = trim($data['request_to']);
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
        $out['company'] = $row->getAttribute('company_id');
        $buyer = Buyer::query()->whereKey($row->getAttribute('buyer_id'))->first(['name', 'short_name']);
        $out['buyer'] = $buyer ? ($buyer->short_name ?: $buyer->name) : null;
        $out['buyer_ac'] = BuyerOffice::query()->whereKey($row->getAttribute('buyer_office_id'))->value('name');
        $out['program_by_name'] = User::query()->whereKey($row->getAttribute('program_by'))->value('name');
        $lines = PrintLine::query()->where('program_id', $row->getKey())->get(['req_qty']);
        $out['lines'] = $lines->count();
        $out['req_qty'] = round((float) $lines->sum('req_qty'), 2);

        return $out;
    }

    /** PD-202600001: the year and the company's next sheet; held to the end of the save. */
    private function nextNumber(): string
    {
        $head = 'PD-'.now()->format('Y');
        DB::select('select pg_advisory_xact_lock(hashtext(?))', ['merch_print_programs:'.app(TenantContext::class)->companyId()]);
        $n = 1 + (int) PrintProgram::query()->where('program_no', 'like', $head.'%')->pluck('program_no')->map(fn (string $v): int => (int) substr($v, strlen($head)))->max();

        return $head.str_pad((string) $n, 5, '0', STR_PAD_LEFT);
    }
}
