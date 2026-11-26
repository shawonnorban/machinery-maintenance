<?php

declare(strict_types=1);

namespace App\Modules\Finishing\Http\Controllers\Api;

use App\Modules\DyeHouse\Support\DyeBatches;
use App\Modules\Finishing\Models\FinBatchReceipt;
use App\Modules\Finishing\Models\FinMachine;
use App\Modules\Finishing\Models\FinMachineProcess;
use App\Modules\Finishing\Models\FinProcessType;
use App\Modules\Finishing\Models\FinProductionLine;
use App\Modules\Finishing\Models\FinProductionSheet;
use App\Modules\Finishing\Support\FinFloor;
use App\Modules\Finishing\Support\FinishingDefaults;
use App\Modules\Tenancy\Models\Floor;
use App\Shared\Http\Api\ApiController;
use App\Shared\Http\Api\ApiResponse;
use App\Shared\Pdf\TablePdf;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Writer\XLSX\Writer;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Finishing Line → DF Production (AnnotechERP 18/02), as Brickland keeps it:
 *
 * - Machine-Wise Production Entry → a shift's sheet on one line (Open or
 *   Tube), one row per batch through one machine on one process. Saving
 *   keeps it a draft, rewritten whole; posting it from the register moves
 *   each batch to the stage its machine and process are mapped to, in the
 *   order the rows came off their machines. A posted sheet reads only —
 *   corrections go through the Amendment.
 * - Process-Wise Delivery → the floor: every card on it, filtered as the
 *   search asks, with how long since its last load (counted here), and a
 *   batch passed on to its next stage by hand. Excel and Pdf write the same rows.
 */
class FinProductionApiController extends ApiController
{
    /** What the production forms pick from. */
    public function options(): JsonResponse
    {
        $this->allow('finishing.production.view');
        FinishingDefaults::ensure($this->caller()->companyId);
        $cards = FinBatchReceipt::query()->where('status', 'ON_FLOOR')->orderBy('receipt_no')->get();
        $batches = FinFloor::batches($cards->pluck('batch_id')->all());
        $company = DB::table('companies')->where('id', $this->caller()->companyId)->value('name');

        return ApiResponse::ok([
            'companies' => [['value' => $this->caller()->companyId, 'label' => $company]],
            'floors' => Floor::query()->orderBy('name')->get(['id', 'name'])->map(fn ($f) => ['value' => $f->id, 'label' => $f->name])->all(),
            'machines' => FinMachine::query()->where('active', true)->orderBy('machine_no')->get(['id', 'machine_no', 'name'])->map(fn ($m) => ['value' => $m->id, 'label' => "{$m->machine_no} — {$m->name}"])->all(),
            'processes' => FinProcessType::query()->where('active', true)->whereIn('group_id', DB::table('fin_process_groups')->where('company_id', $this->caller()->companyId)->where('code', 'DYEING_FINISHING')->select('id'))->orderBy('display_order')->get(['id', 'name', 'short_name'])->map(fn ($p) => ['value' => $p->id, 'label' => "{$p->name} ({$p->short_name})"])->all(),
            'mappings' => FinMachineProcess::query()->where('active', true)->get(['machine_id', 'process_id', 'dia_type'])->map(fn ($m) => ['machine_id' => $m->machine_id, 'process_id' => $m->process_id, 'dia_type' => $m->dia_type])->all(),
            'cards' => $cards->map(fn ($c) => ['value' => $c->id, 'label' => $this->cardLabel($c, $batches[$c->batch_id] ?? null), 'dia_type' => $c->dia_type, 'stage' => $c->stage, 'qty' => (float) $c->recv_qty_kg, 'rolls' => $c->recv_rolls])->values()->all(),
            'receivable' => FinFloor::receivable(),
            'buyers' => DB::table('buyers')->where('company_id', $this->caller()->companyId)->orderBy('name')->get(['id', 'short_name', 'name'])->map(fn ($b) => ['value' => $b->id, 'label' => $b->short_name ?: $b->name])->all(),
            'posted_lines' => $this->postedLines(),
        ]);
    }

