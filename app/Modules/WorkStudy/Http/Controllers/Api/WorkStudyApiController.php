<?php

declare(strict_types=1);

namespace App\Modules\WorkStudy\Http\Controllers\Api;

use App\Modules\Identity\Models\CompanyUser;
use App\Modules\Identity\Models\User;
use App\Modules\Merchandise\Models\Buyer;
use App\Modules\Merchandise\Models\GarmentPart;
use App\Modules\Planning\Models\PlanningLine;
use App\Modules\Planning\Support\PlanningLabels;
use App\Modules\Tenancy\Models\Building;
use App\Modules\Tenancy\Models\Factory;
use App\Modules\Tenancy\Models\Floor;
use App\Modules\WorkStudy\Models\WsCriticalityLine;
use App\Modules\WorkStudy\Models\WsFinishingAccess;
use App\Modules\WorkStudy\Models\WsGarmentItem;
use App\Modules\WorkStudy\Models\WsLearningPoint;
use App\Modules\WorkStudy\Models\WsResponsibleDept;
use App\Modules\WorkStudy\Models\WsResponsiblePerson;
use App\Modules\WorkStudy\Models\WsTargetAccess;
use App\Modules\WorkStudy\Support\WorkStudyDefaults;
use App\Shared\Http\Api\ApiController;
use App\Shared\Http\Api\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * Work Study → Master Data (AnnotechERP 14/01): what its forms pick from; the
 * two access grids saved as a whole; responsible departments and persons;
 * and the lines each criticality band may run.
 */
class WorkStudyApiController extends ApiController
{
    public function lookups(): JsonResponse
    {
        $this->allow('workstudy.master.view');
        $company = $this->caller()->companyId;
        $members = CompanyUser::query()->where('company_id', $company)->where('status', 'ACTIVE')->pluck('user_id');
        $buildings = Building::query()->pluck('factory_id', 'id');
        $factories = Factory::query()->where('company_id', $company)->pluck('code', 'id');
        WorkStudyDefaults::garmentItems($company);

        return ApiResponse::ok([
            'users' => User::query()->whereIn('id', $members)->orderBy('name')->get(['id', 'name'])->map(fn ($u) => ['value' => $u->id, 'label' => $u->name])->all(),
            'floors' => Floor::query()->orderBy('code')->get(['id', 'code', 'building_id'])->map(fn ($f) => ['value' => $f->id, 'label' => ($factories[$buildings[$f->building_id] ?? ''] ?? '?').'>>'.$f->code])->all(),
            'buyers' => Buyer::query()->orderBy('short_name')->get(['id', 'code', 'short_name'])->map(fn ($b) => ['value' => $b->id, 'label' => "{$b->short_name} ({$b->code})"])->all(),
            'lines' => PlanningLine::query()->where('active', true)->orderBy('display_order')->orderBy('code')->get(['id', 'code'])->map(fn ($l) => ['value' => $l->id, 'label' => (PlanningLabels::forLine($l->id)['floor'] ?? '?').'>>'.$l->code])->all(),
            'items' => WsGarmentItem::query()->where('active', true)->orderBy('item_group')->orderBy('name')->get(['id', 'item_group', 'name'])->map(fn ($i) => ['value' => $i->id, 'label' => "{$i->item_group} — {$i->name}"])->all(),
            'categories' => $this->categories(),
            'parts' => GarmentPart::query()->where('active', true)->orderBy('name')->get(['id', 'code', 'name'])->map(fn ($p) => ['value' => $p->id, 'label' => "{$p->name} ({$p->code})"])->all(),
        ]);
    }

    public function targetAccess(): JsonResponse
    {
        $this->allow('workstudy.master.view');

        return ApiResponse::ok(WsTargetAccess::query()->orderBy('created_at')->get()->map(fn (WsTargetAccess $a) => ['user_id' => $a->user_id, 'floor_ids' => $a->floor_ids, 'target_right' => $a->target_right, 'product_right' => $a->product_right, 'is_admin' => $a->is_admin])->all());
    }

