<?php

declare(strict_types=1);

namespace App\Modules\Chat\Support;

use App\Modules\Api\Support\ApiCaller;
use App\Modules\DevSample\Models\DevSample;
use App\Modules\ExportStatement\Models\ExportStatement;
use App\Modules\OrderSummary\Support\OrderScope;
use App\Modules\PreBudget\Models\PbBudget;
use App\Modules\PreBudget\Models\PbRequisition;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

/**
 * Records a chat message can mention with `#` (user, 2026-11-23): an Order
 * Summary job, a Pre-Budget budget or requisition, a development sample and
 * an export statement. Each answers only to whoever may read it — the
 * module's view permission and its own row scope (buyers / own rows) — so a
 * mention never shows someone a record their screens would not.
 *
 * A mention is `type` + `ref` (job no, budget no, serial) and the `token`
 * the message shows (#26NCL00001, #PB-…, #REQ-12, #DS-4, #ES-7).
 */
final class ChatRecords
{
    public const TYPES = [
        'os' => 'ordersummary.view',
        'pb' => 'prebudget.view',
        'req' => 'prebudget.view',
        'ds' => 'devsample.view',
        'es' => 'exportstatement.view',
    ];

    /** @return list<string> the kinds this caller may mention */
    public static function allowed(ApiCaller $caller): array
    {
        return array_values(array_filter(array_keys(self::TYPES), fn (string $t): bool => $caller->can(self::TYPES[$t])));
    }

