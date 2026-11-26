<?php

declare(strict_types=1);

namespace App\Modules\AttendanceDevice\Support;

use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * The attendance terminals' shared rules (AnnotechERP 03/01, 24/03): who a
 * User ID (PIN) belongs to, posting punches to HR, queuing commands for push
 * terminals in the ZKTeco ADMS wording, and whether a terminal is online.
 *
 * A PIN is matched first by an explicit mapping (Biometric → Map), then by
 * the device's rule: Auto (proxy / card number, then employee code), the
 * proxy number only, or the employee code only.
 *
 * Works on raw tables with the company passed in: the terminals' own calls
 * (/iclock/*) arrive without a signed-in user or tenant.
 */
final class Terminals
{
    /** A push terminal that has not called in this long is offline. */
    public const ONLINE_SECONDS = 180;

    /** What Send Command offers. */
    public const COMMANDS = ['INFO', 'CHECK', 'REBOOT'];

    public static function online(object $device): bool
    {
        return $device->last_contact_at !== null && CarbonImmutable::parse($device->last_contact_at)->diffInSeconds(now(), true) <= self::ONLINE_SECONDS;
    }

    /**
     * The employee a PIN belongs to on a terminal, or null.
     */
    public static function match(object $device, string $pin): ?string
    {
        $mapped = DB::table('dev_employee_maps')->where('device_id', $device->id)->where('pin', $pin)->value('employee_id');
        if ($mapped) {
            return $mapped;
        }
        $employees = DB::table('hr_employees')->where('company_id', $device->company_id);
        $byCard = fn () => (clone $employees)->where('card_no', $pin)->value('id');
        $byCode = fn () => (clone $employees)->where('employee_code', $pin)->value('id');

        return match ($device->user_match) {
            'PROXY_NO' => $byCard(),
            'EMPLOYEE_CODE' => $byCode(),
            default => $byCard() ?? $byCode(),
        };
    }

    /**
     * Post Pending to HR: match every punch not yet in HR again — a card or
     * code registered since the punch arrived is matched now. Punches from a
     * terminal that does not post to HR stay where they are.
     *
     * @return array{matched: int, posted: int}
     */
    public static function postPending(string $companyId, ?string $deviceId = null): array
    {
        $devices = DB::table('dev_devices')->where('company_id', $companyId)->when($deviceId, fn ($q, $id) => $q->where('id', $id))->get()->keyBy('id');
        $matched = 0;
        $posted = 0;
        DB::table('dev_punches')->where('company_id', $companyId)->where('posted_to_hr', false)->whereIn('device_id', $devices->keys())
            ->orderBy('punched_at')->chunkById(500, function (Collection $punches) use ($devices, &$matched, &$posted): void {
                $pins = [];
                foreach ($punches as $p) {
                    $device = $devices[$p->device_id];
                    $employee = $p->employee_id ?? ($pins[$p->device_id.'|'.$p->pin] ??= self::match($device, $p->pin));
                    if (! $employee) {
                        continue;
                    }
                    $post = (bool) $device->post_to_hr;
                    DB::table('dev_punches')->where('id', $p->id)->update(['employee_id' => $employee, 'posted_to_hr' => $post]);
                    $matched += $p->employee_id ? 0 : 1;
                    $posted += $post ? 1 : 0;
                }
            });
        // The terminals' users pick up the same matches.
        foreach (DB::table('dev_users')->where('company_id', $companyId)->whereNull('employee_id')->whereIn('device_id', $devices->keys())->get() as $u) {
            if ($e = self::match($devices[$u->device_id], $u->pin)) {
                DB::table('dev_users')->where('id', $u->id)->update(['employee_id' => $e]);
            }
        }

        return ['matched' => $matched, 'posted' => $posted];
    }

    /** Queue a command for a push terminal; answers its number. */
    public static function queue(object $device, string $kind, string $command, string $label, ?string $userId): int
    {
        return (int) DB::table('dev_commands')->insertGetId([
            'company_id' => $device->company_id, 'device_id' => $device->id, 'kind' => $kind, 'command' => $command, 'label' => mb_substr($label, 0, 200),
            'status' => 'QUEUED', 'requested_by' => $userId, 'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    /** The user a terminal is told to hold: PIN, name, card. */
    public static function userCommand(string $pin, string $name, ?string $card): string
    {
        $name = trim(preg_replace('/[\t\r\n]+/', ' ', $name) ?? '');
        $card = $card !== null && ctype_digit($card) ? $card : '';

        return "DATA UPDATE USERINFO PIN={$pin}\tName=".mb_substr($name, 0, 24)."\tPri=0\tPasswd=\tCard={$card}\tGrp=1\tTZ=0000000100000000\tVerify=0";
    }

    public static function fingerCommand(object $fp, string $pin): string
    {
        return "DATA UPDATE FINGERTMP PIN={$pin}\tFID={$fp->finger}\tSize={$fp->size}\tValid={$fp->valid}\tTMP={$fp->template}";
    }

    public static function deleteUserCommand(string $pin): string
    {
        return "DATA DELETE USERINFO PIN={$pin}";
    }

    /** The two queries that make a terminal send everything again: its punches, then its users and fingers. */
    public static function reuploadCommands(): array
    {
        return [
            ['QUERY_LOGS', "DATA QUERY ATTLOG StartTime=2000-01-01 00:00:00\tEndTime=2099-12-31 23:59:59"],
            ['QUERY_USERS', 'DATA QUERY USERINFO'],
            ['QUERY_USERS', 'DATA QUERY FINGERTMP'],
        ];
    }

    /** A terminal's local time (its time zone) as a moment. */
    public static function moment(string $local, float $zone): ?CarbonImmutable
    {
        $minutes = (int) round($zone * 60);
        $offset = sprintf('%s%02d:%02d', $minutes < 0 ? '-' : '+', intdiv(abs($minutes), 60), abs($minutes) % 60);
        try {
            $t = CarbonImmutable::createFromFormat('Y-m-d H:i:s', trim($local), $offset);
        } catch (\Throwable) {
            return null;
        }

        return $t ? $t->utc() : null;
    }

    /** Whether an address may push for a terminal (Allowed Source IPs, empty for any). */
    public static function allowed(object $device, ?string $ip): bool
    {
        $list = array_filter(array_map('trim', preg_split('/[\s,;]+/', (string) $device->allowed_ips) ?: []));

        return $list === [] || ($ip !== null && in_array($ip, $list, true));
    }
}
