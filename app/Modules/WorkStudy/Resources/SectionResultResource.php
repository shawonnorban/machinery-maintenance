<?php

declare(strict_types=1);

namespace App\Modules\WorkStudy\Resources;

use App\Modules\Erp\Support\ResourceDefinition;
use App\Modules\WorkStudy\Models\WsSectionResult;
use App\Modules\WorkStudy\Support\WsSectionProfit;
use App\Shared\Tenancy\TenantContext;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

/**
 * Section Profit & Loss Entry → Records and New section result: what one
 * section earned and produced on a day against its target, and what it cost
 * to earn. Profit or loss is derived on read from income less the three
 * costs, so it can never contradict them. One result a section a day. Work
 * Study → Section Profitability (AnnotechERP 14/08).
 */
class SectionResultResource extends ResourceDefinition
{
    private const AMOUNTS = ['income', 'prod_qty', 'oh_cost', 'mat_cost', 'expense'];

    public function key(): string
    {
        return 'ws-section-results';
    }

    public function model(): string
    {
        return WsSectionResult::class;
    }

    public function viewPermission(): string
    {
        return 'workstudy.profit.view';
    }

    public function managePermission(): string
    {
        return 'workstudy.profit.manage';
    }

    public function columns(): array
    {
        return ['result_date', 'section_id', 'income_target', 'income', 'prod_target', 'prod_qty', 'oh_cost', 'mat_cost', 'expense', 'comments'];
    }

    public function query(): Builder
    {
        return WsSectionResult::query()->orderByDesc('result_date')->orderBy('created_at');
    }

    public function messages(): array
    {
        return [
            'company.required' => __('workstudy.sp.v.company'), 'section_id.required' => __('workstudy.sp.v.section'), 'result_date.required' => __('workstudy.sp.v.date'),
        ];
    }

    public function rules(?Model $row): array
    {
        $required = $row ? 'sometimes' : 'required';
        $company = app(TenantContext::class)->companyId();
        $date = request('result_date', $row?->getAttribute('result_date')?->toDateString());
        $number = ['nullable', 'numeric', 'min:0', 'max:9999999999'];

        return [
            'company' => [$required, Rule::in([$company])],
            'section_id' => [$required, Rule::exists('sections', 'id')->where('company_id', $company), function (string $attribute, mixed $value, \Closure $fail) use ($date, $row): void {
                // One result a section a day.
                if ($date && WsSectionResult::query()->whereDate('result_date', $date)->where('section_id', $value)->when($row, fn ($q) => $q->whereKeyNot($row->getKey()))->exists()) {
                    $fail(__('workstudy.sp.v.result_taken'));
                }
            }],
            'result_date' => [$required, 'date'],
            'income_target' => $number, 'income' => $number, 'prod_target' => $number, 'prod_qty' => $number,
            'oh_cost' => $number, 'mat_cost' => $number, 'expense' => $number,
            'comments' => ['nullable', 'string', 'max:2000'],
        ];
    }

    public function beforeSave(array $data, ?Model $row): array
    {
        unset($data['company']);
        foreach (self::AMOUNTS as $k) {
            if (array_key_exists($k, $data) && $data[$k] === null) {
                $data[$k] = 0;
            }
        }
        if (! $row) {
            $data['created_by'] = $this->actingUserId();
        }

        return $data;
    }

    public function present(Model $row): array
    {
        /** @var WsSectionResult $row */
        $out = parent::present($row);
        $out['company'] = $row->company_id;
        $out['result_date'] = $row->result_date?->toDateString();
        $out['section'] = DB::table('sections')->where('id', $row->section_id)->value('name');
        $out['total_cost'] = round((float) $row->oh_cost + (float) $row->mat_cost + (float) $row->expense, 2);
        $out['profit'] = $row->profit();
        $out['standing'] = WsSectionProfit::standing($out['profit']);

        return $out;
    }
}