    /** Floor-Wise Target Setup: every line at once; a user appears once. */
    public function saveTargetAccess(Request $request): JsonResponse
    {
        $this->allow('workstudy.master.manage');
        $company = $this->caller()->companyId;
        $data = $request->validate([
            'rows' => ['present', 'array'],
            'rows.*.user_id' => ['required', 'distinct', Rule::in($this->members())],
            'rows.*.floor_ids' => ['present', 'array'],
            'rows.*.floor_ids.*' => [Rule::exists('floors', 'id')->where('company_id', $company)],
            'rows.*.target_right' => ['required', Rule::in(WsTargetAccess::RIGHTS)],
            'rows.*.product_right' => ['required', Rule::in(WsTargetAccess::RIGHTS)],
            'rows.*.is_admin' => ['sometimes', 'boolean'],
        ], ['rows.*.user_id.required' => __('workstudy.master.line_needs_user'), 'rows.*.user_id.distinct' => __('workstudy.master.user_once')]);

        DB::transaction(function () use ($data, $company): void {
            WsTargetAccess::query()->delete();
            foreach ($data['rows'] as $row) {
                WsTargetAccess::query()->create(['company_id' => $company, 'user_id' => $row['user_id'], 'floor_ids' => array_values(array_unique($row['floor_ids'])), 'target_right' => $row['target_right'], 'product_right' => $row['product_right'], 'is_admin' => (bool) ($row['is_admin'] ?? false)]);
            }
        });

        return $this->targetAccess();
    }

    public function finishingAccess(): JsonResponse
    {
        $this->allow('workstudy.master.view');

        return ApiResponse::ok(WsFinishingAccess::query()->orderBy('created_at')->get()->map(fn (WsFinishingAccess $a) => ['user_id' => $a->user_id, 'node_type' => $a->node_type, 'floor_ids' => $a->floor_ids])->all());
    }

    /** Finishing Floor Access Setup: every line at once; Iron and Poly are granted separately. */
    public function saveFinishingAccess(Request $request): JsonResponse
    {
        $this->allow('workstudy.master.manage');
        $company = $this->caller()->companyId;
        $data = $request->validate([
            'rows' => ['present', 'array'],
            'rows.*.user_id' => ['required', Rule::in($this->members())],
            'rows.*.node_type' => ['required', Rule::in(WsFinishingAccess::NODES)],
            'rows.*.floor_ids' => ['present', 'array', 'min:1'],
            'rows.*.floor_ids.*' => [Rule::exists('floors', 'id')->where('company_id', $company)],
        ], ['rows.*.user_id.required' => __('workstudy.master.line_needs_user'), 'rows.*.floor_ids.min' => __('workstudy.master.line_needs_floor')]);
        $keys = array_map(fn ($r) => $r['user_id'].'|'.$r['node_type'], $data['rows']);
        if (count($keys) !== count(array_unique($keys))) {
            throw ValidationException::withMessages(['rows' => __('workstudy.master.node_once')]);
        }

        DB::transaction(function () use ($data, $company): void {
            WsFinishingAccess::query()->delete();
            foreach ($data['rows'] as $row) {
                WsFinishingAccess::query()->create(['company_id' => $company, 'user_id' => $row['user_id'], 'node_type' => $row['node_type'], 'floor_ids' => array_values(array_unique($row['floor_ids']))]);
            }
        });

        return $this->finishingAccess();
    }

    /** Departments with how many people answer for each. */
    public function departments(): JsonResponse
    {
        $this->allow('workstudy.master.view');
        WorkStudyDefaults::departments($this->caller()->companyId);
        $counts = WsResponsiblePerson::query()->selectRaw('dept_id, count(*) as n')->groupBy('dept_id')->pluck('n', 'dept_id');

        return ApiResponse::ok(WsResponsibleDept::query()->orderBy('sl')->orderBy('name')->get()->map(fn ($d) => ['id' => $d->id, 'name' => $d->name, 'code' => $d->code, 'sl' => $d->sl, 'count' => (int) ($counts[$d->id] ?? 0)])->all());
    }

