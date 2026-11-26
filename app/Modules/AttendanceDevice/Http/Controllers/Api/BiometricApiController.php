<?php

declare(strict_types=1);

namespace App\Modules\AttendanceDevice\Http\Controllers\Api;

use App\Modules\AttendanceDevice\Support\Terminals;
use App\Shared\Http\Api\ApiController;
use App\Shared\Http\Api\ApiResponse;
use Carbon\CarbonImmutable;
use Illuminate\Database\Query\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * HR & Payroll → Employee → Biometric / Fingerprint (AnnotechERP 24/03), as
 * Brickland lays it out: employees, registered, pending and not registered,
 * devices online and the last sync; the terminals (Device Setup); and two
 * tabs —
 *
 * - Fingerprint Registration: employees with their device mapping and
 *   biometric status. Queue enrolment maps the ticked employees on a terminal
 *   (PIN = card no.) and sends them as users; they are Pending until the
 *   terminal reports their fingers, then Registered. Send to all machines
 *   sends them — and the finger templates kept in the ERP — to every push
 *   terminal; Collect from machines asks every terminal for its users and
 *   fingers; Delete from all machines removes them everywhere.
 * - Employee Mapping: users the terminals hold that no employee is linked
 *   to, with the employee whose card matches; Auto-map by card maps them all.
 *
 * Commands go to push terminals only; a pull terminal is read by the ERP.
 */
class BiometricApiController extends ApiController
{
    private const PER_PAGE = 25;

    public function overview(): JsonResponse
    {
        $this->allowEither('hrpayroll.biometric.view', 'hrpayroll.biometric.manage');
        $c = $this->caller()->companyId;
        $devices = DB::table('dev_devices')->where('company_id', $c)->orderBy('code')->get();
        $users = DB::table('dev_users')->where('company_id', $c)->selectRaw('device_id, count(*) as n, count(employee_id) as matched')->groupBy('device_id')->get()->keyBy('device_id');
        $fingers = DB::table('dev_fingerprints')->where('company_id', $c)->selectRaw('device_id, count(*) as n')->groupBy('device_id')->pluck('n', 'device_id');
        $counts = $this->counts($this->roster());
        $last = $devices->max('last_contact_at');

        return ApiResponse::ok([
            'counts' => $counts,
            'devices_online' => $devices->filter(fn ($d) => $d->state === 'ACTIVE' && Terminals::online($d))->count(),
            'last_sync' => $last ? CarbonImmutable::parse($last)->toIso8601String() : null,
            'devices' => $devices->map(fn ($d) => [
                'code' => $d->code, 'name' => $d->name, 'model' => $d->model, 'serial_no' => $d->serial_no, 'protocol' => $d->protocol, 'location' => $d->location, 'state' => $d->state,
                'online' => $d->state === 'ACTIVE' && Terminals::online($d), 'last_seen' => $d->last_contact_at ? CarbonImmutable::parse($d->last_contact_at)->toIso8601String() : null,
                'users' => $d->user_count ?? (int) ($users[$d->id]->n ?? 0), 'fingers' => $d->fp_count ?? (int) ($fingers[$d->id] ?? 0),
                'mapped' => (int) ($users[$d->id]->matched ?? 0), 'unmapped' => (int) ($users[$d->id]->n ?? 0) - (int) ($users[$d->id]->matched ?? 0),
            ])->values()->all(),
            'enrol_devices' => $devices->where('protocol', 'ADMS_PUSH')->where('state', 'ACTIVE')->map(fn ($d) => ['value' => $d->code, 'label' => "{$d->name} ({$d->code})"])->values()->all(),
            'departments' => DB::table('departments')->where('company_id', $c)->where('status', 'ACTIVE')->orderBy('name')->get(['id', 'name'])->map(fn ($x) => ['value' => $x->id, 'label' => $x->name])->all(),
        ]);
    }

