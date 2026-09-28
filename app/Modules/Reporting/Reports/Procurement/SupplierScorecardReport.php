<?php

declare(strict_types=1);

namespace App\Modules\Reporting\Reports\Procurement;

use App\Modules\Procurement\Models\SupplierScorecard;
use App\Modules\Reporting\Reports\Report;
use App\Modules\Reporting\Reports\ReportQuery;
use Illuminate\Database\Eloquent\Builder;

/**
 * Supplier scorecards as a table (Procurement spec §15, §17).
 *
 * The screen ranks and explains; this exports. It carries the
 * `components` column for the same reason the screen does — a card built
 * from delivery alone and one built from all four are both out of 100,
 * and a spreadsheet that drops that distinction is one somebody will sort
 * and act on without it.
 *
 * Cards with nothing behind them are left out: a supplier nobody ordered
 * from has not earned a place in a ranking.
 */
class SupplierScorecardReport extends Report
{
    public function key(): string
    {
        return 'procurement_supplier_scorecard';
    }

    public function group(): string
    {
        return 'procurement';
    }

    public function permission(): string
    {
        return 'procurement.scorecard.view_any';
    }

    public function filters(): array
    {
        return ['search'];
    }

    public function columns(): array
    {
        return [
            'vendor' => ['label' => 'report.columns.vendor'],
            'code' => ['label' => 'report.columns.code'],
            'period_start' => ['label' => 'report.columns.period_start'],
            'period_end' => ['label' => 'report.columns.period_end'],
            'overall_score' => ['label' => 'report.columns.overall_score', 'numeric' => true],
            'on_time_percent' => ['label' => 'report.columns.on_time_percent', 'numeric' => true],
            'quality_reject_percent' => ['label' => 'report.columns.reject_percent', 'numeric' => true],
            'price_index' => ['label' => 'report.columns.price_index', 'numeric' => true],
            'claim_count' => ['label' => 'report.columns.claims_filed', 'numeric' => true],
            'receipt_count' => ['label' => 'report.columns.receipts', 'numeric' => true],
            'components' => ['label' => 'report.columns.measures_counted'],
            'rating' => ['label' => 'report.columns.rating', 'numeric' => true],
        ];
    }

    public function rows(ReportQuery $query): iterable
    {
        foreach ($this->base($query)->lazy() as $card) {
            yield [
                'vendor' => $card->vendor?->name,
                'code' => $card->vendor?->code,
                'period_start' => $card->period_start?->toDateString(),
                'period_end' => $card->period_end?->toDateString(),
                'overall_score' => $card->overall_score,
                'on_time_percent' => $card->on_time_percent,
                'quality_reject_percent' => $card->quality_reject_percent,
                'price_index' => $card->price_index,
                'claim_count' => (int) $card->claim_count,
                'receipt_count' => (int) $card->receipt_count,
                // Which measures had data; the rest were dropped, not zeroed.
                'components' => implode(', ', array_map(
                    static fn (string $key): string => __("procurement.components.{$key}"),
                    $card->components_json ?? [],
                )),
                'rating' => $card->asRating(),
            ];
        }
    }

    protected function countable(ReportQuery $query): Builder
    {
        return $this->base($query);
    }

    private function base(ReportQuery $query): Builder
    {
        $builder = SupplierScorecard::query()
            ->with('vendor:id,name,code')
            ->whereNotNull('overall_score');

        if (is_string($search = $query->get('search')) && $search !== '') {
            $builder->whereHas('vendor', fn ($v) => $v->whereRaw('LOWER(name) LIKE ?', ['%'.mb_strtolower($search).'%']));
        }

        return $builder->orderByDesc('period_end')->orderByDesc('overall_score');
    }
}
