<?php

declare(strict_types=1);

namespace App\Modules\DyeHouse\Resources;

use App\Modules\DyeHouse\Models\DyeMachineMaintenance;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * Dye Machine Maintenance: a dyeing machine's downtime, planned or breakdown —
 * when maintenance was told, when the machine actually stopped and when it ran
 * again, the defect, who reported it and who serviced it. Downtime is worked
 * out from the stop and the restart, never typed beside them; a job marked
 * resolved says when it ran again.
 */
class DyeMachineMaintenanceResource extends DyeBatchResource
{
    public function key(): string
    {
        return 'dye-machine-maintenance';
    }

    public function model(): string
    {
        return DyeMachineMaintenance::class;
    }

    public function viewPermission(): string
    {
        return 'dyehouse.requisition.view';
    }

    public function managePermission(): string
    {
        return 'dyehouse.requisition.manage';
    }

    public function columns(): array
    {
        return ['dye_machine_id', 'log_date', 'report_time', 'problem_time', 'resolved_time', 'defect_type', 'report_by', 'service_by', 'status', 'remarks'];
    }

    public function query(): Builder
    {
        return DyeMachineMaintenance::query()->orderByDesc('log_date')->orderByDesc('report_time');
    }

    public function messages(): array
    {
        return [
            'dye_machine_id.required' => __('dyehouse.req.v.machine'), 'log_date.required' => __('dyehouse.req.v.log_date'), 'report_time.required' => __('dyehouse.req.v.report_time'),
            'defect_type.required' => __('dyehouse.req.v.defect_type'), 'status.required' => __('dyehouse.req.v.status'),
            'resolved_time.after_or_equal' => __('dyehouse.req.v.resolved_before_problem'),
        ];
    }

    public function rules(?Model $row): array
    {
        $required = $row ? 'sometimes' : 'required';
        $problem = request()->input('problem_time') ?? $row?->getAttribute('problem_time')?->format('Y-m-d H:i');

        return [
            'dye_machine_id' => [$required, $this->own('dye_machines')],
            'log_date' => [$required, 'date'],
            'report_time' => [$required, 'date'],
            'problem_time' => ['nullable', 'date'],
            'resolved_time' => ['nullable', 'date', ...($problem ? ['after_or_equal:'.$problem] : [])],
            'defect_type' => [$required, 'string', 'max:120'],
            'report_by' => ['nullable', $this->own('hr_employees')],
            'service_by' => ['nullable', $this->own('hr_employees')],
            'status' => [$required, Rule::in(DyeMachineMaintenance::STATUSES)],
            'remarks' => ['nullable', 'string', 'max:2000'],
        ];
    }

    public function beforeSave(array $data, ?Model $row): array
    {
        $status = $data['status'] ?? $row?->getAttribute('status');
        $resolved = array_key_exists('resolved_time', $data) ? $data['resolved_time'] : $row?->getAttribute('resolved_time');
        if ($status === 'RESOLVED' && ! $resolved) {
            throw ValidationException::withMessages(['resolved_time' => __('dyehouse.req.v.resolved_needs_time')]);
        }
        if (! $row) {
            $data['created_by'] = $this->actingUserId();
        }

        return $data;
    }

    public function present(Model $row): array
    {
        $out = parent::present($row);
        $stamp = fn ($t) => $t ? CarbonImmutable::parse($t)->format('Y-m-d\TH:i') : null;
        foreach (['report_time', 'problem_time', 'resolved_time'] as $k) {
            $out[$k] = $stamp($row->getAttribute($k));
        }
        $problem = $row->getAttribute('problem_time');
        $resolved = $row->getAttribute('resolved_time');
        $mins = $problem && $resolved ? (int) round($problem->diffInMinutes($resolved)) : null;
        $out['downtime'] = $mins === null ? null : sprintf('%dh %02dm', intdiv($mins, 60), $mins % 60);
        $out['machine'] = $this->names()['machines'][$row->getAttribute('dye_machine_id')] ?? null;
        $people = DB::table('hr_employees')->whereIn('id', array_filter([$row->getAttribute('report_by'), $row->getAttribute('service_by')]))->get(['id', 'employee_code', 'name'])->keyBy('id');
        $name = fn ($id) => isset($people[$id]) ? trim("{$people[$id]->employee_code} — {$people[$id]->name}", ' —') : null;
        $out['reporter'] = $name($row->getAttribute('report_by'));
        $out['servicer'] = $name($row->getAttribute('service_by'));

        return $out;
    }
}
