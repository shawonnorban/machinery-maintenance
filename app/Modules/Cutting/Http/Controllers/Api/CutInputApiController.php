<?php

declare(strict_types=1);

namespace App\Modules\Cutting\Http\Controllers\Api;

use App\Modules\Cutting\Models\CutBundle;
use App\Modules\Cutting\Models\CutInput;
use App\Modules\Cutting\Models\CutInputSize;
use App\Modules\Cutting\Support\CutCards;
use App\Modules\Cutting\Support\CutFabric;
use App\Modules\DyeHouse\Support\DyeBatches;
use App\Shared\Fabric\FabricLength;
use App\Shared\Http\Api\ApiController;
use App\Shared\Http\Api\ApiResponse;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * Cutting Input → Cutting Output → Bundle cards (the user's design of
 * 2026-11-16). The cutting master (`cutting.input.manage`) lays a cutting on
 * New Cutting: PO, colour, cutting number, part, fabric lot, the marker's
 * length, plies and size ratio, and the fabric it takes — warned, never
 * stopped, when that is more than the fabric issued to cutting still holds.
 * The output clerk (`cutting.bundle.manage`) then records on Bundle Entry
 * what was really cut per size, the rejects and the fabric used, and cuts the
 * bundle cards from that output — warned, and asked to confirm, past it.
 */
class CutInputApiController extends ApiController
{
    /** A cutting's inputs, newest first — for one PO's colour, or by status. */
    public function index(Request $request): JsonResponse
    {
        $this->allow('cutting.bundle.view');
        $q = $request->validate(['order_id' => ['nullable', 'string', 'size:26'], 'colour' => ['nullable', 'string', 'max:64'], 'status' => ['nullable', Rule::in(CutInput::STATUSES)]]);
        $rows = CutInput::query()->with('sizes')
            ->when($q['order_id'] ?? null, fn ($w, $o) => $w->where('order_id', $o))
            ->when($q['colour'] ?? null, fn ($w, $c) => $w->whereRaw('lower(colour) = ?', [mb_strtolower(trim($c))]))
            ->when($q['status'] ?? null, fn ($w, $s) => $w->where('status', $s), fn ($w) => $w->where('status', '!=', 'CANCELLED'))
            ->orderByDesc('cut_date')->orderByDesc('input_no')->limit(200)->get();

        return ApiResponse::ok($this->present($rows));
    }

    public function show(string $no): JsonResponse
    {
        $this->allow('cutting.bundle.view');

        return ApiResponse::ok($this->present(collect([$this->find($no)]))[0]);
    }

