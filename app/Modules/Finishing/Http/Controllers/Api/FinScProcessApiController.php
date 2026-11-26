<?php

declare(strict_types=1);

namespace App\Modules\Finishing\Http\Controllers\Api;

use App\Modules\DyeHouse\Support\DyeBatches;
use App\Modules\Finishing\Models\FinBatchReceipt;
use App\Modules\Finishing\Models\FinProcessType;
use App\Modules\Finishing\Models\FinScBill;
use App\Modules\Finishing\Models\FinScBillLine;
use App\Modules\Finishing\Models\FinScChallan;
use App\Modules\Finishing\Models\FinScChallanLine;
use App\Modules\Finishing\Models\FinScReceipt;
use App\Modules\Finishing\Models\FinScReceiptLine;
use App\Modules\Finishing\Support\FinFloor;
use App\Modules\Finishing\Support\FinishingDefaults;
use App\Modules\Finishing\Support\FinSc;
use App\Shared\Http\Api\ApiController;
use App\Shared\Http\Api\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Writer\XLSX\Writer;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Finishing Line → S/C Process (AnnotechERP 18/03), as Brickland keeps it:
 *
 * - Sub-Contract Delivery Challan → one line per shade, a batch on the floor
 *   or a plain lot; saved as a draft, posted from the register (its batches
 *   go out at the processor), cancelled while nothing has come back or been billed.
 * - Sub-Contract Finished Receipt → each line answers the challan line it went
 *   out on (or none: an unmatched return); never more back than is still out;
 *   posting credits the lines, and a batch whose lines are all answered comes
 *   back onto the floor at the stage of the process done for it.
 * - Sub-Contract Process Bill → the party's own bill number, a line per
 *   process against the challan it was done under, amount recomputed on save,
 *   no more kilos billed than came back; rewritten while draft or submitted.
 * - Sub-Contract Party Balance → what each processor still holds, counted now.
 */
class FinScProcessApiController extends ApiController
{
    public function options(): JsonResponse
    {
        $this->allow('finishing.sc.view');
        $c = $this->caller()->companyId;
        FinishingDefaults::ensure($c);
        $cards = FinBatchReceipt::query()->where('status', 'ON_FLOOR')->whereNotIn('stage', FinFloor::DESK_STAGES)->orderBy('receipt_no')->get();
        $batches = FinFloor::batches($cards->pluck('batch_id')->all());
        $posted = FinScChallan::query()->where('status', 'POSTED')->orderByDesc('challan_date')->orderByDesc('challan_no')->get();
        $lines = FinScChallanLine::query()->whereIn('challan_id', $posted->pluck('id'))->orderBy('sort_order')->get()->groupBy('challan_id');
        $returned = FinSc::returned($lines->flatten()->pluck('id')->all());
        $suppliers = DB::table('mp_suppliers')->where('company_id', $c)->pluck('name', 'id');
        [, $processes] = FinFloor::machineAndProcessNames();
        $lineCards = FinBatchReceipt::query()->whereIn('id', $lines->flatten()->pluck('receipt_id')->filter())->get()->keyBy('id');
        $lineBatches = FinFloor::batches($lineCards->pluck('batch_id')->all());

        return ApiResponse::ok([
            'companies' => [['value' => $c, 'label' => DB::table('companies')->where('id', $c)->value('name')]],
            'suppliers' => DB::table('mp_suppliers')->where('company_id', $c)->where('active', true)->orderBy('name')->get(['id', 'name'])->map(fn ($s) => ['value' => $s->id, 'label' => $s->name])->all(),
            'stores' => DB::table('stores')->where('company_id', $c)->where('active', true)->orderBy('name')->get(['id', 'name', 'code'])->map(fn ($s) => ['value' => $s->id, 'label' => "{$s->name} ({$s->code})"])->all(),
            'buyers' => DB::table('buyers')->where('company_id', $c)->orderBy('name')->get(['id', 'short_name', 'name'])->map(fn ($b) => ['value' => $b->id, 'label' => $b->short_name ?: $b->name])->all(),
            'orders' => DB::table('merch_orders')->where('company_id', $c)->orderByDesc('order_no')->get(['id', 'order_no', 'style_no', 'work_style_no', 'buyer_id'])
                ->map(fn ($o) => ['value' => $o->id, 'label' => "{$o->order_no} [".($o->work_style_no ?: $o->style_no).']', 'style' => $o->work_style_no ?: $o->style_no, 'buyer_id' => $o->buyer_id])->all(),
            'processes' => FinProcessType::query()->where('active', true)->whereIn('group_id', DB::table('fin_process_groups')->where('company_id', $c)->where('code', 'DYEING_FINISHING')->select('id'))
                ->orderBy('display_order')->get(['id', 'name', 'short_name', 'rate'])->map(fn ($p) => ['value' => $p->id, 'label' => "{$p->name} ({$p->short_name})", 'rate' => (float) $p->rate])->all(),
            'cards' => $cards->map(function ($card) use ($batches) {
                $b = $batches[$card->batch_id] ?? null;

                return ['value' => $card->id, 'label' => trim(($b->batch_no ?? '—').' · '.($b->colour ?? '—').' · '.$card->receipt_no), 'buyer_id' => $b?->buyer_id, 'order_id' => $b?->order_id, 'style' => $b?->style, 'colour' => $b?->colour,
                    'dia_type' => $card->dia_type === 'BOTH' ? '' : $card->dia_type, 'qty' => (float) $card->recv_qty_kg, 'rolls' => $card->recv_rolls];
            })->values()->all(),
            'challans' => $posted->map(fn ($ch) => ['value' => $ch->id, 'label' => "{$ch->challan_no} · ".($suppliers[$ch->supplier_id] ?? ''), 'challan_no' => $ch->challan_no, 'supplier_id' => $ch->supplier_id, 'challan_date' => $ch->challan_date->toDateString(),
                'lines' => ($lines[$ch->id] ?? collect())->map(function ($l) use ($returned, $processes, $lineCards, $lineBatches) {
                    $card = $l->receipt_id ? ($lineCards[$l->receipt_id] ?? null) : null;
                    $batch = $card ? ($lineBatches[$card->batch_id]->batch_no ?? '—') : ($l->lot_no ?: '—');

                    return ['value' => $l->id, 'label' => "{$batch} · ".($processes[$l->process_id] ?? '').' · '.round((float) $l->qty_kg, 3).' kg', 'process_id' => $l->process_id, 'description' => $l->description, 'lot_no' => $l->lot_no, 'balance' => FinSc::balance($l, $returned), 'rolls' => $l->rolls];
                })->values()->all()])->values()->all(),
        ]);
    }

