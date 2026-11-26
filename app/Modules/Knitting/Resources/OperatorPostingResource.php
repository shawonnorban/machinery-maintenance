<?php

declare(strict_types=1);

namespace App\Modules\Knitting\Resources;

use App\Modules\Identity\Models\CompanyUser;
use App\Modules\Identity\Models\User;
use App\Modules\Knitting\Models\KnitOperatorPosting;
use App\Modules\Knitting\Support\KnittingLabels;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * Machine Operator Assignment: the operator posted to a machine for a
 * schedule, from a moment (a time as well as a day) until the next posting
 * dates it. One live operator per machine per schedule — a posting that
 * overlaps one on file is refused. A change of operator is a new posting.
 * Knit Production → Master Data (AnnotechERP 15/01).
 */
class OperatorPostingResource extends KnittingResource
{
    public function key(): string
    {
        return 'knit-operator-postings';
    }

    public function model(): string
    {
        return KnitOperatorPosting::class;
    }

    public function columns(): array
    {
        return ['machine_id', 'user_id', 'shift_id', 'effective_at', 'until_at', 'active'];
    }

    public function booleans(): array
    {
        return ['active'];
    }

    public function defaults(): array
    {
        return ['active' => true];
    }

    public function query(): Builder
    {
        return KnitOperatorPosting::query()->orderByDesc('effective_at');
    }

    public function messages(): array
    {
        return ['effective_at.required' => __('knitting.master.effective_required'), 'machine_id.required' => __('knitting.master.machine_required'), 'user_id.required' => __('knitting.master.operator_required')];
    }

    public function rules(?Model $row): array
    {
        $required = $row ? 'sometimes' : 'required';
        $company = $this->company();

        return [
            'machine_id' => [$required, Rule::exists('knit_machines', 'id')->where('company_id', $company)],
            'user_id' => [$required, Rule::in(CompanyUser::query()->where('company_id', $company)->where('status', 'ACTIVE')->pluck('user_id')->all())],
            'shift_id' => [$required, Rule::exists('hr_shifts', 'id')->where('company_id', $company)],
            'effective_at' => [$required, 'date'],
            'until_at' => ['nullable', 'date'],
            'active' => ['sometimes', 'boolean'],
        ];
    }

    public function beforeSave(array $data, ?Model $row): array
    {
        $p = array_merge($row ? ['machine_id' => $row->machine_id, 'shift_id' => $row->shift_id, 'effective_at' => $row->effective_at, 'until_at' => $row->until_at, 'active' => $row->active] : ['active' => true], $data);
        $from = Carbon::parse($p['effective_at']);
        $until = ($p['until_at'] ?? null) ? Carbon::parse($p['until_at']) : null;
        if ($until && $until->lte($from)) {
            throw ValidationException::withMessages(['until_at' => __('knitting.master.until_before_effective')]);
        }
        if ($p['active'] ?? true) {
            $clash = KnitOperatorPosting::query()->where('machine_id', $p['machine_id'])->where('shift_id', $p['shift_id'])->where('active', true)
                ->when($row, fn ($q) => $q->whereKeyNot($row->getKey()))
                ->where(fn ($q) => $q->whereNull('until_at')->orWhere('until_at', '>', $from))
                ->when($until, fn ($q) => $q->where('effective_at', '<', $until))
                ->exists();
            if ($clash) {
                throw ValidationException::withMessages(['effective_at' => __('knitting.master.posting_overlaps')]);
            }
        }

        return $data;
    }

    public function present(Model $row): array
    {
        $out = parent::present($row);
        $out['effective_at'] = $row->effective_at?->format('Y-m-d\TH:i');
        $out['until_at'] = $row->until_at?->format('Y-m-d\TH:i');
        $machine = KnittingLabels::machines()[$row->getAttribute('machine_id')] ?? null;
        $user = User::query()->whereKey($row->getAttribute('user_id'))->first(['name', 'employee_code']);
        $out['machine_no'] = $machine['no'] ?? null;
        $out['floor'] = $machine ? (KnittingLabels::floors()[$machine['floor_id']] ?? null) : null;
        $out['emp_code'] = $user?->employee_code;
        $out['name'] = $user?->name;
        $out['shift'] = KnittingLabels::shifts()[$row->getAttribute('shift_id')] ?? null;
        $out['standing'] = $row->until_at === null || $row->until_at->isFuture();

        return $out;
    }
}