    /** The cutting master lays a cutting (or corrects one not yet output). */
    public function save(Request $request, ?string $no = null): JsonResponse
    {
        $this->allow('cutting.input.manage');
        $c = $this->caller()->companyId;
        $row = $no ? $this->find($no) : null;
        if ($row && $row->status !== 'INPUT') {
            throw ValidationException::withMessages(['status' => __('cutting.v.input_locked', ['no' => $row->input_no])]);
        }
        $d = $request->validate([
            'order_id' => ['required', Rule::exists('merch_orders', 'id')->where('company_id', $c)],
            'colour' => ['required', 'string', 'max:64'],
            'cutting_no' => ['required', 'integer', 'min:1', 'max:99999'],
            'parts_name' => ['required', 'string', 'max:60'],
            'batch_no' => ['required', 'string', 'max:40'],
            'lot_id' => ['nullable', Rule::exists('fin_store_lots', 'id')->where('company_id', $c)],
            'cut_date' => ['nullable', 'date'],
            'garment' => ['nullable', 'string', 'max:120'], 'fabric_type' => ['nullable', 'string', 'max:60'], 'gsm' => ['nullable', 'string', 'max:16'], 'dia_width' => ['nullable', 'string', 'max:16'],
            // Lengths in the company's yards or metres (2026-11-16): the marker, or the lay as measured.
            'marker_length' => ['nullable', 'numeric', 'gt:0', 'max:9999'],
            'lay_len' => ['nullable', 'numeric', 'gt:0', 'max:99999999'],
            'plies' => ['required', 'integer', 'min:1', 'max:9999'],
            'fabric_kg' => ['nullable', 'numeric', 'gt:0', 'max:9999999'],
            'sizes' => ['required', 'array', 'min:1', 'max:40'],
            'sizes.*.size' => ['required', 'string', 'max:32', 'distinct:ignore_case'],
            'sizes.*.ratio' => ['required', 'integer', 'min:1', 'max:999'],
            'remarks' => ['nullable', 'string', 'max:1000'],
        ], [
            'order_id.required' => __('cutting.v.po'), 'colour.required' => __('cutting.v.colour'), 'cutting_no.required' => __('cutting.v.cutting_no'),
            'parts_name.required' => __('cutting.v.parts'), 'batch_no.required' => __('cutting.v.batch'), 'plies.required' => __('cutting.v.plies'),
            'sizes.required' => __('cutting.v.ratio'), 'sizes.*.size.distinct' => __('cutting.v.size_twice'),
        ]);
        $order = DB::table('merch_orders')->where('id', $d['order_id'])->first(['id', 'po_no', 'style_no', 'work_style_no', 'buyer_id', 'item', 'gmt_type']);
        $colour = $this->orderColour($order, $d['colour']);
        $parts = trim($d['parts_name']);
        $taken = CutInput::query()->where('order_id', $order->id)->where('status', '!=', 'CANCELLED')->whereRaw('lower(colour) = ?', [mb_strtolower($colour)])
            ->where('cutting_no', $d['cutting_no'])->where('parts_name', $parts)->when($row, fn ($q) => $q->whereKeyNot($row->id))->first();
        if ($taken) {
            throw ValidationException::withMessages(['cutting_no' => __('cutting.v.input_taken', ['no' => $d['cutting_no'], 'part' => $parts, 'input' => $taken->input_no])]);
        }
        // The lay's fabric: as weighed, else from the marker (length × plies × weight per metre).
        // The lay by length: as measured, else marker × plies; its kilos as weighed, else from GSM and width.
        $layM = isset($d['lay_len']) ? FabricLength::toMetres($d['lay_len']) : CutFabric::layMetres(isset($d['marker_length']) ? (float) $d['marker_length'] : null, (int) $d['plies']);
        $kg = isset($d['fabric_kg']) ? (float) $d['fabric_kg'] : ($layM === null ? null : FabricLength::metresToKg($layM, $d['gsm'] ?? null, $d['dia_width'] ?? null));
        $layM ??= $kg === null ? null : FabricLength::kgToMetres($kg, $d['gsm'] ?? null, $d['dia_width'] ?? null);
        if ($layM === null) {
            throw ValidationException::withMessages(['lay_len' => __('cutting.v.fabric_len')]);
        }
        $date = $d['cut_date'] ?? ($row?->cut_date?->toDateString() ?? CarbonImmutable::now('Asia/Dhaka')->toDateString());
        $values = ['cut_date' => $date, 'order_id' => $order->id, 'po_no' => $order->po_no, 'style' => $order->work_style_no ?: $order->style_no, 'buyer_id' => $order->buyer_id, 'colour' => $colour,
            'cutting_no' => $d['cutting_no'], 'parts_name' => $parts, 'batch_no' => mb_strtoupper(trim($d['batch_no'])), 'lot_id' => $d['lot_id'] ?? null,
            'garment' => $this->text($d['garment'] ?? null) ?? ($order->item ?: $order->gmt_type), 'fabric_type' => $this->text($d['fabric_type'] ?? null), 'gsm' => $this->text($d['gsm'] ?? null),
            'dia_width' => $this->text($d['dia_width'] ?? null), 'marker_length' => isset($d['marker_length']) ? FabricLength::toMetres($d['marker_length']) : null, 'plies' => $d['plies'], 'lay_m' => $layM,
            'fabric_kg' => $kg === null ? null : round($kg, 3), 'remarks' => $this->text($d['remarks'] ?? null)];

        $input = DB::transaction(function () use ($row, $values, $d, $date) {
            $input = $row ?? new CutInput(['input_no' => DyeBatches::nextNumber('CUT-', 'cut_inputs', 'input_no', $date, 'Ymd-'), 'status' => 'INPUT', 'created_by' => $this->caller()->auditUserId()]);
            $input->fill($values)->save();
            CutInputSize::query()->where('input_id', $input->id)->delete();
            foreach (array_values($d['sizes']) as $i => $s) {
                CutInputSize::query()->create(['input_id' => $input->id, 'size' => mb_strtoupper(trim($s['size'])), 'ratio' => $s['ratio'], 'planned_qty' => $s['ratio'] * $d['plies'], 'sort_order' => $i + 1]);
            }

            return $input->refresh();
        });
        // Warned, never stopped: the lay may take more than the fabric issued to cutting still holds.
        $fabric = CutFabric::of($order->id, $colour, $input->id);
        $short = $layM > $fabric['available_m'] + 0.0005 ? FabricLength::fromMetres($layM - $fabric['available_m']) : null;
        $out = $this->present(collect([$input]))[0] + ['fabric_short_len' => $short];

        return $row ? ApiResponse::ok($out) : ApiResponse::created($out);
    }