    // ── Delivery Challan ─────────────────────────────────────────────

    public function challans(): JsonResponse
    {
        $this->allow('finishing.sc.view');
        $rows = FinScChallan::query()->orderByDesc('challan_date')->orderByDesc('challan_no')->get();
        $lines = FinScChallanLine::query()->whereIn('challan_id', $rows->pluck('id'))->get()->groupBy('challan_id');
        $returned = FinSc::returned($lines->flatten()->pluck('id')->all());
        [$suppliers, $stores] = $this->names();

        return ApiResponse::ok($rows->map(function (FinScChallan $ch) use ($lines, $returned, $suppliers, $stores) {
            $mine = $lines[$ch->id] ?? collect();
            $qty = round((float) $mine->sum('qty_kg'), 3);
            $recv = round($mine->sum(fn ($l) => $returned[$l->id]['recv'] ?? 0), 3);
            $short = round($mine->sum(fn ($l) => $returned[$l->id]['short'] ?? 0), 3);

            return ['id' => $ch->id, 'challan_no' => $ch->challan_no, 'challan_date' => $ch->challan_date->toDateString(), 'supplier_id' => $ch->supplier_id, 'supplier' => $suppliers[$ch->supplier_id] ?? null,
                'store_id' => $ch->store_id, 'store' => $stores[$ch->store_id] ?? null, 'gate_pass_no' => $ch->gate_pass_no, 'vehicle_no' => $ch->vehicle_no, 'lines' => $mine->count(),
                'qty_kg' => $qty, 'received_kg' => $recv, 'short_kg' => $short, 'balance_kg' => round($qty - $recv - $short, 3), 'status' => $ch->status];
        })->all());
    }

    public function challan(string $no): JsonResponse
    {
        $this->allow('finishing.sc.view');

        return ApiResponse::ok($this->presentChallan(FinScChallan::query()->where('challan_no', $no)->firstOrFail()));
    }

    public function saveChallan(Request $request, ?string $no = null): JsonResponse
    {
        $this->allow('finishing.sc.manage');
        $challan = $no ? $this->challanOf($no, ['DRAFT']) : null;
        $data = $this->challanData($request);
        $challan = DB::transaction(function () use ($challan, $data) {
            $head = ['challan_date' => $data['challan_date'], 'supplier_id' => $data['supplier_id'], 'store_id' => $data['store_id'], 'gate_pass_no' => $data['gate_pass_no'] ?? null, 'vehicle_no' => $data['vehicle_no'] ?? null, 'remarks' => $data['remarks'] ?? null];
            if ($challan) {
                $challan->update($head);
                $challan->lines()->delete();
            } else {
                $challan = FinScChallan::query()->create($head + ['challan_no' => DyeBatches::nextNumber('FSC-CH-', 'fin_sc_challans', 'challan_no', $data['challan_date'], 'Ym'), 'status' => 'DRAFT', 'created_by' => $this->caller()->auditUserId()]);
            }
            foreach ($data['lines'] as $i => $l) {
                FinScChallanLine::query()->create(['challan_id' => $challan->id, 'sort_order' => $i + 1] + $l);
            }

            return $challan;
        });

        return $no ? ApiResponse::ok($this->presentChallan($challan->refresh())) : ApiResponse::created($this->presentChallan($challan->refresh()));
    }

    /** Post a draft: every batch on it goes out at the processor. */
    public function postChallan(string $no): JsonResponse
    {
        $this->allow('finishing.sc.manage');
        $challan = $this->challanOf($no, ['DRAFT']);
        DB::transaction(function () use ($challan): void {
            $cardIds = $challan->lines()->whereNotNull('receipt_id')->pluck('receipt_id')->unique();
            $batches = FinFloor::batches(FinBatchReceipt::query()->whereIn('id', $cardIds)->pluck('batch_id')->all());
            foreach ($cardIds as $id) {
                $card = FinBatchReceipt::query()->lockForUpdate()->findOrFail($id);
                if ($card->status !== 'ON_FLOOR' || in_array($card->stage, FinFloor::DESK_STAGES, true)) {
                    throw ValidationException::withMessages(['challan_no' => __('finishing.sc.v.post_off_floor', ['batch' => $batches[$card->batch_id]->batch_no ?? ''])]);
                }
                FinSc::sendOut($card, $challan->challan_no, $this->caller()->auditUserId());
            }
            $challan->update(['status' => 'POSTED', 'posted_at' => now(), 'posted_by' => $this->caller()->auditUserId()]);
        });

        return ApiResponse::ok($this->presentChallan($challan->refresh()));
    }