    /** Fingerprint Registration's list: searched, by biometric status and department, a page at a time. */
    public function employees(Request $request): JsonResponse
    {
        $this->allowEither('hrpayroll.biometric.view', 'hrpayroll.biometric.manage');
        $f = $request->validate(['q' => ['nullable', 'string', 'max:100'], 'status' => ['nullable', Rule::in(['REGISTERED', 'PENDING', 'NOT_REGISTERED'])], 'department' => ['nullable', 'string'], 'page' => ['nullable', 'integer', 'min:1']]);
        $q = trim((string) ($f['q'] ?? ''));
        $base = $this->roster()
            ->when($q !== '', fn (Builder $b) => $b->where(fn (Builder $w) => $w->where('e.employee_code', 'ilike', "%{$q}%")->orWhere('e.name', 'ilike', "%{$q}%")->orWhere('e.card_no', 'ilike', "%{$q}%")))
            ->when($f['department'] ?? null, fn (Builder $b, string $v) => $b->where('e.department_id', $v));
        $counts = $this->counts(clone $base);
        $rows = DB::query()->fromSub($this->withStatus($base), 'x')->when($f['status'] ?? null, fn (Builder $b, string $v) => $b->where('bio_status', $v));
        $total = (clone $rows)->count();
        $page = (int) ($f['page'] ?? 1);
        $list = $rows->orderBy('employee_code')->forPage($page, self::PER_PAGE)->get();
        $maps = DB::table('dev_employee_maps as m')->join('dev_devices as d', 'd.id', '=', 'm.device_id')->whereIn('m.employee_id', $list->pluck('id'))->orderBy('d.code')->get(['m.employee_id', 'm.pin', 'm.status', 'd.code', 'd.name'])->groupBy('employee_id');

        return ApiResponse::ok(['counts' => $counts, 'total' => $total, 'page' => $page, 'per_page' => self::PER_PAGE, 'rows' => $list->map(fn ($e) => [
            'code' => $e->employee_code, 'name' => $e->name, 'card_no' => $e->card_no, 'department' => $e->department, 'line' => $e->line, 'status' => $e->bio_status,
            'fingers' => (int) $e->fingers, 'registered' => $e->registered ? CarbonImmutable::parse($e->registered)->timezone('Asia/Dhaka')->format('Y-m-d H:i') : null,
            'maps' => ($maps[$e->id] ?? collect())->map(fn ($m) => ['device' => $m->code, 'name' => $m->name, 'pin' => $m->pin, 'status' => $m->status])->values()->all(),
        ])->all()]);
    }

    /** Queue enrolment: map the ticked employees on a terminal (PIN = card no.) and send them as users. */
    public function enrol(Request $request): JsonResponse
    {
        $this->allow('hrpayroll.biometric.manage');
        $d = $request->validate(['employees' => ['required', 'array', 'min:1', 'max:500'], 'employees.*' => ['string'], 'device' => ['required', 'string']], [
            'employees.required' => __('hrpayroll.emp.bio.v.pick'), 'device.required' => __('hrpayroll.emp.bio.v.device'),
        ]);
        $device = $this->pushDevice($d['device']);
        $people = $this->people($d['employees']);
        DB::transaction(function () use ($people, $device): void {
            foreach ($people as $e) {
                $pin = $this->pinFor($e);
                $this->map($device, $e, $pin);
                Terminals::queue($device, 'USER', Terminals::userCommand($pin, $e->name, $e->card_no), __('hrpayroll.emp.bio.cmd.user', ['code' => $e->employee_code, 'pin' => $pin]), $this->caller()->auditUserId());
            }
        });

        return ApiResponse::ok(['message' => __('hrpayroll.emp.bio.done.enrol', ['n' => $people->count(), 'device' => $device->code])]);
    }

    /** Send to all machines: the ticked employees — or everyone with fingers on file — and their templates, to every push terminal. */
    public function send(Request $request): JsonResponse
    {
        $this->allow('hrpayroll.biometric.manage');
        $d = $request->validate(['employees' => ['nullable', 'array', 'max:5000'], 'employees.*' => ['string'], 'everyone' => ['nullable', 'boolean']]);
        $c = $this->caller()->companyId;
        $people = ! empty($d['everyone'])
            ? DB::table('hr_employees')->where('company_id', $c)->where('status', 'ACTIVE')->whereIn('id', DB::table('dev_fingerprints')->where('company_id', $c)->whereNotNull('employee_id')->select('employee_id'))->get()
            : $this->people($d['employees'] ?? []);
        if ($people->isEmpty()) {
            throw ValidationException::withMessages(['employees' => __('hrpayroll.emp.bio.v.pick')]);
        }
        $devices = $this->pushDevices();
        $commands = 0;
        DB::transaction(function () use ($people, $devices, &$commands): void {
            foreach ($people as $e) {
                // One template per finger, the latest reported.
                $fingers = DB::table('dev_fingerprints')->where('employee_id', $e->id)->orderByDesc('reported_at')->get()->unique('finger');
                foreach ($devices as $device) {
                    $pin = DB::table('dev_employee_maps')->where('device_id', $device->id)->where('employee_id', $e->id)->value('pin') ?? $this->pinFor($e);
                    $this->map($device, $e, $pin, $fingers->isNotEmpty());
                    Terminals::queue($device, 'USER', Terminals::userCommand($pin, $e->name, $e->card_no), __('hrpayroll.emp.bio.cmd.user', ['code' => $e->employee_code, 'pin' => $pin]), $this->caller()->auditUserId());
                    $commands++;
                    foreach ($fingers as $fp) {
                        Terminals::queue($device, 'FINGER', Terminals::fingerCommand($fp, $pin), __('hrpayroll.emp.bio.cmd.finger', ['code' => $e->employee_code, 'finger' => $fp->finger]), $this->caller()->auditUserId());
                        $commands++;
                    }
                }
            }
        });

        return ApiResponse::ok(['message' => __('hrpayroll.emp.bio.done.send', ['n' => $people->count(), 'devices' => $devices->count(), 'commands' => $commands])]);
    }

