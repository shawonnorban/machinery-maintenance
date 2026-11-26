<?php

declare(strict_types=1);

namespace App\Modules\Prototype\Support;

use App\Modules\Merchandise\Http\Controllers\Api\StyleApiController;
use App\Modules\Merchandise\Models\Buyer;
use App\Modules\Merchandise\Models\BuyerOffice;
use App\Modules\Merchandise\Models\BuyerOfficeLink;
use App\Modules\Merchandise\Models\Order;
use App\Modules\Merchandise\Models\SampleType;
use App\Modules\Merchandise\Models\Style;
use App\Modules\Merchandise\Support\SampleTypeDefaults;
use App\Modules\Prototype\Models\SampleRequisition;
use App\Modules\Tenancy\Models\Department;
use App\Shared\Tenancy\TenantContext;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

/**
 * What the Prototype Desk screens share (AnnotechERP 07): their document
 * numbers — SR-2026-00001, SMP-, DEV-, REV- — one series per company and
 * year, and the pick lists their forms offer.
 */
final class Prototype
{
    /** The next number of a series: `{PREFIX}-{year}-{five digits}`, by the document's own date. */
    public static function nextNumber(string $prefix, string $table, string $column, string $date): string
    {
        $company = app(TenantContext::class)->companyId();
        $head = $prefix.'-'.CarbonImmutable::parse($date)->format('Y').'-';
        DB::select('select pg_advisory_xact_lock(hashtext(?))', ["{$table}:{$head}:{$company}"]);
        $last = DB::table($table)->where('company_id', $company)->where($column, 'like', $head.'%')->pluck($column)
            ->map(fn (string $v): int => (int) substr($v, strlen($head)))->max();

        return $head.str_pad((string) (1 + (int) $last), 5, '0', STR_PAD_LEFT);
    }

    /** The company's people, by name: the pickers of who requested, decides, makes or receives. @return list<array{value: string, label: string}> */
    public static function users(): array
    {
        return DB::table('company_users')->join('users', 'users.id', '=', 'company_users.user_id')
            ->where('company_users.company_id', app(TenantContext::class)->companyId())->where('company_users.status', 'ACTIVE')
            ->orderBy('users.name')->get(['users.id', 'users.name'])
            ->map(fn ($u): array => ['value' => $u->id, 'label' => $u->name])->all();
    }

    /** @return array<string, string> user id => name */
    public static function names(array $ids): array
    {
        $ids = array_values(array_filter(array_unique($ids)));

        return $ids === [] ? [] : DB::table('users')->whereIn('id', $ids)->pluck('name', 'id')->all();
    }

    /** Every pick list the sample requisition form offers. */
    public static function requisitionOptions(): array
    {
        // A new company's sample types arrive the first time any of their lists is read.
        SampleTypeDefaults::ensure(app(TenantContext::class)->companyId());
        $buyers = Buyer::query()->where('active', true)->orderBy('display_order')->orderBy('short_name')->get(['id', 'code', 'short_name']);
        $houses = BuyerOffice::query()->where('active', true)->where('office_type', 'BUYING_HOUSE')->get(['id', 'name'])->keyBy('id');
        $links = BuyerOfficeLink::query()->whereIn('buyer_office_id', $houses->keys())->get(['buyer_id', 'buyer_office_id']);

        return [
            'buyers' => $buyers->map(fn (Buyer $b): array => [
                'value' => $b->id, 'label' => "{$b->short_name} ({$b->code})",
                'houses' => $links->where('buyer_id', $b->id)->map(fn ($l): array => ['value' => $l->buyer_office_id, 'label' => $houses[$l->buyer_office_id]->name])->values()->all(),
            ])->all(),
            'styles' => Style::query()->whereNotIn('status', ['CANCELLED'])->orderBy('style_ref')->get(['id', 'buyer_id', 'style_ref', 'style_ext', 'description', 'season'])
                ->map(fn (Style $s): array => ['value' => $s->id, 'buyer_id' => $s->buyer_id, 'label' => trim($s->style_ref.($s->style_ext ? "-{$s->style_ext}" : '')).($s->description ? " [{$s->description}]" : ''), 'name' => $s->description, 'season' => $s->season])->all(),
            'orders' => Order::query()->whereNotIn('status', ['CANCELLED'])->orderByDesc('created_at')->get(['id', 'buyer_id', 'style_id', 'order_no', 'job_no'])
                ->map(fn (Order $o): array => ['value' => $o->id, 'buyer_id' => $o->buyer_id, 'style_id' => $o->style_id, 'label' => ($o->job_no ? "{$o->job_no} — " : '').$o->order_no.($o->job_no ? " [{$o->job_no}]" : '')])->all(),
            'sample_types' => SampleType::query()->where('active', true)->orderBy('name')->get(['id', 'code', 'name', 'stage', 'order_required', 'po_required'])
                ->map(fn (SampleType $t): array => ['value' => $t->id, 'label' => "{$t->name} ({$t->code})", 'stage' => $t->stage, 'order_bound' => (bool) ($t->order_required || $t->po_required)])->all(),
            'seasons' => StyleApiController::seasons(),
            'stages' => SampleType::STAGES,
            'priorities' => SampleRequisition::PRIORITIES,
            'departments' => Department::query()->orderBy('name')->get(['id', 'name'])->map(fn ($d): array => ['value' => $d->id, 'label' => $d->name])->all(),
            'users' => self::users(),
        ];
    }
}