    /** Cancel a challan nothing has come back or been billed against; a posted one's batches come back. */
    public function cancelChallan(string $no): JsonResponse
    {
        $this->allow('finishing.sc.manage');
        $challan = $this->challanOf($no, ['DRAFT', 'POSTED']);
        $lineIds = $challan->lines()->pluck('id');
        if (FinScReceiptLine::query()->whereIn('challan_line_id', $lineIds)->exists() || FinScBillLine::query()->where('challan_id', $challan->id)->exists()) {
            throw ValidationException::withMessages(['challan_no' => __('finishing.sc.v.challan_answered', ['no' => $challan->challan_no])]);
        }
        DB::transaction(function () use ($challan): void {
            if ($challan->status === 'POSTED') {
                foreach (FinBatchReceipt::query()->whereIn('id', $challan->lines()->whereNotNull('receipt_id')->pluck('receipt_id'))->get() as $card) {
                    if ($card->stage === 'AT_SUB_CONTRACT') {
                        FinSc::callBack($card, $challan->challan_no, $this->caller()->auditUserId());
                    }
                }
            }
            $challan->update(['status' => 'CANCELLED']);
        });

        return ApiResponse::ok($this->presentChallan($challan->refresh()));
    }

    // ── Finished Receipt ─────────────────────────────────────────────

    public function receipts(): JsonResponse
    {
        $this->allow('finishing.sc.view');
        $rows = FinScReceipt::query()->orderByDesc('receive_date')->orderByDesc('mrr_no')->get();
        $lines = FinScReceiptLine::query()->whereIn('receipt_id', $rows->pluck('id'))->get()->groupBy('receipt_id');
        $challans = FinScChallan::query()->whereIn('id', $rows->pluck('challan_id')->filter())->pluck('challan_no', 'id');
        $batchOf = $this->batchOfChallanLines($lines->flatten()->pluck('challan_line_id')->filter()->all());
        [$suppliers, $stores] = $this->names();

        return ApiResponse::ok($rows->map(function (FinScReceipt $r) use ($lines, $challans, $suppliers, $stores, $batchOf) {
            $mine = $lines[$r->id] ?? collect();

            return ['id' => $r->id, 'mrr_no' => $r->mrr_no, 'receive_date' => $r->receive_date->toDateString(), 'challan_id' => $r->challan_id, 'challan_no' => $challans[$r->challan_id] ?? null,
                'supplier_id' => $r->supplier_id, 'supplier' => $suppliers[$r->supplier_id] ?? null, 'store_id' => $r->store_id, 'store' => $stores[$r->store_id] ?? null, 'gate_pass_no' => $r->gate_pass_no,
                'batches' => $mine->map(fn ($l) => $batchOf[$l->challan_line_id] ?? null)->filter()->unique()->implode(', '),
                'lines' => $mine->count(), 'recv_kg' => round((float) $mine->sum('recv_kg'), 3), 'short_kg' => round((float) $mine->sum('short_kg'), 3), 'status' => $r->status];
        })->all());
    }

    public function receipt(string $no): JsonResponse
    {
        $this->allow('finishing.sc.view');

        return ApiResponse::ok($this->presentReceipt(FinScReceipt::query()->where('mrr_no', $no)->firstOrFail()));
    }

    public function saveReceipt(Request $request, ?string $no = null): JsonResponse
    {
        $this->allow('finishing.sc.manage');
        $receipt = $no ? $this->receiptOf($no, ['DRAFT']) : null;
        $data = $this->receiptData($request, $receipt);
        $receipt = DB::transaction(function () use ($receipt, $data) {
            $head = ['receive_date' => $data['receive_date'], 'challan_id' => $data['challan_id'] ?? null, 'supplier_id' => $data['supplier_id'], 'store_id' => $data['store_id'], 'gate_pass_no' => $data['gate_pass_no'] ?? null, 'remarks' => $data['remarks'] ?? null];
            if ($receipt) {
                $receipt->update($head);
                $receipt->lines()->delete();
            } else {
                $receipt = FinScReceipt::query()->create($head + ['mrr_no' => DyeBatches::nextNumber('FSC-MRR-', 'fin_sc_receipts', 'mrr_no', $data['receive_date'], 'Ym'), 'status' => 'DRAFT', 'created_by' => $this->caller()->auditUserId()]);
            }
            foreach ($data['lines'] as $i => $l) {
                FinScReceiptLine::query()->create(['receipt_id' => $receipt->id, 'sort_order' => $i + 1, 'short_kg' => $l['short_kg'] ?? 0] + $l);
            }

            return $receipt;
        });

        return $no ? ApiResponse::ok($this->presentReceipt($receipt->refresh())) : ApiResponse::created($this->presentReceipt($receipt->refresh()));
    }

    /** Post a draft: it credits the challan lines it answers; batches fully answered come back onto the floor. */
    public function postReceipt(string $no): JsonResponse
    {
        $this->allow('finishing.sc.manage');
        $receipt = $this->receiptOf($no, ['DRAFT']);
        DB::transaction(function () use ($receipt): void {
            $lines = $receipt->lines()->get();
            $this->checkBalances($lines->map(fn ($l) => $l->only(['challan_line_id', 'recv_kg', 'short_kg']))->values()->all(), $receipt->id, 'challan_no');
            $receipt->update(['status' => 'POSTED', 'posted_at' => now(), 'posted_by' => $this->caller()->auditUserId()]);
            $cardIds = FinScChallanLine::query()->whereIn('id', $lines->pluck('challan_line_id')->filter())->whereNotNull('receipt_id')->pluck('receipt_id')->unique();
            foreach (FinBatchReceipt::query()->whereIn('id', $cardIds)->lockForUpdate()->get() as $card) {
                FinSc::bringBackIfDone($card, $receipt->mrr_no, $this->caller()->auditUserId());
            }
        });

        return ApiResponse::ok($this->presentReceipt($receipt->refresh()));
    }

    // ── Process Bill ─────────────────────────────────────────────────