    /** A cutting not yet cut into cards is cancelled; its fabric goes back to what cutting still holds. */
    public function cancel(string $no): JsonResponse
    {
        $this->allow('cutting.input.manage');
        $row = $this->find($no);
        if (CutBundle::query()->where('input_id', $row->id)->where('status', '!=', 'CANCELLED')->exists()) {
            throw ValidationException::withMessages(['status' => __('cutting.v.input_has_cards', ['no' => $row->input_no])]);
        }
        $row->update(['status' => 'CANCELLED']);

        return ApiResponse::ok($this->present(collect([$row]))[0]);
    }

    /** The output clerk records what was really cut per size, the rejects and the fabric used. */
    public function output(Request $request, string $no): JsonResponse
    {
        $this->allow('cutting.bundle.manage');
        $row = $this->find($no);
        if ($row->status === 'CANCELLED') {
            throw ValidationException::withMessages(['status' => __('cutting.v.input_cancelled', ['no' => $row->input_no])]);
        }
        $d = $request->validate([
            'sizes' => ['required', 'array', 'min:1'],
            'sizes.*.size' => ['required', 'string', 'max:32'],
            'sizes.*.output_qty' => ['required', 'integer', 'min:0', 'max:999999'],
            'sizes.*.reject_qty' => ['nullable', 'integer', 'min:0', 'max:999999'],
            'used_len' => ['nullable', 'required_without:used_kg', 'numeric', 'gt:0', 'max:99999999'],
            'used_kg' => ['nullable', 'numeric', 'gt:0', 'max:9999999'],
            'remnant_len' => ['nullable', 'numeric', 'min:0', 'max:99999999'],
            'remnant_kg' => ['nullable', 'numeric', 'min:0', 'max:9999999'],
            'remarks' => ['nullable', 'string', 'max:1000'],
        ], ['sizes.*.output_qty.required' => __('cutting.v.output_qty'), 'used_len.required_without' => __('cutting.v.used_len')]);
        $mine = $row->sizes->keyBy('size');
        DB::transaction(function () use ($row, $d, $mine): void {
            foreach ($d['sizes'] as $s) {
                $size = mb_strtoupper(trim($s['size']));
                $line = $mine[$size] ?? throw ValidationException::withMessages(['sizes' => __('cutting.v.size_not_in_input', ['size' => $size])]);
                $line->update(['output_qty' => $s['output_qty'], 'reject_qty' => $s['reject_qty'] ?? 0]);
            }
            // The fabric used by length, its kilos beside it — given, or at the lay's own kilos per metre.
            $kgPerM = $row->lay_m && $row->fabric_kg ? (float) $row->fabric_kg / (float) $row->lay_m : null;
            $usedM = isset($d['used_len']) ? FabricLength::toMetres($d['used_len']) : ($kgPerM ? round((float) $d['used_kg'] / $kgPerM, 3) : FabricLength::kgToMetres((float) $d['used_kg'], $row->gsm, $row->dia_width));
            $usedKg = isset($d['used_kg']) ? round((float) $d['used_kg'], 3) : ($kgPerM && $usedM !== null ? round($usedM * $kgPerM, 3) : null);
            $remnantM = isset($d['remnant_len']) ? FabricLength::toMetres($d['remnant_len']) : null;
            $row->update(['status' => 'OUTPUT', 'used_m' => $usedM, 'used_kg' => $usedKg, 'remnant_m' => $remnantM,
                'remnant_kg' => isset($d['remnant_kg']) ? round((float) $d['remnant_kg'], 3) : ($kgPerM && $remnantM !== null ? round($remnantM * $kgPerM, 3) : null),
                'output_remarks' => $this->text($d['remarks'] ?? null), 'output_by' => $this->caller()->auditUserId(), 'output_at' => now()]);
        });

        return ApiResponse::ok($this->present(collect([$row->refresh()]))[0]);
    }

