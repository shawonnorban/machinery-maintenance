<?php

declare(strict_types=1);

namespace App\Modules\WorkStudy\Http\Controllers\Api;

use App\Modules\Merchandise\Models\Buyer;
use App\Modules\Merchandise\Models\Order;
use App\Modules\WorkStudy\Models\WsCmRate;
use App\Modules\WorkStudy\Models\WsGarmentItem;
use App\Modules\WorkStudy\Models\WsItemGroup;
use App\Modules\WorkStudy\Models\WsLearningPoint;
use App\Modules\WorkStudy\Models\WsLineProfile;
use App\Modules\WorkStudy\Models\WsResponsiblePerson;
use App\Modules\WorkStudy\Models\WsSpec;
use App\Modules\WorkStudy\Models\WsSpecMachine;
use App\Modules\WorkStudy\Models\WsSpecProcess;
use App\Modules\WorkStudy\Models\WsTechVariation;
use App\Modules\WorkStudy\Support\WsSpecs;
use App\Shared\Files\Actions\StoreFileAttachment;
use App\Shared\Http\Api\ApiController;
use App\Shared\Http\Api\ApiResponse;
use App\Shared\Tenancy\TenantContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * Work Study → Item Specification (AnnotechERP 14/04), as Brickland keeps it:
 * Garment Spec Entry — a style's engineered variation with the process groups
 * it is specified against and the machines it needs (a variation once per
 * style; the nature follows the sewing SMV when not chosen; efficiency capped
 * at 100) — and the Garment Item Specification register: the order items with
 * the specification their style carries, each group's categories and each
 * category's technical variations edited in place. A specification travels by
 * its style and variation, a group and a category by their codes.
 */
class WsSpecApiController extends ApiController
{
    /** What the forms choose from. */
    public function options(): JsonResponse
    {
        $this->allow('workstudy.spec.view');
        WsSpecs::ensure($this->company());
        // Categories follow their group's own order (Knit Tops first, as Brickland lists them).
        $order = WsItemGroup::query()->orderBy('sort_order')->orderBy('name')->pluck('name')->flip()->all();

        return ApiResponse::ok([
            'company' => ['value' => $this->company(), 'label' => DB::table('companies')->where('id', $this->company())->value('name')],
            'process_groups' => DB::table('garment_parts')->where('company_id', $this->company())->where('is_process_group', true)->orderBy('name')->get(['id', 'name'])
                ->map(fn ($p) => ['value' => $p->id, 'label' => $p->name])->values()->all(),
            'sample_types' => DB::table('sample_types')->where('company_id', $this->company())->orderBy('name')->get(['id', 'name'])->map(fn ($s) => ['value' => $s->id, 'label' => $s->name])->values()->all(),
            'groups' => $this->groupRows(),
            'categories' => WsGarmentItem::query()->orderBy('sl')->get()->sortBy(fn (WsGarmentItem $i) => [$order[$i->item_group] ?? 9999, $i->sl])->values()->map(fn (WsGarmentItem $i) => ['value' => $i->id, 'label' => "{$i->item_group} — {$i->name}", 'group' => $i->item_group, 'code' => $i->code, 'active' => $i->active])->values()->all(),
            'fabric_classes' => WsLearningPoint::FABRIC_CLASSES, 'natures' => WsLearningPoint::PROD_TYPES, 'machine_types' => WsLineProfile::MACHINE_TYPES, 'bands' => WsSpecs::BANDS,
        ]);
    }

    /* ---------------- Garment Spec Entry ---------------- */

    public function specs(): JsonResponse
    {
        $this->allow('workstudy.spec.view');

        return ApiResponse::ok($this->specRows(WsSpec::query()->orderBy('style_no')->orderBy('variation_no')->get()));
    }

    public function spec(Request $request): JsonResponse
    {
        $this->allow('workstudy.spec.view');

        return ApiResponse::ok($this->specPayload($this->find((string) $request->query('style'), (int) $request->query('v'))));
    }

