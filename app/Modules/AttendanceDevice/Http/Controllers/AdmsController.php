<?php

declare(strict_types=1);

namespace App\Modules\AttendanceDevice\Http\Controllers;

use App\Modules\AttendanceDevice\Support\Terminals;
use App\Modules\HrPayroll\Support\AttendanceBook;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * The ZKTeco ADMS (push) conversation, as the terminals speak it (AnnotechERP
 * 03/01). A terminal set to this server calls, with its serial number:
 *
 * - GET  /iclock/cdata?SN=…&options=all — the handshake; it is told what to send.
 * - POST /iclock/cdata?SN=…&table=ATTLOG — punches, one per line: PIN, time,
 *   state, verify mode, work code.
 * - POST /iclock/cdata?SN=…&table=OPERLOG (also USERINFO, FINGERTMP, BIODATA,
 *   and /iclock/querydata) — the users it holds and their finger templates.
 * - GET  /iclock/getrequest?SN=… — asks for commands ("C:<number>:<command>").
 * - POST /iclock/devicecmd?SN=… — answers them ("ID=<number>&Return=<code>&CMD=…").
 *
 * Only a terminal registered under its serial number is heard, and only from
 * an allowed address when the list is set. One that is Disabled or awaiting
 * approval is refused its data — it keeps the punches until it is enabled —
 * but its call is still recorded, so the register shows it knocking.
 */
class AdmsController extends Controller
{
    private const MAX_COMMANDS = 20;

    public function cdata(Request $request): Response
    {
        $device = $this->device($request);
        if (! $device) {
            return $this->text('Unknown device', 404);
        }
        $this->touch($device, $request);
        if ($request->isMethod('get')) {
            return $this->text($this->options($device));
        }
        if ($refused = $this->refused($device, $request)) {
            return $refused;
        }
        $table = strtoupper((string) $request->query('table', ''));
        $body = $this->body($request);

        return $this->text($table === 'ATTLOG' ? 'OK: '.$this->punches($device, $body) : 'OK: '.$this->records($device, $body));
    }

    public function querydata(Request $request): Response
    {
        $device = $this->device($request);
        if (! $device) {
            return $this->text('Unknown device', 404);
        }
        $this->touch($device, $request);
        if ($refused = $this->refused($device, $request)) {
            return $refused;
        }
        $body = $this->body($request);
        $type = strtolower((string) $request->query('tablename', ''));

        return $this->text('OK: '.($type === 'attlog' || $type === 'transaction' ? $this->punches($device, $body) : $this->records($device, $body)));
    }

    public function getrequest(Request $request): Response
    {
        $device = $this->device($request);
        if (! $device) {
            return $this->text('Unknown device', 404);
        }
        $this->touch($device, $request);
        // INFO=firmware,users,fingers,punches,ip,…
        if ($info = $request->query('INFO')) {
            $p = explode(',', (string) $info);
            DB::table('dev_devices')->where('id', $device->id)->update(array_filter([
                'firmware' => isset($p[0]) ? mb_substr(trim($p[0]), 0, 60) : null, 'user_count' => isset($p[1]) && ctype_digit(trim($p[1])) ? (int) $p[1] : null,
                'fp_count' => isset($p[2]) && ctype_digit(trim($p[2])) ? (int) $p[2] : null, 'log_count' => isset($p[3]) && ctype_digit(trim($p[3])) ? (int) $p[3] : null,
            ], fn ($v) => $v !== null));
        }
        if ($device->state !== 'ACTIVE' || ! Terminals::allowed($device, $request->ip())) {
            return $this->text('OK');
        }
        $commands = DB::table('dev_commands')->where('device_id', $device->id)->where('status', 'QUEUED')->orderBy('id')->limit(self::MAX_COMMANDS)->get(['id', 'command']);
        if ($commands->isEmpty()) {
            return $this->text('OK');
        }
        DB::table('dev_commands')->whereIn('id', $commands->pluck('id'))->update(['status' => 'SENT', 'sent_at' => now(), 'updated_at' => now()]);

        return $this->text($commands->map(fn ($c) => "C:{$c->id}:{$c->command}")->implode("\n")."\n");
    }