    /**
     * Bundle cards from a cutting's output: per size the pieces to card, in
     * cards of the bundle size. Past the output's good pieces it is refused
     * once with a warning and made when the clerk confirms.
     */
    public function bundles(Request $request, string $no): JsonResponse
    {
        $this->allow('cutting.bundle.manage');
        $row = $this->find($no);
        if ($row->status !== 'OUTPUT') {
            throw ValidationException::withMessages(['status' => __('cutting.v.output_first', ['no' => $row->input_no])]);
        }
        $d = $request->validate([
            'pcs_per_bundle' => ['required', 'integer', 'min:1', 'max:9999'],
            'sizes' => ['required', 'array', 'min:1', 'max:40'],
            'sizes.*.size' => ['required', 'string', 'max:32', 'distinct:ignore_case'],
            'sizes.*.qty' => ['required', 'integer', 'min:1', 'max:99999'],
            'confirm_over' => ['sometimes', 'boolean'],
        ], ['pcs_per_bundle.required' => __('cutting.v.pcs_per_bundle'), 'sizes.required' => __('cutting.v.sizes'), 'sizes.*.size.distinct' => __('cutting.v.size_twice')]);
        $cards = (int) collect($d['sizes'])->sum(fn ($s) => (int) ceil($s['qty'] / $d['pcs_per_bundle']));
        if ($cards > 500) {
            throw ValidationException::withMessages(['pcs_per_bundle' => __('cutting.v.too_many_cards', ['n' => $cards])]);
        }
        $bundled = $this->bundled(collect([$row->id]))[$row->id] ?? collect();
        $sizes = $row->sizes->keyBy('size');
        $over = collect($d['sizes'])->map(function ($s) use ($sizes, $bundled) {
            $size = mb_strtoupper(trim($s['size']));
            $line = $sizes[$size] ?? throw ValidationException::withMessages(['sizes' => __('cutting.v.size_not_in_input', ['size' => $size])]);
            $past = (int) ($bundled[$size] ?? 0) + (int) $s['qty'] - (int) $line->output_qty;

            return $past > 0 ? ['size' => $size, 'over' => $past] : null;
        })->filter()->values();
        if ($over->isNotEmpty() && ! ($d['confirm_over'] ?? false)) {
            throw ValidationException::withMessages(['confirm_over' => __('cutting.v.past_output', ['list' => $over->map(fn ($o) => "{$o['size']} +{$o['over']}")->implode(', ')])]);
        }

        $made = CutCards::cut(['order_id' => $row->order_id, 'input_id' => $row->id, 'colour' => $row->colour, 'cutting_no' => $row->cutting_no, 'parts_name' => $row->parts_name, 'batch_no' => $row->batch_no,
            'cut_date' => CarbonImmutable::now('Asia/Dhaka')->toDateString(), 'garment' => $row->garment, 'fabric_type' => $row->fabric_type, 'gsm' => $row->gsm, 'dia_width' => $row->dia_width],
            array_map(fn ($s) => ['size' => $s['size'], 'qty' => (int) $s['qty']], $d['sizes']), (int) $d['pcs_per_bundle'], $this->caller()->auditUserId());

        return ApiResponse::created(['bundles' => $made->map(fn (CutBundle $b) => ['bundle_no' => $b->bundle_no, 'size' => $b->size, 'serial_from' => $b->serial_from, 'serial_to' => $b->serial_to, 'qty' => $b->qty,
            'cutting_status' => $b->cutting_status, 'additional_qty' => $b->additional_qty])->all(),
            'count' => $made->count(), 'qty' => (int) $made->sum('qty'), 'additional' => (int) $made->sum('additional_qty'), 'input' => $this->present(collect([$row->refresh()]))[0]]);
    }