    /** The register: every sheet with its batches and what went in and came off. */
    public function sheets(): JsonResponse
    {
        $this->allow('finishing.production.view');
        $floors = Floor::query()->pluck('name', 'id');
        $sums = FinProductionLine::query()->selectRaw('sheet_id, count(distinct receipt_id) as batches, sum(input_kg) as input, sum(output_kg) as output')->groupBy('sheet_id')->get()->keyBy('sheet_id');

        return ApiResponse::ok(FinProductionSheet::query()->orderByDesc('production_date')->orderByDesc('sheet_no')->get()->map(function (FinProductionSheet $s) use ($floors, $sums) {
            $t = $sums[$s->id] ?? null;
            $in = round((float) ($t->input ?? 0), 3);
            $out = round((float) ($t->output ?? 0), 3);

            return ['id' => $s->id, 'sheet_no' => $s->sheet_no, 'production_date' => $s->production_date->toDateString(), 'line' => $s->line, 'shift' => $s->shift, 'floor' => $floors[$s->floor_id] ?? null,
                'batches' => (int) ($t->batches ?? 0), 'input_kg' => $in, 'output_kg' => $out, 'loss_kg' => round($in - $out, 3), 'loss_pct' => $in > 0 ? round(($in - $out) / $in * 100, 2) : 0.0, 'status' => $s->status];
        })->all());
    }

    public function sheet(string $no): JsonResponse
    {
        $this->allow('finishing.production.view');

        return ApiResponse::ok($this->present($this->find($no)));
    }

    public function store(Request $request): JsonResponse
    {
        $this->allow('finishing.production.manage');
        $data = $this->validated($request, null);
        $sheet = DB::transaction(function () use ($data) {
            $sheet = FinProductionSheet::query()->create([
                'sheet_no' => DyeBatches::nextNumber('DFP-', 'fin_production_sheets', 'sheet_no', $data['production_date'], 'Ym'),
                'line' => $data['line'], 'production_date' => $data['production_date'], 'floor_id' => $data['floor_id'] ?? null, 'shift' => $data['shift'], 'remarks' => $data['remarks'] ?? null,
                'status' => 'DRAFT', 'created_by' => $this->caller()->auditUserId(),
            ]);
            $this->writeLines($sheet, $data['lines']);

            return $sheet;
        });

        return ApiResponse::created($this->present($sheet->refresh()));
    }

    public function update(Request $request, string $no): JsonResponse
    {
        $this->allow('finishing.production.manage');
        $sheet = $this->draft($no);
        $data = $this->validated($request, $sheet);
        DB::transaction(function () use ($sheet, $data): void {
            $sheet->update(['production_date' => $data['production_date'], 'floor_id' => $data['floor_id'] ?? null, 'shift' => $data['shift'], 'remarks' => $data['remarks'] ?? null]);
            $sheet->lines()->delete();
            $this->writeLines($sheet, $data['lines']);
        });

        return ApiResponse::ok($this->present($sheet->refresh()));
    }

    public function destroy(string $no): JsonResponse
    {
        $this->allow('finishing.production.manage');
        $sheet = $this->draft($no);
        DB::transaction(fn () => $sheet->delete());

        return ApiResponse::ok(['deleted' => true]);
    }