    public function bills(): JsonResponse
    {
        $this->allow('finishing.sc.view');
        $rows = FinScBill::query()->orderByDesc('bill_date')->orderByDesc('created_at')->get();
        $lines = FinScBillLine::query()->whereIn('bill_id', $rows->pluck('id'))->get()->groupBy('bill_id');
        [$suppliers] = $this->names();
        $codes = DB::table('mp_suppliers')->whereIn('id', $rows->pluck('supplier_id'))->pluck('code', 'id');

        return ApiResponse::ok($rows->map(function (FinScBill $b) use ($lines, $suppliers, $codes) {
            $mine = $lines[$b->id] ?? collect();

            return ['id' => $b->id, 'bill_no' => $b->bill_no, 'bill_date' => $b->bill_date->toDateString(), 'supplier_id' => $b->supplier_id, 'supplier' => $suppliers[$b->supplier_id] ?? null, 'party_code' => $codes[$b->supplier_id] ?? null,
                'ref_bill_no' => $b->ref_bill_no, 'rev_no' => $b->rev_no, 'lines' => $mine->count(), 'qty_kg' => round((float) $mine->sum('qty_kg'), 3), 'amount' => round((float) $mine->sum('amount'), 2), 'status' => $b->status];
        })->all());
    }

    public function bill(Request $request, string $no): JsonResponse
    {
        $this->allow('finishing.sc.view');

        return ApiResponse::ok($this->presentBill($this->billOf($no, $request->query('party'))));
    }

    public function saveBill(Request $request, ?string $no = null): JsonResponse
    {
        $this->allow('finishing.sc.manage');
        $bill = $no ? $this->billOf($no, $request->query('party')) : null;
        if ($bill && ! in_array($bill->status, ['DRAFT', 'SUBMITTED'], true)) {
            throw ValidationException::withMessages(['bill_no' => __('finishing.sc.v.bill_stands', ['no' => $bill->bill_no])]);
        }
        $data = $this->billData($request, $bill);
        $bill = DB::transaction(function () use ($bill, $data) {
            $head = ['bill_no' => $data['bill_no'], 'bill_date' => $data['bill_date'], 'supplier_id' => $data['supplier_id'], 'ref_bill_no' => $data['ref_bill_no'] ?? null, 'rev_no' => $data['rev_no'] ?? null, 'rev_description' => $data['rev_description'] ?? null, 'remarks' => $data['remarks'] ?? null];
            if ($bill) {
                $bill->update($head);
                $bill->lines()->delete();
            } else {
                $bill = FinScBill::query()->create($head + ['status' => 'DRAFT', 'created_by' => $this->caller()->auditUserId()]);
            }
            foreach ($data['lines'] as $i => $l) {
                FinScBillLine::query()->create(['bill_id' => $bill->id, 'sort_order' => $i + 1, 'amount' => round((float) $l['qty_kg'] * (float) $l['rate'], 2)] + $l);
            }

            return $bill;
        });

        return $no ? ApiResponse::ok($this->presentBill($bill->refresh())) : ApiResponse::created($this->presentBill($bill->refresh()));
    }

    /** Submit (from draft), Complete (from submitted) or Cancel (before completion). */
    public function billStatus(Request $request, string $no): JsonResponse
    {
        $this->allow('finishing.sc.manage');
        $bill = $this->billOf($no, $request->query('party'));
        $to = $request->validate(['to' => ['required', Rule::in(['SUBMITTED', 'COMPLETED', 'CANCELLED'])]])['to'];
        $from = ['SUBMITTED' => ['DRAFT'], 'COMPLETED' => ['SUBMITTED'], 'CANCELLED' => ['DRAFT', 'SUBMITTED']][$to];
        if (! in_array($bill->status, $from, true)) {
            throw ValidationException::withMessages(['to' => __('finishing.sc.v.bill_step', ['no' => $bill->bill_no, 'status' => __("finishing.sc.opt.bill_status.{$bill->status}")])]);
        }
        $bill->update(['status' => $to]);

        return ApiResponse::ok($this->presentBill($bill->refresh()));
    }

    // ── Party Balance ────────────────────────────────────────────────

    public function balance(Request $request): JsonResponse
    {
        $this->allow('finishing.sc.view');

        return ApiResponse::ok($this->balanceRows($request));
    }

    public function balanceExport(Request $request): StreamedResponse
    {
        $this->allow('finishing.sc.view');
        $rows = $this->balanceRows($request);
        $keys = ['supplier', 'challan_no', 'challan_date', 'batch_no', 'order_no', 'colour', 'process', 'lot_no', 'qty_kg', 'received_kg', 'short_kg', 'balance_kg', 'days_out'];

        return response()->streamDownload(function () use ($rows, $keys): void {
            $writer = new Writer;
            $writer->openToFile('php://output');
            $writer->addRow(Row::fromValues(array_map(fn ($k) => __("finishing.sc.col.{$k}"), $keys)));
            foreach ($rows as $r) {
                $writer->addRow(Row::fromValues(array_map(fn ($k) => $r[$k] ?? '', $keys)));
            }
            $writer->close();
        }, 'sc-party-balance-'.now('Asia/Dhaka')->format('Ymd').'.xlsx', ['Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet']);
    }