    /** New specification: from the Garments Item Specification tab with its grids, or the New tab without. */
    public function storeSpec(Request $request): JsonResponse
    {
        $this->allow('workstudy.spec.manage');
        $data = $this->specInput($request, null);
        $spec = DB::transaction(function () use ($data): WsSpec {
            $spec = WsSpec::query()->create($data['header'] + ['company_id' => $this->company(), 'created_by' => $this->caller()->auditUserId()]);
            $this->writeLines($spec, $data['processes'] ?? [], $data['machines'] ?? []);

            return $spec;
        });

        return ApiResponse::created($this->specPayload($spec->fresh()));
    }

    public function updateSpec(Request $request): JsonResponse
    {
        $this->allow('workstudy.spec.manage');
        $spec = $this->find((string) $request->query('style'), (int) $request->query('v'));
        $data = $this->specInput($request, $spec);
        DB::transaction(function () use ($spec, $data): void {
            $spec->update($data['header']);
            WsSpecProcess::query()->where('spec_id', $spec->id)->delete();
            WsSpecMachine::query()->where('spec_id', $spec->id)->delete();
            $this->writeLines($spec, $data['processes'] ?? [], $data['machines'] ?? []);
        });

        return ApiResponse::ok($this->specPayload($spec->fresh()));
    }

    /** The operation bulletin a specification's SMV was read off; the stored file's link goes back. */
    public function obFile(Request $request, StoreFileAttachment $files): JsonResponse
    {
        $this->allow('workstudy.spec.manage');
        $request->validate(['file' => ['required', 'file', 'max:20480']]);
        $file = $files->handle($request->file('file'), 'ws_spec_ob_file', $this->caller()->companyId, $this->caller()->auditUserId());

        return ApiResponse::created(['id' => $file->id, 'path' => "/api/files/{$file->id}/download"]);
    }

    /* ---------------- Garment Item Specification ---------------- */

    /** GMT Item List: each order's item with the specification its style carries. */
    public function itemList(Request $request): JsonResponse
    {
        $this->allow('workstudy.spec.view');
        $f = $request->only(['month', 'factory_id', 'item_group', 'item_type', 'gmt_type', 'nature', 'q', 'my_buyer', 'not_in_house']);
        $orders = Order::query()->whereNotIn('status', ['CANCELLED'])->where('order_type', '!=', 'PROJECTION')->orderByDesc('conf_date')->orderBy('order_no')->limit(2000)->get();
        $items = DB::table('merch_style_items')->whereIn('style_id', $orders->pluck('style_id')->filter()->unique())->orderBy('sort_order')->get()->groupBy('style_id');
        $specs = WsSpec::query()->whereIn('style_no', $orders->pluck('style_no')->filter()->unique())->orderByDesc('variation_no')->get()->groupBy('style_no');
        // The CM each specification was quoted at for a booking month (Spec-Wise CM Rate, 14/05).
        $rates = WsCmRate::query()->get()->keyBy(fn (WsCmRate $r) => "{$r->spec_id}|{$r->booking_month}");
        $buyers = Buyer::query()->pluck('short_name', 'id');
        $units = DB::table('factories')->where('company_id', $this->company())->pluck('name', 'id');
        $mine = null;
        if (! empty($f['my_buyer'])) {
            $persons = WsResponsiblePerson::query()->where('user_id', $this->caller()->auditUserId())->get();
            $mine = $persons->contains(fn ($p) => $p->all_buyers) ? null : $persons->flatMap(fn ($p) => $p->buyer_ids ?? [])->unique()->all();
        }
        $months = [];
        $rows = [];
        foreach ($orders as $o) {
            $month = ($o->conf_date ?? $o->order_date)?->format('Y-m');
            if ($month) {
                $months[$month] = true;
            }
            $item = ($items[$o->style_id] ?? collect())->first();
            $spec = ($specs[$o->style_no] ?? collect())->first();
            $rate = $spec && $month ? $rates->get("{$spec->id}|{$month}") : null;
            $nature = match ($o->order_type) {
                'REPEAT' => 'R',
                default => 'N',
            };
            $row = [
                'month' => $month, 'order_no' => $o->order_no, 'style_no' => $o->style_no, 'buyer' => $buyers[$o->buyer_id] ?? null, 'unit' => $units[$o->factory_id] ?? null,
                'item' => $item->full_name ?? $item->short_name ?? $o->item, 'item_group' => $item->item_group ?? null, 'item_type' => $item->item_type ?? null, 'gmt_type' => $o->gmt_type,
                'nature' => $nature, 'qty' => (float) $o->total_qty, 'unit_name' => $o->unit,
                'variation' => $spec ? "{$spec->style_no}/{$spec->variation_no}" : null, 'smv' => $spec?->sewing_smv, 'op' => $spec?->op_manpower, 'aso' => $spec?->aso_manpower,
                // CM/Dz and BgtExp% come from Spec-Wise CM Rate (14/05) for the booking month; until a rate is quoted they print zero.
                'cm_dz' => $rate ? (float) $rate->cm_per_dozen : 0, 'bgt_exp' => $rate ? (float) $rate->budget_exp_pct : 0, 'status' => $spec ? 'SPECIFIED' : 'NO_SPEC',
            ];
            $hay = mb_strtolower($o->order_no.' '.$o->style_no);
            $keep = (empty($f['month']) || $month === $f['month']) && (empty($f['factory_id']) || $o->factory_id === $f['factory_id'])
                && (empty($f['item_group']) || $row['item_group'] === $f['item_group']) && (empty($f['item_type']) || $row['item_type'] === $f['item_type'])
                && (empty($f['gmt_type']) || $o->gmt_type === $f['gmt_type']) && (empty($f['nature']) || $f['nature'] === 'ALL' || $nature === $f['nature'])
                && (empty($f['q']) || str_contains($hay, mb_strtolower(trim($f['q'])))) && ($mine === null || in_array($o->buyer_id, $mine, true))
                && (empty($f['not_in_house']) || $o->factory_id === null);
            if ($keep) {
                $rows[] = $row;
            }
        }
        krsort($months);

        return ApiResponse::ok([
            'rows' => $rows,
            'filters' => [
                'months' => array_keys($months),
                'units' => $units->map(fn ($n, $id) => ['value' => $id, 'label' => $n])->values()->all(),
                'item_groups' => DB::table('merch_style_items')->where('company_id', $this->company())->whereNotNull('item_group')->distinct()->orderBy('item_group')->pluck('item_group')->all(),
                'item_types' => DB::table('merch_style_items')->where('company_id', $this->company())->whereNotNull('item_type')->distinct()->orderBy('item_type')->pluck('item_type')->all(),
                'gmt_types' => $orders->pluck('gmt_type')->filter()->unique()->sort()->values()->all(),
            ],
        ]);
    }