    /**
     * Post a draft: each row, in the order it came off its machine, moves its
     * batch to the stage the machine and process are mapped to on this line.
     */
    public function post(string $no): JsonResponse
    {
        $this->allow('finishing.production.manage');
        $sheet = $this->draft($no);
        DB::transaction(function () use ($sheet): void {
            $lines = $sheet->lines()->get()->sortBy(fn ($l) => [$l->unload_time?->getTimestamp() ?? PHP_INT_MAX, $l->sort_order])->values();
            if ($lines->isEmpty()) {
                throw ValidationException::withMessages(['lines' => __('finishing.prod.v.no_lines')]);
            }
            $batches = FinFloor::batches(FinBatchReceipt::query()->whereIn('id', $lines->pluck('receipt_id'))->pluck('batch_id')->all());
            foreach ($lines as $i => $line) {
                $card = FinBatchReceipt::query()->lockForUpdate()->findOrFail($line->receipt_id);
                $batch = $batches[$card->batch_id]->batch_no ?? '';
                if ($card->status !== 'ON_FLOOR') {
                    throw ValidationException::withMessages(['lines' => __('finishing.prod.v.post_returned', ['batch' => $batch, 'no' => $card->receipt_no])]);
                }
                $map = FinFloor::mapping($line->machine_id, $line->process_id, $sheet->line);
                if (! $map) {
                    throw ValidationException::withMessages(['lines' => __('finishing.prod.v.post_unmapped', ['batch' => $batch])]);
                }
                FinFloor::move($card, $map->next_status, 'SHEET', $line->unload_time ?? now(), $sheet->id, $sheet->sheet_no, $this->caller()->auditUserId(), [
                    'last_load_at' => $line->load_time ?? $card->last_load_at, 'last_machine_id' => $line->machine_id, 'last_process_id' => $line->process_id,
                ]);
            }
            $sheet->update(['status' => 'POSTED', 'posted_at' => now(), 'posted_by' => $this->caller()->auditUserId()]);
        });

        return ApiResponse::ok($this->present($sheet->refresh()));
    }

    /** The floor as the search asks for it, and the On Floor / On Hold / Ready counts. */
    public function board(Request $request): JsonResponse
    {
        $this->allow('finishing.production.view');

        return ApiResponse::ok($this->floor($request));
    }

    /** Pass a batch on to another stage by hand — not one only a desk sets. */
    public function deliver(Request $request, string $no): JsonResponse
    {
        $this->allow('finishing.production.manage');
        $card = FinBatchReceipt::query()->where('receipt_no', $no)->firstOrFail();
        $data = $request->validate([
            'to_stage' => ['required', Rule::in(array_values(array_diff(FinProcessType::STAGES, FinFloor::DESK_STAGES)))],
            'remarks' => ['nullable', 'string', 'max:255'],
        ], ['to_stage.required' => __('finishing.prod.v.to_stage'), 'to_stage.in' => __('finishing.prod.v.desk_stage')]);
        if ($card->status !== 'ON_FLOOR' || in_array($card->stage, FinFloor::DESK_STAGES, true)) {
            throw ValidationException::withMessages(['to_stage' => __('finishing.prod.v.card_not_on_floor', ['no' => $card->receipt_no])]);
        }
        if ($card->stage === $data['to_stage']) {
            throw ValidationException::withMessages(['to_stage' => __('finishing.prod.v.same_stage')]);
        }
        DB::transaction(fn () => FinFloor::move($card, $data['to_stage'], 'BOARD', now(), null, $data['remarks'] ?? null, $this->caller()->auditUserId()));

        return ApiResponse::ok(['receipt_no' => $card->receipt_no, 'stage' => $card->refresh()->stage]);
    }

