<?php

declare(strict_types=1);

namespace App\Modules\AttendanceDevice\Http\Controllers\Api;

use App\Modules\AttendanceDevice\Support\Terminals;
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
 * Settings → Device Integration (AnnotechERP 03/01), as Brickland keeps it:
 *
 * - Attendance Devices: the register (online, awaiting approval, punches
 *   today, not yet in HR), Register Device in two tabs — Device and
 *   Attendance — and each terminal's actions: Approve, Test Connection,
 *   Re-upload All Logs, Refresh Device Info, Post Pending to HR, Disable /
 *   Enable, Reboot.
 * - Device Punch Log: punches by date, device, User ID or employee code, and
 *   whether they matched; Post Pending to HR.
 * - Device Enrolled Users: the users each terminal reported.
 * - Device Command Queue: what push terminals were asked to do, and Send
 *   Command (INFO, CHECK, REBOOT).
 *
 * A device is addressed by its code. Punches are never edited. A device with
 * punches is not removed — it is disabled, so the punches keep their source.
 */
class DeviceApiController extends ApiController
{
    private const PUNCH_LIMIT = 500;

    private const COLUMNS = ['code', 'name', 'vendor', 'model', 'protocol', 'serial_no', 'ip_address', 'port', 'comm_key', 'poll_minutes', 'allowed_ips', 'time_zone', 'state', 'location', 'user_match', 'post_to_hr', 'post_daily', 'remarks'];

    public function index(): JsonResponse
    {
        $this->allowEither('settings.device.view', 'settings.device.manage');
        $c = $this->caller()->companyId;
        $devices = DB::table('dev_devices')->where('company_id', $c)->orderBy('code')->get();
        $today = CarbonImmutable::now('Asia/Dhaka')->startOfDay()->utc();
        $users = DB::table('dev_users')->where('company_id', $c)->selectRaw('device_id, count(*) as n, count(employee_id) as matched')->groupBy('device_id')->get()->keyBy('device_id');
        $pending = DB::table('dev_punches')->where('company_id', $c)->where('posted_to_hr', false)->selectRaw('device_id, count(*) as n')->groupBy('device_id')->pluck('n', 'device_id');

        return ApiResponse::ok([
            // Where a push terminal's Cloud Server must point: this server's address; it calls /iclock/ there.
            'adms_url' => rtrim((string) config('app.url'), '/').'/iclock/',
            'summary' => [
                'devices' => $devices->count(), 'online' => $devices->filter(fn ($d) => $d->state === 'ACTIVE' && Terminals::online($d))->count(),
                'awaiting' => $devices->where('state', 'PENDING')->count(), 'punches_today' => DB::table('dev_punches')->where('company_id', $c)->where('punched_at', '>=', $today)->count(),
                'not_in_hr' => (int) $pending->sum(),
            ],
            'rows' => $devices->map(fn ($d) => $this->row($d) + ['users_reported' => (int) ($users[$d->id]->n ?? 0), 'users_matched' => (int) ($users[$d->id]->matched ?? 0), 'pending_hr' => (int) ($pending[$d->id] ?? 0)])->values()->all(),
        ]);
    }

    public function show(string $device): JsonResponse
    {
        $this->allowEither('settings.device.view', 'settings.device.manage');

        return ApiResponse::ok($this->row($this->find($device)));
    }

    public function store(Request $request): JsonResponse
    {
        $this->allow('settings.device.manage');
        $d = $this->validated($request, null);
        $id = strtolower((string) Str::ulid());
        DB::table('dev_devices')->insert($d + ['id' => $id, 'company_id' => $this->caller()->companyId, 'created_by' => $this->caller()->auditUserId(), 'created_at' => now(), 'updated_at' => now()]);

        return ApiResponse::created($this->row(DB::table('dev_devices')->find($id)));
    }

    public function update(Request $request, string $device): JsonResponse
    {
        $this->allow('settings.device.manage');
        $row = $this->find($device);
        DB::table('dev_devices')->where('id', $row->id)->update($this->validated($request, $row) + ['updated_at' => now()]);

        return ApiResponse::ok($this->row(DB::table('dev_devices')->find($row->id)));
    }