    /** @return list<array<string, mixed>> */
    private function balanceRows(Request $request): array
    {
        $q = $request->validate(['party' => ['nullable', 'string'], 'challan' => ['nullable', 'string', 'max:40'], 'open' => ['nullable', 'in:0,1,true,false']]);
        $challans = FinScChallan::query()->where('status', 'POSTED')->when($q['party'] ?? null, fn ($w, $p) => $w->where('supplier_id', $p))
            ->when($q['challan'] ?? null, fn ($w, $n) => $w->where('challan_no', 'ilike', '%'.trim($n).'%'))->orderBy('challan_date')->orderBy('challan_no')->get()->keyBy('id');
        $lines = FinScChallanLine::query()->whereIn('challan_id', $challans->keys())->orderBy('sort_order')->get();
        $returned = FinSc::returned($lines->pluck('id')->all());
        $batchOf = $this->batchOfChallanLines($lines->pluck('id')->all());
        [$suppliers] = $this->names();
        [, $processes] = FinFloor::machineAndProcessNames();
        $orders = DB::table('merch_orders')->whereIn('id', $lines->pluck('order_id')->filter())->pluck('order_no', 'id');
        $open = in_array($q['open'] ?? null, ['1', 'true'], true);
        $today = now('Asia/Dhaka')->startOfDay();
        $rows = [];
        foreach ($lines as $l) {
            $ch = $challans[$l->challan_id];
            $bal = FinSc::balance($l, $returned);
            if ($open && $bal <= 0.0005) {
                continue;
            }
            $rows[] = ['id' => $l->id, 'supplier' => $suppliers[$ch->supplier_id] ?? null, 'challan_no' => $ch->challan_no, 'challan_date' => $ch->challan_date->toDateString(), 'batch_no' => $batchOf[$l->id] ?? null,
                'order_no' => $orders[$l->order_id] ?? null, 'colour' => $l->colour, 'process' => $processes[$l->process_id] ?? null, 'lot_no' => $l->lot_no, 'qty_kg' => round((float) $l->qty_kg, 3),
                'received_kg' => $returned[$l->id]['recv'] ?? 0.0, 'short_kg' => $returned[$l->id]['short'] ?? 0.0, 'balance_kg' => $bal, 'days_out' => $bal > 0.0005 ? (int) $ch->challan_date->copy()->startOfDay()->diffInDays($today) : null];
        }

        return $rows;
    }

    // ── Validation ───────────────────────────────────────────────────

    /** @return array<string, mixed> */
    private function challanData(Request $request): array
    {
        $own = $this->own();
        $data = $request->validate([
            'challan_date' => ['required', 'date'], 'supplier_id' => ['required', $own('mp_suppliers')], 'store_id' => ['required', $own('stores')],
            'gate_pass_no' => ['nullable', 'string', 'max:40'], 'vehicle_no' => ['nullable', 'string', 'max:40'], 'remarks' => ['nullable', 'string', 'max:2000'],
            'lines' => ['required', 'array', 'min:1', 'max:200'],
            'lines.*.receipt_id' => ['nullable', $own('fin_batch_receipts')], 'lines.*.buyer_id' => ['nullable', $own('buyers')], 'lines.*.order_id' => ['nullable', $own('merch_orders')],
            'lines.*.style' => ['nullable', 'string', 'max:120'], 'lines.*.colour' => ['nullable', 'string', 'max:120'], 'lines.*.process_id' => ['required', $own('fin_process_types')],
            'lines.*.description' => ['nullable', 'string', 'max:255'], 'lines.*.lot_no' => ['nullable', 'string', 'max:40'], 'lines.*.dia_type' => ['nullable', Rule::in(['OPEN', 'TUBE'])],
            'lines.*.qty_kg' => ['required', 'numeric', 'gt:0', 'max:99999999'], 'lines.*.rolls' => ['nullable', 'integer', 'min:0', 'max:999999'], 'lines.*.rate' => ['nullable', 'numeric', 'min:0', 'max:9999999'],
            'lines.*.remarks' => ['nullable', 'string', 'max:255'],
        ], [
            'challan_date.required' => __('finishing.sc.v.challan_date'), 'supplier_id.required' => __('finishing.sc.v.supplier'), 'store_id.required' => __('finishing.sc.v.store'),
            'lines.required' => __('finishing.sc.v.no_lines_challan'), 'lines.min' => __('finishing.sc.v.no_lines_challan'),
        ]);
        ksort($data['lines']);
        $data['lines'] = array_values($data['lines']);
        $cards = FinBatchReceipt::query()->whereIn('id', array_filter(array_column($data['lines'], 'receipt_id')))->get()->keyBy('id');
        $batches = FinFloor::batches($cards->pluck('batch_id')->all());
        $errors = [];
        $perCard = [];
        foreach ($data['lines'] as $i => &$l) {
            $n = $i + 1;
            if (! ($l['receipt_id'] ?? null)) {
                if (! ($l['lot_no'] ?? null) && ! ($l['description'] ?? null)) {
                    $errors["lines.{$i}.receipt_id"] = __('finishing.sc.v.row_what', ['n' => $n]);
                }
                continue;
            }
            $card = $cards[$l['receipt_id']];
            $b = $batches[$card->batch_id] ?? null;
            if ($card->status !== 'ON_FLOOR' || in_array($card->stage, FinFloor::DESK_STAGES, true)) {
                $errors["lines.{$i}.receipt_id"] = __('finishing.sc.v.row_off_floor', ['n' => $n, 'batch' => $b->batch_no ?? '']);
                continue;
            }
            $perCard[$card->id] = ($perCard[$card->id] ?? 0) + (float) $l['qty_kg'];
            if ($perCard[$card->id] > (float) $card->recv_qty_kg + 0.0005) {
                $errors["lines.{$i}.qty_kg"] = __('finishing.sc.v.row_over_card', ['n' => $n, 'batch' => $b->batch_no ?? '', 'kg' => $this->kg($card->recv_qty_kg)]);
            }
            $l['buyer_id'] = ($l['buyer_id'] ?? null) ?: $b?->buyer_id;
            $l['order_id'] = ($l['order_id'] ?? null) ?: $b?->order_id;
            $l['style'] = ($l['style'] ?? null) ?: $b?->style;
            $l['colour'] = ($l['colour'] ?? null) ?: $b?->colour;
        }
        unset($l);
        if ($errors) {
            throw ValidationException::withMessages($errors);
        }

        return $data;
    }

