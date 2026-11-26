<?php

declare(strict_types=1);

namespace App\Modules\Accounting\Http\Controllers\Api;

use App\Modules\Accounting\Models\AccMrrBill;
use App\Modules\Accounting\Support\AccountingDefaults;
use App\Modules\Accounting\Support\MrrSources;
use App\Shared\Http\Api\ApiController;
use App\Shared\Http\Api\ApiResponse;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * Finance & Ledger → Combined MRR Bill (AnnotechERP 23/02): a supplier's bill
 * drawn from one or more of its material receive reports of one kind. A line
 * is what its MRR already says (date, order, unit, quantity, value in BDT);
 * the bill adds other cost, VDS and TDS, and the net is derived — total plus
 * other cost, less VDS and TDS. Each MRR sits on one bill. The bill number is
 * issued on first save (MCB-2026-000001); Finalize locks it, Mark as Fixed
 * Asset records that the goods were capitalised.
 */
class MrrBillApiController extends ApiController
{
    public function options(): JsonResponse
    {
        $this->allowEither('accounting.bill.view', 'accounting.bill.manage');
        $c = $this->caller()->companyId;
        AccountingDefaults::accounts($c);

        return ApiResponse::ok([
            'today' => CarbonImmutable::now('Asia/Dhaka')->toDateString(),
            'companies' => [['value' => $c, 'label' => (string) DB::table('companies')->where('id', $c)->value('name')]],
            'suppliers' => DB::table('mp_suppliers')->where('company_id', $c)->where('active', true)->orderBy('name')->get(['id', 'name', 'code'])->map(fn ($s) => ['value' => $s->id, 'label' => $s->name.($s->code ? " ({$s->code})" : '')])->all(),
            'accounts' => DB::table('acc_accounts')->where('company_id', $c)->where('postable', true)->where('active', true)->orderBy('code')->get(['id', 'code', 'name'])->map(fn ($a) => ['value' => $a->id, 'label' => "{$a->code} — {$a->name}"])->all(),
            'people' => DB::table('hr_employees')->where('company_id', $c)->orderBy('name')->get(['id', 'name', 'employee_code'])->map(fn ($e) => ['value' => $e->id, 'label' => $e->name.($e->employee_code ? " ({$e->employee_code})" : '')])->all(),
            'users' => DB::table('users as u')->join('company_users as cu', 'cu.user_id', '=', 'u.id')->where('cu.company_id', $c)->orderBy('u.name')->get(['u.id', 'u.name'])->map(fn ($u) => ['value' => $u->id, 'label' => $u->name])->all(),
        ]);
    }

    public function index(): JsonResponse
    {
        $this->allowEither('accounting.bill.view', 'accounting.bill.manage');

        return ApiResponse::ok($this->rows(AccMrrBill::query()->orderByDesc('bill_date')->orderByDesc('bill_no')->get()));
    }

    /** The supplier's receipts of a kind not yet on another bill. */
    public function mrrs(Request $request): JsonResponse
    {
        $this->allowEither('accounting.bill.view', 'accounting.bill.manage');
        $d = $request->validate(['type' => ['required', Rule::in(MrrSources::KINDS)], 'supplier' => ['required', 'string'], 'except' => ['nullable', 'string']]);
        $taken = DB::table('acc_mrr_bill_lines')->where('company_id', $this->caller()->companyId)->where('mrr_kind', $d['type'])->when($d['except'] ?? null, fn ($w, $v) => $w->where('bill_id', '!=', $v))->pluck('mrr_id')->flip();
        $rows = (new MrrSources($this->caller()->companyId))->receipts($d['type'], $d['supplier'])->reject(fn ($r) => isset($taken[$r->id]));

        return ApiResponse::ok($rows->map(fn ($r) => ['value' => $r->id, 'label' => $r->mrr_no] + (array) $r)->values()->all());
    }

    public function show(string $id): JsonResponse
    {
        $this->allowEither('accounting.bill.view', 'accounting.bill.manage');

        return ApiResponse::ok($this->body(AccMrrBill::query()->findOrFail($id)));
    }

