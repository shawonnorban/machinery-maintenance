<?php

declare(strict_types=1);

namespace App\Modules\Knitting\Support\Reports;

use App\Modules\Merchandise\Models\Buyer;
use App\Modules\Merchandise\Models\Order;
use App\Modules\Knitting\Support\KnittingLabels;
use App\Modules\Knitting\Support\YarnProcurement;
use App\Shared\Tenancy\TenantContext;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Support\Facades\DB;

/**
 * One report run's parameter boxes (From/To Date, Yarn Item, YR Approve?,
 * Date Type, Buyer A/C, Style/Order#, Store, Production Type, QC boxes …)
 * and the names its ids stand for, read once per run.
 */
final class ReportQuery
{
    /** @var array<string, mixed> */
    private array $cache = [];

    /** @param array<string, mixed> $f */
    public function __construct(public readonly array $f) {}

    public function from(): ?string
    {
        return $this->f['from'] ?? null;
    }

    public function to(): ?string
    {
        return $this->f['to'] ?? null;
    }

    public function get(string $key): ?string
    {
        $v = $this->f[$key] ?? null;

        return $v === null || $v === '' ? null : (string) $v;
    }

    /** The From/To window on a date (or timestamp) column. */
    public function window(Builder|QueryBuilder $q, string $column): Builder|QueryBuilder
    {
        return $q->when($this->from(), fn ($q, $d) => $q->whereDate($column, '>=', $d))->when($this->to(), fn ($q, $d) => $q->whereDate($column, '<=', $d));
    }

    /** Whether a date falls in the window (rows built in PHP). */
    public function inWindow(?string $date): bool
    {
        if ($date === null) {
            return false;
        }
        $d = substr($date, 0, 10);

        return (! $this->from() || $d >= $this->from()) && (! $this->to() || $d <= $this->to());
    }

    /** "Up to the To Date" — a stock is struck on the window's end, today when none. */
    public function asOf(): string
    {
        return $this->to() ?? CarbonImmutable::now('Asia/Dhaka')->toDateString();
    }

    /** Yarn Item's composition and count ("<composition>|<count>"). */
    public function yarnItem(): array
    {
        $v = $this->get('yarn_item');

        return $v ? array_pad(explode('|', $v, 2), 2, null) : [null, null];
    }

    public function company(): string
    {
        return (string) app(TenantContext::class)->companyId();
    }

    // ── Names ─────────────────────────────────────────────────────────────

    /** @return array<string, string> */
    public function buyers(): array
    {
        return $this->cache['buyers'] ??= Buyer::query()->get(['id', 'name', 'short_name'])->mapWithKeys(fn (Buyer $b) => [$b->id => $b->short_name ?: $b->name])->all();
    }

    /** @return array<string, Order> */
    public function orders(): array
    {
        return $this->cache['orders'] ??= Order::query()->get(['id', 'order_no', 'job_no', 'style_no', 'work_style_no', 'buyer_id', 'ship_date', 'order_type'])->keyBy('id')->all();
    }

    public function order(?string $id): ?Order
    {
        return $id ? ($this->orders()[$id] ?? null) : null;
    }

    public function orderNo(?string $id): ?string
    {
        return $this->order($id)?->order_no;
    }

    public function style(?string $id): ?string
    {
        $o = $this->order($id);

        return $o ? ($o->work_style_no ?: $o->style_no) : null;
    }

    public function job(?string $id): ?string
    {
        return $this->order($id)?->job_no;
    }

    /** @return array<string, string> */
    public function parties(): array
    {
        return $this->cache['parties'] ??= DB::table('mp_suppliers')->where('company_id', $this->company())->pluck('name', 'id')->all();
    }

    /** @return array<string, string> */
    public function stores(): array
    {
        return $this->cache['stores'] ??= DB::table('stores')->where('company_id', $this->company())->pluck('name', 'id')->all();
    }

    /** @return array<string, string> */
    public function floors(): array
    {
        return $this->cache['floors'] ??= DB::table('floors')->where('company_id', $this->company())->pluck('name', 'id')->all();
    }

    /** @return array<string, string> */
    public function shifts(): array
    {
        return $this->cache['shifts'] ??= DB::table('hr_shifts')->where('company_id', $this->company())->pluck('name', 'id')->all();
    }

    /** @return array<string, string> */
    public function machines(): array
    {
        return $this->cache['machines'] ??= collect(KnittingLabels::machines())->map(fn ($m) => $m['no'])->all();
    }

    /** @return array<string, string> */
    public function refs(): array
    {
        return $this->cache['refs'] ??= KnittingLabels::refs();
    }

    /** @return array<string, string> */
    public function colours(): array
    {
        return $this->cache['colours'] ??= DB::table('colours')->where('company_id', $this->company())->pluck('name', 'id')->all();
    }

    /** @return array<string, string> */
    public function fabricTypes(): array
    {
        return $this->cache['fabric_types'] ??= DB::table('fabric_types')->where('company_id', $this->company())->pluck('name', 'id')->all();
    }

    /** @return array<string, string> */
    public function employees(): array
    {
        return $this->cache['employees'] ??= DB::table('hr_employees')->where('company_id', $this->company())->get(['id', 'name', 'employee_code'])
            ->mapWithKeys(fn ($e) => [$e->id => $e->employee_code ? "{$e->name} ({$e->employee_code})" : $e->name])->all();
    }

    /** @return array<string, string> */
    public function users(): array
    {
        return $this->cache['users'] ??= DB::table('users')->pluck('name', 'id')->all();
    }

    /** @return array{compositions: array<string, string>, counts: array<string, string>} */
    public function yarnNames(): array
    {
        return $this->cache['yarn'] ??= YarnProcurement::names();
    }

    /** "100% Cotton 30/1" — a yarn line's composition and count. */
    public function yarn(?string $compositionId, ?string $countId, ?string $colour = null): ?string
    {
        $n = $this->yarnNames();

        return trim(implode(' ', array_filter([$n['counts'][$countId] ?? null, $n['compositions'][$compositionId] ?? null, $colour]))) ?: null;
    }

    /** Needle and oil items (the general store's), "NDL-VO-71 · Vosges needle". @return array<string, string> */
    public function items(): array
    {
        return $this->cache['items'] ??= DB::table('gs_items')->where('company_id', $this->company())->get(['id', 'item_code', 'name'])
            ->mapWithKeys(fn ($i) => [$i->id => "{$i->item_code} · {$i->name}"])->all();
    }

    /** The needle and oil stores. @return array<string, string> */
    public function gsStores(): array
    {
        return $this->cache['gs_stores'] ??= DB::table('stores')->where('company_id', $this->company())->pluck('name', 'id')->all();
    }
}