    /** Itm Group & Category: a group's categories. */
    public function categories(string $code): JsonResponse
    {
        $this->allow('workstudy.spec.view');

        return ApiResponse::ok($this->categoryRows($this->group($code)));
    }

    /** Save a group's categories as listed; one quoted on a variation or a part mapping stays (clear Active instead). */
    public function saveCategories(Request $request, string $code): JsonResponse
    {
        $this->allow('workstudy.spec.manage');
        $group = $this->group($code);
        $data = $request->validate([
            'rows' => ['present', 'array'],
            'rows.*.id' => ['nullable', 'string'],
            'rows.*.name' => ['required', 'string', 'max:120'],
            'rows.*.active' => ['boolean'],
        ], ['rows.*.name.required' => __('workstudy.spec.v.category_name')]);
        ksort($data['rows']);
        $rows = array_values($data['rows']);
        $existing = WsGarmentItem::query()->where('item_group', $group->name)->get()->keyBy('id');
        $names = array_map(fn ($r) => mb_strtolower(trim($r['name'])), $rows);
        foreach ($names as $i => $n) {
            if (array_search($n, $names, true) !== $i) {
                throw ValidationException::withMessages(["rows.{$i}.name" => __('workstudy.spec.v.category_taken')]);
            }
        }
        $removed = $existing->keys()->diff(collect($rows)->pluck('id')->filter());
        $used = $removed->filter(fn ($id) => WsTechVariation::query()->where('garment_item_id', $id)->exists() || DB::table('ws_item_parts')->where('garment_item_id', $id)->exists());
        if ($used->isNotEmpty()) {
            throw ValidationException::withMessages(['rows' => __('workstudy.spec.v.category_in_use', ['name' => $existing[$used->first()]->name])]);
        }
        DB::transaction(function () use ($rows, $existing, $removed, $group): void {
            WsGarmentItem::query()->whereIn('id', $removed)->delete();
            foreach ($rows as $n => $r) {
                $values = ['name' => trim($r['name']), 'active' => (bool) ($r['active'] ?? true), 'sl' => $n + 1];
                $current = isset($r['id']) ? $existing->get($r['id']) : null;
                $current
                    ? $current->update($values)
                    : WsGarmentItem::query()->create($values + ['company_id' => $this->company(), 'item_group' => $group->name, 'code' => WsSpecs::uniqueCode('ITM', $values['name'], fn ($c) => WsGarmentItem::query()->where('code', $c)->exists())]);
            }
        });

        return ApiResponse::ok($this->categoryRows($group));
    }