    /** @return array<string, mixed> */
    private function receiptData(Request $request, ?FinScReceipt $receipt): array
    {
        $own = $this->own();
        $data = $request->validate([
            'receive_date' => ['required', 'date'], 'challan_id' => ['nullable', $own('fin_sc_challans')], 'supplier_id' => ['required', $own('mp_suppliers')], 'store_id' => ['required', $own('stores')],
            'gate_pass_no' => ['nullable', 'string', 'max:40'], 'remarks' => ['nullable', 'string', 'max:2000'],
            'lines' => ['required', 'array', 'min:1', 'max:200'],
            'lines.*.challan_line_id' => ['nullable', $own('fin_sc_challan_lines')], 'lines.*.description' => ['nullable', 'string', 'max:255'], 'lines.*.lot_no' => ['nullable', 'string', 'max:40'],
            'lines.*.recv_kg' => ['required', 'numeric', 'min:0', 'max:99999999'], 'lines.*.rolls' => ['nullable', 'integer', 'min:0', 'max:999999'], 'lines.*.short_kg' => ['nullable', 'numeric', 'min:0', 'max:99999999'],
            'lines.*.remarks' => ['nullable', 'string', 'max:255'],
        ], [
            'receive_date.required' => __('finishing.sc.v.receive_date'), 'supplier_id.required' => __('finishing.sc.v.party'), 'store_id.required' => __('finishing.sc.v.store'),
            'lines.required' => __('finishing.sc.v.no_lines_receipt'), 'lines.min' => __('finishing.sc.v.no_lines_receipt'),
        ]);
        ksort($data['lines']);
        $data['lines'] = array_values($data['lines']);
        $challan = ($data['challan_id'] ?? null) ? FinScChallan::query()->findOrFail($data['challan_id']) : null;
        if ($challan) {
            if ($challan->status !== 'POSTED') {
                throw ValidationException::withMessages(['challan_id' => __('finishing.sc.v.challan_not_posted', ['no' => $challan->challan_no])]);
            }
            if ($challan->supplier_id !== $data['supplier_id']) {
                throw ValidationException::withMessages(['supplier_id' => __('finishing.sc.v.party_of_challan', ['no' => $challan->challan_no])]);
            }
            if (substr((string) $data['receive_date'], 0, 10) < $challan->challan_date->toDateString()) {
                throw ValidationException::withMessages(['receive_date' => __('finishing.sc.v.receipt_before_challan', ['no' => $challan->challan_no])]);
            }
        }
        $errors = [];
        $ofChallan = $challan ? FinScChallanLine::query()->where('challan_id', $challan->id)->pluck('id')->all() : [];
        foreach ($data['lines'] as $i => $l) {
            $n = $i + 1;
            if (($l['challan_line_id'] ?? null) && ! in_array($l['challan_line_id'], $ofChallan, true)) {
                $errors["lines.{$i}.challan_line_id"] = __('finishing.sc.v.row_not_on_challan', ['n' => $n]);
            }
            if ((float) $l['recv_kg'] + (float) ($l['short_kg'] ?? 0) <= 0) {
                $errors["lines.{$i}.recv_kg"] = __('finishing.sc.v.row_nothing', ['n' => $n]);
            }
        }
        if ($errors) {
            throw ValidationException::withMessages($errors);
        }
        $this->checkBalances($data['lines'], $receipt?->id, 'lines');

        return $data;
    }

    /** No line may take back more than its challan line still has out (posted receipts counted, this one left out). @param list<array<string, mixed>> $lines */
    private function checkBalances(array $lines, ?string $receiptId, string $key): void
    {
        $ids = array_values(array_unique(array_filter(array_column($lines, 'challan_line_id'))));
        $challanLines = FinScChallanLine::query()->whereIn('id', $ids)->get()->keyBy('id');
        $returned = FinSc::returned($ids, $receiptId);
        $taking = [];
        foreach ($lines as $i => $l) {
            if (! ($l['challan_line_id'] ?? null)) {
                continue;
            }
            $id = $l['challan_line_id'];
            $taking[$id] = ($taking[$id] ?? 0) + (float) $l['recv_kg'] + (float) ($l['short_kg'] ?? 0);
            $left = FinSc::balance($challanLines[$id], $returned);
            if ($taking[$id] > $left + 0.0005) {
                throw ValidationException::withMessages([$key === 'lines' ? "lines.{$i}.recv_kg" : $key => __('finishing.sc.v.row_over_balance', ['n' => $i + 1, 'kg' => $this->kg($left)])]);
            }
        }
    }