    /** The board's rows as a PDF (to print) or a workbook. */
    public function export(Request $request, string $format): Response|StreamedResponse
    {
        $this->allow('finishing.production.view');
        abort_unless(in_array($format, ['pdf', 'xlsx'], true), 404);
        $rows = $this->floor($request)['rows'];
        $keys = ['batch_no', 'receipt_no', 'buyer', 'style', 'order_no', 'colour', 'dia_type', 'recv_qty_kg', 'recv_rolls', 'stage', 'last_process', 'last_machine', 'spent'];
        $columns = array_map(fn ($k) => ['label' => __("finishing.prod.col.{$k}"), 'numeric' => in_array($k, ['recv_qty_kg', 'recv_rolls'], true)], $keys);
        $body = array_map(fn ($r) => [$r['batch_no'], $r['receipt_no'], $r['buyer'], $r['style'], $r['order_no'], $r['colour'], __("finishing.prod.opt.dia.{$r['dia_type']}"), $r['recv_qty_kg'], $r['recv_rolls'],
            __("finishing.prod.opt.stage.{$r['stage']}"), $r['last_process'], $r['last_machine'], $r['spent_minutes'] === null ? null : intdiv($r['spent_minutes'], 60).'h '.str_pad((string) ($r['spent_minutes'] % 60), 2, '0', STR_PAD_LEFT).'m'], $rows);
        $total = $body ? [__('finishing.prod.total'), null, null, null, null, null, null, round(array_sum(array_column($rows, 'recv_qty_kg')), 3), array_sum(array_column($rows, 'recv_rolls')), null, null, null, null] : null;
        $name = 'process-wise-delivery-'.now('Asia/Dhaka')->format('Ymd-Hi');
        if ($format === 'pdf') {
            $meta = [__('finishing.prod.printed') => now('Asia/Dhaka')->format('d M Y h:i A'), __('finishing.prod.col.batch_count') => (string) count($rows)];

            return response((new TablePdf(__('finishing.prod.screens.df-process-wise-delivery'), $columns, $body, $meta, $total, __('finishing.prod.descriptions.df-process-wise-delivery')))->output(), 200, ['Content-Type' => 'application/pdf', 'Content-Disposition' => 'inline; filename="'.$name.'.pdf"']);
        }

        return response()->streamDownload(function () use ($columns, $body, $total): void {
            $writer = new Writer;
            $writer->openToFile('php://output');
            $writer->addRow(Row::fromValues(array_map(fn ($c) => $c['label'], $columns)));
            foreach ([...$body, ...($total ? [$total] : [])] as $r) {
                $writer->addRow(Row::fromValues(array_map(fn ($v) => $v ?? '', $r)));
            }
            $writer->close();
        }, $name.'.xlsx', ['Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet']);
    }