    private function find(string $no): CutInput
    {
        return CutInput::query()->with('sizes')->where('input_no', $no)->firstOr(fn () => abort(404));
    }

    /** The colour as the order writes it (its size breakdown), or as typed when the order has none. */
    private function orderColour(object $order, string $colour): string
    {
        $colours = DB::table('merch_order_sizes')->where('order_id', $order->id)->distinct()->pluck('colour');
        if ($colours->isEmpty()) {
            return trim($colour);
        }

        return $colours->first(fn ($c) => mb_strtolower((string) $c) === mb_strtolower(trim($colour)))
            ?? throw ValidationException::withMessages(['colour' => __('cutting.v.colour_not_on_order', ['colour' => trim($colour), 'po' => $order->po_no])]);
    }

    /** @return \Illuminate\Support\Collection<string, \Illuminate\Support\Collection<string, int>> pieces carded per input, per size */
    private function bundled(\Illuminate\Support\Collection $ids): \Illuminate\Support\Collection
    {
        return CutBundle::query()->whereIn('input_id', $ids)->where('status', '!=', 'CANCELLED')->get(['input_id', 'size', 'qty'])
            ->groupBy('input_id')->map(fn ($g) => $g->groupBy('size')->map(fn ($s) => (int) $s->sum('qty')));
    }

    /** @return list<array<string, mixed>> */
    private function present(\Illuminate\Support\Collection $rows): array
    {
        $bundled = $this->bundled($rows->pluck('id'));
        $users = DB::table('users')->whereIn('id', $rows->pluck('created_by')->merge($rows->pluck('output_by'))->filter()->unique())->pluck('name', 'id');
        $lots = DB::table('fin_store_lots')->whereIn('id', $rows->pluck('lot_id')->filter())->pluck('lot_code', 'id');

        return $rows->map(function (CutInput $r) use ($bundled, $users, $lots) {
            $mine = $bundled[$r->id] ?? collect();
            $sizes = $r->sizes->map(fn (CutInputSize $s) => ['size' => $s->size, 'ratio' => $s->ratio, 'planned_qty' => $s->planned_qty, 'output_qty' => $s->output_qty, 'reject_qty' => $s->reject_qty,
                'bundled_qty' => (int) ($mine[$s->size] ?? 0)])->values();

            return ['input_no' => $r->input_no, 'cut_date' => $r->cut_date->toDateString(), 'order_id' => $r->order_id, 'po_no' => $r->po_no, 'style' => $r->style, 'colour' => $r->colour,
                'cutting_no' => $r->cutting_no, 'parts_name' => $r->parts_name, 'batch_no' => $r->batch_no, 'lot_id' => $r->lot_id, 'lot_code' => $lots[$r->lot_id] ?? null,
                'garment' => $r->garment, 'fabric_type' => $r->fabric_type, 'gsm' => $r->gsm, 'dia_width' => $r->dia_width,
                'len_unit' => FabricLength::unit(), 'marker_length' => FabricLength::fromMetres($r->marker_length), 'plies' => $r->plies, 'lay_len' => FabricLength::fromMetres($r->lay_m),
                'fabric_kg' => $r->fabric_kg, 'used_len' => FabricLength::fromMetres($r->used_m), 'used_kg' => $r->used_kg, 'remnant_len' => FabricLength::fromMetres($r->remnant_m), 'remnant_kg' => $r->remnant_kg, 'status' => $r->status,
                'remarks' => $r->remarks, 'output_remarks' => $r->output_remarks, 'sizes' => $sizes->all(),
                'planned_qty' => (int) $sizes->sum('planned_qty'), 'output_qty' => $r->status === 'OUTPUT' ? (int) $sizes->sum('output_qty') : null, 'reject_qty' => (int) $sizes->sum('reject_qty'),
                'bundled_qty' => (int) $sizes->sum('bundled_qty'),
                'created_by' => $users[$r->created_by] ?? null, 'output_by' => $users[$r->output_by] ?? null, 'output_at' => $r->output_at?->toIso8601String()];
        })->values()->all();
    }

    private function text(?string $v): ?string
    {
        return $v !== null && trim($v) !== '' ? trim($v) : null;
    }
}