    /** Gmt Tech Variations: a category's technical variations. */
    public function variations(string $code): JsonResponse
    {
        $this->allow('workstudy.spec.view');

        return ApiResponse::ok($this->variationRows($this->category($code)));
    }

    /** Save a category's variations as listed; every line needs a technical specification. */
    public function saveVariations(Request $request, string $code): JsonResponse
    {
        $this->allow('workstudy.spec.manage');
        $item = $this->category($code);
        $smv = ['nullable', 'numeric', 'min:0', 'max:9999'];
        $data = $request->validate([
            'rows' => ['present', 'array'],
            'rows.*.id' => ['nullable', 'string'],
            'rows.*.tech_spec' => ['required', 'string', 'max:200'],
            'rows.*.op' => $smv, 'rows.*.aso' => $smv, 'rows.*.smv' => $smv,
            ...collect(array_keys(WsSpecs::BANDS))->mapWithKeys(fn ($b) => ["rows.*.{$b}" => $smv])->all(),
        ], ['rows.*.tech_spec.required' => __('workstudy.spec.v.tech_spec')]);
        ksort($data['rows']);
        $rows = array_values($data['rows']);
        DB::transaction(function () use ($rows, $item): void {
            $existing = WsTechVariation::query()->where('garment_item_id', $item->id)->get()->keyBy('id');
            WsTechVariation::query()->where('garment_item_id', $item->id)->whereNotIn('id', collect($rows)->pluck('id')->filter()->all())->delete();
            foreach ($rows as $n => $r) {
                $values = collect($r)->only(['tech_spec', 'op', 'aso', 'smv', ...array_keys(WsSpecs::BANDS)])->map(fn ($v) => $v === '' ? null : $v)->all() + ['sort_order' => $n + 1];
                $current = isset($r['id']) ? $existing->get($r['id']) : null;
                $current ? $current->update($values) : WsTechVariation::query()->create($values + ['company_id' => $this->company(), 'garment_item_id' => $item->id]);
            }
        });

        return ApiResponse::ok($this->variationRows($item));
    }

    /* ---------------- helpers ---------------- */

    private function company(): string
    {
        return app(TenantContext::class)->companyId();
    }