    /** Collect from machines: every push terminal sends its users and fingers again. */
    public function collect(): JsonResponse
    {
        $this->allow('hrpayroll.biometric.manage');
        $devices = $this->pushDevices();
        foreach ($devices as $device) {
            Terminals::queue($device, 'QUERY_USERS', 'DATA QUERY USERINFO', __('settings.dev.cmd.query_users'), $this->caller()->auditUserId());
            Terminals::queue($device, 'QUERY_USERS', 'DATA QUERY FINGERTMP', __('settings.dev.cmd.query_users'), $this->caller()->auditUserId());
        }

        return ApiResponse::ok(['message' => __('hrpayroll.emp.bio.done.collect', ['devices' => $devices->count()])]);
    }

    /** Delete from all machines: the user and every finger off every push terminal, and the templates kept here. */
    public function delete(Request $request): JsonResponse
    {
        $this->allow('hrpayroll.biometric.manage');
        $d = $request->validate(['employees' => ['required', 'array', 'min:1', 'max:500'], 'employees.*' => ['string']], ['employees.required' => __('hrpayroll.emp.bio.v.pick')]);
        $people = $this->people($d['employees']);
        $devices = $this->pushDevices();
        DB::transaction(function () use ($people, $devices): void {
            foreach ($people as $e) {
                foreach ($devices as $device) {
                    $pins = DB::table('dev_employee_maps')->where('device_id', $device->id)->where('employee_id', $e->id)->pluck('pin')
                        ->merge(DB::table('dev_users')->where('device_id', $device->id)->where('employee_id', $e->id)->pluck('pin'))->push($this->pinFor($e))->unique();
                    foreach ($pins as $pin) {
                        Terminals::queue($device, 'DELETE', Terminals::deleteUserCommand($pin), __('hrpayroll.emp.bio.cmd.delete', ['code' => $e->employee_code, 'pin' => $pin]), $this->caller()->auditUserId());
                    }
                }
                DB::table('dev_employee_maps')->where('employee_id', $e->id)->delete();
                DB::table('dev_fingerprints')->where('employee_id', $e->id)->delete();
                DB::table('dev_users')->where('employee_id', $e->id)->whereIn('device_id', $devices->pluck('id'))->delete();
            }
        });

        return ApiResponse::ok(['message' => __('hrpayroll.emp.bio.done.delete', ['n' => $people->count(), 'devices' => $devices->count()])]);
    }

    /** Map one employee to a terminal under a PIN (defaults to the card number); what the terminal already holds under it follows. */
    public function mapOne(Request $request): JsonResponse
    {
        $this->allow('hrpayroll.biometric.manage');
        $d = $request->validate(['employee' => ['required', 'string'], 'device' => ['required', 'string'], 'pin' => ['required', 'string', 'max:24', 'regex:/^[0-9A-Za-z]+$/']], [
            'device.required' => __('hrpayroll.emp.bio.v.device'), 'pin.required' => __('hrpayroll.emp.bio.v.pin'), 'pin.regex' => __('hrpayroll.emp.bio.v.pin'),
        ]);
        $device = $this->device($d['device']);
        $e = $this->people([$d['employee']])->first();
        $this->map($device, $e, $d['pin']);

        return ApiResponse::ok(['message' => __('hrpayroll.emp.bio.done.mapped', ['code' => $e->employee_code, 'device' => $device->code, 'pin' => $d['pin']])]);
    }

