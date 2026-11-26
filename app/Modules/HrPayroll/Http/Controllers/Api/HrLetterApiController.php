<?php

declare(strict_types=1);

namespace App\Modules\HrPayroll\Http\Controllers\Api;

use App\Modules\HrPayroll\Support\LetterBook;
use App\Shared\Http\Api\ApiController;
use App\Shared\Http\Api\ApiResponse;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * HR & Payroll → Letter Management (AnnotechERP 24/09), as Brickland lays
 * the four screens out:
 *
 * - **Letter Master:** the letter types (add, switch on or off) and each
 *   type's template — title, subject, reference format, effective date,
 *   gazette, salutation, main body, clauses, salary tables, closing,
 *   signature, footer — with the employee fields to insert, the versions
 *   (open, activate, deactivate, delete an unused one) and a live preview.
 *   Saving a version no letter came from corrects it; otherwise it makes the
 *   next version, active.
 * - **Generate Letter:** one employee by number or card, or a filtered
 *   group ticked; letter, effective date and probation; preview, generate
 *   (stored on Letter History), print, PDF, e-mail.
 * - **Letter History / Letter Reports:** every letter issued, filtered;
 *   view, print, PDF; the register prints and exports.
 */
class HrLetterApiController extends ApiController
{
    private const TZ = 'Asia/Dhaka';

    private const MAX_RUN = 300;

    private const MAX_ROWS = 2000;

    /* ── Letter Master ─────────────────────────────────────────────── */

    public function master(): JsonResponse
    {
        $this->allowEither('hrpayroll.letter.view', 'hrpayroll.letter.template', 'hrpayroll.letter.generate');
        $book = $this->book();
        $book->ensure();
        $first = $book->people()->whereIn('e.status', ['ACTIVE', 'SUSPENDED'])->first();

        return ApiResponse::ok($this->masterData() + [
            'gazettes' => DB::table('hr_gazettes')->where('company_id', $this->caller()->companyId)->where('active', true)->orderByDesc('year')->orderBy('name')->get(['id', 'name'])->map(fn ($g) => ['value' => $g->id, 'label' => $g->name])->all(),
            'can_edit' => $this->caller()->can('hrpayroll.letter.template'), 'today' => $this->today(), 'letterhead' => $book->letterhead(),
            'sample' => $first ? $book->details(collect([$first]))[0] : null,
        ]);
    }

    public function addType(Request $request): JsonResponse
    {
        $this->allow('hrpayroll.letter.template');
        $d = $request->validate(['name' => ['required', 'string', 'max:120']], ['name.required' => __('hrpayroll.lt.v.type_name')]);
        $name = trim($d['name']);
        $code = Str::upper(Str::slug($name, '_')) ?: 'CUSTOM';
        $c = $this->caller()->companyId;
        if (DB::table('hr_letter_types')->where('company_id', $c)->where(fn ($q) => $q->where('code', $code)->orWhereRaw('lower(name) = ?', [Str::lower($name)]))->exists()) {
            throw ValidationException::withMessages(['name' => __('hrpayroll.lt.v.type_exists')]);
        }
        DB::table('hr_letter_types')->insert(['id' => LetterBook::ulid(), 'company_id' => $c, 'code' => $code, 'name' => $name, 'active' => true,
            'sort_order' => (int) DB::table('hr_letter_types')->where('company_id', $c)->max('sort_order') + 10, 'created_at' => now(), 'updated_at' => now()]);

        return ApiResponse::created(['message' => __('hrpayroll.lt.done.type_added', ['name' => $name]), 'code' => $code] + $this->masterData());
    }

    public function toggleType(string $code): JsonResponse
    {
        $this->allow('hrpayroll.letter.template');
        $t = $this->type($code);
        DB::table('hr_letter_types')->where('id', $t->id)->update(['active' => ! $t->active, 'updated_at' => now()]);

        return ApiResponse::ok(['message' => __($t->active ? 'hrpayroll.lt.done.type_off' : 'hrpayroll.lt.done.type_on', ['name' => $t->name])] + $this->masterData());
    }