    public function save(Request $request, ?string $id = null): JsonResponse
    {
        $this->allow('accounting.bill.manage');
        $c = $this->caller()->companyId;
        $b = $id ? AccMrrBill::query()->findOrFail($id) : null;
        if ($b?->final) {
            throw ValidationException::withMessages(['bill_date' => __('accounting.vo.v.locked', ['no' => $b->bill_no])]);
        }
        $account = Rule::exists('acc_accounts', 'id')->where('company_id', $c)->where('postable', true)->where('active', true);
        $d = $request->validate([
            'supplier_id' => ['required', Rule::exists('mp_suppliers', 'id')->where('company_id', $c)],
            'bill_date' => ['required', 'date'],
            'mrr_type' => ['required', Rule::in(MrrSources::KINDS)],
            'prepared_by' => ['nullable', Rule::exists('hr_employees', 'id')->where('company_id', $c)],
            'maker_id' => ['nullable', Rule::exists('users', 'id')],
            'credit_account_id' => ['required', $account],
            'remarks' => ['nullable', 'string', 'max:2000'],
            'lines' => ['required', 'array', 'min:1'],
            'lines.*.mrr_id' => ['required', 'string', 'distinct'],
            'lines.*.debit_account_id' => ['required', $account],
            'lines.*.other_cost' => ['nullable', 'numeric', 'min:0', 'max:99999999'],
            'lines.*.vds' => ['nullable', 'numeric', 'min:0', 'max:99999999'],
            'lines.*.tds' => ['nullable', 'numeric', 'min:0', 'max:99999999'],
        ], ['lines.required' => __('accounting.vo.v.mb_lines'), 'lines.min' => __('accounting.vo.v.mb_lines')], self::attrs());
        ksort($d['lines']);
        $lines = array_values($d['lines']);
        $mrrs = (new MrrSources($c))->receipts($d['mrr_type'], null, array_column($lines, 'mrr_id'));
        $taken = DB::table('acc_mrr_bill_lines as l')->join('acc_mrr_bills as b', 'b.id', '=', 'l.bill_id')->where('l.company_id', $c)->where('l.mrr_kind', $d['mrr_type'])->whereIn('l.mrr_id', array_column($lines, 'mrr_id'))
            ->when($b, fn ($w) => $w->where('l.bill_id', '!=', $b->id))->pluck('b.bill_no', 'l.mrr_id');
        $rows = [];
        foreach ($lines as $n => $l) {
            $m = $mrrs[$l['mrr_id']] ?? null;
            if (! $m) {
                throw ValidationException::withMessages(["lines.{$n}.mrr_id" => __('accounting.vo.v.mb_mrr', ['n' => $n + 1])]);
            }
            if ($m->supplier_id !== $d['supplier_id']) {
                throw ValidationException::withMessages(["lines.{$n}.mrr_id" => __('accounting.vo.v.mb_supplier', ['mrr' => $m->mrr_no])]);
            }
            if (isset($taken[$m->id])) {
                throw ValidationException::withMessages(["lines.{$n}.mrr_id" => __('accounting.vo.v.mb_taken', ['mrr' => $m->mrr_no, 'bill' => $taken[$m->id]])]);
            }
            $other = round((float) ($l['other_cost'] ?? 0), 4);
            $vds = round((float) ($l['vds'] ?? 0), 4);
            $tds = round((float) ($l['tds'] ?? 0), 4);
            $net = round($m->total_amount + $other - $vds - $tds, 3);
            if ($net < 0) {
                throw ValidationException::withMessages(["lines.{$n}.vds" => __('accounting.vo.v.mb_net', ['mrr' => $m->mrr_no])]);
            }
            $rows[] = ['debit_account_id' => $l['debit_account_id'], 'mrr_kind' => $d['mrr_type'], 'mrr_id' => $m->id, 'mrr_no' => $m->mrr_no, 'mrr_date' => $m->mrr_date, 'po_wo' => $m->po_wo ? mb_substr($m->po_wo, 0, 48) : null, 'rate' => $m->rate,
                'uom' => $m->uom ? mb_substr($m->uom, 0, 24) : null, 'total_qty' => $m->total_qty, 'total_amount' => $m->total_amount, 'other_cost' => $other, 'vds' => $vds, 'tds' => $tds, 'net_amount' => $net];
        }

        $saved = DB::transaction(function () use ($d, $b, $c, $rows) {
            $head = collect($d)->except('lines')->all() + ['company_id' => $c];
            if ($b) {
                $b->update($head);
                DB::table('acc_mrr_bill_lines')->where('bill_id', $b->id)->delete();
            } else {
                $b = AccMrrBill::query()->create($head + ['bill_no' => $this->nextNo($d['bill_date']), 'created_by' => $this->caller()->auditUserId()]);
            }
            $now = now();
            DB::table('acc_mrr_bill_lines')->insert(array_map(fn ($r, $i) => $r + ['id' => strtolower((string) Str::ulid()), 'company_id' => $c, 'bill_id' => $b->id, 'sort_order' => $i + 1, 'created_at' => $now, 'updated_at' => $now], $rows, array_keys($rows)));

            return $b->fresh();
        });

        return $id ? ApiResponse::ok($this->body($saved)) : ApiResponse::created($this->body($saved));
    }

    public function final(string $id): JsonResponse
    {
        $this->allow('accounting.bill.manage');
        $b = AccMrrBill::query()->findOrFail($id);
        $b->update(['final' => true, 'finalized_at' => now()]);

        return ApiResponse::ok($this->body($b->fresh()));
    }

    public function fixedAsset(string $id): JsonResponse
    {
        $this->allow('accounting.bill.manage');
        $b = AccMrrBill::query()->findOrFail($id);
        $b->update(['fixed_asset' => true]);

        return ApiResponse::ok($this->body($b->fresh()));
    }

    public function destroy(string $id): JsonResponse
    {
        $this->allow('accounting.bill.manage');
        $b = AccMrrBill::query()->findOrFail($id);
        if ($b->final) {
            throw ValidationException::withMessages(['id' => __('accounting.vo.v.locked', ['no' => $b->bill_no])]);
        }
        $b->delete();

        return ApiResponse::ok(['deleted' => true]);
    }

