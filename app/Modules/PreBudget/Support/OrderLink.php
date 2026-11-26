<?php

declare(strict_types=1);

namespace App\Modules\PreBudget\Support;

use App\Modules\OrderSummary\Models\OsOrder;
use App\Modules\OrderSummary\Support\OrderScope;
use App\Shared\Files\Models\FileAttachment;
use App\Shared\Formula\Formula;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Order Summary → Add Requisition (user, 2026-11-24). The orders a user may
 * make a requisition from — their Order Summary orders (the same buyers, the
 * same rows) that are not DROPPED, Cancelled or shipped out and have no
 * requisition yet — and what a picked order fills on a requisition row. The
 * filled cells come from the order and stay as the order has them (changed
 * in Order Summary); Order Booking Date and the rest are typed as before.
 */
final class OrderLink
{
    /** Requisition column ← Order Summary column. */
    public const MAP = [
        'factory' => 'factory', 'buyer' => 'buyer', 'brand' => 'brand', 'season' => 'season_quarter', 'dept' => 'department',
        'style' => 'style_number_name', 'order_type' => 'order_type', 'job_no' => 'job_no', 'contract_no' => 'commercial_contract_number',
        'delivery_date' => 'shipout_date', 'po_wise_rfi_sd_date' => 'po_wise_rfi_sd', 'yarn_booking_date' => 'yarn_booking_date',
        'item' => 'item', 'wash_type' => 'wash_info_at_garmenting', 'quantity_ticket' => 'quantity_ticket', 'pac' => 'pac', 'fob_price' => 'fob',
    ];

    /**
     * A style's quantity and price (user, 2026-11-24): Quantity (Ticket), PAC and FOB Price sit on its first
     * row only (Order Qty and FOB Value follow from them); its other parts' rows carry none — not copied, not
     * filled from the order, and not required there.
     */
    public const QTY = ['quantity_ticket', 'pac', 'fob_price'];

    /** The first (lowest serial) live requisition of each order: order id => requisition id. @param  list<string>  $orderIds @return array<string, string> */
    public static function firstRows(array $orderIds): array
    {
        $orderIds = array_values(array_unique(array_filter($orderIds)));
        if ($orderIds === []) {
            return [];
        }

        return \App\Modules\PreBudget\Models\PbRequisition::query()->whereIn('os_order_id', $orderIds)->orderBy('serial_no')->orderBy('created_at')
            ->get(['id', 'os_order_id'])->groupBy('os_order_id')->map(fn ($g) => $g->first()->id)->all();
    }

    /** The cells an order fills (and locks) on one of its rows: its first row all of them, its other parts' rows all but the quantity and price. @return array<string, mixed> */
    public static function fillFor(OsOrder $o, RequisitionSheet $sheet, bool $first): array
    {
        $fill = self::fill($o, $sheet);

        return $first ? $fill : array_diff_key($fill, array_flip(self::QTY));
    }

    /** Order Types that never get a requisition (user: DROPPED and Cancelled — a cancellation with liability is one too). */
    public const CLOSED_TYPES = ['DROPPED', 'Cancelled', 'Cancelled + Liability'];

    public const SHIPPED = 'Ship Out';

    /** The orders a user reaches in Order Summary: their buyers' and the rows they entered (null user: every order). */
    public static function scoped(?string $userId, bool $viewAll): Builder
    {
        $query = OsOrder::query();
        $buyers = $userId === null ? null : OrderScope::buyerIds($userId, $viewAll);
        if ($buyers !== null) {
            $query->where(fn ($q) => $q->whereIn('buyer_id', $buyers)->orWhere('created_by', $userId));
        }

        return $query;
    }

    /** Orders still open (not dropped, cancelled or shipped out) with no requisition. */
    public static function open(Builder $query): Builder
    {
        return $query
            ->whereRaw("coalesce(\"values\"->>'order_type', '') <> all(?::text[])", ['{'.implode(',', array_map(fn ($v) => '"'.$v.'"', self::CLOSED_TYPES)).'}'])
            ->whereRaw("coalesce(\"values\"->>'shipment_status', '') <> ?", [self::SHIPPED])
            ->whereNotExists(fn ($q) => $q->selectRaw('1')->from('pb_requisitions as pr')->whereColumn('pr.os_order_id', 'os_orders.id')->whereNull('pr.deleted_at'));
    }

    /** Why an order cannot be picked (null: it can) — checked again on Complete. */
    public static function blocked(OsOrder $o): ?string
    {
        $v = $o->values ?? [];
        if (in_array((string) ($v['order_type'] ?? ''), self::CLOSED_TYPES, true)) {
            return 'closed';
        }
        if ((string) ($v['shipment_status'] ?? '') === self::SHIPPED) {
            return 'shipped';
        }
        $has = \App\Modules\PreBudget\Models\PbRequisition::query()->where('os_order_id', $o->id)->exists();

        return $has ? 'taken' : null;
    }

