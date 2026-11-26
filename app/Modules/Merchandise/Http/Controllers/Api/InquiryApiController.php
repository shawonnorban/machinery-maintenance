<?php

declare(strict_types=1);

namespace App\Modules\Merchandise\Http\Controllers\Api;

use App\Modules\MaterialProcurement\Models\MaterialSupplier;
use App\Modules\Merchandise\Models\Buyer;
use App\Modules\Merchandise\Models\BuyerOffice;
use App\Modules\Merchandise\Models\BuyerOfficeLink;
use App\Modules\Merchandise\Models\BuyerSupplierConfig;
use App\Modules\Merchandise\Models\Inquiry;
use App\Modules\Merchandise\Models\RateLine;
use App\Modules\Merchandise\Models\RateLineRate;
use App\Modules\Merchandise\Resources\InquiryResource;
use App\Shared\Http\Api\ApiController;
use App\Shared\Http\Api\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Validation\ValidationException;

/**
 * Price Quotation Entry's panels (AnnotechERP 06/05): Quotation — the buyers
 * that have inquiries, each buyer's inquiries, and moving one along (send the
 * quotation, revise it, confirm it, cancel it); Buyer Rates — the inquiry rate
 * chart in force for the inquiry's buyer on the inquiry date, with the buyer's
 * nominated and alternative suppliers.
 */
class InquiryApiController extends ApiController
{
    /** The moves and the statuses each may start from. */
    private const MOVES = [
        'send' => ['DRAFT', 'REVISED', 'QUOTATION_SENT'],
        'revise' => ['DRAFT', 'QUOTATION_SENT', 'REVISED'],
        'confirm' => ['DRAFT', 'QUOTATION_SENT', 'REVISED'],
        'cancel' => ['DRAFT', 'QUOTATION_SENT', 'REVISED'],
    ];

    /** Buyers with at least one inquiry, and how many. */
    public function buyers(): JsonResponse
    {
        $this->allow('merchandise.inquiry.view');
        $counts = Inquiry::query()->selectRaw('buyer_id, count(*) as n')->groupBy('buyer_id')->pluck('n', 'buyer_id');
        $buyers = Buyer::query()->whereIn('id', $counts->keys())->orderBy('short_name')->get(['id', 'code', 'short_name', 'name']);

        return ApiResponse::ok($buyers->map(fn (Buyer $b): array => ['id' => $b->id, 'label' => $b->short_name ?: $b->name, 'code' => $b->code, 'count' => (int) $counts[$b->id]])->values()->all());
    }

    /** One buyer's inquiries, newest first. */
    public function ofBuyer(string $buyer): JsonResponse
    {
        $this->allow('merchandise.inquiry.view');
        $resource = app(InquiryResource::class);
        $rows = Inquiry::query()->where('buyer_id', $buyer)->latest('inquiry_date')->latest('inquiry_no')->get();

        return ApiResponse::ok($rows->map(fn (Inquiry $i): array => $resource->present($i))->all());
    }

    public function move(string $inquiry, string $move): JsonResponse
    {
        $this->allow('merchandise.inquiry.manage');
        abort_unless(isset(self::MOVES[$move]), 404);
        $row = Inquiry::query()->whereKey($inquiry)->firstOrFail();
        $status = $row->status;

        if (! in_array($status, self::MOVES[$move], true)) {
            $key = $status === 'CONFIRMED' ? "merchandise.inquiry.refused.confirmed_{$move}" : 'merchandise.inquiry.refused.cancelled';
            throw ValidationException::withMessages(['status' => __($key, ['no' => $row->inquiry_no])]);
        }

        match ($move) {
            'send' => $row->update(['status' => 'QUOTATION_SENT']),
            'revise' => $row->update(['status' => 'REVISED', 'revision' => $row->revision + 1]),
            'confirm' => $row->update(['status' => 'CONFIRMED']),
            'cancel' => $row->update(['status' => 'CANCELLED']),
        };
        $row->refresh();

        return ApiResponse::ok([
            'inquiry' => app(InquiryResource::class)->present($row),
            'message' => __("merchandise.inquiry.moved.{$move}", ['no' => $row->inquiry_no, 'rev' => $row->revision]),
        ]);
    }

    /**
     * Buyer Rates: every active line of the inquiry rate chart, for each of the
     * buyer's offices, with the rate in force on the inquiry date.
     */
    public function rates(string $inquiry): JsonResponse
    {
        $this->allow('merchandise.inquiry.view');
        $row = Inquiry::query()->whereKey($inquiry)->firstOrFail();
        $date = $row->inquiry_date->toDateString();

        $officeIds = BuyerOfficeLink::query()->where('buyer_id', $row->buyer_id)->pluck('buyer_office_id');
        $offices = BuyerOffice::query()->whereIn('id', $officeIds)->orderBy('name')->get(['id', 'name']);
        $lines = RateLine::query()->where('active', true)->orderByRaw($this->groupOrder())->orderBy('sort_order')->orderBy('description')->get();

        // The newest rate on or before the inquiry date, per line and office.
        $rates = RateLineRate::query()->whereIn('buyer_office_id', $officeIds)->whereDate('effective_from', '<=', $date)
            ->orderBy('effective_from')->get()
            ->keyBy(fn (RateLineRate $r): string => $r->rate_line_id.'|'.$r->buyer_office_id);

        $config = BuyerSupplierConfig::query()->where('buyer_id', $row->buyer_id)->whereNull('item_category_id')->where('active', true)->first();
        $supplier = fn (?string $id): ?string => $id ? MaterialSupplier::query()->whereKey($id)->value('name') : null;
        $nominated = $supplier($config?->nominated_supplier_id);
        $alternative = $supplier($config?->alternative_supplier_id);

        $out = [];
        foreach ($offices as $office) {
            foreach ($lines as $line) {
                $rate = $rates[$line->id.'|'.$office->id] ?? null;
                $out[] = [
                    'key' => $line->id.'|'.$office->id,
                    'rate_group' => $line->rate_group,
                    'description' => $line->description,
                    'uom' => $line->uom,
                    'rate' => $rate ? (float) $rate->rate : null,
                    'currency' => $rate?->currency,
                    'effective_from' => $rate?->effective_from?->toDateString(),
                    'buyer_office' => $office->name,
                    'nominated_supplier' => $nominated,
                    'alternative_supplier' => $alternative,
                    'quoted' => $rate !== null,
                ];
            }
        }

        return ApiResponse::ok(['inquiry' => app(InquiryResource::class)->present($row), 'rates' => $out]);
    }

    private function groupOrder(): string
    {
        $cases = collect(RateLine::GROUPS)->map(fn (string $g, int $i): string => "WHEN '{$g}' THEN {$i}")->implode(' ');

        return "CASE rate_group {$cases} ELSE 99 END";
    }
}