    /** Save a template: a version no letter came from is corrected; otherwise the next version, active. */
    public function saveTemplate(Request $request): JsonResponse
    {
        $this->allow('hrpayroll.letter.template');
        $c = $this->caller()->companyId;
        $d = $request->validate([
            'type' => ['required', 'string'], 'version' => ['nullable', 'integer'], 'title' => ['required', 'string', 'max:300'], 'subject' => ['nullable', 'string', 'max:300'],
            'reference_format' => ['nullable', 'string', 'max:120'], 'effective_from' => ['nullable', 'date_format:Y-m-d'], 'gazette_id' => ['nullable', Rule::exists('hr_gazettes', 'id')->where('company_id', $c)],
            'salutation' => ['nullable', 'string', 'max:2000'], 'opening_message' => ['nullable', 'string', 'max:10000'], 'clauses' => ['nullable', 'array', 'max:60'], 'clauses.*' => ['nullable', 'string', 'max:5000'],
            'show_salary_tables' => ['boolean'], 'salary_tables_after' => ['nullable', 'integer', 'min:0', 'max:60'], 'closing_message' => ['nullable', 'string', 'max:5000'],
            'signature_title' => ['nullable', 'string', 'max:200'], 'signature_company' => ['nullable', 'string', 'max:200'], 'footer_note' => ['nullable', 'string', 'max:5000'],
        ], ['title.required' => __('hrpayroll.lt.v.title')]);
        $type = $this->type($d['type']);
        $values = [
            'title' => trim($d['title']), 'subject' => trim((string) ($d['subject'] ?? '')), 'reference_format' => trim((string) ($d['reference_format'] ?? '')),
            'effective_from' => $d['effective_from'] ?? $this->today(), 'gazette_id' => $d['gazette_id'] ?? null, 'salutation' => $d['salutation'] ?? null, 'opening_message' => $d['opening_message'] ?? null,
            'clauses' => json_encode(array_values(array_filter(array_map(fn ($x) => trim((string) $x), $d['clauses'] ?? []), fn ($x) => $x !== '')), JSON_UNESCAPED_UNICODE),
            'show_salary_tables' => (bool) ($d['show_salary_tables'] ?? false), 'salary_tables_after' => (int) ($d['salary_tables_after'] ?? 0), 'closing_message' => $d['closing_message'] ?? null,
            'signature_title' => $d['signature_title'] ?? null, 'signature_company' => $d['signature_company'] ?? null, 'footer_note' => $d['footer_note'] ?? null,
            'updated_by' => $this->caller()->auditUserId(), 'updated_at' => now(),
        ];
        $editing = isset($d['version']) ? DB::table('hr_letter_templates')->where('letter_type_id', $type->id)->where('version', $d['version'])->first() : null;
        if ($editing && ! DB::table('hr_letters')->where('template_id', $editing->id)->exists()) {
            DB::table('hr_letter_templates')->where('id', $editing->id)->update($values);
            $version = (int) $editing->version;
            $message = __('hrpayroll.lt.done.saved_in_place', ['name' => $type->name, 'v' => $version]);
        } else {
            $version = (int) DB::table('hr_letter_templates')->where('letter_type_id', $type->id)->max('version') + 1;
            DB::transaction(function () use ($type, $values, $version, $c): void {
                DB::table('hr_letter_templates')->where('letter_type_id', $type->id)->where('status', 'ACTIVE')->update(['status' => 'SUPERSEDED', 'updated_at' => now()]);
                DB::table('hr_letter_templates')->insert($values + ['id' => LetterBook::ulid(), 'company_id' => $c, 'letter_type_id' => $type->id, 'version' => $version, 'status' => 'ACTIVE', 'created_by' => $this->caller()->auditUserId(), 'created_at' => now()]);
            });
            $message = __('hrpayroll.lt.done.saved_new', ['name' => $type->name, 'v' => $version]);
        }

        return ApiResponse::ok(['message' => $message, 'version' => $version] + $this->masterData());
    }

    public function templateStatus(Request $request, string $code, int $version): JsonResponse
    {
        $this->allow('hrpayroll.letter.template');
        $d = $request->validate(['action' => ['required', Rule::in(['activate', 'deactivate'])]]);
        $type = $this->type($code);
        $t = $this->templateRow($type, $version);
        DB::transaction(function () use ($d, $type, $t): void {
            if ($d['action'] === 'activate') {
                DB::table('hr_letter_templates')->where('letter_type_id', $type->id)->where('status', 'ACTIVE')->update(['status' => 'SUPERSEDED', 'updated_at' => now()]);
                DB::table('hr_letter_templates')->where('id', $t->id)->update(['status' => 'ACTIVE', 'updated_at' => now()]);
            } else {
                DB::table('hr_letter_templates')->where('id', $t->id)->update(['status' => 'INACTIVE', 'updated_at' => now()]);
            }
        });

        return ApiResponse::ok(['message' => __("hrpayroll.lt.done.{$d['action']}d", ['name' => $type->name, 'v' => $version])] + $this->masterData());
    }

