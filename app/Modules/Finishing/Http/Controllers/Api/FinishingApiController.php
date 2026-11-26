<?php

declare(strict_types=1);

namespace App\Modules\Finishing\Http\Controllers\Api;

use App\Modules\DyeHouse\Models\DyeMachineList;
use App\Modules\DyeHouse\Support\DyeHouseDefaults;
use App\Modules\Finishing\Models\FinMachine;
use App\Modules\Finishing\Models\FinProcessGroup;
use App\Modules\Finishing\Models\FinProcessSubgroup;
use App\Modules\Finishing\Models\FinProcessType;
use App\Modules\Finishing\Support\FinishingDefaults;
use App\Modules\Merchandise\Models\OriginCountry;
use App\Modules\Tenancy\Models\Floor;
use App\Shared\Files\Actions\StoreFileAttachment;
use App\Shared\Http\Api\ApiController;
use App\Shared\Http\Api\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * Finishing Line → Master Data (AnnotechERP 18/01): what its forms pick from;
 * the process groups and each group's sub-groups, saved as a whole; and a
 * machine's key image, stored privately and named by path.
 */
class FinishingApiController extends ApiController
{
    public function lookups(): JsonResponse
    {
        $this->allow('finishing.master.view');
        $company = $this->caller()->companyId;
        FinishingDefaults::ensure($company);
        DyeHouseDefaults::machineLists($company);
        $lists = DyeMachineList::query()->where('active', true)->orderBy('sort_order')->orderBy('name')->get(['id', 'list', 'name']);

        return ApiResponse::ok([
            'groups' => FinProcessGroup::query()->where('active', true)->orderBy('sort_order')->get(['id', 'name'])->map(fn ($g) => ['value' => $g->id, 'label' => $g->name])->all(),
            'subgroups' => FinProcessSubgroup::query()->where('active', true)->orderBy('sort_order')->get(['id', 'group_id', 'name'])->groupBy('group_id')->map(fn ($s) => $s->map(fn ($x) => ['value' => $x->id, 'label' => $x->name])->values())->all(),
            'processes' => FinProcessType::query()->where('active', true)->orderBy('display_order')->get(['id', 'name', 'short_name'])->map(fn ($p) => ['value' => $p->id, 'label' => "{$p->name} ({$p->short_name})"])->all(),
            'machines' => FinMachine::query()->where('active', true)->orderBy('machine_no')->get(['id', 'machine_no', 'name'])->map(fn ($m) => ['value' => $m->id, 'label' => "{$m->machine_no} — {$m->name}"])->all(),
            'brands' => $lists->where('list', 'BRAND')->values()->map(fn ($l) => ['value' => $l->id, 'label' => $l->name])->all(),
            'locations' => $lists->where('list', 'LOCATION')->values()->map(fn ($l) => ['value' => $l->id, 'label' => $l->name])->all(),
            'countries' => OriginCountry::query()->where('active', true)->orderBy('name')->get(['id', 'name'])->map(fn ($c) => ['value' => $c->id, 'label' => $c->name])->all(),
            'floors' => Floor::query()->orderBy('name')->get(['id', 'name'])->map(fn ($f) => ['value' => $f->id, 'label' => $f->name])->all(),
        ]);
    }

    /** The groups with how many processes and sub-groups each holds. */
    public function groups(): JsonResponse
    {
        $this->allow('finishing.master.view');
        FinishingDefaults::ensure($this->caller()->companyId);
        $processes = FinProcessType::query()->selectRaw('group_id, count(*) as n')->groupBy('group_id')->pluck('n', 'group_id');
        $subs = FinProcessSubgroup::query()->selectRaw('group_id, count(*) as n')->groupBy('group_id')->pluck('n', 'group_id');

        return ApiResponse::ok(FinProcessGroup::query()->orderBy('sort_order')->orderBy('name')->get()->map(fn ($g) => ['key' => $g->id, 'label' => $g->name, 'processes' => (int) ($processes[$g->id] ?? 0), 'count' => (int) ($subs[$g->id] ?? 0)])->all());
    }

    public function saveGroup(Request $request): JsonResponse
    {
        $this->allow('finishing.master.manage');
        $company = $this->caller()->companyId;
        $data = $request->validate([
            'code' => ['required', 'string', 'max:24', 'regex:/^[A-Za-z0-9._-]+$/', Rule::unique('fin_process_groups', 'code')->where('company_id', $company)],
            'name' => ['required', 'string', 'max:255'],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:9999'],
        ], ['code.regex' => __('finishing.master.code_format')]);
        $group = FinProcessGroup::query()->create(['company_id' => $company, 'code' => strtoupper($data['code']), 'name' => $data['name'], 'sort_order' => $data['sort_order'] ?? 0, 'active' => true]);

        return ApiResponse::created(['id' => $group->id]);
    }

