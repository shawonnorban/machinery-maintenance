<?php

declare(strict_types=1);

namespace App\Modules\SalesShipment\Support;

use App\Modules\SalesShipment\Models\SsSetting;
use Illuminate\Support\Facades\DB;

/**
 * Sales & Shipment (AnnotechERP 21), what every screen of the module reads
 * from its settings (21/01): the company's row — or Brickland's defaults
 * while none is saved — and the next number of a series, printed the way the
 * settings say: prefix, year, running number padded to the sequence width
 * (SHP-2026-000001).
 */
final class SsSettings
{
    /** The company's settings, or an unsaved row carrying the defaults. */
    public static function get(): SsSetting
    {
        return SsSetting::query()->first() ?? new SsSetting([
            'ship_against_delivered' => false, 'require_export_docs' => true, 'due_alert_days' => 7, 'cert_alert_days' => 30, 'seq_width' => 6, ...SsSetting::PREFIXES,
        ]);
    }

    /** The next number of a series ("prefix_shipment" …) in a table's column, for the year of a date. */
    public static function nextNo(string $series, string $table, string $column, string $date, string $companyId): string
    {
        $s = self::get();
        $stem = $s->{$series}.'-'.substr($date, 0, 4).'-';
        $last = DB::table($table)->where('company_id', $companyId)->where($column, 'like', $stem.'%')->orderByRaw('length('.$column.') desc')->orderByDesc($column)->value($column);
        $n = $last ? (int) substr($last, strlen($stem)) + 1 : 1;

        return $stem.str_pad((string) $n, $s->seq_width, '0', STR_PAD_LEFT);
    }
}