    /** @return array{header: array<string, mixed>, processes: ?list<array<string, mixed>>, machines: ?list<array<string, mixed>>} */
    private function specInput(Request $request, ?WsSpec $spec): array
    {
        $company = $this->company();
        $eff = ['nullable', 'numeric', 'min:0', 'max:100'];
        $data = $request->validate([
            'style_no' => ['required', 'string', 'max:64'],
            'variation_no' => ['required', 'integer', 'min:1', 'max:999'],
            'description' => ['required', 'string', 'max:200'],
            'fabric_class' => ['nullable', Rule::in(WsLearningPoint::FABRIC_CLASSES)],
            'criticality' => ['nullable', Rule::in(WsLearningPoint::PROD_TYPES)],
            'op_manpower' => ['required', 'integer', 'min:0', 'max:999'],
            'aso_manpower' => ['required', 'integer', 'min:0', 'max:999'],
            'sewing_smv' => ['required', 'numeric', 'gt:0', 'max:999'],
            'iron_smv' => ['nullable', 'numeric', 'min:0', 'max:999'],
            'poly_smv' => ['nullable', 'numeric', 'min:0', 'max:999'],
            'avg_efficiency' => $eff, 'pick_efficiency' => $eff, 'balancing_efficiency' => $eff,
            'ob_file_path' => ['nullable', 'string', 'max:500'],
            'is_smv_artwork' => ['boolean'], 'is_fusing' => ['boolean'],
            'sample_type_id' => ['nullable', Rule::exists('sample_types', 'id')->where('company_id', $company)],
            'processes' => ['nullable', 'array'],
            'processes.*.garment_part_id' => ['required', Rule::exists('garment_parts', 'id')->where('company_id', $company)],
            'processes.*.specification' => ['nullable', 'string', 'max:300'],
            'machines' => ['nullable', 'array'],
            'machines.*.machine_type' => ['required', Rule::in(WsLineProfile::MACHINE_TYPES)],
            'machines.*.requirement' => ['required', 'integer', 'min:1', 'max:9999'],
        ], [
            'sewing_smv.required' => __('workstudy.spec.v.smv'), 'sewing_smv.gt' => __('workstudy.spec.v.smv'),
            'style_no.required' => __('workstudy.spec.v.style'), 'variation_no.required' => __('workstudy.spec.v.variation'), 'description.required' => __('workstudy.spec.v.description'),
            'avg_efficiency.max' => __('workstudy.spec.v.efficiency'), 'pick_efficiency.max' => __('workstudy.spec.v.efficiency'), 'balancing_efficiency.max' => __('workstudy.spec.v.efficiency'),
            'processes.*.garment_part_id.required' => __('workstudy.spec.v.process'), 'machines.*.machine_type.required' => __('workstudy.spec.v.machine'),
            'machines.*.requirement.required' => __('workstudy.spec.v.requirement'), 'machines.*.requirement.min' => __('workstudy.spec.v.requirement'),
        ]);
        $data['style_no'] = strtoupper(trim($data['style_no']));
        $taken = WsSpec::query()->where('style_no', $data['style_no'])->where('variation_no', $data['variation_no'])->when($spec, fn ($q) => $q->whereKeyNot($spec->id))->exists();
        if ($taken) {
            throw ValidationException::withMessages(['variation_no' => __('workstudy.spec.v.variation_taken', ['style' => $data['style_no'], 'v' => $data['variation_no']])]);
        }
        foreach (['processes' => 'garment_part_id', 'machines' => 'machine_type'] as $grid => $key) {
            if (! empty($data[$grid])) {
                ksort($data[$grid]);
                $data[$grid] = array_values($data[$grid]);
                $seen = [];
                foreach ($data[$grid] as $i => $line) {
                    if (isset($seen[$line[$key]])) {
                        throw ValidationException::withMessages(["{$grid}.{$i}.{$key}" => __("workstudy.spec.v.{$grid}_once")]);
                    }
                    $seen[$line[$key]] = true;
                }
            }
        }
        // The nature follows the sewing SMV when none is chosen.
        $data['criticality'] ??= WsSpecs::natureOf((float) $data['sewing_smv']);
        $processes = $request->has('processes') ? ($data['processes'] ?? []) : null;
        $machines = $request->has('machines') ? ($data['machines'] ?? []) : null;
        unset($data['processes'], $data['machines']);
        if ($spec && $processes === null) {
            $processes = WsSpecProcess::query()->where('spec_id', $spec->id)->orderBy('sort_order')->get(['garment_part_id', 'specification'])->toArray();
        }
        if ($spec && $machines === null) {
            $machines = WsSpecMachine::query()->where('spec_id', $spec->id)->orderBy('sort_order')->get(['machine_type', 'requirement'])->toArray();
        }

        return ['header' => $data, 'processes' => $processes, 'machines' => $machines];
    }

    /**
     * @param  list<array<string, mixed>>  $processes
     * @param  list<array<string, mixed>>  $machines
     */
    private function writeLines(WsSpec $spec, array $processes, array $machines): void
    {
        foreach ($processes as $n => $p) {
            WsSpecProcess::query()->create(['company_id' => $spec->company_id, 'spec_id' => $spec->id, 'garment_part_id' => $p['garment_part_id'], 'specification' => $p['specification'] ?? null, 'sort_order' => $n + 1]);
        }
        foreach ($machines as $n => $m) {
            WsSpecMachine::query()->create(['company_id' => $spec->company_id, 'spec_id' => $spec->id, 'machine_type' => $m['machine_type'], 'requirement' => $m['requirement'], 'sort_order' => $n + 1]);
        }
    }

