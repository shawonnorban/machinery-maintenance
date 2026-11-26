<?php

declare(strict_types=1);

namespace App\Modules\DyeHouse\Http\Controllers\Api;

use App\Modules\DyeHouse\Models\DyeColourGroup;
use App\Modules\DyeHouse\Models\DyeDosingTemplate;
use App\Modules\DyeHouse\Models\DyeDosingTemplateAgent;
use App\Modules\DyeHouse\Models\DyeMethod;
use App\Modules\DyeHouse\Support\DyeBatches;
use App\Modules\DyeHouse\Support\DyeHouseDefaults;
use App\Modules\Inventory\Support\UomCodes;
use App\Shared\Http\Api\ApiController;
use App\Shared\Http\Api\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * Dye House → Lab-Dip (AnnotechERP 17/02): what Dosing Template Master and Lab
 * Dip Recipe Entry pick from, and dosing templates — a header and its agents,
 * one line per dosed agent in step order, saved as a whole. A template is
 * addressed by its code (TPL-0001), which is issued on save.
 */
class DyeLabDipApiController extends ApiController
{
    public function options(): JsonResponse
    {
        $this->allow('dyehouse.labdip.view');
        $company = $this->caller()->companyId;
        DyeHouseDefaults::methods($company);
        DyeHouseDefaults::colourGroups($company);
        $buyers = DB::table('buyers')->where('company_id', $company)->get(['id', 'code', 'short_name', 'name'])->keyBy('id');

        return ApiResponse::ok([
            'methods' => DyeMethod::query()->where('active', true)->orderBy('name')->get(['id', 'name'])->map(fn ($m) => ['value' => $m->id, 'label' => $m->name])->all(),
            'colour_groups' => DyeColourGroup::query()->where('active', true)->orderBy('display_order')->orderBy('name')->get(['id', 'name'])->map(fn ($g) => ['value' => $g->id, 'label' => $g->name])->all(),
            'chemicals' => DyeBatches::chemicals(),
            'buyers' => $buyers->sortBy(fn ($b) => $b->short_name ?: $b->name)->map(fn ($b) => ['value' => $b->id, 'label' => ($b->short_name ?: $b->name).($b->code ? " ({$b->code})" : '')])->values()->all(),
            // Brickland's style list: every style, named with its buyer ("770MR · LPP").
            'styles' => DB::table('merch_styles')->where('company_id', $company)->orderBy('style_ref')->get(['id', 'style_ref', 'buyer_id'])
                ->map(fn ($s) => ['value' => $s->id, 'label' => trim($s->style_ref.' · '.($buyers[$s->buyer_id]->short_name ?? $buyers[$s->buyer_id]->name ?? ''), ' ·'), 'buyer_id' => $s->buyer_id])->values()->all(),
            'colours' => DB::table('colours')->where('company_id', $company)->where('active', true)->orderBy('name')->get(['id', 'code', 'name'])
                ->map(fn ($c) => ['value' => $c->id, 'label' => $c->code ? "{$c->name} ({$c->code})" : $c->name])->values()->all(),
            'fabric_types' => DB::table('fabric_types')->where('company_id', $company)->where('active', true)->orderBy('name')->get(['id', 'name'])
                ->map(fn ($f) => ['value' => $f->id, 'label' => $f->name])->values()->all(),
            'types' => DyeDosingTemplate::TYPES,
            'processes' => DyeDosingTemplateAgent::PROCESSES,
        ]);
    }

    public function index(): JsonResponse
    {
        $this->allow('dyehouse.labdip.view');
        $methods = DB::table('dye_methods')->pluck('name', 'id');
        $groups = DB::table('dye_colour_groups')->pluck('name', 'id');
        $counts = DyeDosingTemplateAgent::query()->selectRaw('template_id, count(*) as n')->groupBy('template_id')->pluck('n', 'template_id');

        return ApiResponse::ok(DyeDosingTemplate::query()->orderBy('code')->get()->map(fn (DyeDosingTemplate $t) => [
            'code' => $t->code, 'name' => $t->name, 'short_name' => $t->short_name, 'dye_method' => $methods[$t->dye_method_id] ?? null,
            'colour_group' => $groups[$t->colour_group_id] ?? null, 'template_type' => $t->template_type, 'agents' => (int) ($counts[$t->id] ?? 0), 'is_finalized' => $t->is_finalized,
        ])->all());
    }

    public function show(string $code): JsonResponse
    {
        $this->allow('dyehouse.labdip.view');

        return ApiResponse::ok($this->present($this->template($code)));
    }

