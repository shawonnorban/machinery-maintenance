<?php

declare(strict_types=1);

namespace App\Modules\OrderSummary\Support;

use App\Modules\Identity\Models\MailService;
use App\Modules\OrderSummary\Models\OsChange;
use App\Modules\OrderSummary\Models\OsMailSetting;
use App\Modules\OrderSummary\Models\OsOrder;
use Illuminate\Support\Facades\DB;

/**
 * Order Summary's instant mail (user, 2026-11-22): an order's JOB No, PO
 * Number or Shipment ID Number added or changed is mailed at once to the
 * buyer's team (OrderScope: the merchandisers, their team head and the
 * department head) — one mail per person for the whole save, never to the
 * one who saved. The admin may turn it off or choose other columns in Mail
 * Settings; it goes through the edit mail's mail service. Run from the
 * save's notifications (SheetNotices → OrderNotices), after the response.
 */
final class OrderKeyMail
{
    /** The columns mailed when nobody has chosen others. */
    public const DEFAULT_COLUMNS = ['job_no', 'po_number', 'shipment_id_number'];

    /** Whether it is on, and which columns (the company's context set). @return array{0: bool, 1: list<string>} */
    public static function settings(?OsMailSetting $s): array
    {
        return [$s ? (bool) $s->instant_enabled : true, array_values($s?->instant_columns ?: self::DEFAULT_COLUMNS)];
    }

    /** @return int mails sent */
    public static function batch(string $batch): int
    {
        $s = OsMailSetting::query()->first();
        [$on, $columns] = self::settings($s);
        if (! $on || $columns === []) {
            return 0;
        }
        $changes = OsChange::query()->where('batch', $batch)->whereIn('kind', ['CREATE', 'EDIT'])->whereIn('column_key', $columns)->orderBy('changed_at')->get();
        if ($changes->isEmpty()) {
            return 0;
        }
        $companyId = (string) $changes->first()->company_id;
        $service = MailService::query()->where('company_id', $companyId)->where('active', true)
            ->when($s?->mail_service_id, fn ($q, $id) => $q->whereKey($id), fn ($q) => $q->orderByDesc('is_default'))->first();
        if (! $service) {
            return 0;
        }
        $actor = $changes->first()->changed_by;
        $byUser = [];
        foreach ($changes->groupBy('buyer_id') as $buyer => $rows) {
            if ($buyer === '' || $buyer === null) {
                continue;
            }
            foreach (OrderScope::recipientsFor((string) $buyer) as $u) {
                if ($u !== $actor) {
                    $byUser[$u] = ($byUser[$u] ?? collect())->merge($rows);
                }
            }
        }
        if ($byUser === []) {
            return 0;
        }
        $sheet = new Sheet($companyId);
        $labels = $sheet->columns->pluck('label', 'key');
        $orders = OsOrder::withTrashed()->whereKey($changes->pluck('order_id')->unique())->get()->keyBy('id');
        $users = DB::table('users')->pluck('name', 'id');
        $emails = DB::table('users')->whereIn('id', array_keys($byUser))->where('status', 'ACTIVE')->pluck('email', 'id');
        $what = $changes->pluck('column_key')->unique()->map(fn ($k) => $labels[$k] ?? $k)->implode(' / ');
        $by = $users[$actor] ?? '—';
        $mailer = MailDigest::mailer($service);
        $sent = 0;
        foreach ($byUser as $userId => $rows) {
            if (empty($emails[$userId])) {
                continue;
            }
            $orderCount = $rows->pluck('order_id')->unique()->count();
            $subject = __('ordersummary.mail.key_subject', ['what' => $what, 'n' => $orderCount, 'by' => $by]);
            $intro = __('ordersummary.mail.key_intro', ['by' => $by, 'what' => $what, 'n' => $orderCount]);
            $mailer->to($emails[$userId])->send(new OrderEditDigestMail(MailDigest::html($rows, $orders, $labels, $users, $intro), $service, $rows->count(), $subject));
            $sent++;
        }

        return $sent;
    }
}