    public function deleteTemplate(string $code, int $version): JsonResponse
    {
        $this->allow('hrpayroll.letter.template');
        $type = $this->type($code);
        $t = $this->templateRow($type, $version);
        if (DB::table('hr_letters')->where('template_id', $t->id)->exists()) {
            throw ValidationException::withMessages(['version' => __('hrpayroll.lt.v.template_used')]);
        }
        DB::table('hr_letter_templates')->where('id', $t->id)->delete();

        return ApiResponse::ok(['message' => __('hrpayroll.lt.done.deleted', ['name' => $type->name, 'v' => $version])] + $this->masterData());
    }

    /* ── Generate Letter ───────────────────────────────────────────── */

    public function generateOptions(): JsonResponse
    {
        $this->allowEither('hrpayroll.letter.generate', 'hrpayroll.letter.view');
        $this->book()->ensure();
        $c = $this->caller()->companyId;
        $pick = fn (string $table, string $label = 'name') => DB::table($table)->where('company_id', $c)->when(in_array($table, ['departments', 'sections', 'floors', 'designations'], true), fn ($q) => $q->where('status', 'ACTIVE'))
            ->orderBy($label)->get(['id', $label])->map(fn ($x) => ['value' => $x->id, 'label' => $x->{$label}])->all();
        $types = DB::table('hr_letter_types')->where('company_id', $c)->where('active', true)->orderBy('sort_order')->get();
        $active = DB::table('hr_letter_templates as t')->leftJoin('hr_gazettes as g', 'g.id', '=', 't.gazette_id')->whereIn('t.letter_type_id', $types->pluck('id'))->where('t.status', 'ACTIVE')->get(['t.*', 'g.name as gazette'])->keyBy('letter_type_id');

        return ApiResponse::ok([
            'letter_types' => $types->map(fn ($t) => ['code' => $t->code, 'name' => $t->name, 'template' => isset($active[$t->id]) ? LetterBook::template($active[$t->id], $active[$t->id]->gazette) : null])->values()->all(),
            'sections' => $pick('sections'), 'floors' => $pick('floors'), 'departments' => $pick('departments'), 'designations' => $pick('designations'),
            'grades' => DB::table('grades')->where('company_id', $c)->orderBy('code')->get(['id', 'name', 'code'])->map(fn ($g) => ['value' => $g->id, 'label' => $g->name ?: $g->code])->all(),
            'employee_types' => $pick('hr_employment_types'),
            'can_generate' => $this->caller()->can('hrpayroll.letter.generate'), 'today' => $this->today(), 'letterhead' => $this->book()->letterhead(),
        ]);
    }

    /** One employee by number or card, with their letter details. */
    public function employee(Request $request): JsonResponse
    {
        $this->allowEither('hrpayroll.letter.generate', 'hrpayroll.letter.view');
        $q = trim((string) $request->query('q', ''));
        $e = $q === '' ? null : $this->book()->people()->where(fn ($w) => $w->whereRaw('lower(e.employee_code) = ?', [Str::lower($q)])->orWhereRaw('lower(e.card_no) = ?', [Str::lower($q)]))->first();

        return ApiResponse::ok($e ? $this->book()->details(collect([$e]), $this->gazetteOf((string) $request->query('type', '')))[0] : null);
    }

