<?php

declare(strict_types=1);

namespace App\Modules\HrPayroll\Http\Controllers\Api;

use App\Modules\HrPayroll\Models\HrEmployee;
use App\Modules\Tenancy\Models\Section;
use App\Shared\Http\Api\ApiResponse;
use App\Shared\Tenancy\TenantContext;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * HR & Payroll → Employee → ID Card (AnnotechERP 24/03), as Brickland keeps
 * it: a print queue. Search narrows the queue by employee status, section,
 * joining date and part of a code; Add queues every employee those boxes
 * pick, with the Print Option (Bangla, English, Mtex English) and Single
 * employee in a page?; Add to print queue queues one. An employee is queued
 * once — queuing them again changes the option. Print Queue and a row's
 * Print read the card from the employee profile at that moment: photo,
 * section, designation.
 */
class HrIdCardApiController extends HrEmployeeApiController
{
    public const FONTS = ['BANGLA', 'ENGLISH', 'MTEX_ENGLISH'];

    public function cardOptions(): JsonResponse
    {
        $this->allow('hrpayroll.employee.view');

        return ApiResponse::ok([
            'employees' => HrEmployee::query()->orderBy('employee_code')->get(['employee_code', 'name', 'status'])->map(fn ($e) => ['value' => $e->employee_code, 'label' => "{$e->name} ({$e->employee_code})", 'status' => $e->status])->all(),
            'sections' => Section::query()->where('status', 'ACTIVE')->orderBy('name')->get(['id', 'name'])->map(fn ($s) => ['value' => $s->id, 'label' => $s->name])->all(),
            'statuses' => HrEmployee::STATUSES, 'fonts' => self::FONTS,
        ]);
    }

    /** The queue, narrowed by the Search boxes. */
    public function queue(Request $request): JsonResponse
    {
        $this->allow('hrpayroll.employee.view');
        $f = $this->search($request);
        $rows = $this->picked($f)->join('hr_id_card_queue as q', 'q.employee_id', '=', 'hr_employees.id')
            ->orderByDesc('q.created_at')->orderBy('hr_employees.employee_code')->get(['hr_employees.*', 'q.id as queue_id', 'q.font', 'q.single_per_page', 'q.created_at as queued_at']);
        $names = $this->names($rows);

        return ApiResponse::ok($rows->map(fn (HrEmployee $e) => [
            'id' => $e->queue_id, 'code' => $e->employee_code, 'name' => $e->name, 'photo' => (bool) $e->photo_file_id, 'section' => $names['section'][$e->section_id] ?? null,
            'designation' => $names['designation'][$e->designation_id] ?? null, 'font' => $e->font, 'single' => (bool) $e->single_per_page,
            'queued_at' => CarbonImmutable::parse($e->queued_at)->timezone('Asia/Dhaka')->format('Y-m-d H:i'),
        ])->values()->all());
    }

    /** Add to print queue: one employee. */
    public function addOne(Request $request): JsonResponse
    {
        $this->allow('hrpayroll.employee.manage');
        $d = $request->validate(['employee' => ['required', 'string'], 'font' => ['required', Rule::in(self::FONTS)], 'single_per_page' => ['nullable', 'boolean']], [
            'employee.required' => __('hrpayroll.emp.idc.v.employee'), 'font.required' => __('hrpayroll.emp.idc.v.font'),
        ]);
        $e = HrEmployee::query()->where('employee_code', $d['employee'])->first() ?? throw ValidationException::withMessages(['employee' => __('hrpayroll.emp.idc.v.employee')]);
        $this->enqueue(new Collection([$e]), $d['font'], (bool) ($d['single_per_page'] ?? false));

        return ApiResponse::ok(['queued' => 1]);
    }

    /** Add: every employee the Search boxes pick, with the Print Option chosen there. */
    public function addPicked(Request $request): JsonResponse
    {
        $this->allow('hrpayroll.employee.manage');
        $f = $this->search($request);
        $d = $request->validate(['font' => ['required', Rule::in(self::FONTS)], 'single_per_page' => ['nullable', 'boolean']]);
        $people = $this->picked($f)->get();
        if ($people->isEmpty()) {
            throw ValidationException::withMessages(['employee' => __('hrpayroll.emp.idc.v.none')]);
        }
        $this->enqueue($people, $d['font'], (bool) ($d['single_per_page'] ?? false));

        return ApiResponse::ok(['queued' => $people->count()]);
    }