    public function destroy(string $device): JsonResponse
    {
        $this->allow('settings.device.manage');
        $row = $this->find($device);
        if (DB::table('dev_punches')->where('device_id', $row->id)->exists()) {
            throw ValidationException::withMessages(['device' => __('settings.dev.v.has_punches', ['code' => $row->code])]);
        }
        DB::table('dev_devices')->where('id', $row->id)->delete();

        return ApiResponse::ok(['deleted' => $row->code]);
    }

    /** A row action: approve, test, reupload, info, repost, disable, enable, reboot. */
    public function act(Request $request, string $device): JsonResponse
    {
        $this->allow('settings.device.manage');
        $row = $this->find($device);
        $action = $request->validate(['action' => ['required', Rule::in(['approve', 'test', 'reupload', 'info', 'repost', 'disable', 'enable', 'reboot'])]])['action'];
        $push = $row->protocol === 'ADMS_PUSH';
        $user = $this->caller()->auditUserId();
        if (in_array($action, ['reupload', 'info', 'reboot'], true) && ! $push) {
            throw ValidationException::withMessages(['action' => __('settings.dev.v.push_only')]);
        }
        $message = match ($action) {
            'approve', 'enable' => $this->setState($row, 'ACTIVE', $action === 'approve' ? 'approved' : 'enabled'),
            'disable' => $this->setState($row, 'DISABLED', 'disabled'),
            'test' => $this->test($row),
            'reupload' => tap(__('settings.dev.done.reupload', ['code' => $row->code]), function () use ($row, $user): void {
                foreach (Terminals::reuploadCommands() as [$kind, $cmd]) {
                    Terminals::queue($row, $kind, $cmd, __('settings.dev.cmd.'.strtolower($kind)), $user);
                }
            }),
            'info' => tap(__('settings.dev.done.info', ['code' => $row->code]), fn () => Terminals::queue($row, 'INFO', 'INFO', __('settings.dev.cmd.INFO'), $user)),
            'reboot' => tap(__('settings.dev.done.reboot', ['code' => $row->code]), fn () => Terminals::queue($row, 'REBOOT', 'REBOOT', __('settings.dev.cmd.REBOOT'), $user)),
            'repost' => (function () use ($row): string {
                $r = Terminals::postPending($row->company_id, $row->id);

                return __('settings.dev.done.repost', $r);
            })(),
        };
        DB::table('dev_devices')->where('id', $row->id)->update(['last_result' => mb_substr($message, 0, 300), 'updated_at' => now()]);

        return ApiResponse::ok(['message' => $message, 'row' => $this->row(DB::table('dev_devices')->find($row->id))]);
    }

    /* ── Device Punch Log ──────────────────────────────────────────── */

    public function options(): JsonResponse
    {
        $this->allowEither('settings.device.view', 'settings.device.manage');

        return ApiResponse::ok([
            'devices' => DB::table('dev_devices')->where('company_id', $this->caller()->companyId)->orderBy('code')->get(['code', 'name', 'protocol'])->map(fn ($d) => ['value' => $d->code, 'label' => "{$d->code} — {$d->name}", 'protocol' => $d->protocol])->all(),
            'commands' => array_map(fn ($c) => ['value' => $c, 'label' => __("settings.dev.cmd.{$c}")], Terminals::COMMANDS),
            'today' => CarbonImmutable::now('Asia/Dhaka')->toDateString(),
        ]);
    }