    private function find(string $style, int $variation): WsSpec
    {
        return WsSpec::query()->where('style_no', strtoupper(trim($style)))->where('variation_no', $variation)->first()
            ?? throw ValidationException::withMessages(['style_no' => __('workstudy.spec.v.no_spec', ['style' => strtoupper(trim($style)), 'v' => $variation])]);
    }

    /**
     * @param  Collection<int, WsSpec>  $specs
     * @return list<array<string, mixed>>
     */
    private function specRows(Collection $specs): array
    {
        $machines = WsSpecMachine::query()->whereIn('spec_id', $specs->pluck('id'))->selectRaw('spec_id, count(*) as n, sum(requirement) as mcs')->groupBy('spec_id')->get()->keyBy('spec_id');

        return $specs->map(fn (WsSpec $s) => [
            'id' => $s->id, 'style_no' => $s->style_no, 'variation_no' => $s->variation_no, 'label' => "{$s->style_no}/{$s->variation_no}", 'description' => $s->description,
            'fabric_class' => $s->fabric_class, 'criticality' => $s->criticality, 'op_manpower' => $s->op_manpower, 'aso_manpower' => $s->aso_manpower, 'sewing_smv' => $s->sewing_smv,
            'avg_efficiency' => $s->avg_efficiency, 'machine_types' => (int) ($machines[$s->id]->n ?? 0), 'machines' => (int) ($machines[$s->id]->mcs ?? 0),
        ])->values()->all();
    }

    /** @return array<string, mixed> */
    private function specPayload(WsSpec $spec): array
    {
        return $this->specRows(collect([$spec]))[0] + $spec->only(['iron_smv', 'poly_smv', 'pick_efficiency', 'balancing_efficiency', 'ob_file_path', 'is_smv_artwork', 'is_fusing', 'sample_type_id']) + [
            'processes' => WsSpecProcess::query()->where('spec_id', $spec->id)->orderBy('sort_order')->get(['garment_part_id', 'specification'])->toArray(),
            'machines_list' => WsSpecMachine::query()->where('spec_id', $spec->id)->orderBy('sort_order')->get(['machine_type', 'requirement'])->toArray(),
        ];
    }

    /** @return list<array<string, mixed>> */
    private function groupRows(): array
    {
        $counts = WsGarmentItem::query()->selectRaw('item_group, count(*) as n')->groupBy('item_group')->pluck('n', 'item_group');

        return WsItemGroup::query()->orderBy('sort_order')->orderBy('name')->get()
            ->map(fn (WsItemGroup $g) => ['id' => $g->id, 'key' => $g->code, 'label' => $g->name, 'code' => $g->code, 'count' => (int) ($counts[$g->name] ?? 0)])->values()->all();
    }

    private function group(string $code): WsItemGroup
    {
        WsSpecs::ensure($this->company());

        return WsItemGroup::query()->where('code', strtoupper($code))->firstOrFail();
    }

    private function category(string $code): WsGarmentItem
    {
        WsSpecs::ensure($this->company());

        return WsGarmentItem::query()->where('code', strtoupper($code))->firstOrFail();
    }

    /** @return list<array<string, mixed>> */
    private function categoryRows(WsItemGroup $group): array
    {
        return WsGarmentItem::query()->where('item_group', $group->name)->orderBy('sl')->orderBy('name')->get()
            ->map(fn (WsGarmentItem $i) => ['id' => $i->id, 'name' => $i->name, 'code' => $i->code, 'active' => $i->active])->values()->all();
    }

    /** @return list<array<string, mixed>> */
    private function variationRows(WsGarmentItem $item): array
    {
        return WsTechVariation::query()->where('garment_item_id', $item->id)->orderBy('sort_order')->get()
            ->map(fn (WsTechVariation $v) => $v->only(['id', 'tech_spec', 'op', 'aso', 'smv', ...array_keys(WsSpecs::BANDS)]))->values()->all();
    }
}