    public function unmap(Request $request): JsonResponse
    {
        $this->allow('hrpayroll.biometric.manage');
        $d = $request->validate(['employee' => ['required', 'string'], 'device' => ['required', 'string']]);
        $device = $this->device($d['device']);
        $e = $this->people([$d['employee']])->first();
        $pins = DB::table('dev_employee_maps')->where('device_id', $device->id)->where('employee_id', $e->id)->pluck('pin');
        DB::table('dev_employee_maps')->where('device_id', $device->id)->where('employee_id', $e->id)->delete();
        // What the terminal holds under the PIN is no longer theirs — unless the device's own rule still matches it.
        foreach ($pins as $pin) {
            $again = Terminals::match($device, $pin);
            DB::table('dev_users')->where('device_id', $device->id)->where('pin', $pin)->update(['employee_id' => $again]);
            DB::table('dev_fingerprints')->where('device_id', $device->id)->where('pin', $pin)->update(['employee_id' => $again]);
        }

        return ApiResponse::ok(['message' => __('hrpayroll.emp.bio.done.unmapped', ['code' => $e->employee_code, 'device' => $device->code])]);
    }

    /** Employee Mapping: users the terminals hold that no employee is linked to, with the employee their card or PIN points at. */
    public function unmapped(): JsonResponse
    {
        $this->allowEither('hrpayroll.biometric.view', 'hrpayroll.biometric.manage');
        $rows = $this->unmatched();

        return ApiResponse::ok(['rows' => $rows->values()->all(), 'suggested' => $rows->whereNotNull('suggested_code')->count()]);
    }

    /** Auto-map by card: every unmatched user whose card (or PIN) is an employee's card number. */
    public function automap(): JsonResponse
    {
        $this->allow('hrpayroll.biometric.manage');
        $n = 0;
        foreach ($this->unmatched()->whereNotNull('suggested_code') as $u) {
            $device = $this->device($u['device']);
            $e = $this->people([$u['suggested_code']])->first();
            if (! DB::table('dev_employee_maps')->where('device_id', $device->id)->where('employee_id', $e->id)->exists()) {
                $this->map($device, $e, $u['pin'], DB::table('dev_fingerprints')->where('device_id', $device->id)->where('pin', $u['pin'])->exists());
                $n++;
            }
        }

        return ApiResponse::ok(['message' => __('hrpayroll.emp.bio.done.automap', ['n' => $n])]);
    }

    /* ── Helpers ───────────────────────────────────────────────────── */

    /** The company's employees with department and line. */
    private function roster(): Builder
    {
        return DB::table('hr_employees as e')->leftJoin('departments as dp', 'dp.id', '=', 'e.department_id')->leftJoin('production_lines as ln', 'ln.id', '=', 'e.line_id')
            ->where('e.company_id', $this->caller()->companyId)->where('e.status', '!=', 'SEPARATED');
    }

