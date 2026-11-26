<?php

declare(strict_types=1);

use App\Modules\OrderSummary\Support\ColumnCatalog;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Order Summary (AnnotechERP 32): the value colours of the user's own Excel
 * sheet (Order Summary.xlsx → Total Order) become the columns' default
 * colour rules. A company's column gets them only while its rules are still
 * the first defaults (or none) — rules someone set in Column Setup stay.
 */
return new class extends Migration
{
    /** The defaults these replace. */
    private const OLD = [
        'order_type' => [['op' => 'contains', 'value' => 'Confirmed', 'tone' => 'green'], ['op' => 'contains', 'value' => 'BLOCK', 'tone' => 'yellow'], ['op' => 'contains', 'value' => 'Cancel', 'tone' => 'red']],
        'reprint_days_left' => [['op' => 'lt', 'value' => '0', 'tone' => 'red'], ['op' => 'lte', 'value' => '30', 'tone' => 'yellow']],
        'days_left_in_hand' => [['op' => 'lt', 'value' => '0', 'tone' => 'red'], ['op' => 'lte', 'value' => '30', 'tone' => 'yellow']],
        'price_status' => [['op' => 'contains', 'value' => 'NOT', 'tone' => 'pink'], ['op' => 'contains', 'value' => 'CONFIRM', 'tone' => 'green']],
        'po_wise_rfi_sd' => [['op' => 'any', 'tone' => 'blue']],
    ];

    public function up(): void
    {
        $rules = [];
        foreach (ColumnCatalog::COLUMNS as $c) {
            if (! empty($c['rules'])) {
                $rules[$c['key']] = $c['rules'];
            }
        }
        DB::table('os_columns')->where('is_system', true)->whereIn('key', array_keys($rules))->orderBy('id')
            ->each(function (object $row) use ($rules): void {
                $current = $row->color_rules === null ? null : json_decode((string) $row->color_rules, true);
                if ($current === null || $current === [] || $current == (self::OLD[$row->key] ?? null)) {
                    DB::table('os_columns')->where('id', $row->id)->update(['color_rules' => json_encode($rules[$row->key]), 'updated_at' => now()]);
                }
            });
    }

    public function down(): void
    {
        // The colours stay; the first defaults are not put back.
    }
};