    /** @return array{rows: list<array<string, mixed>>, choices: array<string, list<string>>, tiles: array<string, int>} */
    private function floor(Request $request): array
    {
        $q = $request->validate([
            'dia' => ['nullable', Rule::in(FinBatchReceipt::DIAS)], 'stage' => ['nullable', Rule::in(FinProcessType::STAGES)], 'booking' => ['nullable', Rule::in(FinBatchReceipt::BOOKINGS)],
            'buyer' => ['nullable', 'string'], 'style' => ['nullable', 'string', 'max:120'], 'order' => ['nullable', 'string', 'max:64'], 'colour' => ['nullable', 'string', 'max:120'], 'batch' => ['nullable', 'string', 'max:40'],
        ]);
        $cards = FinBatchReceipt::query()->where('status', 'ON_FLOOR')
            ->when($q['stage'] ?? null, fn ($w, $s) => $w->where('stage', $s), fn ($w) => $w->where('stage', '!=', 'DELIVERED'))
            ->when($q['dia'] ?? null, fn ($w, $d) => $w->where('dia_type', $d))
            ->when($q['booking'] ?? null, fn ($w, $b) => $w->where('booking_type', $b))
            ->orderBy('receipt_no')->get();
        $batches = FinFloor::batches($cards->pluck('batch_id')->all());
        [$machines, $processes] = FinFloor::machineAndProcessNames();
        $has = fn (?string $text, ?string $needle) => ! $needle || str_contains(mb_strtolower((string) $text), mb_strtolower(trim($needle)));
        $now = now();
        $rows = [];
        foreach ($cards as $c) {
            $b = $batches[$c->batch_id] ?? null;
            if (($q['buyer'] ?? null) && $b?->buyer_id !== $q['buyer']) {
                continue;
            }
            if (! $has($b?->batch_no, $q['batch'] ?? null) || ! $has($b?->style, $q['style'] ?? null) || ! $has($b?->order_no, $q['order'] ?? null) || ! $has($b?->colour, $q['colour'] ?? null)) {
                continue;
            }
            $rows[] = [
                'id' => $c->id, 'receipt_no' => $c->receipt_no, 'receive_date' => $c->receive_date->toDateString(), 'batch_no' => $b?->batch_no, 'buyer' => $b?->buyer, 'style' => $b?->style, 'order_no' => $b?->order_no, 'colour' => $b?->colour,
                'dia_type' => $c->dia_type, 'booking_type' => $c->booking_type, 'recv_qty_kg' => round((float) $c->recv_qty_kg, 3), 'recv_rolls' => (int) $c->recv_rolls, 'stage' => $c->stage,
                'stage_at' => $c->stage_at?->toIso8601String(), 'last_load_at' => $c->last_load_at?->toIso8601String(),
                'last_machine' => $machines[$c->last_machine_id] ?? null, 'last_process' => $processes[$c->last_process_id] ?? null,
                'spent_minutes' => $c->last_load_at ? max(0, (int) floor($c->last_load_at->diffInMinutes($now, true))) : null,
            ];
        }
        $all = $rows;
        // What the Style, Order # and Color boxes offer: everything on the floor, whatever the search.
        $floor = FinFloor::batches(FinBatchReceipt::query()->where('status', 'ON_FLOOR')->where('stage', '!=', 'DELIVERED')->pluck('batch_id')->all());
        $pickOf = fn (string $k) => collect($floor)->pluck($k)->filter()->unique()->sort()->values()->all();

        return ['rows' => $rows, 'choices' => ['styles' => $pickOf('style'), 'orders' => $pickOf('order_no'), 'colours' => $pickOf('colour')], 'tiles' => [
            'on_floor' => count(array_filter($all, fn ($r) => ! in_array($r['stage'], ['ON_HOLD', 'READY_FOR_DELIVERY', 'DELIVERED'], true))),
            'on_hold' => count(array_filter($all, fn ($r) => $r['stage'] === 'ON_HOLD')),
            'ready' => count(array_filter($all, fn ($r) => $r['stage'] === 'READY_FOR_DELIVERY')),
        ]];
    }

