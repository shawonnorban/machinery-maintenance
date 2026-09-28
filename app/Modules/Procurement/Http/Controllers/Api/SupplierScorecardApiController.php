<?php

declare(strict_types=1);

namespace App\Modules\Procurement\Http\Controllers\Api;

use App\Modules\Procurement\Actions\CalculateSupplierScorecard;
use App\Modules\Procurement\Models\SupplierScorecard;
use App\Modules\Vendor\Models\Vendor;
use App\Shared\Http\Api\ApiController;
use App\Shared\Http\Api\ApiResponse;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Supplier scorecards over the API (Procurement spec §15).
 *
 * Calculating one is a POST rather than something the GET does on the way
 * past: it writes a stored snapshot and moves the supplier's rating, and
 * an endpoint that quietly rewrote both every time a screen was opened
 * would make the number impossible to talk about.
 */
class SupplierScorecardApiController extends ApiController
{
    public const VIEW = 'procurement.scorecard.view_any';

    public const CALCULATE = 'procurement.scorecard.calculate';

    private const FILTERS = ['vendor_id'];

    private const SORTS = ['overall_score', 'period_end', 'on_time_percent', 'quality_reject_percent'];

    public function __construct(private readonly CalculateSupplierScorecard $scorecards) {}

    public function index(Request $request): JsonResponse
    {
        $this->allow(self::VIEW);

        $query = SupplierScorecard::query()->with('vendor:id,name,code,status,rating');

        if (is_string($search = $request->query('search')) && $search !== '') {
            $query->whereHas('vendor', fn ($v) => $v->whereRaw('LOWER(name) LIKE ?', ['%'.mb_strtolower($search).'%']));
        }

        // A card with nothing behind it is noise on a ranking screen, so
        // it is left out unless somebody asks for it.
        if (! $request->boolean('include_empty')) {
            $query->whereNotNull('overall_score');
        }

        $query = $this->applyFilters($query, $request, self::FILTERS);
        $query = $this->applySort($query, $request, self::SORTS, 'overall_score');

        return ApiResponse::paginated(
            $query->paginate($this->perPage($request))->withQueryString(),
            fn (SupplierScorecard $card): array => $this->card($card),
        );
    }

    /** The newest card for one supplier, for the vendor page. */
    public function forVendor(Vendor $vendor): JsonResponse
    {
        $this->allow(self::VIEW);

        $card = SupplierScorecard::where('vendor_id', $vendor->id)
            ->orderByDesc('period_end')
            ->with('vendor:id,name,code,status,rating')
            ->first();

        return ApiResponse::ok([
            'vendor' => ['id' => $vendor->id, 'name' => $vendor->name, 'rating' => $vendor->rating],
            'scorecard' => $card === null ? null : $this->card($card),
            'weights' => SupplierScorecard::WEIGHTS,
            'can_calculate' => $this->caller()->can(self::CALCULATE),
        ]);
    }

    public function calculate(Request $request): JsonResponse
    {
        $this->allow(self::CALCULATE);

        $data = $request->validate([
            'vendor_id' => ['nullable', 'string', 'size:26'],
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date'],
        ]);

        $to = isset($data['to']) ? CarbonImmutable::parse($data['to']) : CarbonImmutable::today();
        // Three months by default — see `ScoreSuppliers` for why a single
        // month is too short a window to judge a mill on.
        $from = isset($data['from']) ? CarbonImmutable::parse($data['from']) : $to->subMonths(3)->addDay();

        if (isset($data['vendor_id'])) {
            $card = $this->scorecards->forVendor(
                Vendor::findOrFail($data['vendor_id']), $from, $to, $this->caller()->auditUserId(),
            );

            return ApiResponse::created($this->card($card));
        }

        $result = $this->scorecards->forAllVendors($from, $to, $this->caller()->auditUserId());

        return ApiResponse::ok([
            'scored' => $result['cards'],
            'vendors' => $result['vendors'],
            'period_start' => $from->toDateString(),
            'period_end' => $to->toDateString(),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function card(SupplierScorecard $card): array
    {
        return [
            'id' => $card->id,
            'period_start' => $card->period_start?->toDateString(),
            'period_end' => $card->period_end?->toDateString(),
            'vendor' => $card->vendor === null ? null : [
                'id' => $card->vendor->id,
                'name' => $card->vendor->name,
                'code' => $card->vendor->code,
                'status' => $card->vendor->status,
                'rating' => $card->vendor->rating,
            ],
            'receipt_count' => (int) $card->receipt_count,
            'dated_receipt_count' => (int) $card->dated_receipt_count,
            'on_time_count' => (int) $card->on_time_count,
            'on_time_percent' => $card->on_time_percent,
            'received_value' => (string) $card->received_value,
            'rejected_value' => (string) $card->rejected_value,
            'quality_reject_percent' => $card->quality_reject_percent,
            'price_index' => $card->price_index,
            'price_score' => $card->price_score,
            'claim_count' => (int) $card->claim_count,
            'claim_frequency_percent' => $card->claim_frequency_percent,
            'overall_score' => $card->overall_score,
            'rating' => $card->asRating(),
            // Which measures had data; the rest were dropped from the
            // weighting rather than scored as zero.
            'components' => $card->components_json ?? [],
            'computed_at' => $card->computed_at?->toIso8601String(),
        ];
    }
}