    public function updateCard(Request $request, string $id): JsonResponse
    {
        $this->allow('hrpayroll.employee.manage');
        $d = $request->validate(['font' => ['required', Rule::in(self::FONTS)], 'single_per_page' => ['nullable', 'boolean']]);
        $n = DB::table('hr_id_card_queue')->where('company_id', app(TenantContext::class)->companyId())->where('id', $id)->update(['font' => $d['font'], 'single_per_page' => (bool) ($d['single_per_page'] ?? false), 'updated_at' => now()]);
        abort_if($n === 0, 404);

        return ApiResponse::ok(['updated' => true]);
    }

    public function removeCard(string $id): JsonResponse
    {
        $this->allow('hrpayroll.employee.manage');
        $n = DB::table('hr_id_card_queue')->where('company_id', app(TenantContext::class)->companyId())->where('id', $id)->delete();
        abort_if($n === 0, 404);

        return ApiResponse::ok(['deleted' => true]);
    }

    /** Clear Queue: every card waiting to be printed. */
    public function clear(): JsonResponse
    {
        $this->allow('hrpayroll.employee.manage');

        return ApiResponse::ok(['deleted' => DB::table('hr_id_card_queue')->where('company_id', app(TenantContext::class)->companyId())->delete()]);
    }

    /** The cards as they print now — the whole queue, or the ones asked for — read from the employee profiles. */
    public function cards(Request $request): JsonResponse
    {
        $this->allow('hrpayroll.employee.view');
        $ids = array_filter(explode(',', (string) $request->query('ids', '')));
        $company = app(TenantContext::class)->companyId();
        $rows = HrEmployee::query()->join('hr_id_card_queue as q', 'q.employee_id', '=', 'hr_employees.id')
            ->when($ids, fn (Builder $b) => $b->whereIn('q.id', $ids))->orderBy('q.created_at')->orderBy('hr_employees.employee_code')
            ->get(['hr_employees.*', 'q.id as queue_id', 'q.font', 'q.single_per_page']);
        $names = $this->names($rows);
        $org = DB::table('companies')->where('id', $company)->first(['name', 'address']);

        return ApiResponse::ok(['company' => $org?->name, 'address' => $org?->address, 'cards' => $rows->map(fn (HrEmployee $e) => [
            'id' => $e->queue_id, 'code' => $e->employee_code, 'card_no' => $e->card_no, 'name' => $e->name, 'name_bn' => $e->name_bn, 'font' => $e->font, 'single' => (bool) $e->single_per_page,
            'designation' => $names['designation'][$e->designation_id] ?? null, 'section' => $names['section'][$e->section_id] ?? null, 'department' => $names['department'][$e->department_id] ?? null,
            'joining_date' => $e->joining_date?->toDateString(), 'blood_group' => $e->blood_group, 'photo_file_id' => $e->photo_file_id, 'emergency_mobile' => $e->emergency_mobile,
        ])->values()->all()]);
    }

    /** @return array<string, mixed> */
    private function search(Request $request): array
    {
        return $request->validate(['status' => ['nullable', Rule::in(HrEmployee::STATUSES)], 'section' => ['nullable', 'string'], 'joining_date' => ['nullable', 'date'], 'code' => ['nullable', 'string', 'max:40']]);
    }

    /** @param array<string, mixed> $f */
    private function picked(array $f): Builder
    {
        $code = trim((string) ($f['code'] ?? ''));

        return HrEmployee::query()
            ->when($f['status'] ?? null, fn (Builder $b, string $v) => $b->where('hr_employees.status', $v))
            ->when($f['section'] ?? null, fn (Builder $b, string $v) => $b->where('hr_employees.section_id', $v))
            ->when($f['joining_date'] ?? null, fn (Builder $b, string $v) => $b->whereDate('hr_employees.joining_date', $v))
            ->when($code !== '', fn (Builder $b) => $b->where('hr_employees.employee_code', 'ilike', "%{$code}%"));
    }

    /** Queue each employee once: queuing again changes the option and keeps the place. */
    private function enqueue(\Illuminate\Support\Collection $people, string $font, bool $single): void
    {
        $company = app(TenantContext::class)->companyId();
        $now = now();
        DB::transaction(function () use ($people, $font, $single, $company, $now): void {
            foreach ($people as $e) {
                $done = DB::table('hr_id_card_queue')->where('company_id', $company)->where('employee_id', $e->id)->update(['font' => $font, 'single_per_page' => $single, 'updated_at' => $now]);
                if (! $done) {
                    DB::table('hr_id_card_queue')->insert(['id' => strtolower((string) Str::ulid()), 'company_id' => $company, 'employee_id' => $e->id, 'font' => $font, 'single_per_page' => $single, 'created_by' => $this->caller()->auditUserId(), 'created_at' => $now, 'updated_at' => $now]);
                }
            }
        });
    }
}