    /** @return array<string, mixed> */
    private function validated(Request $request, ?FinProductionSheet $sheet): array
    {
        $company = $this->caller()->companyId;
        $own = fn (string $t) => Rule::exists($t, 'id')->where('company_id', $company);
        $data = $request->validate([
            'line' => [$sheet ? 'prohibited' : 'required', Rule::in(FinProductionSheet::LINES)],
            'production_date' => ['required', 'date'],
            'floor_id' => ['nullable', $own('floors')],
            'shift' => ['required', Rule::in(FinProductionSheet::SHIFTS)],
            'remarks' => ['nullable', 'string', 'max:2000'],
            'lines' => ['required', 'array', 'min:1', 'max:200'],
            'lines.*.receipt_id' => ['required', $own('fin_batch_receipts')],
            'lines.*.machine_id' => ['required', $own('fin_machines')],
            'lines.*.process_id' => ['required', $own('fin_process_types')],
            'lines.*.input_kg' => ['required', 'numeric', 'gt:0', 'max:99999999'],
            'lines.*.output_kg' => ['required', 'numeric', 'min:0', 'max:99999999'],
            'lines.*.rolls' => ['nullable', 'integer', 'min:0', 'max:999999'],
            'lines.*.fin_gsm' => ['nullable', 'numeric', 'min:0', 'max:9999'],
            'lines.*.fin_dia' => ['nullable', 'numeric', 'min:0', 'max:9999'],
            'lines.*.load_time' => ['nullable', 'date'],
            'lines.*.unload_time' => ['nullable', 'date'],
            'lines.*.remarks' => ['nullable', 'string', 'max:255'],
        ], [
            'line.prohibited' => __('finishing.prod.v.line_fixed'), 'production_date.required' => __('finishing.prod.v.production_date'), 'shift.required' => __('finishing.prod.v.shift'),
            'lines.required' => __('finishing.prod.v.no_lines'), 'lines.min' => __('finishing.prod.v.no_lines'),
        ]);
        ksort($data['lines']);
        $data['lines'] = array_values($data['lines']);
        $line = $sheet?->line ?? $data['line'];
        $cards = FinBatchReceipt::query()->whereIn('id', array_column($data['lines'], 'receipt_id'))->get()->keyBy('id');
        $batches = FinFloor::batches($cards->pluck('batch_id')->all());
        $errors = [];
        foreach ($data['lines'] as $i => $l) {
            $n = $i + 1;
            $card = $cards[$l['receipt_id']];
            $batch = $batches[$card->batch_id]->batch_no ?? '';
            if ($card->status !== 'ON_FLOOR' || in_array($card->stage, FinFloor::DESK_STAGES, true)) {
                $errors["lines.{$i}.receipt_id"] = __('finishing.prod.v.row_off_floor', ['n' => $n, 'batch' => $batch]);
            } elseif (! FinFloor::fitsLine($card->dia_type, $line)) {
                $errors["lines.{$i}.receipt_id"] = __('finishing.prod.v.row_wrong_line', ['n' => $n, 'batch' => $batch, 'dia' => __("finishing.prod.opt.dia.{$card->dia_type}"), 'line' => __("finishing.prod.opt.line.{$line}")]);
            } elseif ($card->receive_date->toDateString() > substr((string) $data['production_date'], 0, 10)) {
                $errors["lines.{$i}.receipt_id"] = __('finishing.prod.v.row_before_receipt', ['n' => $n, 'batch' => $batch, 'date' => $card->receive_date->toDateString()]);
            }
            if ((float) $l['output_kg'] > (float) $l['input_kg']) {
                $errors["lines.{$i}.output_kg"] = __('finishing.prod.v.row_output_over', ['n' => $n]);
            }
            if (! FinFloor::mapping($l['machine_id'], $l['process_id'], $line)) {
                $errors["lines.{$i}.process_id"] = __('finishing.prod.v.row_unmapped', ['n' => $n, 'line' => __("finishing.prod.opt.line.{$line}")]);
            }
            if (($l['load_time'] ?? null) && ($l['unload_time'] ?? null) && Carbon::parse($l['unload_time'])->lt(Carbon::parse($l['load_time']))) {
                $errors["lines.{$i}.unload_time"] = __('finishing.prod.v.row_unload_before', ['n' => $n]);
            }
        }
        if ($errors) {
            throw ValidationException::withMessages($errors);
        }

        return $data;
    }

    /** @param list<array<string, mixed>> $lines */
    private function writeLines(FinProductionSheet $sheet, array $lines): void
    {
        foreach ($lines as $i => $l) {
            FinProductionLine::query()->create([
                'sheet_id' => $sheet->id, 'receipt_id' => $l['receipt_id'], 'machine_id' => $l['machine_id'], 'process_id' => $l['process_id'],
                'input_kg' => $l['input_kg'], 'output_kg' => $l['output_kg'], 'rolls' => $l['rolls'] ?? null, 'fin_gsm' => $l['fin_gsm'] ?? null, 'fin_dia' => $l['fin_dia'] ?? null,
                'load_time' => ($l['load_time'] ?? null) ? Carbon::parse($l['load_time'], 'Asia/Dhaka')->utc() : null, 'unload_time' => ($l['unload_time'] ?? null) ? Carbon::parse($l['unload_time'], 'Asia/Dhaka')->utc() : null,
                'remarks' => $l['remarks'] ?? null, 'sort_order' => $i + 1,
            ]);
        }
    }

    private function find(string $no): FinProductionSheet
    {
        return FinProductionSheet::query()->where('sheet_no', $no)->firstOrFail();
    }

