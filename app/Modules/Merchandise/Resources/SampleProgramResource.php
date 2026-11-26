<?php

declare(strict_types=1);

namespace App\Modules\Merchandise\Resources;

use App\Modules\Erp\Support\ResourceDefinition;
use App\Modules\Merchandise\Models\BuyerOffice;
use App\Modules\Merchandise\Models\Order;
use App\Modules\Merchandise\Models\SampleLine;
use App\Modules\Merchandise\Models\SampleProgram;
use App\Shared\Tenancy\TenantContext;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * Lab Dip & Sample → Sample Programme → Records, New program and Edit
 * (AnnotechERP 06/10). A programme is dated, may name the fabric booking or
 * requisition its fabric came in on (Tran Ref#), the buyer account, the job
 * (order) and the work style; it is numbered SMP-YYYY00001 as it is raised,
 * as Brickland does. Its lines come from the Sample Lines panel; a programme
 * with a line in a production batch stays.
 */
class SampleProgramResource extends ResourceDefinition
{
    public function key(): string
    {
        return 'merch-sample-programs';
    }

    public function model(): string
    {
        return SampleProgram::class;
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
        return ['program_no', 'program_date', 'tran_ref', 'buyer_office_id', 'order_id', 'work_style_no', 'status', 'remarks'];
    }

    public function defaults(): array
    {
        return ['status' => 'DRAFT'];
    }

    public function query(): Builder
    {
        return SampleProgram::query()->latest('program_date')->latest('program_no');
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
            'program_date' => [$required, 'date'],
            'tran_ref' => ['nullable', 'string', 'max:64'],
            'buyer_office_id' => ['nullable', Rule::exists('buyer_offices', 'id')->where('company_id', $company)],
            'order_id' => ['nullable', Rule::exists('merch_orders', 'id')->where('company_id', $company)],
            'work_style_no' => ['nullable', 'string', 'max:96'],
            'status' => ['nullable', Rule::in(SampleProgram::STATUSES)],
            'remarks' => ['nullable', 'string', 'max:2000'],
        ];
    }

    public function messages(): array
    {
        return ['program_date.required' => __('merchandise.sample.date_required')];
    }

    public function beforeSave(array $data, ?Model $row): array
    {
        foreach (['tran_ref', 'work_style_no', 'remarks'] as $text) {
            if (array_key_exists($text, $data)) {
                $data[$text] = is_string($data[$text]) && trim($data[$text]) !== '' ? trim($data[$text]) : null;
            }
        }
        if ($row === null) {
            $data['program_no'] = $this->nextNumber();
            $data['created_by'] = $this->actingUserId();
        }

        return $data;
    }

    public function beforeDelete(Model $row): void
    {
        if (SampleLine::query()->where('program_id', $row->getKey())->get()->contains(fn (SampleLine $l): bool => $l->inBatch())) {
            throw ValidationException::withMessages(['id' => __('merchandise.sample.program_in_batch')]);
        }
    }

    public function present(Model $row): array
    {
        $out = parent::present($row);
        $order = $row->getAttribute('order_id') ? Order::query()->whereKey($row->getAttribute('order_id'))->first(['order_no', 'job_no', 'work_style_no']) : null;
        $out['order_no'] = $order?->order_no;
        // Brickland's Job/Work Style#: the work style keyed here, else the job's own.
        $out['job_label'] = $row->getAttribute('work_style_no') ?: ($order ? ($order->work_style_no ?: $order->job_no) : null);
        $out['buyer_ac'] = $row->getAttribute('buyer_office_id') ? BuyerOffice::query()->whereKey($row->getAttribute('buyer_office_id'))->value('name') : null;
        $lines = SampleLine::query()->where('program_id', $row->getKey())->get(['req_qty']);
        $out['lines'] = $lines->count();
        $out['req_qty'] = round((float) $lines->sum('req_qty'), 2);

        return $out;
    }

    /** SMP-202600001: the year and the company's next programme, as Brickland numbers them; held to the end of the save. */
    private function nextNumber(): string
    {
        $head = 'SMP-'.now()->format('Y');
        DB::select('select pg_advisory_xact_lock(hashtext(?))', ['merch_sample_programs:'.app(TenantContext::class)->companyId()]);
        $n = 1 + (int) SampleProgram::query()->where('program_no', 'like', $head.'%')->pluck('program_no')->map(fn (string $v): int => (int) substr($v, strlen($head)))->max();

        return $head.str_pad((string) $n, 5, '0', STR_PAD_LEFT);
    }
}