    /** A template and its agents together: the lines given are the lines kept. */
    public function save(Request $request, ?string $code = null): JsonResponse
    {
        $this->allow('dyehouse.labdip.manage');
        $row = $code ? $this->template($code) : null;
        $company = $this->caller()->companyId;
        $phases = array_keys(DyeDosingTemplateAgent::PROCESSES);
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255', Rule::unique('dye_dosing_templates', 'name')->where('company_id', $company)->ignore($row?->id)],
            'short_name' => ['nullable', 'string', 'max:64'],
            'dye_method_id' => ['required', Rule::exists('dye_methods', 'id')->where('company_id', $company)],
            'colour_group_id' => ['required', Rule::exists('dye_colour_groups', 'id')->where('company_id', $company)],
            'template_type' => ['required', Rule::in(DyeDosingTemplate::TYPES)],
            'is_finalized' => ['sometimes', 'boolean'],
            'agents' => ['present', 'array', 'max:200'],
            'agents.*.step_no' => ['required', 'integer', 'min:1', 'max:999', 'distinct'],
            'agents.*.dye_method_id' => ['nullable', Rule::exists('dye_methods', 'id')->where('company_id', $company)],
            'agents.*.phase' => ['required', Rule::in($phases)],
            'agents.*.process' => ['nullable', 'string', 'max:32'],
            'agents.*.item_id' => ['required', Rule::exists('items', 'id')->where('company_id', $company)],
            'agents.*.is_labdip' => ['sometimes', 'boolean'],
            'agents.*.is_line_break' => ['sometimes', 'boolean'],
            'agents.*.dose' => ['nullable', 'numeric', 'min:0', 'max:9999999'],
            'agents.*.uom' => ['nullable', 'string', 'max:16', UomCodes::rule($company)],
        ], [
            'name.required' => __('dyehouse.labdip.v.name'), 'name.unique' => __('dyehouse.labdip.v.name_taken'),
            'dye_method_id.required' => __('dyehouse.labdip.v.method'), 'colour_group_id.required' => __('dyehouse.labdip.v.group'),
            'template_type.required' => __('dyehouse.labdip.v.type'),
            'agents.*.step_no.required' => __('dyehouse.labdip.v.step'), 'agents.*.step_no.distinct' => __('dyehouse.labdip.v.step_repeated'),
            'agents.*.phase.required' => __('dyehouse.labdip.v.phase'), 'agents.*.item_id.required' => __('dyehouse.labdip.v.agent'),
        ]);
        ksort($data['agents']);
        foreach ($data['agents'] as $i => $a) {
            if (($a['process'] ?? null) && ! in_array($a['process'], DyeDosingTemplateAgent::PROCESSES[$a['phase']], true)) {
                throw ValidationException::withMessages(["agents.{$i}.process" => __('dyehouse.labdip.v.process_phase', ['step' => $a['step_no']])]);
            }
        }

        $row = DB::transaction(function () use ($data, $row, $company): DyeDosingTemplate {
            $head = ['name' => trim($data['name']), 'short_name' => $data['short_name'] ?? null, 'dye_method_id' => $data['dye_method_id'],
                'colour_group_id' => $data['colour_group_id'], 'template_type' => $data['template_type'], 'is_finalized' => (bool) ($data['is_finalized'] ?? false)];
            if ($row) {
                $row->update($head);
                DyeDosingTemplateAgent::query()->where('template_id', $row->id)->delete();
            } else {
                $row = DyeDosingTemplate::query()->create($head + ['code' => $this->nextCode($company), 'created_by' => $this->caller()->auditUserId()]);
            }
            foreach (collect($data['agents'])->sortBy('step_no') as $a) {
                DyeDosingTemplateAgent::query()->create([
                    'template_id' => $row->id, 'step_no' => $a['step_no'], 'dye_method_id' => $a['dye_method_id'] ?? null, 'phase' => $a['phase'], 'process' => $a['process'] ?? null,
                    'item_id' => $a['item_id'], 'is_labdip' => (bool) ($a['is_labdip'] ?? false), 'is_line_break' => (bool) ($a['is_line_break'] ?? false),
                    'dose' => $a['dose'] ?? null, 'uom' => UomCodes::normalize($a['uom'] ?? null),
                ]);
            }

            return $row;
        });

        return ApiResponse::ok($this->present($row->refresh()), [], $code ? 200 : 201);
    }

    public function destroy(string $code): JsonResponse
    {
        $this->allow('dyehouse.labdip.manage');
        $this->template($code)->delete();

        return ApiResponse::ok(['deleted' => $code]);
    }

    private function template(string $code): DyeDosingTemplate
    {
        return DyeDosingTemplate::query()->where('code', $code)->firstOrFail();
    }

    /** TPL-0001, TPL-0002 … per company, under a lock so two saves never share one. */
    private function nextCode(string $company): string
    {
        DB::select('select pg_advisory_xact_lock(hashtext(?))', ['dye_dosing_templates:'.$company]);
        $last = DyeDosingTemplate::query()->where('code', 'like', 'TPL-%')->pluck('code')->map(fn ($c) => (int) substr($c, 4))->max() ?? 0;

        return 'TPL-'.str_pad((string) ($last + 1), 4, '0', STR_PAD_LEFT);
    }

    /** @return array<string, mixed> */
    private function present(DyeDosingTemplate $t): array
    {
        $agents = $t->agents()->get();
        $items = DB::table('items')->whereIn('id', $agents->pluck('item_id'))->get(['id', 'item_code', 'name'])->keyBy('id');

        return [
            'code' => $t->code, 'name' => $t->name, 'short_name' => $t->short_name, 'dye_method_id' => $t->dye_method_id, 'colour_group_id' => $t->colour_group_id,
            'template_type' => $t->template_type, 'is_finalized' => $t->is_finalized,
            'agents' => $agents->map(fn (DyeDosingTemplateAgent $a) => [
                'step_no' => $a->step_no, 'dye_method_id' => $a->dye_method_id, 'phase' => $a->phase, 'process' => $a->process, 'item_id' => $a->item_id,
                'item' => isset($items[$a->item_id]) ? "{$items[$a->item_id]->name} ({$items[$a->item_id]->item_code})" : null,
                'is_labdip' => $a->is_labdip, 'is_line_break' => $a->is_line_break, 'dose' => $a->dose === null ? null : (float) $a->dose, 'uom' => $a->uom,
            ])->values()->all(),
        ];
    }
}