    /** A group of employees by section, floor, department, designation, grade, type, joining date and status. */
    public function employees(Request $request): JsonResponse
    {
        $this->allowEither('hrpayroll.letter.generate', 'hrpayroll.letter.view');
        $f = $request->validate([
            'type' => ['nullable', 'string'], 'section' => ['nullable', 'string'], 'floor' => ['nullable', 'string'], 'department' => ['nullable', 'string'], 'designation' => ['nullable', 'string'],
            'grade' => ['nullable', 'string'], 'employee_type' => ['nullable', 'string'], 'join_from' => ['nullable', 'date_format:Y-m-d'], 'join_to' => ['nullable', 'date_format:Y-m-d'], 'status' => ['nullable', Rule::in(['ACTIVE', 'ALL'])],
        ]);
        $q = $this->book()->people();
        foreach (['section' => 'section_id', 'floor' => 'floor_id', 'department' => 'department_id', 'designation' => 'designation_id', 'grade' => 'grade_id', 'employee_type' => 'employment_type_id'] as $k => $col) {
            if (! empty($f[$k])) {
                $q->where("e.{$col}", $f[$k]);
            }
        }
        $q->when(! empty($f['join_from']), fn ($w) => $w->where('e.joining_date', '>=', $f['join_from']))->when(! empty($f['join_to']), fn ($w) => $w->where('e.joining_date', '<=', $f['join_to']))
            ->when(($f['status'] ?? 'ACTIVE') === 'ACTIVE', fn ($w) => $w->whereIn('e.status', ['ACTIVE', 'SUSPENDED']));
        $rows = $q->limit(self::MAX_RUN + 1)->get();
        $truncated = $rows->count() > self::MAX_RUN;
        $list = $this->book()->details($rows->take(self::MAX_RUN), $this->gazetteOf((string) ($f['type'] ?? '')));

        return ApiResponse::ok(['employees' => $list, 'truncated' => $truncated, 'message' => __('hrpayroll.lt.done.loaded', ['n' => count($list)])]);
    }

    /** Generate a letter for each employee: stored on Letter History with the details it was merged with. */
    public function generate(Request $request): JsonResponse
    {
        $this->allow('hrpayroll.letter.generate');
        $d = $request->validate([
            'type' => ['required', 'string'], 'employees' => ['required', 'array', 'min:1', 'max:'.self::MAX_RUN], 'employees.*' => ['string'],
            'letter_date' => ['required', 'date_format:Y-m-d'], 'effective_date' => ['nullable', 'date_format:Y-m-d'], 'probation_months' => ['nullable', 'integer', 'min:0', 'max:24'],
        ], ['employees.required' => __('hrpayroll.lt.v.employees'), 'letter_date.required' => __('hrpayroll.lt.v.letter_date')]);
        $type = $this->type($d['type']);
        $t = DB::table('hr_letter_templates')->where('letter_type_id', $type->id)->where('status', 'ACTIVE')->first();
        if (! $type->active || ! $t) {
            throw ValidationException::withMessages(['type' => __('hrpayroll.lt.v.no_template', ['name' => $type->name])]);
        }
        $book = $this->book();
        $people = $book->people()->whereIn('e.employee_code', $d['employees'])->get();
        if ($people->count() !== count(array_unique($d['employees']))) {
            throw ValidationException::withMessages(['employees' => __('hrpayroll.lt.v.employees')]);
        }
        $details = collect($book->details($people, $t->gazette_id))->keyBy('id');
        $batch = count($d['employees']) > 1 ? 'B-'.str_replace('-', '', $d['letter_date']).'-'.str_pad((string) (DB::table('hr_letters')->where('company_id', $this->caller()->companyId)->whereNotNull('batch_no')->distinct()->count('batch_no') + 1), 3, '0', STR_PAD_LEFT) : null;
        $refs = [];
        DB::transaction(function () use ($people, $details, $book, $type, $t, $d, $batch, &$refs): void {
            foreach ($people->values() as $i => $e) {
                $ref = $book->nextRef($type, (string) $t->reference_format, $d['letter_date']);
                DB::table('hr_letters')->insert([
                    'id' => LetterBook::ulid(), 'company_id' => $this->caller()->companyId, 'ref_no' => $ref, 'batch_no' => $batch, 'letter_type_id' => $type->id, 'template_id' => $t->id,
                    'template_version' => $t->version, 'employee_id' => $e->id, 'letter_date' => $d['letter_date'], 'effective_date' => $d['effective_date'] ?? null, 'probation_months' => (int) ($d['probation_months'] ?? 0),
                    'employee' => json_encode($details[$e->employee_code], JSON_UNESCAPED_UNICODE), 'department' => $e->department, 'section' => $e->section, 'floor' => $e->floor,
                    'status' => 'GENERATED', 'generated_by' => $this->caller()->auditUserId(), 'created_at' => now(), 'updated_at' => now(),
                ]);
                $refs[] = $ref;
            }
        });

        return ApiResponse::created(['message' => count($refs) === 1 ? __('hrpayroll.lt.done.generated_one', ['ref' => $refs[0]]) : __('hrpayroll.lt.done.generated', ['n' => count($refs), 'batch' => $batch]), 'refs' => $refs, 'batch_no' => $batch, 'letters' => $this->letters($refs)]);
    }

