<?php

declare(strict_types=1);

namespace App\Modules\Accounting\Http\Controllers\Api;

use App\Shared\Http\Api\ApiController;
use App\Shared\Http\Api\ApiResponse;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

/**
 * Finance & Ledger → Voucher Audit (AnnotechERP 23/06): read-only registers
 * over the voucher log the voucher screens write (acc_voucher_logs, 23/03).
 *
 * - Voucher Activity Trail: every state change — raised, amended, approved,
 *   posted, unposted, removed.
 * - Deleted Voucher History: the removed vouchers, kept as they stood; the
 *   number stays text, for the row it pointed at is gone.
 * - Voucher Amendment Log: the amendments only; the note names what changed
 *   against the voucher as it stood before.
 *
 * Nothing here is written or edited — an audit trail that could be edited
 * would not be one.
 */
class VoucherAuditApiController extends ApiController
{
    public const KINDS = ['activity' => null, 'deleted' => ['DELETE'], 'amended' => ['UPDATE']];

    /** Header fields compared between one amendment and what came before it. */
    private const FIELDS = ['voucher_date' => 'f.voucher_date', 'voucher_type' => 'f.voucher_type', 'trx_source' => 'f.source_ref', 'bill_no' => 'f.bill_no', 'voucher_for_type' => 'f.voucher_for_type', 'voucher_for' => 'f.voucher_for', 'narration' => 'f.narration', 'action_status' => 'f.action_status'];

    public function index(Request $request): JsonResponse
    {
        $this->allowEither('accounting.voucher.view', 'accounting.voucher.manage');
        $kind = $request->validate(['kind' => ['required', Rule::in(array_keys(self::KINDS))]])['kind'];
        $c = $this->caller()->companyId;
        $company = (string) DB::table('companies')->where('id', $c)->value('name');
        $all = DB::table('acc_voucher_logs as g')->leftJoin('users as u', 'u.id', '=', 'g.user_id')->where('g.company_id', $c)->orderBy('g.created_at')->orderBy('g.id')->get(['g.*', 'u.name as by']);
        $before = [];
        $rows = [];
        foreach ($all as $g) {
            $snap = json_decode($g->snapshot, true) ?: [];
            $prev = $before[$g->voucher_id] ?? null;
            $before[$g->voucher_id] = $snap;
            if (self::KINDS[$kind] !== null && ! in_array($g->action, self::KINDS[$kind], true)) {
                continue;
            }
            $rows[] = ['id' => $g->id, 'action' => $g->action, 'posting_status' => $g->posting_status, 'voucher_no' => $g->voucher_no, 'voucher_ref_no' => $g->voucher_ref_no, 'voucher_date' => $snap['voucher_date'] ?? null, 'company' => $company,
                'voucher_type' => $snap['voucher_type'] ?? null, 'trx_source' => $snap['trx_source'] ?? null, 'voucher_for_type' => $snap['voucher_for_type'] ?? null, 'voucher_for' => $snap['voucher_for'] ?? null, 'created_at' => $snap['created_at'] ?? null, 'created_by' => $snap['prepared_by'] ?? null,
                'action_status' => $snap['action_status'] ?? null, 'at' => CarbonImmutable::parse($g->created_at)->timezone('Asia/Dhaka')->toDateTimeString(), 'by' => $g->by, 'bill_no' => $snap['bill_no'] ?? null, 'amount' => (float) ($snap['amount'] ?? 0), 'note' => $this->note($g->action, $snap, $prev)];
        }

        return ApiResponse::ok(array_reverse($rows));
    }

    /** What the row says in a line: the fields an amendment changed, or what a removed voucher held. */
    private function note(string $action, array $snap, ?array $prev): ?string
    {
        $lines = $snap['lines'] ?? [];
        if ($action === 'DELETE') {
            return __('accounting.vo.au.note_deleted', ['n' => count($lines), 'amount' => number_format((float) ($snap['amount'] ?? 0), 2)]);
        }
        if ($action !== 'UPDATE') {
            return $snap['narration'] ?? null;
        }
        if (! $prev) {
            return __('accounting.vo.au.note_amended');
        }
        $changed = [];
        foreach (self::FIELDS as $field => $label) {
            if (($snap[$field] ?? null) != ($prev[$field] ?? null)) {
                $changed[] = __("accounting.vo.{$label}");
            }
        }
        $shape = fn (array $ls) => array_map(fn ($l) => [$l['account_id'] ?? null, $l['cost_center_id'] ?? null, (float) ($l['debit'] ?? 0), (float) ($l['credit'] ?? 0), $l['note'] ?? null, $l['bill_no'] ?? null, $l['ref_no'] ?? null], $ls);
        if ($shape($lines) != $shape($prev['lines'] ?? [])) {
            $changed[] = (float) ($snap['amount'] ?? 0) !== (float) ($prev['amount'] ?? 0)
                ? __('accounting.vo.au.note_lines_amount', ['from' => number_format((float) ($prev['amount'] ?? 0), 2), 'to' => number_format((float) ($snap['amount'] ?? 0), 2)])
                : __('accounting.vo.au.note_lines');
        }

        return $changed ? __('accounting.vo.au.note_changed', ['what' => implode(', ', $changed)]) : __('accounting.vo.au.note_resaved');
    }
}