    private function draft(string $no): FinProductionSheet
    {
        $sheet = $this->find($no);
        if ($sheet->status !== 'DRAFT') {
            throw ValidationException::withMessages(['sheet_no' => __('finishing.prod.v.sheet_posted', ['no' => $sheet->sheet_no])]);
        }

        return $sheet;
    }

    /** @return array<string, mixed> */
    private function present(FinProductionSheet $s): array
    {
        $lines = $s->lines()->get();
        $cards = FinBatchReceipt::query()->whereIn('id', $lines->pluck('receipt_id'))->get()->keyBy('id');
        $batches = FinFloor::batches($cards->pluck('batch_id')->all());
        [$machines, $processes] = FinFloor::machineAndProcessNames();
        $local = fn ($t) => $t?->copy()->setTimezone('Asia/Dhaka')->format('Y-m-d\TH:i');

        return [
            'id' => $s->id, 'sheet_no' => $s->sheet_no, 'line' => $s->line, 'production_date' => $s->production_date->toDateString(), 'floor_id' => $s->floor_id, 'shift' => $s->shift, 'remarks' => $s->remarks, 'status' => $s->status,
            'posted_at' => $s->posted_at?->toIso8601String(),
            'lines' => $lines->map(fn (FinProductionLine $l) => [
                'id' => $l->id, 'receipt_id' => $l->receipt_id, 'batch' => $this->cardLabel($cards[$l->receipt_id] ?? null, isset($cards[$l->receipt_id]) ? ($batches[$cards[$l->receipt_id]->batch_id] ?? null) : null),
                'machine_id' => $l->machine_id, 'machine' => $machines[$l->machine_id] ?? null, 'process_id' => $l->process_id, 'process' => $processes[$l->process_id] ?? null,
                'input_kg' => round((float) $l->input_kg, 3), 'output_kg' => round((float) $l->output_kg, 3), 'rolls' => $l->rolls, 'fin_gsm' => $l->fin_gsm === null ? null : (float) $l->fin_gsm, 'fin_dia' => $l->fin_dia === null ? null : (float) $l->fin_dia,
                'load_time' => $local($l->load_time), 'unload_time' => $local($l->unload_time), 'remarks' => $l->remarks,
            ])->values()->all(),
        ];
    }

    /** "B-26110011 · Navy · FBR-2026110001". */
    private function cardLabel(?FinBatchReceipt $card, ?object $batch): string
    {
        return $card ? trim(($batch->batch_no ?? '—').' · '.($batch->colour ?? '—').' · '.$card->receipt_no) : '—';
    }

    /** Posted sheets' lines, for the Amendment to pick. @return list<array<string, mixed>> */
    private function postedLines(): array
    {
        $sheets = FinProductionSheet::query()->where('status', 'POSTED')->orderByDesc('production_date')->orderByDesc('sheet_no')->get()->keyBy('id');
        if ($sheets->isEmpty()) {
            return [];
        }
        $lines = FinProductionLine::query()->whereIn('sheet_id', $sheets->keys())->orderBy('sort_order')->get();
        $cards = FinBatchReceipt::query()->whereIn('id', $lines->pluck('receipt_id'))->get()->keyBy('id');
        $batches = FinFloor::batches($cards->pluck('batch_id')->all());
        [, $processes] = FinFloor::machineAndProcessNames();

        return $lines->sortBy(fn ($l) => [$sheets->keys()->search($l->sheet_id), $l->sort_order])->map(function ($l) use ($sheets, $cards, $batches, $processes) {
            $s = $sheets[$l->sheet_id];
            $b = $batches[$cards[$l->receipt_id]->batch_id] ?? null;

            return ['value' => $l->id, 'label' => "{$s->sheet_no} · ".($b->batch_no ?? '—').' · '.($processes[$l->process_id] ?? '—'), 'line' => $s->line, 'input_kg' => round((float) $l->input_kg, 3), 'output_kg' => round((float) $l->output_kg, 3), 'machine_id' => $l->machine_id, 'process_id' => $l->process_id];
        })->values()->all();
    }
}
