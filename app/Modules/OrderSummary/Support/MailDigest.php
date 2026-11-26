<?php

declare(strict_types=1);

namespace App\Modules\OrderSummary\Support;

use App\Modules\Identity\Models\MailService;
use App\Modules\OrderSummary\Models\OsChange;
use App\Modules\OrderSummary\Models\OsMailSetting;
use App\Modules\OrderSummary\Models\OsOrder;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;

/**
 * Order Summary's edit mail (AnnotechERP 32, the user's decision of
 * 2026-11-18): only edits — which order, which column, what it was and what
 * it is now, who changed it and when — sent at the times of day the admin
 * set (Mail Schedule), each time holding every edit not mailed yet. Each
 * person gets only the orders of buyers under them (OrderScope): a
 * merchandiser their buyers, a team head their team's, a department head
 * their department's; nobody is sent an empty mail. It goes through the
 * company's Mail Service Configuration (the chosen service, else the default).
 */
final class MailDigest
{
    /** The slot due now ('Y-m-d H:i'), or null when none is (in the factory's time). */
    public static function dueSlot(OsMailSetting $s, CarbonImmutable $now): ?string
    {
        if (! $s->enabled || empty($s->times)) {
            return null;
        }
        $local = $now->setTimezone(Sheet::TIMEZONE);
        $due = null;
        foreach ($s->times as $t) {
            if (! preg_match('/^([01]\d|2[0-3]):[0-5]\d$/', (string) $t)) {
                continue;
            }
            $slot = $local->format('Y-m-d').' '.$t;
            if ($t <= $local->format('H:i') && ($due === null || $slot > $due)) {
                $due = $slot;
            }
        }

        return $due !== null && $due !== $s->last_slot ? $due : null;
    }

    /**
     * Send what is due for one company (its tenant context set by the
     * caller). `$force` sends now whatever is waiting, slot or not (Test).
     *
     * @return array{sent: int, changes: int, skipped?: string}
     */
    public static function run(string $companyId, CarbonImmutable $now, bool $force = false): array
    {
        $s = OsMailSetting::query()->where('company_id', $companyId)->first();
        $slot = $s ? self::dueSlot($s, $now) : null;
        if (! $force && $slot === null) {
            return ['sent' => 0, 'changes' => 0];
        }
        $service = MailService::query()->where('company_id', $companyId)->where('active', true)
            ->when($s?->mail_service_id, fn ($q, $id) => $q->whereKey($id), fn ($q) => $q->orderByDesc('is_default'))->first();
        if (! $service) {
            return ['sent' => 0, 'changes' => 0, 'skipped' => 'no_mail_service'];
        }
        $changes = OsChange::query()->where('company_id', $companyId)->where('kind', 'EDIT')->whereNull('mailed_at')->where('changed_at', '<=', $now)->orderBy('changed_at')->get();
        $sent = 0;
        if ($changes->isNotEmpty()) {
            $sheet = new Sheet($companyId);
            $labels = $sheet->columns->pluck('label', 'key');
            $orders = OsOrder::withTrashed()->whereKey($changes->pluck('order_id')->unique())->get()->keyBy('id');
            $users = DB::table('users')->pluck('name', 'id');
            $byUser = [];
            foreach ($changes->groupBy('buyer_id') as $buyer => $rows) {
                if ($buyer === '' || $buyer === null) {
                    continue;
                }
                foreach (OrderScope::recipientsFor((string) $buyer) as $u) {
                    $byUser[$u] = ($byUser[$u] ?? collect())->merge($rows);
                }
            }
            $mailer = self::mailer($service);
            $emails = DB::table('users')->whereIn('id', array_keys($byUser))->where('status', 'ACTIVE')->pluck('email', 'id');
            foreach ($byUser as $userId => $rows) {
                if (empty($emails[$userId])) {
                    continue;
                }
                $mailer->to($emails[$userId])->send(new OrderEditDigestMail(self::html($rows, $orders, $labels, $users), $service, count($rows)));
                $sent++;
            }
        }
        OsChange::query()->whereKey($changes->pluck('id'))->update(['mailed_at' => $now]);
        if ($s && $slot !== null) {
            $s->forceFill(['last_slot' => $slot, 'last_sent_at' => $now])->save();
        }

        return ['sent' => $sent, 'changes' => $changes->count()];
    }

    /** A mailer on the company's SMTP service; the password comes from the environment variable it names. */
    public static function mailer(MailService $m): \Illuminate\Contracts\Mail\Mailer
    {
        $name = 'os_digest_'.$m->id;
        config(["mail.mailers.{$name}" => [
            'transport' => 'smtp', 'host' => $m->smtp_host, 'port' => (int) $m->port, 'encryption' => $m->encryption ?: null,
            'username' => $m->smtp_username, 'password' => env($m->password_env), 'timeout' => 30,
        ]]);

        return Mail::mailer($name);
    }

    /** The mail's body: order by order, the changed cells. */
    public static function html(Collection $rows, Collection $orders, Collection $labels, Collection $users, ?string $intro = null): string
    {
        $e = fn ($v) => htmlspecialchars((string) $v, ENT_QUOTES);
        $tz = Sheet::TIMEZONE;
        $out = '<div style="font:13px Segoe UI,Arial,sans-serif;color:#111827"><p>'.$e($intro ?? __('ordersummary.mail.intro', ['count' => $rows->count()])).'</p>';
        foreach ($rows->sortBy('changed_at')->groupBy('order_id') as $orderId => $cells) {
            $o = $orders[$orderId] ?? null;
            $v = $o?->values ?? [];
            $title = implode(' · ', array_filter(['#'.($o?->serial_no ?? '?'), $v['buyer'] ?? null, implode(', ', (array) ($v['style_number_name'] ?? [])), implode(', ', (array) ($v['job_no'] ?? [])), implode(', ', (array) ($v['po_number'] ?? []))]));
            $out .= '<h3 style="margin:18px 0 6px;font-size:14px;color:#4f46e5">'.$e($title).'</h3>'
                .'<table cellpadding="6" cellspacing="0" style="border-collapse:collapse;width:100%;font-size:12px"><tr style="background:#eef2ff">'
                .'<th align="left">'.$e(__('ordersummary.mail.column')).'</th><th align="left">'.$e(__('ordersummary.mail.before')).'</th><th align="left">'.$e(__('ordersummary.mail.after')).'</th><th align="left">'.$e(__('ordersummary.mail.by')).'</th><th align="left">'.$e(__('ordersummary.mail.at')).'</th></tr>';
            foreach ($cells as $c) {
                $out .= '<tr style="border-top:1px solid #e5e7eb"><td>'.$e($labels[$c->column_key] ?? $c->column_key).'</td><td style="color:#b91c1c">'.$e($c->old_value ?? '—').'</td><td style="color:#047857">'.$e($c->new_value ?? '—').'</td><td>'.$e($users[$c->changed_by] ?? '—').'</td><td>'.$e($c->changed_at?->setTimezone($tz)->format('d-M-Y h:i A')).'</td></tr>';
            }
            $out .= '</table>';
        }

        return $out.'</div>';
    }
}