    public function saveDepartment(Request $request): JsonResponse
    {
        $this->allow('workstudy.master.manage');
        $company = $this->caller()->companyId;
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255', Rule::unique('ws_responsible_depts', 'name')->where('company_id', $company)],
            'code' => ['nullable', 'string', 'max:16'],
            'sl' => ['nullable', 'integer', 'min:0', 'max:9999'],
        ], ['name.required' => __('workstudy.master.dept_required')]);
        $dept = WsResponsibleDept::query()->create(['company_id' => $company, 'name' => $data['name'], 'code' => $data['code'] ?? null, 'sl' => $data['sl'] ?? 0]);

        return ApiResponse::created(['id' => $dept->id]);
    }

    /** Every responsible person, with their department — the Records tab. */
    public function allPersons(): JsonResponse
    {
        $this->allow('workstudy.master.view');
        $depts = WsResponsibleDept::query()->get(['id', 'name', 'sl'])->keyBy('id');
        $users = User::query()->whereIn('id', WsResponsiblePerson::query()->pluck('user_id'))->pluck('name', 'id');

        return ApiResponse::ok(WsResponsiblePerson::query()->orderBy('created_at')->get()
            ->sortBy(fn ($p) => sprintf('%06d', $depts[$p->dept_id]->sl ?? 0).($depts[$p->dept_id]->name ?? ''))->values()
            ->map(fn ($p) => $this->person($p, $users) + ['dept' => $depts[$p->dept_id]->name ?? null])->all());
    }

    public function persons(string $dept): JsonResponse
    {
        $this->allow('workstudy.master.view');
        $row = WsResponsibleDept::query()->whereKey($dept)->firstOrFail();
        $users = User::query()->whereIn('id', WsResponsiblePerson::query()->where('dept_id', $row->id)->pluck('user_id'))->pluck('name', 'id');

        return ApiResponse::ok(WsResponsiblePerson::query()->where('dept_id', $row->id)->orderBy('created_at')->get()->map(fn ($p) => $this->person($p, $users))->all());
    }

    /** Responsible Dept-Person Mapping: the selected department's whole grid at once. */
    public function savePersons(Request $request, string $dept): JsonResponse
    {
        $this->allow('workstudy.master.manage');
        $row = WsResponsibleDept::query()->whereKey($dept)->firstOrFail();
        $company = $this->caller()->companyId;
        $data = $request->validate([
            'rows' => ['present', 'array'],
            'rows.*.user_id' => ['required', 'distinct', Rule::in($this->members())],
            'rows.*.floor_ids' => ['present', 'array'],
            'rows.*.floor_ids.*' => [Rule::exists('floors', 'id')->where('company_id', $company)],
            'rows.*.all_floors' => ['sometimes', 'boolean'],
            'rows.*.buyer_ids' => ['present', 'array'],
            'rows.*.buyer_ids.*' => [Rule::exists('buyers', 'id')->where('company_id', $company)],
            'rows.*.all_buyers' => ['sometimes', 'boolean'],
        ], ['rows.*.user_id.required' => __('workstudy.master.line_needs_employee'), 'rows.*.user_id.distinct' => __('workstudy.master.person_taken')]);

        DB::transaction(function () use ($data, $row, $company): void {
            WsResponsiblePerson::query()->where('dept_id', $row->id)->delete();
            foreach ($data['rows'] as $line) {
                $allFloors = (bool) ($line['all_floors'] ?? false);
                $allBuyers = (bool) ($line['all_buyers'] ?? false);
                WsResponsiblePerson::query()->create([
                    'company_id' => $company, 'dept_id' => $row->id, 'user_id' => $line['user_id'],
                    'floor_ids' => $allFloors ? [] : array_values(array_unique($line['floor_ids'])), 'all_floors' => $allFloors,
                    'buyer_ids' => $allBuyers ? [] : array_values(array_unique($line['buyer_ids'])), 'all_buyers' => $allBuyers,
                ]);
            }
        });

        return $this->persons($row->id);
    }

    /** Criticality bands with how many lines run each. */
    public function criticality(): JsonResponse
    {
        $this->allow('workstudy.master.view');
        $counts = WsCriticalityLine::query()->where('runs', true)->selectRaw('criticality, count(*) as n')->groupBy('criticality')->pluck('n', 'criticality');

        return ApiResponse::ok(array_map(fn (string $band) => ['key' => $band, 'count' => (int) ($counts[$band] ?? 0)], WsLearningPoint::PROD_TYPES));
    }

    /** Every active line, ticked where it runs the band, with its preferred category there. */
    public function criticalityLines(string $band): JsonResponse
    {
        $this->allow('workstudy.master.view');
        abort_unless(in_array($band, WsLearningPoint::PROD_TYPES, true), 404);
        $set = WsCriticalityLine::query()->where('criticality', $band)->get()->keyBy('planning_line_id');

        return ApiResponse::ok(PlanningLine::query()->where('active', true)->orderBy('display_order')->orderBy('code')->get(['id', 'code'])->map(function ($l) use ($set) {
            $labels = PlanningLabels::forLine($l->id);

            return ['planning_line_id' => $l->id, 'unit_floor' => ($labels['unit'] ?? '—').' || '.($labels['floor'] ?? '—'), 'line' => $l->code, 'runs' => (bool) $set->get($l->id)?->runs, 'preferred_group' => $set->get($l->id)?->preferred_group];
        })->all());
    }

    /** Product Criticality-Line & Preferred Gmt Category: the band's whole grid at once. */
    public function saveCriticality(Request $request, string $band): JsonResponse
    {
        $this->allow('workstudy.master.manage');
        abort_unless(in_array($band, WsLearningPoint::PROD_TYPES, true), 404);
        $company = $this->caller()->companyId;
        $data = $request->validate([
            'rows' => ['present', 'array'],
            'rows.*.planning_line_id' => ['required', 'distinct', Rule::exists('planning_lines', 'id')->where('company_id', $company)],
            'rows.*.runs' => ['sometimes', 'boolean'],
            'rows.*.preferred_group' => ['nullable', Rule::in($this->categories())],
        ]);

        DB::transaction(function () use ($data, $band, $company): void {
            WsCriticalityLine::query()->where('criticality', $band)->delete();
            foreach ($data['rows'] as $line) {
                if (! ($line['runs'] ?? false) && empty($line['preferred_group'])) {
                    continue;
                }
                WsCriticalityLine::query()->create(['company_id' => $company, 'criticality' => $band, 'planning_line_id' => $line['planning_line_id'], 'runs' => (bool) ($line['runs'] ?? false), 'preferred_group' => $line['preferred_group'] ?? null]);
            }
        });

        return $this->criticalityLines($band);
    }

    /** Planning Learning Curve: one fabric class's bands × days at once; a blank cell drops that day. */
    public function saveLearningCurve(Request $request, string $fabricClass): JsonResponse
    {
        $this->allow('workstudy.master.manage');
        abort_unless(in_array($fabricClass, WsLearningPoint::FABRIC_CLASSES, true), 404);
        $company = $this->caller()->companyId;
        $data = $request->validate([
            'points' => ['present', 'array'],
            'points.*.prod_type' => ['required', Rule::in(WsLearningPoint::PROD_TYPES)],
            'points.*.day' => ['required', 'integer', 'min:1', 'max:365'],
            'points.*.efficiency' => ['required', 'numeric', 'min:0', 'max:100'],
        ], ['points.*.efficiency.max' => __('workstudy.master.efficiency_max')]);
        $keys = array_map(fn ($p) => $p['prod_type'].'|'.$p['day'], $data['points']);
        if (count($keys) !== count(array_unique($keys))) {
            throw ValidationException::withMessages(['points' => __('workstudy.master.day_taken')]);
        }

        DB::transaction(function () use ($data, $fabricClass, $company): void {
            WsLearningPoint::query()->where('fabric_class', $fabricClass)->delete();
            foreach ($data['points'] as $point) {
                WsLearningPoint::query()->create(['company_id' => $company, 'fabric_class' => $fabricClass] + $point);
            }
        });

        return ApiResponse::ok(WsLearningPoint::query()->where('fabric_class', $fabricClass)->orderBy('prod_type')->orderBy('day')->get(['prod_type', 'day', 'efficiency'])->all());
    }

    /** @param Collection<string, string> $users */
    private function person(WsResponsiblePerson $p, $users): array
    {
        return ['id' => $p->id, 'dept_id' => $p->dept_id, 'user_id' => $p->user_id, 'user' => $users[$p->user_id] ?? null, 'floor_ids' => $p->floor_ids, 'all_floors' => $p->all_floors, 'buyer_ids' => $p->buyer_ids, 'all_buyers' => $p->all_buyers];
    }

    /** @return list<string> Brickland's garment categories, and any item group added since. */
    private function categories(): array
    {
        return array_values(array_unique([...WorkStudyDefaults::GMT_CATEGORIES, ...WsGarmentItem::query()->distinct()->pluck('item_group')->all()]));
    }

    /** @return list<string> */
    private function members(): array
    {
        return CompanyUser::query()->where('company_id', $this->caller()->companyId)->where('status', 'ACTIVE')->pluck('user_id')->all();
    }
}