    /* ── Bodies ────────────────────────────────────────────────────── */

    private function rows(Collection $bills): array
    {
        $c = $this->caller()->companyId;
        $company = (string) DB::table('companies')->where('id', $c)->value('name');
        $suppliers = DB::table('mp_suppliers')->whereIn('id', $bills->pluck('supplier_id')->unique())->pluck('name', 'id');
        $lines = DB::table('acc_mrr_bill_lines')->whereIn('bill_id', $bills->pluck('id'))->orderBy('sort_order')->get(['bill_id', 'mrr_id', 'mrr_kind', 'mrr_no', 'total_amount', 'net_amount'])->groupBy('bill_id');
        $people = DB::table('hr_employees')->whereIn('id', $bills->pluck('prepared_by')->filter()->unique())->pluck('name', 'id');
        $users = DB::table('users')->whereIn('id', $bills->pluck('created_by')->merge($bills->pluck('maker_id'))->filter()->unique())->pluck('name', 'id');
        $challans = $this->challans($lines->flatten(1));

        return $bills->map(function (AccMrrBill $b) use ($company, $suppliers, $lines, $people, $users, $challans) {
            $g = $lines->get($b->id, collect());

            return ['id' => $b->id, 'bill_no' => $b->bill_no, 'bill_date' => $b->bill_date->toDateString(), 'company' => $company, 'supplier_id' => $b->supplier_id, 'supplier' => $suppliers[$b->supplier_id] ?? '—', 'mrr_type' => $b->mrr_type,
                'mrr_nos' => $g->pluck('mrr_no')->implode(', '), 'challans' => $g->map(fn ($l) => $challans[$l->mrr_id] ?? null)->filter()->unique()->implode(', ') ?: null, 'total' => round((float) $g->sum('total_amount'), 3), 'net' => round((float) $g->sum('net_amount'), 3),
                'remarks' => $b->remarks, 'final' => $b->final, 'fixed_asset' => $b->fixed_asset, 'prepared_by' => $b->prepared_by ? ($people[$b->prepared_by] ?? null) : null, 'created_by' => $b->created_by ? ($users[$b->created_by] ?? null) : null, 'locked' => $b->final];
        })->values()->all();
    }

    /** @return array<string, mixed> */
    private function body(AccMrrBill $b): array
    {
        $lines = DB::table('acc_mrr_bill_lines')->where('bill_id', $b->id)->orderBy('sort_order')->get();

        return array_merge($this->rows(collect([$b]))[0], ['prepared_by' => $b->prepared_by, 'maker_id' => $b->maker_id, 'credit_account_id' => $b->credit_account_id,
            'lines' => $lines->map(fn ($l) => ['mrr_id' => $l->mrr_id, 'mrr_no' => $l->mrr_no, 'debit_account_id' => $l->debit_account_id, 'mrr_date' => substr((string) $l->mrr_date, 0, 10), 'po_wo' => $l->po_wo, 'rate' => (float) $l->rate, 'uom' => $l->uom,
                'total_qty' => (float) $l->total_qty, 'total_amount' => (float) $l->total_amount, 'other_cost' => (float) $l->other_cost, 'vds' => (float) $l->vds, 'tds' => (float) $l->tds, 'net_amount' => (float) $l->net_amount])->values()->all()]);
    }

    /** @return array<string, string> MRR id => the supplier's challan number, where the receipt keeps one. */
    private function challans(Collection $lines): array
    {
        $out = [];
        $tables = ['YARN' => 'knit_yarn_receipts', 'DYES' => 'dye_chem_receipts', 'TRIMS' => 'merch_acc_mrrs', 'OIL' => 'knit_oil_receipts', 'NEEDLE' => 'knit_needle_receipts'];
        foreach ($lines->groupBy('mrr_kind') as $kind => $g) {
            if (isset($tables[$kind])) {
                $out += DB::table($tables[$kind])->whereIn('id', $g->pluck('mrr_id'))->whereNotNull('challan_no')->pluck('challan_no', 'id')->all();
            }
        }

        return $out;
    }

    private function nextNo(string $date): string
    {
        $base = 'MCB-'.substr($date, 0, 4).'-';
        $last = AccMrrBill::query()->where('bill_no', 'like', $base.'%')->orderByDesc('bill_no')->value('bill_no');

        return $base.str_pad((string) ((int) substr((string) $last, strlen($base)) + 1), 6, '0', STR_PAD_LEFT);
    }

    /** @return array<string, string> */
    public static function attrs(): array
    {
        $a = [];
        foreach (['supplier_id', 'bill_date', 'mrr_type', 'prepared_by', 'maker_id', 'credit_account_id', 'remarks', 'lines'] as $k) {
            $a[$k] = __("accounting.vo.attr.mb_{$k}");
        }
        foreach (['mrr_id', 'debit_account_id', 'other_cost', 'vds', 'tds'] as $k) {
            $a["lines.*.{$k}"] = __("accounting.vo.attr.mb_line_{$k}");
        }

        return $a;
    }
}
