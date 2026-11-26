<?php

declare(strict_types=1);

namespace App\Modules\PreBudget\Console;

use App\Modules\OrderSummary\Models\OsOrder;
use App\Modules\PreBudget\Models\PbRequisition;
use App\Modules\PreBudget\Support\MerchLists;
use App\Modules\Tenancy\Models\Company;
use App\Shared\Formula\Formula;
use App\Shared\Tenancy\TenantContext;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Requisitions made before the order picker (user, 2026-11-24): each linked
 * to its Order Summary order only when there is no doubt — the same buyer,
 * style and season (and the same JOB No when the requisition has one) and
 * exactly one such order not linked to another requisition. A requisition's
 * rows (its parts: the same buyer, style, season and JOB No) go to the same
 * order. Everything else is listed, not guessed: no order, or several.
 * Without --apply it only reports.
 */
class LinkOrdersCommand extends Command
{
    protected $signature = 'pb:link-orders {company : Company code (or id)} {--apply : Link the sure matches (otherwise only report)}';

    protected $description = 'Link requisitions made before the order picker to their Order Summary orders (sure matches only)';

    public function handle(TenantContext $tenant): int
    {
        $ref = (string) $this->argument('company');
        $company = Company::query()->where('code', $ref)->orWhere('id', $ref)->first();
        if (! $company) {
            $this->error("No company {$ref}.");

            return self::FAILURE;
        }
        $tenant->set($company->id);
        $reqs = PbRequisition::query()->where('company_id', $company->id)->whereNull('os_order_id')->where('status', PbRequisition::COMPLETE)->orderBy('serial_no')->get();
        $taken = PbRequisition::query()->where('company_id', $company->id)->whereNotNull('os_order_id')->pluck('os_order_id')->flip();
        $orders = OsOrder::query()->where('company_id', $company->id)->get(['id', 'serial_no', 'values'])->groupBy(fn (OsOrder $o) => self::key(
            $o->values['buyer'] ?? null, ($o->values['style_number_name'] ?? [])[0] ?? null, $o->values['season_quarter'] ?? null,
        ));

        $sure = [];
        $rows = [];
        // One requisition = its rows of one buyer, style, season and JOB No.
        foreach ($reqs->groupBy(fn (PbRequisition $r) => self::key($r->values['buyer'] ?? null, $r->values['style'] ?? null, $r->values['season'] ?? null).'|'.self::jobs($r->values['job_no'] ?? null)) as $group) {
            $v = $group->first()->values;
            $jobs = self::jobs($v['job_no'] ?? null);
            $found = ($orders[self::key($v['buyer'] ?? null, $v['style'] ?? null, $v['season'] ?? null)] ?? collect())
                ->reject(fn (OsOrder $o) => isset($taken[$o->id]))
                ->filter(fn (OsOrder $o) => $jobs === '' || self::jobs($o->values['job_no'] ?? null) === $jobs)->values();
            $serials = $group->pluck('serial_no')->implode(', ');
            $label = trim(($v['buyer'] ?? '—').' · '.($v['style'] ?? '—').' · '.($v['season'] ?? '—').($jobs !== '' ? " · {$jobs}" : ''));
            if ($found->count() === 1) {
                $sure[] = [$group, $found->first()];
                $taken[$found->first()->id] = true;
                $rows[] = [$serials, $label, 'SSL '.$found->first()->serial_no, 'link'];
            } else {
                $rows[] = [$serials, $label, $found->isEmpty() ? '—' : 'SSL '.$found->pluck('serial_no')->implode(', '), $found->isEmpty() ? 'no order' : 'several orders'];
            }
        }
        $this->table(['Requisition S/N', 'Buyer · Style · Season · JOB No', 'Order', 'Result'], $rows);
        if ($this->option('apply') && $sure !== []) {
            DB::transaction(function () use ($sure): void {
                foreach ($sure as [$group, $order]) {
                    PbRequisition::query()->whereKey($group->pluck('id'))->update(['os_order_id' => $order->id]);
                }
            });
        }
        $this->info(sprintf('%d requisition(s) checked: %d sure match(es)%s, %d left as they are.', $reqs->count(), collect($sure)->sum(fn ($s) => $s[0]->count()), $this->option('apply') ? ' linked' : ' (run with --apply to link)', $reqs->count() - collect($sure)->sum(fn ($s) => $s[0]->count())));

        return self::SUCCESS;
    }

    private static function key(mixed $buyer, mixed $style, mixed $season): string
    {
        return MerchLists::squash(Formula::text($buyer)).'|'.MerchLists::squash(Formula::text($style)).'|'.MerchLists::squash(Formula::text($season));
    }

    private static function jobs(mixed $raw): string
    {
        return Formula::blank($raw) ? '' : collect((array) $raw)->map(fn ($j) => strtoupper(trim((string) $j)))->filter()->sort()->implode(',');
    }
}
