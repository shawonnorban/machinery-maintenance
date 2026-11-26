<?php

declare(strict_types=1);

namespace App\Modules\Merchandise\Http\Controllers\Api;

use App\Modules\Identity\Models\User;
use App\Modules\Merchandise\Models\BuyerOffice;
use App\Modules\Merchandise\Models\RateLine;
use App\Modules\Merchandise\Models\RateLineRate;
use App\Modules\Merchandise\Support\MerchandiseDefaults;
use App\Shared\Http\Api\ApiController;
use App\Shared\Http\Api\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

/**
 * Inquiry Rate Chart → Rate Chart and Rate History (AnnotechERP 06/01): a buyer
 * office's latest rate for every rate line, and every revision before it. A
 * rate is never edited — a new effective date adds a revision, and the earlier
 * rate stays in the history.
 */
class RateChartApiController extends ApiController
{
    public function show(string $office): JsonResponse
    {
        $this->allow('merchandise.master.view');
        $row = BuyerOffice::query()->whereKey($office)->firstOrFail();
        MerchandiseDefaults::ensureRateLines($this->caller()->companyId);

        return ApiResponse::ok($this->chart($row));
    }

    public function save(Request $request, string $office): JsonResponse
    {
        $this->allow('merchandise.master.manage');
        $row = BuyerOffice::query()->whereKey($office)->firstOrFail();
        $company = $this->caller()->companyId;
        $data = $request->validate([
            'rates' => ['present', 'array'],
            'rates.*.rate_line_id' => ['required', 'distinct', Rule::exists('rate_lines', 'id')->where('company_id', $company)],
            'rates.*.rate' => ['required', 'numeric', 'min:0', 'max:9999999999'],
            'rates.*.currency' => ['required', 'string', 'size:3', 'alpha'],
            'rates.*.effective_from' => ['required', 'date'],
        ]);

        DB::transaction(function () use ($data, $row, $company): void {
            $latest = $this->latest($row);
            foreach ($data['rates'] as $rate) {
                $current = $latest->get($rate['rate_line_id']);
                $currency = strtoupper($rate['currency']);
                $unchanged = $current
                    && (float) $current->rate === (float) $rate['rate']
                    && $current->currency === $currency
                    && $current->effective_from->format('Y-m-d') === substr($rate['effective_from'], 0, 10);
                if ($unchanged) {
                    continue;
                }
                // The same line on the same day is one revision, corrected in place.
                RateLineRate::query()->updateOrCreate(
                    ['rate_line_id' => $rate['rate_line_id'], 'buyer_office_id' => $row->id, 'effective_from' => substr($rate['effective_from'], 0, 10)],
                    ['company_id' => $company, 'rate' => $rate['rate'], 'currency' => $currency, 'entered_by' => $this->caller()->user?->id],
                );
            }
        });

        return ApiResponse::ok($this->chart($row));
    }

    public function history(string $office): JsonResponse
    {
        $this->allow('merchandise.master.view');
        $row = BuyerOffice::query()->whereKey($office)->firstOrFail();
        $lines = RateLine::query()->get()->keyBy('id');
        $names = User::query()->whereIn('id', RateLineRate::query()->where('buyer_office_id', $row->id)->pluck('entered_by')->filter()->unique())->pluck('name', 'id');

        return ApiResponse::ok(RateLineRate::query()->where('buyer_office_id', $row->id)->orderByDesc('effective_from')->orderByDesc('created_at')->get()
            ->map(fn (RateLineRate $r): array => [
                'id' => $r->id,
                'rate_group' => $lines[$r->rate_line_id]?->rate_group,
                'description' => $lines[$r->rate_line_id]?->description,
                'uom' => $lines[$r->rate_line_id]?->uom,
                'rate' => (float) $r->rate,
                'currency' => $r->currency,
                'effective_from' => $r->effective_from?->format('Y-m-d'),
                'entered_by' => $r->entered_by ? ($names[$r->entered_by] ?? null) : null,
                'entered_at' => $r->created_at?->toIso8601String(),
            ])->all());
    }

    /** Every buyer office, with how many lines it has a rate for. */
    public function offices(): JsonResponse
    {
        $this->allow('merchandise.master.view');
        $counts = RateLineRate::query()->selectRaw('buyer_office_id, count(distinct rate_line_id) as n')->groupBy('buyer_office_id')->pluck('n', 'buyer_office_id');

        return ApiResponse::ok(BuyerOffice::query()->orderBy('name')->get()
            ->map(fn (BuyerOffice $o): array => ['id' => $o->id, 'name' => $o->name, 'count' => (int) ($counts[$o->id] ?? 0)])->all());
    }

    /**
     * @return Collection<string, RateLineRate>
     */
    private function latest(BuyerOffice $office)
    {
        return RateLineRate::query()->where('buyer_office_id', $office->id)->orderByDesc('effective_from')->get()->unique('rate_line_id')->keyBy('rate_line_id');
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function chart(BuyerOffice $office): array
    {
        $latest = $this->latest($office);

        return RateLine::query()->where('active', true)->orderBy('sort_order')->get()->map(function (RateLine $l) use ($latest): array {
            $r = $latest->get($l->id);

            return ['rate_line_id' => $l->id, 'rate_group' => $l->rate_group, 'description' => $l->description, 'uom' => $l->uom, 'rate' => $r ? (float) $r->rate : null, 'currency' => $r?->currency, 'effective_from' => $r?->effective_from?->format('Y-m-d')];
        })->all();
    }
}