    /** Letters to view or print: each with its template version and the details it was merged with. */
    public function printable(Request $request): JsonResponse
    {
        $this->allowEither('hrpayroll.letter.view', 'hrpayroll.letter.generate');
        $d = $request->validate(['refs' => ['required', 'array', 'min:1', 'max:'.self::MAX_RUN], 'refs.*' => ['string']]);

        return ApiResponse::ok(['letters' => $this->letters($d['refs']), 'letterhead' => $this->book()->letterhead()]);
    }

    public function printed(Request $request): JsonResponse
    {
        $this->allow('hrpayroll.letter.generate');
        $d = $request->validate(['refs' => ['required', 'array', 'min:1', 'max:'.self::MAX_RUN], 'refs.*' => ['string']]);
        DB::table('hr_letters')->where('company_id', $this->caller()->companyId)->whereIn('ref_no', $d['refs'])
            ->update(['status' => 'PRINTED', 'print_count' => DB::raw('print_count + 1'), 'printed_at' => now(), 'updated_at' => now()]);

        return ApiResponse::ok(['printed' => count($d['refs'])]);
    }

    /* ── Letter History / Reports ──────────────────────────────────── */

    public function history(): JsonResponse
    {
        $this->allowEither('hrpayroll.letter.view', 'hrpayroll.letter.generate');
        $c = $this->caller()->companyId;
        $rows = DB::table('hr_letters as l')->join('hr_employees as e', 'e.id', '=', 'l.employee_id')->join('hr_letter_types as t', 't.id', '=', 'l.letter_type_id')
            ->leftJoin('designations as d', 'd.id', '=', 'e.designation_id')->leftJoin('users as u', 'u.id', '=', 'l.generated_by')
            ->where('l.company_id', $c)->orderByDesc('l.created_at')->orderByDesc('l.ref_no')->limit(self::MAX_ROWS + 1)
            ->get(['l.*', 'e.employee_code', 'e.card_no', 'e.name', 'd.name as designation', 't.code as type_code', 't.name as type_name', 'u.name as generator']);
        $truncated = $rows->count() > self::MAX_ROWS;
        $rows = $rows->take(self::MAX_ROWS);
        $distinct = fn (string $k) => $rows->pluck($k)->filter()->unique()->sort()->values()->map(fn ($v) => ['value' => $v, 'label' => $v])->all();

        return ApiResponse::ok([
            'rows' => $rows->map(fn ($l) => [
                'ref' => $l->ref_no, 'batch_no' => $l->batch_no, 'type' => $l->type_code, 'letter_type' => $l->type_name, 'code' => $l->employee_code, 'card_no' => $l->card_no, 'name' => $l->name,
                'designation' => $l->designation, 'department' => $l->department, 'section' => $l->section, 'floor' => $l->floor, 'letter_date' => substr((string) $l->letter_date, 0, 10),
                'generated_at' => (string) $l->created_at, 'generated_by' => $l->generator, 'version' => (int) $l->template_version, 'status' => $l->status, 'print_count' => (int) $l->print_count,
            ])->values()->all(),
            'truncated' => $truncated,
            'letter_types' => DB::table('hr_letter_types')->where('company_id', $c)->orderBy('sort_order')->get(['code', 'name'])->map(fn ($t) => ['value' => $t->code, 'label' => $t->name])->all(),
            'departments' => $distinct('department'), 'sections' => $distinct('section'), 'floors' => $distinct('floor'), 'generated_by' => $distinct('generator'),
            'can_generate' => $this->caller()->can('hrpayroll.letter.generate'),
        ]);
    }

    /* ── Helpers ───────────────────────────────────────────────────── */

    private function book(): LetterBook
    {
        return new LetterBook($this->caller()->companyId);
    }

    private function today(): string
    {
        return CarbonImmutable::now(self::TZ)->toDateString();
    }

    private function type(string $code): object
    {
        $t = DB::table('hr_letter_types')->where('company_id', $this->caller()->companyId)->where('code', $code)->first();
        abort_unless($t, 404);

        return $t;
    }

    private function templateRow(object $type, int $version): object
    {
        $t = DB::table('hr_letter_templates')->where('letter_type_id', $type->id)->where('version', $version)->first();
        abort_unless($t, 404);

        return $t;
    }