    /** One order as the picker lists it. @return array<string, mixed> */
    public static function option(OsOrder $o): array
    {
        $v = $o->values ?? [];
        $jobs = array_values(array_filter(array_map('strval', (array) ($v['job_no'] ?? []))));

        return [
            'id' => $o->id, 'ssl' => $o->serial_no, 'style' => self::first($v['style_number_name'] ?? null), 'season' => $v['season_quarter'] ?? null,
            'buyer' => $v['buyer'] ?? null, 'job_no' => $jobs[0] ?? null, 'rfi' => $v['po_wise_rfi_sd'] ?? null,
            'order_type' => $v['order_type'] ?? null, 'quantity' => $v['quantity_pcs'] ?? null,
        ];
    }

    /**
     * The requisition cells an order fills: each mapped column the order has
     * a value in (locked on the row — changed in Order Summary). One the
     * order leaves empty (Brand, Wash … not yet entered there) is typed on
     * the requisition.
     *
     * @return array<string, mixed>
     */
    public static function fill(OsOrder $o, RequisitionSheet $sheet): array
    {
        $v = $o->values ?? [];
        $out = [];
        foreach (self::MAP as $to => $from) {
            if (! $sheet->column($to)) {
                continue;
            }
            $raw = $v[$from] ?? null;
            $out[$to] = match ($to) {
                'style' => self::first($raw),
                'job_no' => Formula::blank($raw) ? null : array_values(array_filter(array_map('strval', (array) $raw))),
                'contract_no' => Formula::blank($raw) ? null : implode(', ', array_filter(array_map('strval', (array) $raw))),
                default => is_array($raw) ? implode(', ', $raw) : $raw,
            };
        }

        return array_filter($out, fn ($v) => ! Formula::blank($v));
    }

    /** What a picked order only suggests (typed over freely): Entry Type from its Fabric Category when that says Knit or Woven. @return array<string, string> */
    public static function prefill(OsOrder $o): array
    {
        $category = mb_strtolower((string) ($o->values['fabric_category'] ?? ''));
        $type = str_contains($category, 'woven') ? 'Woven' : (str_contains($category, 'knit') ? 'Knit' : null);

        return $type === null ? [] : ['entry_type' => $type];
    }

    /** The columns an order may fill on this sheet (on a row, those it has a value in are locked). @return list<string> */
    public static function lockedKeys(RequisitionSheet $sheet): array
    {
        return array_values(array_filter(array_keys(self::MAP), fn ($k) => $sheet->column($k) !== null));
    }

    /**
     * The requisitions with each order's Order Qty and FOB Value counted once (user, 2026-11-24): the
     * rows made from one order (its parts — Body, Rib, Pocket…) all carry the order's quantity and price,
     * so only the first (lowest serial) keeps Order Qty and FOB Value for adding up; the others carry 0 —
     * as Norban types a style's other parts. Rows not made from an order are left as typed. The rows
     * returned are copies for working out totals, never saved.
     *
     * @param  \Illuminate\Support\Collection<int, \App\Modules\PreBudget\Models\PbRequisition>  $reqs
     * @return \Illuminate\Support\Collection<int, \App\Modules\PreBudget\Models\PbRequisition>
     */
    public static function countOnce(\Illuminate\Support\Collection $reqs): \Illuminate\Support\Collection
    {
        $first = $reqs->filter(fn ($r) => $r->os_order_id !== null)->sortBy('serial_no')->groupBy('os_order_id')->map(fn ($g) => $g->first()->id);

        return $reqs->map(function ($r) use ($first) {
            if ($r->os_order_id === null || ($first[$r->os_order_id] ?? null) === $r->id) {
                return $r;
            }
            $copy = clone $r;
            $copy->values = array_merge($r->values ?? [], ['order_qty' => 0, 'fob_value' => 0]);

            return $copy;
        });
    }

    /** The order's artwork as the requisition's own image (a copy: either can change on its own). */
    public static function copyArtwork(OsOrder $o, string $companyId, ?string $userId): ?FileAttachment
    {
        $id = $o->values['artwork'] ?? null;
        $src = is_string($id) ? FileAttachment::query()->find($id) : null;
        if (! $src || ! Storage::disk($src->disk)->exists($src->path)) {
            return null;
        }
        $ext = pathinfo($src->path, PATHINFO_EXTENSION);
        $path = "attachments/{$companyId}/".Str::lower((string) Str::ulid()).($ext !== '' ? ".{$ext}" : '');
        Storage::disk($src->disk)->copy($src->path, $path);
        $copy = $src->replicate();
        $copy->forceFill(['attachable_type' => 'pb_artwork', 'attachable_id' => $companyId, 'path' => $path, 'uploaded_by' => $userId])->save();

        return $copy;
    }

    private static function first(mixed $raw): ?string
    {
        $s = trim((string) (is_array($raw) ? ($raw[0] ?? '') : ($raw ?? '')));

        return $s === '' ? null : $s;
    }
}