    public function devicecmd(Request $request): Response
    {
        $device = $this->device($request);
        if (! $device) {
            return $this->text('Unknown device', 404);
        }
        $this->touch($device, $request);
        $info = [];
        $current = null;
        foreach (preg_split('/\r?\n/', $this->body($request)) ?: [] as $line) {
            $line = trim($line);
            if ($line === '') {
                continue;
            }
            if (str_starts_with($line, 'ID=')) {
                parse_str($line, $a);
                $current = (int) ($a['ID'] ?? 0);
                $code = isset($a['Return']) ? (int) $a['Return'] : null;
                DB::table('dev_commands')->where('device_id', $device->id)->where('id', $current)->update([
                    'status' => $code !== null && $code < 0 ? 'FAILED' : 'DONE', 'return_code' => $code, 'answered_at' => now(), 'updated_at' => now(),
                ]);

                continue;
            }
            // INFO's answer: key=value lines after the ID line.
            if (str_contains($line, '=')) {
                [$k, $v] = array_map('trim', explode('=', ltrim($line, '~'), 2));
                $info[strtolower($k)] = $v;
            }
        }
        if ($info) {
            DB::table('dev_devices')->where('id', $device->id)->update(array_filter([
                'firmware' => isset($info['fwversion']) ? mb_substr($info['fwversion'], 0, 60) : null, 'user_count' => isset($info['usercount']) ? (int) $info['usercount'] : null,
                'fp_count' => isset($info['fpcount']) ? (int) $info['fpcount'] : null, 'log_count' => isset($info['transactioncount']) ? (int) $info['transactioncount'] : null,
                'model' => isset($info['devicename']) && ! $device->model ? mb_substr($info['devicename'], 0, 60) : null,
            ], fn ($v) => $v !== null));
            if ($current) {
                DB::table('dev_commands')->where('device_id', $device->id)->where('id', $current)->update(['result' => mb_substr(json_encode($info), 0, 2000)]);
            }
        }

        return $this->text('OK');
    }

    public function ping(Request $request): Response
    {
        if ($device = $this->device($request)) {
            $this->touch($device, $request);
        }

        return $this->text('OK');
    }

    /* ── What the terminal sends ───────────────────────────────────── */

    /** ATTLOG: PIN, time, state, verify, work code — a punch already kept is skipped. */
    private function punches(object $device, string $body): int
    {
        $now = now();
        $pins = [];
        $saved = 0;
        $latest = null;
        $touched = [];
        foreach (preg_split('/\r?\n/', $body) ?: [] as $line) {
            $f = explode("\t", trim($line));
            if (count($f) < 2 || $f[0] === '') {
                continue;
            }
            $at = Terminals::moment($f[1], (float) $device->time_zone);
            if (! $at) {
                continue;
            }
            $pin = mb_substr(trim($f[0]), 0, 24);
            $employee = $pins[$pin] ??= Terminals::match($device, $pin);
            $saved += DB::table('dev_punches')->insertOrIgnore([
                'id' => strtolower((string) Str::ulid()), 'company_id' => $device->company_id, 'device_id' => $device->id, 'pin' => $pin, 'punched_at' => $at,
                'state' => (int) ($f[2] ?? 0), 'verify' => (int) ($f[3] ?? 1), 'work_code' => isset($f[4]) && $f[4] !== '' ? mb_substr($f[4], 0, 12) : null,
                'source' => 'PUSH', 'employee_id' => $employee, 'posted_to_hr' => $employee !== null && $device->post_to_hr, 'received_at' => $now,
            ]);
            $latest = $latest === null || $at->greaterThan($latest) ? $at : $latest;
            if ($employee !== null) {
                // The day it falls on, and the day before (a night shift's OUT next morning).
                $local = $at->timezone('Asia/Dhaka');
                $touched[$local->toDateString()][$employee] = true;
                $touched[$local->subDay()->toDateString()][$employee] = true;
            }
        }
        // "Update daily attendance in/out time": the days these punches fall on are worked out again now.
        if ($device->post_daily && $touched) {
            $book = new AttendanceBook($device->company_id);
            foreach ($touched as $date => $employees) {
                $book->process($date, $date, array_keys($employees));
            }
        }
        if ($latest && ($device->last_punch_at === null || $latest->greaterThan($device->last_punch_at))) {
            DB::table('dev_devices')->where('id', $device->id)->update(['last_punch_at' => $latest]);
        }

        return $saved;
    }