    /** @return array<string, mixed> */
    private function billData(Request $request, ?FinScBill $bill): array
    {
        $own = $this->own();
        $data = $request->validate([
            'bill_date' => ['required', 'date'], 'bill_no' => ['required', 'string', 'max:40'], 'supplier_id' => ['required', $own('mp_suppliers')],
            'ref_bill_no' => ['nullable', 'string', 'max:40'], 'rev_no' => ['nullable', 'string', 'max:16'], 'rev_description' => ['nullable', 'string', 'max:255'], 'remarks' => ['nullable', 'string', 'max:2000'],
            'lines' => ['required', 'array', 'min:1', 'max:200'],
            'lines.*.challan_id' => ['required', $own('fin_sc_challans')], 'lines.*.process_id' => ['required', $own('fin_process_types')], 'lines.*.description' => ['nullable', 'string', 'max:255'],
            'lines.*.qty_kg' => ['required', 'numeric', 'gt:0', 'max:99999999'], 'lines.*.rate' => ['nullable', 'numeric', 'min:0', 'max:9999999'], 'lines.*.remarks' => ['nullable', 'string', 'max:255'],
        ], [
            'bill_date.required' => __('finishing.sc.v.bill_date'), 'bill_no.required' => __('finishing.sc.v.bill_no'), 'supplier_id.required' => __('finishing.sc.v.supplier'),
            'lines.required' => __('finishing.sc.v.no_lines_bill'), 'lines.min' => __('finishing.sc.v.no_lines_bill'),
        ]);
        ksort($data['lines']);
        $data['lines'] = array_values($data['lines']);
        $data['bill_no'] = mb_strtoupper(trim($data['bill_no']));
        $taken = FinScBill::query()->where('supplier_id', $data['supplier_id'])->where('bill_no', $data['bill_no'])->when($bill, fn ($q) => $q->whereKeyNot($bill->id))->exists();
        if ($taken) {
            throw ValidationException::withMessages(['bill_no' => __('finishing.sc.v.bill_twice', ['no' => $data['bill_no']])]);
        }
        $challans = FinScChallan::query()->whereIn('id', array_column($data['lines'], 'challan_id'))->get()->keyBy('id');
        $rates = FinProcessType::query()->pluck('rate', 'id');
        $errors = [];
        $billing = [];
        foreach ($data['lines'] as $i => &$l) {
            $n = $i + 1;
            $ch = $challans[$l['challan_id']];
            if ($ch->status !== 'POSTED' || $ch->supplier_id !== $data['supplier_id']) {
                $errors["lines.{$i}.challan_id"] = __('finishing.sc.v.row_challan_party', ['n' => $n, 'no' => $ch->challan_no]);
                continue;
            }
            $sent = FinScChallanLine::query()->where('challan_id', $ch->id)->where('process_id', $l['process_id'])->pluck('id')->all();
            if (! $sent) {
                $errors["lines.{$i}.process_id"] = __('finishing.sc.v.row_process_not_sent', ['n' => $n, 'no' => $ch->challan_no]);
                continue;
            }
            $back = array_sum(array_column(FinSc::returned($sent), 'recv'));
            $billed = (float) FinScBillLine::query()->where('challan_id', $ch->id)->where('process_id', $l['process_id'])
                ->whereIn('bill_id', FinScBill::query()->where('status', '!=', 'CANCELLED')->when($bill, fn ($q) => $q->whereKeyNot($bill->id))->select('id'))->sum('qty_kg');
            $key = "{$ch->id}:{$l['process_id']}";
            $billing[$key] = ($billing[$key] ?? 0) + (float) $l['qty_kg'];
            if ($billing[$key] > $back - $billed + 0.0005) {
                $errors["lines.{$i}.qty_kg"] = __('finishing.sc.v.row_over_received', ['n' => $n, 'kg' => $this->kg(max(0, $back - $billed))]);
            }
            $l['rate'] = $l['rate'] ?? (float) ($rates[$l['process_id']] ?? 0);
        }
        unset($l);
        if ($errors) {
            throw ValidationException::withMessages($errors);
        }

        return $data;
    }

    // ── Presenting ───────────────────────────────────────────────────

    /** @return array<string, mixed> */
    private function presentChallan(FinScChallan $ch): array
    {
        $lines = $ch->lines()->get();
        $returned = FinSc::returned($lines->pluck('id')->all());
        $cards = FinBatchReceipt::query()->whereIn('id', $lines->pluck('receipt_id')->filter())->get()->keyBy('id');
        $batches = FinFloor::batches($cards->pluck('batch_id')->all());
        [, $processes] = FinFloor::machineAndProcessNames();
        $buyers = DB::table('buyers')->whereIn('id', $lines->pluck('buyer_id')->filter())->get(['id', 'short_name', 'name'])->mapWithKeys(fn ($b) => [$b->id => $b->short_name ?: $b->name]);
        $orders = DB::table('merch_orders')->whereIn('id', $lines->pluck('order_id')->filter())->pluck('order_no', 'id');

        return [
            'id' => $ch->id, 'challan_no' => $ch->challan_no, 'challan_date' => $ch->challan_date->toDateString(), 'supplier_id' => $ch->supplier_id, 'store_id' => $ch->store_id,
            'gate_pass_no' => $ch->gate_pass_no, 'vehicle_no' => $ch->vehicle_no, 'remarks' => $ch->remarks, 'status' => $ch->status,
            'lines' => $lines->map(function (FinScChallanLine $l) use ($returned, $cards, $batches, $processes, $buyers, $orders) {
                $card = $l->receipt_id ? ($cards[$l->receipt_id] ?? null) : null;
                $b = $card ? ($batches[$card->batch_id] ?? null) : null;

                return ['id' => $l->id, 'receipt_id' => $l->receipt_id, 'batch' => $card ? trim(($b->batch_no ?? '—').' · '.($b->colour ?? '—').' · '.$card->receipt_no) : '—',
                    'buyer_id' => $l->buyer_id, 'buyer' => $buyers[$l->buyer_id] ?? null, 'order_id' => $l->order_id, 'order_no' => $orders[$l->order_id] ?? null, 'style' => $l->style, 'colour' => $l->colour,
                    'process_id' => $l->process_id, 'process' => $processes[$l->process_id] ?? null, 'description' => $l->description, 'lot_no' => $l->lot_no, 'dia_type' => $l->dia_type,
                    'qty_kg' => round((float) $l->qty_kg, 3), 'rolls' => $l->rolls, 'rate' => $l->rate === null ? null : (float) $l->rate, 'remarks' => $l->remarks,
                    'received_kg' => $returned[$l->id]['recv'] ?? 0.0, 'balance_kg' => FinSc::balance($l, $returned)];
            })->values()->all(),
        ];
    }

