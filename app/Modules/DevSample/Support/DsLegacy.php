<?php

declare(strict_types=1);

namespace App\Modules\DevSample\Support;

use Illuminate\Support\Facades\DB;

/**
 * A sample kept in the typed columns (before Column Setup, 2026-11-21) as the
 * sheet's values: lists as their labels, the buyer by name, the image as the
 * artwork, and colours and price levels as pairs columns — lines of
 * {label, value}: "Navy" 60 pcs, "BIO" $2.48 (the first line the summary price).
 */
final class DsLegacy
{
    /** @return array<string, mixed> */
    public static function values(object $row): array
    {
        $json = fn ($v) => is_string($v) ? (json_decode($v, true) ?: []) : (array) ($v ?? []);
        $v = [];
        foreach (['season', 'customer_name', 'brand', 'department', 'division', 'styling', 'job_no', 'fabrication', 'awb_tracking', 'remarks'] as $key) {
            $v[$key] = self::text($row->{$key} ?? null);
        }
        if (! empty($row->buyer_id)) {
            $b = DB::table('buyers')->where('id', $row->buyer_id)->first(['short_name', 'name']);
            $v['buyer'] = $b ? ($b->short_name ?: $b->name) : null;
        }
        $v['artwork'] = $row->image_file_id ?? null;
        $v['items'] = array_values(array_filter(array_map('trim', $json($row->items ?? null)))) ?: null;
        $v['colours'] = self::lines(array_map(fn ($c) => ['label' => $c['name'] ?? '', 'value' => $c['qty'] ?? null], $json($row->colours ?? null)));
        foreach (array_keys(DevSampleOptions::lists()) as $list) {
            $v[$list] = ! empty($row->{$list}) ? DevSampleOptions::label($list, $row->{$list}) : null;
        }
        foreach (['tp_rcv_date', 'buyer_requested_date', 'submission_deadline', 'sub_plan_date', ...array_keys(DsCatalog::TRACKING), 'last_price_quote_date'] as $d) {
            $v[$d] = ! empty($row->{$d}) ? substr((string) $row->{$d}, 0, 10) : null;
        }
        foreach (['sample_qty', 'possible_qty', 'actual_order_qty'] as $q) {
            $v[$q] = $row->{$q} !== null ? (int) $row->{$q} : null;
        }
        foreach (['target', 'fob'] as $kind) {
            $levels = $json($row->{"{$kind}_prices"} ?? null);
            if ($levels === [] && $row->{"{$kind}_price"} !== null) {
                $levels = [['label' => '', 'price' => $row->{"{$kind}_price"}]];
            }
            $v["{$kind}_price"] = self::lines(array_map(fn ($l) => ['label' => ($l['label'] ?? '') === '-' ? '' : ($l['label'] ?? ''), 'value' => $l['price'] ?? null], $levels));
        }
        if (! empty($row->last_price_quote_by)) {
            $v['last_price_quote_by'] = DB::table('users')->where('id', $row->last_price_quote_by)->value('name');
        }
        $fob = $v['fob_price'][0]['value'] ?? null;
        if ($v['possible_qty'] !== null && $fob !== null) {
            $v['fob_value'] = round($v['possible_qty'] * $fob, 2);
        }

        return array_filter($v, fn ($x) => $x !== null && $x !== '' && $x !== []);
    }

    /**
     * Values of the first Column Setup sheet (colours as "Navy: 60, White: 40",
     * one number per price, every level in Price Levels) as pairs columns.
     *
     * @param  array<string, mixed>  $v
     * @return array<string, mixed>
     */
    public static function toPairs(array $v): array
    {
        if (isset($v['colours']) && is_string($v['colours'])) {
            $v['colours'] = self::lines(array_map(function (string $part): array {
                [$name, $qty] = array_pad(array_map('trim', explode(':', $part, 2)), 2, null);

                return ['label' => $name, 'value' => $qty];
            }, array_filter(array_map('trim', preg_split('/[,\n]/', $v['colours'])))));
        }
        // "Target: BIO $2.48, CONV $2.3\nFOB: …" → each kind's levels.
        $levels = [];
        foreach (preg_split('/\n/', (string) ($v['price_levels'] ?? '')) as $line) {
            if (preg_match('/^\s*(Target|FOB)\s*:\s*(.+)$/i', $line, $m)) {
                $kind = strtolower($m[1]) === 'fob' ? 'fob' : 'target';
                foreach (array_map('trim', explode(',', $m[2])) as $level) {
                    if (preg_match('/^(.*?)\s*\$\s*([\d.]+)$/', $level, $l)) {
                        $levels[$kind][] = ['label' => trim($l[1]) === '-' ? '' : trim($l[1]), 'value' => $l[2]];
                    }
                }
            }
        }
        foreach (['target', 'fob'] as $kind) {
            $key = "{$kind}_price";
            if (! empty($levels[$kind])) {
                $v[$key] = self::lines($levels[$kind]);
            } elseif (isset($v[$key]) && ! is_array($v[$key])) {
                $v[$key] = self::lines([['label' => '', 'value' => $v[$key]]]);
            }
        }
        unset($v['price_levels']);

        return array_filter($v, fn ($x) => $x !== null && $x !== '' && $x !== []);
    }

    /**
     * Clean lines: trimmed labels, numbers as numbers, an empty line dropped.
     *
     * @param  iterable<array{label?: mixed, value?: mixed}>  $lines
     * @return list<array{label: string, value: float|int|null}>|null
     */
    private static function lines(iterable $lines): ?array
    {
        $out = [];
        foreach ($lines as $l) {
            $label = trim((string) ($l['label'] ?? ''));
            $value = $l['value'] ?? null;
            $value = $value === null || $value === '' ? null : (is_numeric($value) ? $value + 0 : null);
            if ($label !== '' || $value !== null) {
                $out[] = ['label' => $label, 'value' => is_float($value) ? round($value, 4) : $value];
            }
        }

        return $out ?: null;
    }

    private static function text(mixed $v): ?string
    {
        $s = trim((string) ($v ?? ''));

        return $s === '' ? null : $s;
    }
}
