<?php

declare(strict_types=1);

namespace App\Modules\ColourLab\Http\Controllers\Api;

use App\Modules\ColourLab\Models\LabCertificate;
use App\Modules\ColourLab\Models\LabCertRegister;
use App\Modules\ColourLab\Models\LabFabricTest;
use App\Modules\ColourLab\Models\LabTrial;
use App\Modules\ColourLab\Models\LabTrialLine;
use App\Modules\ColourLab\Support\ColourLab;
use App\Modules\Merchandise\Models\Buyer;
use App\Modules\Merchandise\Models\Order;
use App\Shared\Http\Api\ApiController;
use App\Shared\Http\Api\ApiResponse;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

/**
 * Colour Lab → Reports (AnnotechERP 16/02): Brickland's Dyeing Lab Reports —
 * one report picked from the list and Preview reads it from the lab's own
 * tables at that moment: fabric tests (register, dia and GSM variance,
 * colour fastness, failed and conditional, buyer-wise pass rate), chemical
 * trials (register, recipe details, chemical usage, awaiting sign-off),
 * certificates (register, renewals due, movements) and the labs. Each report
 * answers its columns ("key:type") and rows; there are no other boxes.
 */
class ColourLabReportApiController extends ApiController
{
    /** Brickland's list, in its order, with the group each sits under. */
    public const REPORTS = [
        'fabric-test-register' => 'fabric_test', 'dia-gsm-variance' => 'fabric_test', 'colour-fastness' => 'fabric_test', 'failed-conditional' => 'fabric_test',
        'buyer-pass-rate' => 'fabric_test', 'trial-register' => 'chemical_trial', 'trial-recipe' => 'chemical_trial', 'chemical-usage' => 'chemical_trial',
        'trials-awaiting' => 'chemical_trial', 'certification-register' => 'certification', 'renewal-due' => 'certification', 'certificate-movement' => 'certification',
        'lab-master' => 'master_data',
    ];

    public function show(Request $request, string $report): JsonResponse
    {
        $this->allow('colourlab.report.view');
        $request->merge(['report' => $report]);
        $request->validate(['report' => ['required', Rule::in(array_keys(self::REPORTS))]]);
        $method = lcfirst(str_replace(' ', '', ucwords(str_replace('-', ' ', $report))));
        [$spec, $rows] = $this->{$method}();
        $columns = array_map(function (string $s): array {
            [$key, $type] = array_pad(explode(':', $s, 2), 2, 'text');

            return ['key' => $key, 'type' => $type];
        }, $spec);

        return ApiResponse::ok(['report' => $report, 'group' => self::REPORTS[$report], 'columns' => $columns, 'rows' => array_values($rows instanceof Collection ? $rows->all() : $rows)]);
    }

    // ── Fabric tests ──────────────────────────────────────────────────────

    /** @return Collection<int, array<string, mixed>> */
    private function tests(): Collection
    {
        $rows = LabFabricTest::query()->orderByDesc('test_date')->orderByDesc('test_no')->get();
        $buyers = Buyer::query()->whereIn('id', $rows->pluck('buyer_id'))->get()->mapWithKeys(fn (Buyer $b) => [$b->id => $b->short_name ?: $b->name]);
        $orders = Order::query()->whereIn('id', $rows->pluck('order_id')->filter())->pluck('order_no', 'id');
        $types = DB::table('fabric_types')->whereIn('id', $rows->pluck('fabric_type_id')->filter())->pluck('name', 'id');

        return $rows->map(fn (LabFabricTest $t) => [
            'test_no' => $t->test_no, 'test_date' => $t->test_date->toDateString(), 'customer' => $buyers[$t->buyer_id] ?? null, 'style' => $t->style_no, 'order_no' => $orders[$t->order_id] ?? null,
            'fabric' => $types[$t->fabric_type_id] ?? null, 'colour' => $t->colour, 'lot_no' => $t->lot_no, 'qty' => $t->qty, 'result' => $t->summary, 'status' => $t->status,
            'style_lot' => trim(implode(' · ', array_filter([$t->style_no, $t->lot_no]))) ?: null, 'remarks' => $t->decision_note ?: $t->remarks, 'buyer_id' => $t->buyer_id,
            'dia' => self::pair($t->std_dia, $t->act_dia), 'gsm' => self::pair($t->std_gsm, $t->act_gsm), 'shrinkage' => self::pair($t->shrink_l, $t->shrink_w, false),
            'spirality' => $t->spirality, 'wash' => $t->wash, 'rub_dry' => $t->rub_dry, 'rub_wet' => $t->rub_wet, 'light' => $t->light, 'perspiration' => $t->perspiration,
            'lab_floor_id' => $t->lab_floor_id,
        ]);
    }