    /** OPERLOG and the query answers: USER and FP / BIODATA lines; OPLOG lines are skipped. */
    private function records(object $device, string $body): int
    {
        $now = now();
        $n = 0;
        foreach (preg_split('/\r?\n/', $body) ?: [] as $line) {
            $line = trim($line);
            if ($line === '' || str_starts_with($line, 'OPLOG')) {
                continue;
            }
            $kind = strtoupper(strtok($line, " \t") ?: '');
            $fields = [];
            foreach (explode("\t", preg_replace('/^(USER|FP|BIODATA|USERINFO|FINGERTMP)\s+/i', '', $line) ?? '') as $pair) {
                if (str_contains($pair, '=')) {
                    [$k, $v] = explode('=', $pair, 2);
                    $fields[strtolower(trim($k))] = $v;
                }
            }
            $pin = mb_substr(trim($fields['pin'] ?? ''), 0, 24);
            if ($pin === '') {
                continue;
            }
            $template = $fields['tmp'] ?? null;
            if ($template !== null && ($kind !== 'BIODATA' || (int) ($fields['type'] ?? 1) === 1)) {
                $finger = (int) ($fields['fid'] ?? $fields['index'] ?? 0);
                $employee = Terminals::match($device, $pin);
                DB::table('dev_fingerprints')->upsert([[
                    'id' => strtolower((string) Str::ulid()), 'company_id' => $device->company_id, 'device_id' => $device->id, 'pin' => $pin, 'finger' => $finger,
                    'size' => (int) ($fields['size'] ?? strlen($template)), 'valid' => (int) ($fields['valid'] ?? 1), 'template' => $template, 'employee_id' => $employee, 'reported_at' => $now,
                ]], ['device_id', 'pin', 'finger'], ['size', 'valid', 'template', 'employee_id', 'reported_at']);
                // Fingers reported: the mapping is Registered.
                DB::table('dev_employee_maps')->where('device_id', $device->id)->where('pin', $pin)->where('status', 'PENDING')->update(['status' => 'REGISTERED', 'registered_at' => $now, 'updated_at' => $now]);
                $n++;

                continue;
            }
            if (array_key_exists('name', $fields) || array_key_exists('card', $fields) || array_key_exists('pri', $fields)) {
                $card = preg_replace('/\D/', '', (string) ($fields['card'] ?? '')) ?: null;
                DB::table('dev_users')->upsert([[
                    'id' => strtolower((string) Str::ulid()), 'company_id' => $device->company_id, 'device_id' => $device->id, 'pin' => $pin,
                    'name' => mb_substr(trim($fields['name'] ?? ''), 0, 120) ?: null, 'card_no' => $card ? mb_substr($card, 0, 40) : null,
                    'privilege' => (int) ($fields['pri'] ?? 0), 'employee_id' => Terminals::match($device, $pin), 'reported_at' => $now,
                ]], ['device_id', 'pin'], ['name', 'card_no', 'privilege', 'employee_id', 'reported_at']);
                $n++;
            }
        }

        return $n;
    }

    /* ── Plumbing ─────────────────────────────────────────────────── */

    private function device(Request $request): ?object
    {
        $sn = trim((string) $request->query('SN', $request->query('sn', '')));

        return $sn === '' ? null : DB::table('dev_devices')->where('serial_no', $sn)->where('protocol', 'ADMS_PUSH')->first();
    }

    private function touch(object $device, Request $request): void
    {
        DB::table('dev_devices')->where('id', $device->id)->update(['last_contact_at' => now(), 'last_contact_ip' => $request->ip()]);
    }

    /** Data from a terminal that is not Active, or from an address not on its list, is refused — the terminal keeps it. */
    private function refused(object $device, Request $request): ?Response
    {
        if ($device->state !== 'ACTIVE') {
            return $this->text('Device not active', 403);
        }
        if (! Terminals::allowed($device, $request->ip())) {
            DB::table('dev_devices')->where('id', $device->id)->update(['last_result' => mb_substr('Refused a push from '.$request->ip().' (not an allowed source address).', 0, 300)]);

            return $this->text('Address not allowed', 403);
        }

        return null;
    }

    /** What a terminal is told at the handshake: send everything, poll every ten seconds, in its own time zone. */
    private function options(object $device): string
    {
        $zone = rtrim(rtrim(number_format((float) $device->time_zone, 2, '.', ''), '0'), '.');

        return implode("\n", [
            "GET OPTION FROM: {$device->serial_no}", 'ATTLOGStamp=None', 'OPERLOGStamp=None', 'ATTPHOTOStamp=None', 'ErrorDelay=30', 'Delay=10',
            'TransTimes=00:00;14:05', 'TransInterval=1', 'TransFlag=TransData AttLog OpLog EnrollUser ChgUser EnrollFP ChgFP', "TimeZone={$zone}", 'Realtime=1', 'Encrypt=None',
        ])."\n";
    }

    private function body(Request $request): string
    {
        return (string) $request->getContent();
    }

    private function text(string $body, int $status = 200): Response
    {
        return response($body, $status)->header('Content-Type', 'text/plain');
    }
}