    /** The gazette an active template of a type quotes, if any. */
    private function gazetteOf(string $code): ?string
    {
        if ($code === '') {
            return null;
        }

        return DB::table('hr_letter_templates as t')->join('hr_letter_types as y', 'y.id', '=', 't.letter_type_id')->where('y.company_id', $this->caller()->companyId)->where('y.code', $code)->where('t.status', 'ACTIVE')->value('t.gazette_id');
    }

    /** Types and every template version, for Letter Master. @return array<string, mixed> */
    private function masterData(): array
    {
        $c = $this->caller()->companyId;
        $types = DB::table('hr_letter_types')->where('company_id', $c)->orderBy('sort_order')->get();
        $templates = DB::table('hr_letter_templates as t')->leftJoin('hr_gazettes as g', 'g.id', '=', 't.gazette_id')->leftJoin('users as u', 'u.id', '=', 't.updated_by')->leftJoin('users as cb', 'cb.id', '=', 't.created_by')
            ->where('t.company_id', $c)->orderByDesc('t.version')->get(['t.*', 'g.name as gazette', 'u.name as updater', 'cb.name as creator']);
        $counts = DB::table('hr_letters')->where('company_id', $c)->groupBy('template_id')->selectRaw('template_id, count(*) as n')->pluck('n', 'template_id');

        return [
            'types' => $types->map(fn ($t) => ['code' => $t->code, 'name' => $t->name, 'active' => (bool) $t->active,
                'version' => $templates->first(fn ($x) => $x->letter_type_id === $t->id && $x->status === 'ACTIVE')?->version])->values()->all(),
            'templates' => $templates->map(fn ($t) => ['type' => $types->firstWhere('id', $t->letter_type_id)->code] + LetterBook::template($t, $t->gazette, (int) ($counts[$t->id] ?? 0), $t->updater ?? $t->creator ?? __('hrpayroll.lt.system')))->values()->all(),
        ];
    }

    /** @param list<string> $refs @return list<array<string, mixed>> */
    private function letters(array $refs): array
    {
        $rows = DB::table('hr_letters as l')->join('hr_letter_templates as t', 't.id', '=', 'l.template_id')->join('hr_letter_types as y', 'y.id', '=', 'l.letter_type_id')->leftJoin('hr_gazettes as g', 'g.id', '=', 't.gazette_id')
            ->where('l.company_id', $this->caller()->companyId)->whereIn('l.ref_no', $refs)->get(['l.*', 't.title', 't.subject', 't.reference_format', 't.salutation', 't.opening_message', 't.clauses', 't.closing_message',
                't.signature_title', 't.signature_company', 't.footer_note', 't.show_salary_tables', 't.salary_tables_after', 't.gazette_id', 't.effective_from', 't.status as t_status', 't.version', 't.letter_type_id', 't.updated_at as t_updated', 'g.name as gazette', 'y.name as type_name'])
            ->keyBy('ref_no');

        return collect($refs)->filter(fn ($r) => isset($rows[$r]))->map(function ($r) use ($rows) {
            $l = $rows[$r];
            $tpl = (object) ['letter_type_id' => $l->letter_type_id, 'version' => $l->version, 'title' => $l->title, 'subject' => $l->subject, 'reference_format' => $l->reference_format, 'salutation' => $l->salutation,
                'opening_message' => $l->opening_message, 'clauses' => $l->clauses, 'closing_message' => $l->closing_message, 'signature_title' => $l->signature_title, 'signature_company' => $l->signature_company,
                'footer_note' => $l->footer_note, 'show_salary_tables' => $l->show_salary_tables, 'salary_tables_after' => $l->salary_tables_after, 'gazette_id' => $l->gazette_id, 'effective_from' => $l->effective_from,
                'status' => $l->t_status, 'updated_at' => $l->t_updated];

            return [
                'ref' => $l->ref_no, 'letter_type' => $l->type_name, 'letter_date' => substr((string) $l->letter_date, 0, 10), 'effective_date' => $l->effective_date ? substr((string) $l->effective_date, 0, 10) : null,
                'probation_months' => (int) $l->probation_months, 'template' => LetterBook::template($tpl, $l->gazette), 'employee' => json_decode((string) $l->employee, true),
            ];
        })->values()->all();
    }
}