    /** @return list<array{type: string, ref: string, token: string, title: string, sub: string}> */
    public static function search(ApiCaller $caller, string $type, string $q): array
    {
        // Every kind at once (the picker's first tab): a few of each, a typed "PB-…" or "REQ-…" finds its own kind.
        if ($type === 'all') {
            return collect(self::allowed($caller))->flatMap(fn (string $t) => array_slice(self::search($caller, $t, self::bare($t, $q)), 0, 5))->take(20)->values()->all();
        }
        if (! isset(self::TYPES[$type]) || ! $caller->can(self::TYPES[$type])) {
            return [];
        }
        $like = '%'.str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], trim($q)).'%';

        return match ($type) {
            'os' => self::searchJobs($caller, $like),
            'pb' => self::scopedBudgets($caller)
                ->where(fn ($w) => $w->where('budget_no', 'ilike', $like)->orWhere('buyer_name', 'ilike', $like)->orWhere('contract_no', 'ilike', $like))
                ->latest('updated_at')->limit(20)->get()
                ->map(fn (PbBudget $b) => ['type' => 'pb', 'ref' => $b->budget_no, 'token' => '#'.$b->budget_no, 'title' => $b->budget_no, 'sub' => self::join([$b->buyer_name, $b->contract_no, $b->status])])->all(),
            'req' => self::rows(self::scopedRequisitions($caller), $like, 'REQ', ['buyer', 'style', 'job_no']),
            'ds' => self::rows(self::visibleSamples($caller), $like, 'DS', ['buyer', 'styling', 'job_no']),
            'es' => self::rows(ExportStatement::query(), $like, 'ES', ['buyer_name', 'invoice_no', 'job_no']),
        };
    }

    /**
     * Cards for mentioned records, by "type:ref"; null where the caller may not
     * read it (or it is gone), so the chip stays a plain reference.
     *
     * @param  list<string>  $keys
     * @return array<string, array<string, mixed>|null>
     */
    public static function cards(ApiCaller $caller, array $keys): array
    {
        $out = [];
        foreach (array_slice(array_unique($keys), 0, 40) as $key) {
            [$type, $ref] = array_pad(explode(':', (string) $key, 2), 2, '');
            $out[$key] = isset(self::TYPES[$type]) && $ref !== '' && $caller->can(self::TYPES[$type]) ? self::card($caller, $type, $ref) : null;
        }

        return $out;
    }

    /** Whether a mention names a real record of this company (kept on the message as typed otherwise dropped). */
    public static function exists(string $companyId, string $type, string $ref): bool
    {
        return match ($type) {
            'os' => DB::table('os_orders')->where('company_id', $companyId)->whereNull('deleted_at')->whereRaw(self::jobMatch(), [$ref, $ref])->exists(),
            'pb' => DB::table('pb_budgets')->where('company_id', $companyId)->whereNull('deleted_at')->where('budget_no', $ref)->exists(),
            'req' => ctype_digit($ref) && DB::table('pb_requisitions')->where('company_id', $companyId)->whereNull('deleted_at')->where('serial_no', (int) $ref)->exists(),
            'ds' => ctype_digit($ref) && DB::table('ds_samples')->where('company_id', $companyId)->whereNull('deleted_at')->where('serial_no', (int) $ref)->exists(),
            'es' => ctype_digit($ref) && DB::table('es_statements')->where('company_id', $companyId)->whereNull('deleted_at')->where('serial_no', (int) $ref)->exists(),
            default => false,
        };
    }

    /** @return array<string, mixed>|null */
    private static function card(ApiCaller $caller, string $type, string $ref): ?array
    {
        if ($type === 'os') {
            $rows = self::scopedJobRows($caller)->whereRaw(self::jobMatch('os_orders'), [$ref, $ref])->get(['values']);
            if ($rows->isEmpty()) {
                return null;
            }
            $v = $rows->map(fn ($r) => is_array($r->values) ? $r->values : (json_decode((string) $r->values, true) ?: []));
            $ldd = $v->pluck('proposed_ldd_etd')->filter()->sort()->first();

            return self::shape('os', $ref, '#'.$ref, __('chat.card.order'), [
                [__('chat.card.buyer'), self::text($v->pluck('buyer')->flatten()->unique()->all())],
                [__('chat.card.style'), self::text($v->pluck('style_number_name')->flatten()->unique()->take(3)->all())],
                [__('chat.card.qty'), number_format((float) $v->sum(fn ($x) => (float) ($x['quantity_pcs'] ?? 0)))],
                [__('chat.card.pos'), (string) $rows->count()],
                [__('chat.card.ship'), $ldd ? (string) $ldd : null],
            ], self::text($v->pluck('shipment_status')->filter()->unique()->all()), '/order-summary/list?'.http_build_query(['f' => ['job_no' => [$ref]]]));
        }
        if ($type === 'pb') {
            $b = self::scopedBudgets($caller)->withCount('requisitions')->where('budget_no', $ref)->first();

            return $b ? self::shape('pb', $ref, '#'.$ref, __('chat.card.budget'), [
                [__('chat.card.buyer'), $b->buyer_name],
                [__('chat.card.contract'), $b->contract_no],
                [__('chat.card.requisitions'), (string) $b->requisitions_count],
            ], (string) $b->status, '/pre-budget/budget-overview?'.http_build_query(['budget' => $b->budget_no])) : null;
        }
        if (! ctype_digit($ref)) {
            return null;
        }
        [$query, $prefix, $title, $path, $fields, $status] = match ($type) {
            'req' => [self::scopedRequisitions($caller), 'REQ', __('chat.card.requisition'), '/pre-budget/all-requisition', [['buyer', 'buyer'], ['style', 'style'], ['job', 'job_no'], ['qty', 'order_qty'], ['delivery', 'delivery_date']], 'status'],
            'ds' => [self::visibleSamples($caller), 'DS', __('chat.card.sample'), '/dev-sample-status/list', [['buyer', 'buyer'], ['style', 'styling'], ['job', 'job_no'], ['deadline', 'submission_deadline']], 'sample_status'],
            'es' => [ExportStatement::query(), 'ES', __('chat.card.export'), '/export-statement/list', [['buyer', 'buyer_name'], ['invoice', 'invoice_no'], ['style', 'style_no'], ['job', 'job_no'], ['qty', 'export_qty'], ['ex_factory', 'ex_factory_date']], 'status'],
        };
        $row = $query->where('serial_no', (int) $ref)->first();
        if (! $row) {
            return null;
        }
        $values = is_array($row->values) ? $row->values : [];
        $lines = array_map(fn ($f) => [__('chat.card.'.$f[0]), self::text($values[$f[1]] ?? $row->getAttribute($f[1]))], $fields);
        $url = $path.'?'.http_build_query(['f' => ['serial_no' => [(string) $row->serial_no]]]); // the sheet opens on this one row

        return self::shape($type, $ref, "#{$prefix}-{$ref}", $title, $lines, self::text($row->getAttribute($status)), $url);
    }

    /** @param  list<array{0: string, 1: ?string}>  $lines  @return array<string, mixed> */
    private static function shape(string $type, string $ref, string $token, string $title, array $lines, ?string $status, string $url): array
    {
        return [
            'type' => $type, 'ref' => $ref, 'token' => $token, 'title' => $title, 'status' => $status ?: null, 'url' => $url,
            'fields' => array_values(array_map(fn ($l) => ['label' => $l[0], 'value' => $l[1]], array_filter($lines, fn ($l) => $l[1] !== null && $l[1] !== ''))),
        ];
    }

    /** @return list<array<string, string>> */
    private static function searchJobs(ApiCaller $caller, string $like): array
    {
        $rows = self::scopedJobRows($caller)
            ->crossJoin(DB::raw(self::jobElements('os_orders').' as e(job)'))
            ->where(fn ($w) => $w->where('e.job', 'ilike', $like)->orWhereRaw("os_orders.values->>'buyer' ilike ?", [$like])->orWhereRaw("(os_orders.values->'style_number_name')::text ilike ?", [$like])->orWhereRaw("(os_orders.values->'po_number')::text ilike ?", [$like]))
            ->groupBy('e.job')
            ->selectRaw("e.job, min(os_orders.values->>'buyer') as buyer, min((os_orders.values->'style_number_name')::text) as style, count(*) as n, max(os_orders.updated_at) as at")
            ->orderByDesc('at')->limit(20)->get();

        return $rows->map(fn ($r) => ['type' => 'os', 'ref' => $r->job, 'token' => '#'.$r->job, 'title' => $r->job,
            'sub' => self::join([$r->buyer, self::text(json_decode((string) $r->style, true)), trans_choice('chat.card.po_count', (int) $r->n, ['n' => $r->n])])])->all();
    }

    /** @param  list<string>  $keys  @return list<array<string, string>> */
    private static function rows(Builder $query, string $like, string $prefix, array $keys): array
    {
        return $query->where(fn ($w) => $w->whereRaw('cast(serial_no as text) ilike ?', [$like])->orWhereRaw('"values"::text ilike ?', [$like]))
            ->latest('updated_at')->limit(20)->get()
            ->map(function ($r) use ($prefix, $keys) {
                $v = is_array($r->values) ? $r->values : [];

                return ['type' => strtolower($prefix), 'ref' => (string) $r->serial_no, 'token' => "#{$prefix}-{$r->serial_no}", 'title' => "{$prefix}-{$r->serial_no}",
                    'sub' => self::join(array_map(fn ($k) => self::text($v[$k] ?? null), $keys))];
            })->all();
    }

    private static function scopedJobRows(ApiCaller $caller): \Illuminate\Database\Query\Builder
    {
        $query = DB::table('os_orders')->where('os_orders.company_id', $caller->companyId)->whereNull('os_orders.deleted_at');
        $scope = $caller->auditUserId() ? OrderScope::buyerIds($caller->auditUserId(), $caller->can('ordersummary.view_all')) : null;
        if ($scope !== null) {
            $query->where(fn ($q) => $q->whereIn('os_orders.buyer_id', $scope)->orWhere('os_orders.created_by', $caller->auditUserId()));
        }

        return $query;
    }

    /** A revised budget keeps its number: only its latest revision answers to it. */
    private static function scopedBudgets(ApiCaller $caller): Builder
    {
        return self::byBuyers(PbBudget::query(), $caller, 'prebudget.view_all')
            ->whereNotExists(fn ($q) => $q->from('pb_budgets as newer')->whereColumn('newer.budget_no', 'pb_budgets.budget_no')
                ->whereColumn('newer.company_id', 'pb_budgets.company_id')->whereColumn('newer.revision_no', '>', 'pb_budgets.revision_no')->whereNull('newer.deleted_at'));
    }

    private static function scopedRequisitions(ApiCaller $caller): Builder
    {
        return self::byBuyers(PbRequisition::query(), $caller, 'prebudget.view_all');
    }

    private static function visibleSamples(ApiCaller $caller): Builder
    {
        $query = DevSample::query();

        return $caller->can('devsample.view_all') ? $query : $query->where('created_by', $caller->auditUserId());
    }

    private static function byBuyers(Builder $query, ApiCaller $caller, string $viewAll): Builder
    {
        $me = $caller->auditUserId();
        $scope = $me ? OrderScope::buyerIds($me, $caller->can($viewAll)) : null;

        return $scope === null ? $query : $query->where(fn ($q) => $q->whereIn('buyer_id', $scope)->orWhere('created_by', $me));
    }

    /** The job numbers of a row's `job_no` cell (a list, or one string). */
    private static function jobElements(string $table): string
    {
        return "lateral jsonb_array_elements_text(case jsonb_typeof({$table}.\"values\"->'job_no') when 'array' then {$table}.\"values\"->'job_no' when 'string' then jsonb_build_array({$table}.\"values\"->'job_no') else '[]'::jsonb end)";
    }

    private static function jobMatch(string $table = 'os_orders'): string
    {
        return "(({$table}.\"values\"->'job_no') @> to_jsonb(array[?]::text[]) or ({$table}.\"values\"->>'job_no') = ?)";
    }

    /** A typed "REQ-12" / "DS-4" / "ES-7" searched within its kind as the bare serial. */
    private static function bare(string $type, string $q): string
    {
        $prefix = ['req' => 'REQ-', 'ds' => 'DS-', 'es' => 'ES-'][$type] ?? null;

        return $prefix && stripos($q, $prefix) === 0 ? substr($q, strlen($prefix)) : $q;
    }

    private static function text(mixed $value): ?string
    {
        if ($value instanceof \DateTimeInterface) {
            return $value->format('Y-m-d');
        }
        if (is_array($value)) {
            $value = implode(', ', array_filter(array_map(fn ($x) => is_scalar($x) ? (string) $x : null, $value), fn ($x) => $x !== null && $x !== ''));
        }
        $value = is_scalar($value) ? trim((string) $value) : '';

        return $value === '' ? null : $value;
    }

    /** @param  list<?string>  $parts */
    private static function join(array $parts): string
    {
        return implode(' · ', array_filter($parts, fn ($p) => $p !== null && $p !== ''));
    }
}