    public function punches(Request $request): JsonResponse
    {
        $this->allowEither('settings.device.view', 'settings.device.manage');
        $c = $this->caller()->companyId;
        $f = $request->validate(['from' => ['nullable', 'date'], 'to' => ['nullable', 'date'], 'device' => ['nullable', 'string'], 'pin' => ['nullable', 'string', 'max:40'], 'match' => ['nullable', Rule::in(['matched', 'unmatched'])]]);
        // With no dates, today's punches.
        $from = $f['from'] ?? (empty($f['to']) ? CarbonImmutable::now('Asia/Dhaka')->toDateString() : null);
        $to = $f['to'] ?? (empty($f['from']) ? CarbonImmutable::now('Asia/Dhaka')->toDateString() : null);
        $pin = trim((string) ($f['pin'] ?? ''));
        $q = DB::table('dev_punches as p')->join('dev_devices as d', 'd.id', '=', 'p.device_id')->leftJoin('hr_employees as e', 'e.id', '=', 'p.employee_id')->where('p.company_id', $c)
            ->when($from, fn ($w, $x) => $w->where('p.punched_at', '>=', CarbonImmutable::parse($x, 'Asia/Dhaka')->startOfDay()->utc()))
            ->when($to, fn ($w, $x) => $w->where('p.punched_at', '<=', CarbonImmutable::parse($x, 'Asia/Dhaka')->endOfDay()->utc()))
            ->when($f['device'] ?? null, fn ($w, $x) => $w->where('d.code', $x))
            ->when($pin !== '', fn ($w) => $w->where(fn ($o) => $o->where('p.pin', $pin)->orWhere('e.employee_code', $pin)))
            ->when(($f['match'] ?? null) === 'matched', fn ($w) => $w->whereNotNull('p.employee_id'))->when(($f['match'] ?? null) === 'unmatched', fn ($w) => $w->whereNull('p.employee_id'));
        $summary = (clone $q)->selectRaw('count(*) as n, count(p.employee_id) as matched, sum(case when p.posted_to_hr then 1 else 0 end) as posted')->first();
        $rows = $q->orderByDesc('p.punched_at')->limit(self::PUNCH_LIMIT)->get(['p.*', 'd.code as device', 'e.employee_code as code', 'e.name as employee']);

        return ApiResponse::ok([
            'summary' => ['punches' => (int) $summary->n, 'matched' => (int) $summary->matched, 'unmatched' => (int) $summary->n - (int) $summary->matched, 'posted' => (int) $summary->posted],
            'rows' => $rows->map(fn ($p) => [
                'id' => $p->id, 'time' => $this->local($p->punched_at), 'device' => $p->device, 'pin' => $p->pin, 'code' => $p->code, 'employee' => $p->employee, 'state' => (int) $p->state, 'verify' => (int) $p->verify,
                'source' => $p->source, 'in_hr' => (bool) $p->posted_to_hr, 'matched' => $p->employee_id !== null, 'received' => $this->local($p->received_at, true),
            ])->all(),
        ]);
    }

    /** Post Pending to HR, from every device. */
    public function postPending(): JsonResponse
    {
        $this->allow('settings.device.manage');

        return ApiResponse::ok(['message' => __('settings.dev.done.repost', Terminals::postPending($this->caller()->companyId))]);
    }

    /* ── Device Enrolled Users ─────────────────────────────────────── */

    public function users(Request $request): JsonResponse
    {
        $this->allowEither('settings.device.view', 'settings.device.manage');
        $f = $request->validate(['device' => ['nullable', 'string'], 'match' => ['nullable', Rule::in(['matched', 'unmatched'])]]);
        $q = DB::table('dev_users as u')->join('dev_devices as d', 'd.id', '=', 'u.device_id')->leftJoin('hr_employees as e', 'e.id', '=', 'u.employee_id')->where('u.company_id', $this->caller()->companyId)
            ->when($f['device'] ?? null, fn ($w, $x) => $w->where('d.code', $x))
            ->when(($f['match'] ?? null) === 'matched', fn ($w) => $w->whereNotNull('u.employee_id'))->when(($f['match'] ?? null) === 'unmatched', fn ($w) => $w->whereNull('u.employee_id'));
        $all = (clone $q)->selectRaw('count(*) as n, count(u.employee_id) as matched')->first();
        $rows = $q->orderBy('d.code')->orderByRaw('length(u.pin)')->orderBy('u.pin')->limit(5000)->get(['u.*', 'd.code as device', 'e.employee_code as code', 'e.name as employee']);

        return ApiResponse::ok([
            'summary' => ['enrolled' => (int) $all->n, 'unmatched' => (int) $all->n - (int) $all->matched],
            'rows' => $rows->map(fn ($u) => [
                'id' => $u->id, 'device' => $u->device, 'pin' => $u->pin, 'name' => $u->name, 'card' => $u->card_no, 'role' => (int) $u->privilege, 'matched' => $u->employee_id !== null,
                'employee' => $u->employee_id ? "{$u->employee} ({$u->code})" : null, 'reported' => $this->local($u->reported_at, true),
            ])->all(),
        ]);
    }

    /* ── Device Command Queue ──────────────────────────────────────── */

