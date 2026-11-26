<?php

declare(strict_types=1);

namespace App\Modules\WorkStudy\Resources;

use App\Modules\Erp\Support\ResourceDefinition;
use App\Modules\WorkStudy\Models\WsOtPlan;
use App\Modules\WorkStudy\Support\WsOvertime;
use App\Shared\Tenancy\TenantContext;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * Garment Overtime Plan → Records and New OT plan row: a manpower group on a
 * floor for one day — its line, department and designation, the general-hours
 * headcount and hours — planned for so many hours from 6PM, each hour holding
 * its own operators and ASOs (they are paid at different rates). One plan a
 * group a day; a record is removed, not edited — the OT Plan sheet changes
 * it. Work Study → OT & Salary (AnnotechERP 14/06).
 */
class OtPlanResource extends ResourceDefinition
{
    public function key(): string
    {
        return 'ws-ot-plans';
    }

    public function model(): string
    {
        return WsOtPlan::class;
    }

    public function viewPermission(): string
    {
        return 'workstudy.ot.view';
    }

    public function managePermission(): string
    {
        return 'workstudy.ot.manage';
    }

    public function columns(): array
    {
        return ['plan_date', 'floor_id', 'planning_line_id', 'department_id', 'designation_id', 'mp_gen', 'wh_gen', 'prev_wh', 'remarks'];
    }

    public function query(): Builder
    {
        return WsOtPlan::query()->orderByDesc('plan_date')->orderBy('created_at');
    }

    public function messages(): array
    {
        return [
            'plan_date.required' => __('workstudy.ot.v.date'),
            'floor_id.required' => __('workstudy.ot.v.floor'),
            'mp_gen.required' => __('workstudy.ot.v.mp'),
            'hour_count.required' => __('workstudy.ot.v.hours'),
            'hour_count.min' => __('workstudy.ot.v.hours'),
            'hour_count.max' => __('workstudy.ot.v.hours_max', ['n' => count(WsOvertime::SLOTS)]),
        ];
    }

    public function rules(?Model $row): array
    {
        $required = $row ? 'sometimes' : 'required';
        $company = app(TenantContext::class)->companyId();
        $count = ['nullable', 'integer', 'min:0', 'max:9999'];

        return [
            'plan_date' => [$required, 'date'],
            'floor_id' => [$required, Rule::exists('floors', 'id')->where('company_id', $company)],
            'planning_line_id' => ['nullable', Rule::exists('planning_lines', 'id')->where('company_id', $company)->where('floor_id', request('floor_id', $row?->getAttribute('floor_id')))],
            'department_id' => ['nullable', Rule::exists('departments', 'id')->where('company_id', $company)],
            'designation_id' => ['nullable', Rule::exists('designations', 'id')->where('company_id', $company)],
            'mp_gen' => [$required, 'integer', 'min:0', 'max:9999'],
            'wh_gen' => ['nullable', 'numeric', 'min:0', 'max:24'],
            'prev_wh' => ['nullable', 'numeric', 'min:0', 'max:24'],
            'hour_count' => [$required, 'integer', 'min:1', 'max:'.count(WsOvertime::SLOTS)],
            'op_per_hour' => $count,
            'aso_per_hour' => $count,
            'remarks' => ['nullable', 'string', 'max:255'],
        ];
    }

    public function beforeSave(array $data, ?Model $row): array
    {
        $op = (int) ($data['op_per_hour'] ?? 0);
        $aso = (int) ($data['aso_per_hour'] ?? 0);
        if (isset($data['hour_count'])) {
            if ($op === 0 && $aso === 0) {
                throw ValidationException::withMessages(['op_per_hour' => __('workstudy.ot.v.per_hour')]);
            }
            $data['slots'] = array_fill_keys(array_map('strval', WsOvertime::firstSlots((int) $data['hour_count'])), ['op' => $op, 'aso' => $aso]);
        }
        unset($data['hour_count'], $data['op_per_hour'], $data['aso_per_hour']);
        if (! $row) {
            $key = WsOvertime::key($data['planning_line_id'] ?? null, $data['department_id'] ?? null, $data['designation_id'] ?? null);
            $taken = WsOtPlan::query()->where('floor_id', $data['floor_id'])->whereDate('plan_date', $data['plan_date'])->get()
                ->contains(fn (WsOtPlan $p) => WsOvertime::key($p->planning_line_id, $p->department_id, $p->designation_id) === $key);
            if ($taken) {
                throw ValidationException::withMessages(['designation_id' => __('workstudy.ot.v.group_taken')]);
            }
            $data['created_by'] = $this->actingUserId();
        }

        return $data;
    }

    public function present(Model $row): array
    {
        $out = parent::present($row);
        $out['plan_date'] = $row->getAttribute('plan_date')?->toDateString();
        $floor = DB::table('floors')->where('id', $row->getAttribute('floor_id'))->first(['name', 'code']);
        $out['floor'] = $floor?->name;
        $out['floor_code'] = $floor?->code;
        $out['line'] = DB::table('planning_lines')->where('id', $row->getAttribute('planning_line_id'))->value('code');
        $out['department'] = DB::table('departments')->where('id', $row->getAttribute('department_id'))->value('name');
        $out['designation'] = DB::table('designations')->where('id', $row->getAttribute('designation_id'))->value('name');
        $out['hours'] = WsOvertime::hours($row->getAttribute('slots'));

        return $out;
    }
}