    /** @return array<string, mixed> */
    private function presentReceipt(FinScReceipt $r): array
    {
        $lines = $r->lines()->get();
        $challanLines = FinScChallanLine::query()->whereIn('id', $lines->pluck('challan_line_id')->filter())->get()->keyBy('id');
        $batchOf = $this->batchOfChallanLines($challanLines->keys()->all());
        [, $processes] = FinFloor::machineAndProcessNames();

        return [
            'id' => $r->id, 'mrr_no' => $r->mrr_no, 'receive_date' => $r->receive_date->toDateString(), 'challan_id' => $r->challan_id, 'supplier_id' => $r->supplier_id, 'store_id' => $r->store_id,
            'gate_pass_no' => $r->gate_pass_no, 'remarks' => $r->remarks, 'status' => $r->status,
            'lines' => $lines->map(function (FinScReceiptLine $l) use ($challanLines, $batchOf, $processes) {
                $cl = $l->challan_line_id ? ($challanLines[$l->challan_line_id] ?? null) : null;

                return ['id' => $l->id, 'challan_line_id' => $l->challan_line_id, 'batch' => $cl ? (($batchOf[$cl->id] ?? $cl->lot_no ?? '—').' · '.($processes[$cl->process_id] ?? '')) : '—',
                    'description' => $l->description, 'lot_no' => $l->lot_no, 'recv_kg' => round((float) $l->recv_kg, 3), 'rolls' => $l->rolls, 'short_kg' => round((float) $l->short_kg, 3), 'remarks' => $l->remarks];
            })->values()->all(),
        ];
    }

    /** @return array<string, mixed> */
    private function presentBill(FinScBill $b): array
    {
        $lines = $b->lines()->get();
        $challans = FinScChallan::query()->whereIn('id', $lines->pluck('challan_id'))->pluck('challan_no', 'id');
        [, $processes] = FinFloor::machineAndProcessNames();

        return [
            'id' => $b->id, 'bill_no' => $b->bill_no, 'bill_date' => $b->bill_date->toDateString(), 'supplier_id' => $b->supplier_id, 'party_code' => DB::table('mp_suppliers')->where('id', $b->supplier_id)->value('code'),
            'ref_bill_no' => $b->ref_bill_no, 'rev_no' => $b->rev_no, 'rev_description' => $b->rev_description, 'remarks' => $b->remarks, 'status' => $b->status,
            'amount' => round((float) $lines->sum('amount'), 2),
            'lines' => $lines->map(fn (FinScBillLine $l) => ['id' => $l->id, 'challan_id' => $l->challan_id, 'challan' => $challans[$l->challan_id] ?? null, 'process_id' => $l->process_id, 'process' => $processes[$l->process_id] ?? null,
                'description' => $l->description, 'qty_kg' => round((float) $l->qty_kg, 3), 'rate' => (float) $l->rate, 'amount' => round((float) $l->amount, 2), 'remarks' => $l->remarks])->values()->all(),
        ];
    }

    // ── Lookups ──────────────────────────────────────────────────────

    /** @param list<string> $statuses */
    private function challanOf(string $no, array $statuses): FinScChallan
    {
        $ch = FinScChallan::query()->where('challan_no', $no)->firstOrFail();
        if (! in_array($ch->status, $statuses, true)) {
            throw ValidationException::withMessages(['challan_no' => __('finishing.sc.v.challan_locked', ['no' => $ch->challan_no, 'status' => __("finishing.sc.opt.challan_status.{$ch->status}")])]);
        }

        return $ch;
    }

    /** @param list<string> $statuses */
    private function receiptOf(string $no, array $statuses): FinScReceipt
    {
        $r = FinScReceipt::query()->where('mrr_no', $no)->firstOrFail();
        if (! in_array($r->status, $statuses, true)) {
            throw ValidationException::withMessages(['mrr_no' => __('finishing.sc.v.receipt_posted', ['no' => $r->mrr_no])]);
        }

        return $r;
    }

    /** A bill by its own number — the party's code telling apart two parties' same number. */
    private function billOf(string $no, ?string $party): FinScBill
    {
        $q = FinScBill::query()->where('bill_no', mb_strtoupper(trim($no)));
        if ($party) {
            $q->whereIn('supplier_id', DB::table('mp_suppliers')->where('company_id', $this->caller()->companyId)->where('code', $party)->select('id'));
        }

        return $q->orderByDesc('created_at')->firstOrFail();
    }

    /** "B-26110011" for each challan line that carries a batch. @param list<string> $ids @return array<string, string> */
    private function batchOfChallanLines(array $ids): array
    {
        $lines = FinScChallanLine::query()->whereIn('id', $ids)->whereNotNull('receipt_id')->get(['id', 'receipt_id']);
        $cards = FinBatchReceipt::query()->whereIn('id', $lines->pluck('receipt_id'))->pluck('batch_id', 'id');
        $batches = FinFloor::batches($cards->values()->all());

        return $lines->mapWithKeys(fn ($l) => [$l->id => $batches[$cards[$l->receipt_id] ?? '']->batch_no ?? null])->filter()->all();
    }

    /** @return array{0: Collection<string, string>, 1: Collection<string, string>} */
    private function names(): array
    {
        $c = $this->caller()->companyId;

        return [
            DB::table('mp_suppliers')->where('company_id', $c)->pluck('name', 'id'),
            DB::table('stores')->where('company_id', $c)->get(['id', 'name', 'code'])->mapWithKeys(fn ($s) => [$s->id => "{$s->name} ({$s->code})"]),
        ];
    }

    private function own(): \Closure
    {
        $c = $this->caller()->companyId;

        return fn (string $t) => Rule::exists($t, 'id')->where('company_id', $c);
    }

    private function kg(mixed $v): string
    {
        return rtrim(rtrim(number_format((float) $v, 3, '.', ''), '0'), '.');
    }
}
