<?php

declare(strict_types=1);

namespace App\Modules\DevSample\Support;

use App\Modules\Identity\Models\LookupTable;

/**
 * Dev Sample Status's fixed choices (AnnotechERP 31), Norban's own lists
 * kept as codes — labels come from lang/devsample.php `opt.<list>.<code>` —
 * and the three masters it picks from the shared lookup tables (Brand,
 * Season, Garment Department).
 */
final class DevSampleOptions
{
    public const REQUIRED_CATEGORIES = ['SAMPLE', 'SAMPLE_WITH_PRICE'];

    public const SAMPLE_TYPES = ['PROTO', 'PP', 'REVISED_PROTO', 'STRIKE_OFF', 'SMS', 'FABRIC_HANGER', 'PRICE_ONLY', 'SHIPMENT', 'OTHERS'];

    public const PRICE_STATUSES = ['OFFERED', 'PENDING_OFFER', 'CANCELLED', 'SELF_DROP', 'CONFIRMED'];

    public const SAMPLE_STATUSES = ['SUBMISSION_PENDING', 'SUBMITTED', 'CLARIFICATION', 'CANCELLED', 'SELF_DROP'];

    public const ORDER_CONFIRMATIONS = ['CONFIRMED_WITH_DEV', 'CONFIRMED_WITHOUT_DEV', 'ORDER_CONFIRMED', 'CONFIRMATION_PENDING', 'NEGOTIATION', 'PENDING_OFFER', 'DROP', 'CANCELLED', 'SELF_DROP'];

    public const ORDER_SITUATIONS = ['FABRIC_BOOKING', 'KNITTING', 'DYEING_FINISHING', 'CUTTING', 'PRINT_EMB', 'SEWING', 'READY_TO_SEND', 'SENT_TO_BUYER', 'CANCELLED', 'SELF_DROP'];

    /** The order confirmations the "Order Dropped" card counts. */
    public const DROPPED = ['DROP', 'SELF_DROP', 'CANCELLED'];

    /** The Pivot Report's detail columns (sample status). */
    public const REPORT_SAMPLE_STATUSES = ['SUBMITTED', 'SUBMISSION_PENDING', 'CANCELLED', 'SELF_DROP'];

    /** Lookup table codes => what each Dev Sample field picks. */
    public const LOOKUPS = ['brand' => 'BRAND', 'season' => 'SEASON', 'department' => 'GARMENT_DEPARTMENT'];

    /** @return array<string, list<string>> list => codes, for the screens and validation */
    public static function lists(): array
    {
        return [
            'required_category' => self::REQUIRED_CATEGORIES,
            'sample_type' => self::SAMPLE_TYPES,
            'price_status' => self::PRICE_STATUSES,
            'sample_status' => self::SAMPLE_STATUSES,
            'order_confirmation' => self::ORDER_CONFIRMATIONS,
            'current_order_situation' => self::ORDER_SITUATIONS,
        ];
    }

    /** @return list<array{value: string, label: string}> */
    public static function labelled(string $list): array
    {
        return array_map(fn (string $code): array => ['value' => $code, 'label' => self::label($list, $code)], self::lists()[$list]);
    }

    public static function label(string $list, ?string $code): string
    {
        if ($code === null || $code === '') {
            return '';
        }
        $key = "devsample.opt.{$list}.{$code}";
        $text = __($key);

        return $text === $key ? $code : $text;
    }

    /**
     * A lookup table's active names (the company's own and the shared ones),
     * in its order.
     *
     * @return list<string>
     */
    public static function lookup(string $code): array
    {
        $table = LookupTable::query()->visible()->where('code', $code)->orderByRaw('company_id is null')->first();
        if (! $table) {
            return [];
        }

        return $table->values()->visible()->where('active', true)->orderBy('sort_order')->orderBy('name')->pluck('name')
            ->map(fn ($n) => trim((string) $n))->filter()->unique()->values()->all();
    }

    /** @return list<array{value: string, label: string}> */
    public static function lookupOptions(string $code): array
    {
        return array_map(fn (string $n): array => ['value' => $n, 'label' => $n], self::lookup($code));
    }
}