    /** Each employee's biometric status: Registered (fingers on file), Pending (mapped, no fingers yet), Not Registered. */
    private function withStatus(Builder $q): Builder
    {
        $fingers = '(select count(*) from dev_fingerprints f where f.employee_id = e.id)';
        $pending = 'exists (select 1 from dev_employee_maps m where m.employee_id = e.id)';

        return $q->selectRaw("e.id, e.employee_code, e.name, e.card_no, dp.name as department, ln.name as line, {$fingers} as fingers,
            (select min(f.reported_at) from dev_fingerprints f where f.employee_id = e.id) as registered,
            case when {$fingers} > 0 or exists (select 1 from dev_employee_maps m where m.employee_id = e.id and m.status = 'REGISTERED') then 'REGISTERED' when {$pending} then 'PENDING' else 'NOT_REGISTERED' end as bio_status");
    }

    /** @return array{employees: int, registered: int, pending: int, not_registered: int} */
    private function counts(Builder $q): array
    {
        $by = DB::query()->fromSub($this->withStatus($q), 'x')->selectRaw('bio_status, count(*) as n')->groupBy('bio_status')->pluck('n', 'bio_status');

        return ['employees' => (int) $by->sum(), 'registered' => (int) ($by['REGISTERED'] ?? 0), 'pending' => (int) ($by['PENDING'] ?? 0), 'not_registered' => (int) ($by['NOT_REGISTERED'] ?? 0)];
    }

    /** @return Collection<int, array<string, mixed>> */
    private function unmatched(): Collection
    {
        $c = $this->caller()->companyId;
        $byCard = DB::table('hr_employees')->where('company_id', $c)->pluck('employee_code', 'card_no');
        $names = DB::table('hr_employees')->where('company_id', $c)->pluck('name', 'employee_code');
        // An employee already mapped on that terminal is not suggested again — one PIN per employee per terminal.
        $mapped = DB::table('dev_employee_maps as m')->join('hr_employees as e', 'e.id', '=', 'm.employee_id')->where('m.company_id', $c)->get(['m.device_id', 'e.employee_code'])
            ->map(fn ($m) => "{$m->device_id}|{$m->employee_code}")->flip();

        return DB::table('dev_users as u')->join('dev_devices as d', 'd.id', '=', 'u.device_id')->where('u.company_id', $c)->whereNull('u.employee_id')->orderBy('d.code')->orderBy('u.pin')->limit(2000)
            ->get(['u.pin', 'u.name', 'u.card_no', 'u.device_id', 'd.code as device', 'd.name as device_name'])
            ->map(function ($u) use ($byCard, $names, $mapped) {
                $code = ($u->card_no ? ($byCard[$u->card_no] ?? null) : null) ?? ($byCard[$u->pin] ?? null);
                $code = $code !== null && isset($mapped["{$u->device_id}|{$code}"]) ? null : $code;

                return ['device' => $u->device, 'device_name' => $u->device_name, 'pin' => $u->pin, 'name' => $u->name, 'card_no' => $u->card_no, 'suggested_code' => $code, 'suggested' => $code ? "{$names[$code]} ({$code})" : null];
            });
    }

    /** The PIN an employee goes onto a terminal under: the card number, else the employee code's digits. */
    private function pinFor(object $e): string
    {
        $card = preg_replace('/\D/', '', (string) $e->card_no);

        return mb_substr($card !== '' ? $card : (preg_replace('/\D/', '', (string) $e->employee_code) ?: (string) $e->employee_code), 0, 24);
    }

    /** Map (or re-map) an employee on a terminal; the users, fingers and punches already under the PIN become theirs. */
    private function map(object $device, object $e, string $pin, bool $registered = false): void
    {
        $taken = DB::table('dev_employee_maps')->where('device_id', $device->id)->where('pin', $pin)->where('employee_id', '!=', $e->id)->value('employee_id');
        if ($taken) {
            $who = DB::table('hr_employees')->where('id', $taken)->value('employee_code');
            throw ValidationException::withMessages(['pin' => __('hrpayroll.emp.bio.v.pin_taken', ['pin' => $pin, 'device' => $device->code, 'code' => $who])]);
        }
        $now = now();
        $hasFingers = $registered || DB::table('dev_fingerprints')->where('device_id', $device->id)->where('pin', $pin)->exists();
        $existing = DB::table('dev_employee_maps')->where('device_id', $device->id)->where('employee_id', $e->id)->first();
        $values = ['pin' => $pin, 'updated_at' => $now] + ($hasFingers ? ['status' => 'REGISTERED', 'registered_at' => $existing?->registered_at ?? $now] : []);
        if ($existing) {
            DB::table('dev_employee_maps')->where('id', $existing->id)->update($values);
        } else {
            DB::table('dev_employee_maps')->insert($values + ['id' => strtolower((string) Str::ulid()), 'company_id' => $device->company_id, 'device_id' => $device->id, 'employee_id' => $e->id,
                'status' => $hasFingers ? 'REGISTERED' : 'PENDING', 'created_by' => $this->caller()->auditUserId(), 'created_at' => $now]);
        }
        foreach (['dev_users', 'dev_fingerprints'] as $table) {
            DB::table($table)->where('device_id', $device->id)->where('pin', $pin)->update(['employee_id' => $e->id]);
        }
        DB::table('dev_punches')->where('device_id', $device->id)->where('pin', $pin)->whereNull('employee_id')->update(['employee_id' => $e->id, 'posted_to_hr' => (bool) $device->post_to_hr]);
    }

    /** @param list<string> $codes */
    private function people(array $codes): Collection
    {
        $people = DB::table('hr_employees')->where('company_id', $this->caller()->companyId)->whereIn('employee_code', $codes)->get();
        if ($people->count() !== count(array_unique($codes))) {
            throw ValidationException::withMessages(['employees' => __('hrpayroll.emp.bio.v.pick')]);
        }

        return $people;
    }

    private function device(string $code): object
    {
        return DB::table('dev_devices')->where('company_id', $this->caller()->companyId)->where('code', $code)->first()
            ?? throw ValidationException::withMessages(['device' => __('hrpayroll.emp.bio.v.device')]);
    }

    private function pushDevice(string $code): object
    {
        $d = $this->device($code);
        if ($d->protocol !== 'ADMS_PUSH' || $d->state !== 'ACTIVE') {
            throw ValidationException::withMessages(['device' => __('hrpayroll.emp.bio.v.push_active')]);
        }

        return $d;
    }

    private function pushDevices(): Collection
    {
        $devices = DB::table('dev_devices')->where('company_id', $this->caller()->companyId)->where('protocol', 'ADMS_PUSH')->where('state', 'ACTIVE')->orderBy('code')->get();
        if ($devices->isEmpty()) {
            throw ValidationException::withMessages(['device' => __('hrpayroll.emp.bio.v.no_devices')]);
        }

        return $devices;
    }
}