    /** "72 / 71.5 (-0.5)" — a standard against what was found and the gap; or simply "L / W". */
    private static function pair(?float $a, ?float $b, bool $gap = true): ?string
    {
        if ($a === null && $b === null) {
            return null;
        }
        $f = fn (?float $v) => $v === null ? '—' : rtrim(rtrim(number_format($v, 2, '.', ''), '0'), '.');
        $out = $f($a).' / '.$f($b);
        if ($gap && $a !== null && $b !== null) {
            $d = round($b - $a, 2);
            $out .= ' ('.($d > 0 ? '+' : '').$f($d).')';
        }

        return $out;
    }

    private function fabricTestRegister(): array
    {
        return [['test_no', 'test_date:date', 'customer', 'style', 'order_no', 'fabric', 'colour', 'lot_no', 'qty:number', 'result:summary', 'status:test_status'], $this->tests()];
    }

    private function diaGsmVariance(): array
    {
        return [['test_no', 'customer', 'style_lot', 'dia', 'gsm', 'shrinkage', 'spirality:number', 'result:summary'], $this->tests()];
    }

    private function colourFastness(): array
    {
        return [['test_no', 'customer', 'colour', 'wash', 'rub_dry', 'rub_wet', 'light', 'perspiration', 'result:summary'], $this->tests()];
    }

    /** Tests that did not pass outright: failed or passed on conditions. */
    private function failedConditional(): array
    {
        return [['test_no', 'test_date:date', 'customer', 'style_lot', 'result:summary', 'remarks', 'status:test_status'],
            $this->tests()->whereIn('result', ['FAIL', 'CONDITIONAL'])->values()];
    }

    /** Per customer: tests, how they came out, and the share passed of those decided. */
    private function buyerPassRate(): array
    {
        $rows = $this->tests()->groupBy('customer')->map(function (Collection $g, string $customer): array {
            $passed = $g->where('result', 'PASS')->count();
            $decided = $g->whereNotNull('result')->count();

            return ['customer' => $customer ?: null, 'tested' => $g->count(), 'passed' => $passed, 'conditional' => $g->where('result', 'CONDITIONAL')->count(),
                'failed' => $g->where('result', 'FAIL')->count(), 'pending' => $g->whereNull('result')->count(), 'pass_rate' => $decided ? round(100 * $passed / $decided, 2) : null];
        })->sortBy('customer')->values();

        return [['customer', 'tested:int', 'passed:int', 'conditional:int', 'failed:int', 'pending:int', 'pass_rate:pct'], $rows];
    }

    // ── Chemical trials ───────────────────────────────────────────────────

    /** @return array{0: Collection<int, LabTrial>, 1: Collection<string, Collection<int, LabTrialLine>>} */
    private function trials(): array
    {
        $trials = LabTrial::query()->orderByDesc('trial_date')->orderByDesc('trial_no')->get();

        return [$trials, LabTrialLine::query()->whereIn('trial_id', $trials->pluck('id'))->orderBy('sort_order')->get()->groupBy('trial_id')];
    }

    /** A trial's result read from its lines: Fail if any failed, Re-trial if any is to be tried again, Pass when all passed, else Pending. */
    private static function trialResult(Collection $lines): string
    {
        return match (true) {
            $lines->isEmpty() => 'PENDING',
            $lines->contains('result', 'FAIL') => 'FAIL',
            $lines->contains('result', 'RETRIAL') => 'RETRIAL',
            $lines->every(fn ($l) => $l->result === 'PASS') => 'PASS',
            default => 'PENDING',
        };
    }

    private function trialRegister(): array
    {
        [$trials, $lines] = $this->trials();
        $floors = ColourLab::floors();
        $users = ColourLab::userNames();
        $rows = $trials->map(function (LabTrial $t) use ($lines, $floors, $users): array {
            $mine = $lines[$t->id] ?? collect();

            return ['trial_no' => $t->trial_no, 'trial_date' => $t->trial_date->toDateString(), 'lab' => $floors[$t->lab_floor_id] ?? null, 'trial_by' => $users[$t->trial_by] ?? null,
                'buyer' => null, 'chemicals' => $mine->count(), 'batch' => round((float) $mine->sum('sample_qty'), 3), 'result' => self::trialResult($mine), 'status' => $t->status];
        });

        return [['trial_no', 'trial_date:date', 'lab', 'trial_by', 'buyer', 'chemicals:int', 'batch:number', 'result:result', 'status:trial_status'], $rows];
    }