    public function commands(): JsonResponse
    {
        $this->allowEither('settings.device.view', 'settings.device.manage');
        $rows = DB::table('dev_commands as c')->join('dev_devices as d', 'd.id', '=', 'c.device_id')->leftJoin('users as u', 'u.id', '=', 'c.requested_by')->where('c.company_id', $this->caller()->companyId)
            ->orderByDesc('c.id')->limit(1000)->get(['c.*', 'd.code as device', 'u.name as by']);

        return ApiResponse::ok($rows->map(fn ($c) => [
            'id' => (int) $c->id, 'device' => $c->device, 'kind' => $c->kind, 'label' => $c->label, 'by' => $c->by, 'queued' => $this->local($c->created_at, true),
            'delivered' => $this->local($c->sent_at, true), 'answered' => $this->local($c->answered_at, true), 'return_code' => $c->return_code === null ? null : (int) $c->return_code, 'status' => $c->status,
        ])->all());
    }

    public function sendCommand(Request $request): JsonResponse
    {
        $this->allow('settings.device.manage');
        $d = $request->validate(['device' => ['required', 'string'], 'command' => ['required', Rule::in(Terminals::COMMANDS)]], [
            'device.required' => __('settings.dev.v.device'), 'command.required' => __('settings.dev.v.command'),
        ]);
        $row = $this->find($d['device']);
        if ($row->protocol !== 'ADMS_PUSH') {
            throw ValidationException::withMessages(['device' => __('settings.dev.v.push_only')]);
        }
        $id = Terminals::queue($row, $d['command'], $d['command'], __("settings.dev.cmd.{$d['command']}"), $this->caller()->auditUserId());

        return ApiResponse::created(['id' => $id]);
    }

    public function cancelCommand(string $id): JsonResponse
    {
        $this->allow('settings.device.manage');
        $n = DB::table('dev_commands')->where('company_id', $this->caller()->companyId)->where('id', (int) $id)->where('status', 'QUEUED')->update(['status' => 'CANCELLED', 'updated_at' => now()]);
        if (! $n) {
            throw ValidationException::withMessages(['command' => __('settings.dev.v.not_queued')]);
        }

        return ApiResponse::ok(['cancelled' => (int) $id]);
    }

    /* ── Helpers ───────────────────────────────────────────────────── */

    private function find(string $code): object
    {
        return DB::table('dev_devices')->where('company_id', $this->caller()->companyId)->where('code', $code)->first() ?? abort(404);
    }