    public function subgroups(string $group): JsonResponse
    {
        $this->allow('finishing.master.view');
        $row = FinProcessGroup::query()->whereKey($group)->firstOrFail();
        $uses = FinProcessType::query()->whereNotNull('subgroup_id')->selectRaw('subgroup_id, count(*) as n')->groupBy('subgroup_id')->pluck('n', 'subgroup_id');

        return ApiResponse::ok(FinProcessSubgroup::query()->where('group_id', $row->id)->orderBy('sort_order')->orderBy('name')->get()
            ->map(fn ($s) => ['id' => $s->id, 'code' => $s->code, 'name' => $s->name, 'sort_order' => (string) $s->sort_order, 'active' => $s->active, 'processes' => (int) ($uses[$s->id] ?? 0)])->all());
    }

    /** A group's sub-groups as a whole: edited, added, and unused ones removed. */
    public function saveSubgroups(Request $request, string $group): JsonResponse
    {
        $this->allow('finishing.master.manage');
        $row = FinProcessGroup::query()->whereKey($group)->firstOrFail();
        $company = $this->caller()->companyId;
        $data = $request->validate([
            'rows' => ['present', 'array'],
            'rows.*.id' => ['nullable', Rule::exists('fin_process_subgroups', 'id')->where('group_id', $row->id)],
            'rows.*.code' => ['required', 'string', 'max:24', 'regex:/^[A-Za-z0-9._-]+$/'],
            'rows.*.name' => ['required', 'string', 'max:255'],
            'rows.*.sort_order' => ['nullable', 'integer', 'min:0', 'max:9999'],
            'rows.*.active' => ['sometimes', 'boolean'],
        ], ['rows.*.code.required' => __('finishing.master.subgroup_needs_code'), 'rows.*.name.required' => __('finishing.master.subgroup_needs_name'), 'rows.*.code.regex' => __('finishing.master.code_format')]);
        $codes = array_map(fn ($r) => strtoupper(trim($r['code'])), $data['rows']);
        $kept = array_filter(array_column($data['rows'], 'id'));
        $elsewhere = FinProcessSubgroup::query()->whereIn(DB::raw('upper(code)'), $codes)->whereNotIn('id', $kept)->where('group_id', '!=', $row->id)->exists();
        if (count($codes) !== count(array_unique($codes)) || $elsewhere) {
            throw ValidationException::withMessages(['rows' => __('finishing.master.subgroup_code_taken')]);
        }
        $dropped = FinProcessSubgroup::query()->where('group_id', $row->id)->whereNotIn('id', $kept)->pluck('id');
        if ($dropped->isNotEmpty() && FinProcessType::query()->whereIn('subgroup_id', $dropped)->exists()) {
            throw ValidationException::withMessages(['rows' => __('finishing.master.subgroup_in_use')]);
        }

        DB::transaction(function () use ($data, $row, $company, $dropped): void {
            FinProcessSubgroup::query()->whereIn('id', $dropped)->delete();
            foreach ($data['rows'] as $line) {
                $values = ['code' => strtoupper(trim($line['code'])), 'name' => trim($line['name']), 'sort_order' => (int) ($line['sort_order'] ?? 0), 'active' => (bool) ($line['active'] ?? true)];
                empty($line['id'])
                    ? FinProcessSubgroup::query()->create($values + ['company_id' => $company, 'group_id' => $row->id])
                    : FinProcessSubgroup::query()->whereKey($line['id'])->firstOrFail()->update($values);
            }
        });

        return $this->subgroups($row->id);
    }

    /** A photograph of a machine's nameplate: stored privately, its path returned for the Key Image box. */
    public function keyImage(Request $request, StoreFileAttachment $files): JsonResponse
    {
        $this->allow('finishing.master.manage');
        $request->validate(['file' => ['required', 'file']]);
        $file = $files->handle($request->file('file'), 'fin_machine_key_image', $this->caller()->companyId, $this->caller()->auditUserId());

        return ApiResponse::created(['id' => $file->id, 'path' => "/api/files/{$file->id}/download"]);
    }
}