    private function trialRecipe(): array
    {
        [$trials, $lines] = $this->trials();
        $items = DB::table('items')->whereIn('id', $lines->flatten()->pluck('item_id'))->get(['id', 'item_code', 'name'])->keyBy('id');
        $rows = [];
        foreach ($trials as $t) {
            foreach (($lines[$t->id] ?? collect())->values() as $i => $l) {
                $dose = $l->actual_dose ?? $l->claim_dose;
                $rows[] = ['trial_no' => $t->trial_no, 'trial_date' => $t->trial_date->toDateString(), 'n' => $i + 1,
                    'chemical' => isset($items[$l->item_id]) ? "{$items[$l->item_id]->name} ({$items[$l->item_id]->item_code})" : null,
                    'dosage' => $dose === null ? null : rtrim(rtrim(number_format($dose, 4, '.', ''), '0'), '.').' '.$l->dose_unit, 'qty' => $l->sample_qty, 'remarks' => $l->comments];
            }
        }

        return [['trial_no', 'trial_date:date', 'n:int', 'chemical', 'dosage', 'qty:number', 'remarks'], $rows];
    }

    /** Per chemical: the trials it was in, the sample kilograms, its average dose (as found, else as claimed) and when it was last tried. */
    private function chemicalUsage(): array
    {
        [$trials, $lines] = $this->trials();
        $dates = $trials->pluck('trial_date', 'id');
        $items = DB::table('items')->whereIn('id', $lines->flatten()->pluck('item_id'))->get(['id', 'item_code', 'name'])->keyBy('id');
        $rows = $lines->flatten()->groupBy('item_id')->map(function (Collection $g, string $item) use ($items, $dates): array {
            $doses = $g->map(fn ($l) => $l->actual_dose ?? $l->claim_dose)->filter(fn ($d) => $d !== null);
            $units = $g->pluck('dose_unit')->unique()->values();

            return ['chemical' => isset($items[$item]) ? "{$items[$item]->name} ({$items[$item]->item_code})" : null, 'trials' => $g->pluck('trial_id')->unique()->count(),
                'total_qty' => round((float) $g->sum('sample_qty'), 3),
                'avg_dosage' => $doses->isEmpty() ? null : rtrim(rtrim(number_format($doses->avg(), 3, '.', ''), '0'), '.').($units->count() === 1 ? ' '.$units[0] : ''),
                'last_used' => $g->map(fn ($l) => $dates[$l->trial_id] ?? null)->filter()->max()?->toDateString()];
        })->sortBy('chemical')->values();

        return [['chemical', 'trials:int', 'total_qty:number', 'avg_dosage', 'last_used:date'], $rows];
    }

    /** Open trials and what each waits on: to be started, results on its lines, or completion. */
    private function trialsAwaiting(): array
    {
        [$trials, $lines] = $this->trials();
        $floors = ColourLab::floors();
        $users = ColourLab::userNames();
        $rows = $trials->whereIn('status', LabTrial::OPEN)->map(function (LabTrial $t) use ($lines, $floors, $users): array {
            $open = ($lines[$t->id] ?? collect())->where('result', 'PENDING')->count();

            return ['trial_no' => $t->trial_no, 'trial_date' => $t->trial_date->toDateString(), 'lab' => $floors[$t->lab_floor_id] ?? null, 'trial_by' => $users[$t->trial_by] ?? null,
                'status' => $t->status, 'waiting' => $t->status === 'PENDING' ? 'START' : ($open > 0 ? 'RESULTS' : 'COMPLETE'), 'open_lines' => $open];
        })->values();

        return [['trial_no', 'trial_date:date', 'lab', 'trial_by', 'status:trial_status', 'waiting:waiting'], $rows];
    }

    // ── Certificates ──────────────────────────────────────────────────────

    /** A certificate's standing today: inactive, expired, due for renewal (inside its notice), or valid. */
    private static function certStatus(LabCertificate $c, CarbonImmutable $today): string
    {
        return match (true) {
            ! $c->active => 'INACTIVE',
            $c->valid_date->lt($today) => 'EXPIRED',
            $today->greaterThanOrEqualTo($c->valid_date->copy()->subDays($c->notify_days)) => 'DUE',
            default => 'VALID',
        };
    }