    /** @return array<string, mixed> */
    private function validated(Request $request, ?object $row): array
    {
        $c = $this->caller()->companyId;
        $request->merge(['serial_no' => trim((string) $request->input('serial_no')) ?: null, 'ip_address' => trim((string) $request->input('ip_address')) ?: null, 'code' => trim((string) $request->input('code'))]);
        $push = $request->input('protocol', 'ADMS_PUSH') === 'ADMS_PUSH';
        $d = $request->validate([
            'code' => ['required', 'string', 'max:24', Rule::unique('dev_devices', 'code')->where('company_id', $c)->ignore($row?->id)],
            'name' => ['required', 'string', 'max:120'], 'vendor' => ['nullable', Rule::in(['ZKTECO', 'OTHER'])], 'model' => ['nullable', 'string', 'max:60'],
            'protocol' => ['required', Rule::in(['ADMS_PUSH', 'TCP_PULL'])],
            'serial_no' => [$push ? 'required' : 'nullable', 'string', 'max:40', Rule::unique('dev_devices', 'serial_no')->ignore($row?->id)],
            'ip_address' => [$push ? 'nullable' : 'required', 'ip'], 'port' => ['nullable', 'integer', 'min:1', 'max:65535'], 'comm_key' => ['nullable', 'integer', 'min:0', 'max:999999'],
            'poll_minutes' => ['nullable', 'integer', 'min:1', 'max:1440'], 'allowed_ips' => ['nullable', 'string', 'max:500'], 'time_zone' => ['nullable', 'numeric', 'min:-12', 'max:14'],
            'state' => ['nullable', Rule::in(['ACTIVE', 'DISABLED', 'PENDING'])], 'location' => ['nullable', 'string', 'max:120'],
            'user_match' => ['nullable', Rule::in(['AUTO', 'PROXY_NO', 'EMPLOYEE_CODE'])], 'post_to_hr' => ['nullable', 'boolean'], 'post_daily' => ['nullable', 'boolean'], 'remarks' => ['nullable', 'string', 'max:1000'],
        ], [
            'required' => __('settings.dev.v.required'), 'serial_no.required' => __('settings.dev.v.serial_required'), 'ip_address.required' => __('settings.dev.v.ip_required'),
            'serial_no.unique' => __('settings.dev.v.serial_taken'), 'code.unique' => __('settings.dev.v.code_taken'), 'ip_address.ip' => __('settings.dev.v.ip'),
        ], ['code' => __('settings.dev.f.code'), 'name' => __('settings.dev.f.name'), 'protocol' => __('settings.dev.f.protocol')]);
        foreach (preg_split('/[\s,;]+/', (string) ($d['allowed_ips'] ?? '')) ?: [] as $ip) {
            if ($ip !== '' && ! filter_var($ip, FILTER_VALIDATE_IP)) {
                throw ValidationException::withMessages(['allowed_ips' => __('settings.dev.v.allowed_ip', ['ip' => $ip])]);
            }
        }

        return array_intersect_key($d + ['vendor' => 'ZKTECO', 'port' => 4370, 'comm_key' => 0, 'poll_minutes' => 5, 'time_zone' => 6, 'state' => 'ACTIVE', 'user_match' => 'AUTO'], array_flip(self::COLUMNS)) + [
            'post_to_hr' => (bool) ($d['post_to_hr'] ?? true), 'post_daily' => (bool) ($d['post_daily'] ?? true),
            'port' => (int) ($d['port'] ?? 4370), 'comm_key' => (int) ($d['comm_key'] ?? 0), 'poll_minutes' => (int) ($d['poll_minutes'] ?? 5), 'time_zone' => (float) ($d['time_zone'] ?? 6),
        ];
    }

    private function setState(object $row, string $state, string $done): string
    {
        DB::table('dev_devices')->where('id', $row->id)->update(['state' => $state, 'updated_at' => now()]);

        return __("settings.dev.done.{$done}", ['code' => $row->code]);
    }

    /** Test Connection: the terminal's address when there is one; for a push terminal without one, when it last called. */
    private function test(object $row): string
    {
        if ($row->ip_address) {
            $start = microtime(true);
            $socket = @fsockopen($row->ip_address, (int) $row->port, $errno, $error, 3.0);
            if ($socket) {
                fclose($socket);

                return __('settings.dev.done.reached', ['address' => "{$row->ip_address}:{$row->port}", 'ms' => (int) round((microtime(true) - $start) * 1000)]);
            }

            return __('settings.dev.done.unreachable', ['address' => "{$row->ip_address}:{$row->port}", 'error' => $error ?: 'timeout']);
        }
        if ($row->last_contact_at) {
            return __('settings.dev.done.last_call', ['ago' => CarbonImmutable::parse($row->last_contact_at)->diffForHumans(), 'ip' => $row->last_contact_ip ?? '—']);
        }

        return __('settings.dev.done.never_called');
    }

    /** @return array<string, mixed> */
    private function row(object $d): array
    {
        $out = [];
        foreach (self::COLUMNS as $k) {
            $out[$k] = $d->{$k};
        }

        return $out + [
            'post_to_hr' => (bool) $d->post_to_hr, 'post_daily' => (bool) $d->post_daily, 'time_zone' => (float) $d->time_zone,
            'online' => $d->state === 'ACTIVE' && Terminals::online($d), 'last_contact_at' => $d->last_contact_at ? CarbonImmutable::parse($d->last_contact_at)->toIso8601String() : null,
            'last_contact_ip' => $d->last_contact_ip, 'last_punch' => $this->local($d->last_punch_at), 'firmware' => $d->firmware,
            'user_count' => $d->user_count, 'fp_count' => $d->fp_count, 'log_count' => $d->log_count, 'last_result' => $d->last_result,
        ];
    }

    private function local(mixed $at, bool $seconds = false): ?string
    {
        return $at ? CarbonImmutable::parse($at)->timezone('Asia/Dhaka')->format($seconds ? 'Y-m-d H:i:s' : 'Y-m-d H:i') : null;
    }
}