    private function certificationRegister(): array
    {
        $today = CarbonImmutable::now('Asia/Dhaka')->startOfDay();
        $types = DB::table('lab_cert_types')->get(['id', 'name', 'issuing_body'])->keyBy('id');
        $users = ColourLab::userNames();
        $rows = LabCertificate::query()->orderBy('valid_date')->get()->map(fn (LabCertificate $c) => [
            'cert_type' => $types[$c->cert_type_id]->name ?? null, 'issuing_body' => $types[$c->cert_type_id]->issuing_body ?? null, 'document_ref' => $c->document_ref,
            'apply_date' => $c->apply_date->toDateString(), 'issue_date' => $c->issue_date->toDateString(), 'valid_date' => $c->valid_date->toDateString(),
            'cert_status' => self::certStatus($c, $today), 'applied_by' => $users[$c->applied_by] ?? null,
        ]);

        return [['cert_type', 'issuing_body', 'document_ref', 'apply_date:date', 'issue_date:date', 'valid_date:date', 'cert_status:cert_status', 'applied_by'], $rows];
    }

    /** Active certificates inside their notice period or already lapsed, soonest first, with the days left. */
    private function renewalDue(): array
    {
        $today = CarbonImmutable::now('Asia/Dhaka')->startOfDay();
        $types = ColourLab::certTypes();
        $rows = LabCertificate::query()->where('active', true)->orderBy('valid_date')->get()
            ->filter(fn (LabCertificate $c) => in_array(self::certStatus($c, $today), ['DUE', 'EXPIRED'], true))
            ->map(fn (LabCertificate $c) => ['document_ref' => $c->document_ref, 'cert_type' => $types[$c->cert_type_id] ?? null, 'valid_date' => $c->valid_date->toDateString(),
                'falls_due' => (int) $today->diffInDays($c->valid_date, false), 'notify_days' => $c->notify_days, 'mail_to' => $c->mail_to, 'cert_status' => self::certStatus($c, $today)])->values();

        return [['document_ref', 'cert_type', 'valid_date:date', 'falls_due:int', 'notify_days:int', 'mail_to', 'cert_status:cert_status'], $rows];
    }

    private function certificateMovement(): array
    {
        $certs = ColourLab::certificates();
        $rows = LabCertRegister::query()->orderByDesc('entry_date')->orderByDesc('register_no')->get()->map(fn (LabCertRegister $r) => [
            'register_no' => $r->register_no, 'certificate' => $certs[$r->certificate_id] ?? null, 'entry_date' => $r->entry_date->toDateString(), 'action' => $r->action,
            'issued_to' => $r->issued_to, 'copies' => $r->copies, 'due_date' => $r->due_date?->toDateString(), 'return_date' => $r->return_date?->toDateString(),
        ]);

        return [['register_no', 'certificate', 'entry_date:date', 'action:action', 'issued_to', 'copies:int', 'due_date:date', 'return_date:date'], $rows];
    }

    // ── Master data ───────────────────────────────────────────────────────

    /** The floors the lab works on — any floor a trial, test or certificate names — with its counts. */
    private function labMaster(): array
    {
        $company = ColourLab::company();
        $trials = LabTrial::query()->groupBy('lab_floor_id')->selectRaw('lab_floor_id, count(*) as n')->pluck('n', 'lab_floor_id');
        $tests = LabFabricTest::query()->whereNotNull('lab_floor_id')->groupBy('lab_floor_id')->selectRaw('lab_floor_id, count(*) as n')->pluck('n', 'lab_floor_id');
        $certs = LabCertificate::query()->groupBy('lab_floor_id')->selectRaw('lab_floor_id, count(*) as n')->pluck('n', 'lab_floor_id');
        $ids = $trials->keys()->merge($tests->keys())->merge($certs->keys())->unique();
        $companyName = DB::table('companies')->where('id', $company)->value('name');
        $rows = DB::table('floors')->leftJoin('buildings', 'buildings.id', '=', 'floors.building_id')->whereIn('floors.id', $ids)->orderBy('floors.name')
            ->get(['floors.id', 'floors.code', 'floors.name', 'floors.status', 'buildings.name as building'])
            ->map(fn ($f) => ['code' => $f->code, 'lab' => $f->name, 'company' => $companyName, 'location' => $f->building, 'in_charge' => null,
                'trials' => (int) ($trials[$f->id] ?? 0), 'tests' => (int) ($tests[$f->id] ?? 0), 'floor_status' => $f->status ?: 'ACTIVE']);

        return [['code', 'lab', 'company', 'location', 'in_charge', 'trials:int', 'tests:int', 'floor_status:floor_status'], $rows];
    }
}
