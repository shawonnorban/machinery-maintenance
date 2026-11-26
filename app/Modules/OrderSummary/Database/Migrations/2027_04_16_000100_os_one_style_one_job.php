<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * One style and one JOB No a row (user, 2026-11-23): an order row is one
 * style, and the rows holding two (notes typed into the cell — "New: 0709",
 * "(Old: 2235103)", "QUICK RESPONSE" — or two orders joined with "+") keep
 * the first and move the rest into Remarks. Both columns then take one value
 * (still kept as a list, so every reader of them works as before).
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::table('os_columns')->whereIn('key', ['style_number_name', 'job_no'])->update(['multiple' => false, 'updated_at' => now()]);

        $labels = ['style_number_name' => 'Style', 'job_no' => 'JOB No'];
        DB::table('os_orders')
            ->whereRaw("jsonb_typeof(\"values\"->'style_number_name') = 'array' and jsonb_array_length(\"values\"->'style_number_name') > 1")
            ->orWhereRaw("jsonb_typeof(\"values\"->'job_no') = 'array' and jsonb_array_length(\"values\"->'job_no') > 1")
            ->orderBy('id')->select(['id', 'values'])->chunk(200, function ($rows) use ($labels): void {
                foreach ($rows as $row) {
                    $v = json_decode((string) $row->values, true);
                    $notes = [];
                    foreach ($labels as $key => $label) {
                        $list = array_values(array_filter(array_map(fn ($x) => trim((string) $x), (array) ($v[$key] ?? [])), fn ($x) => $x !== ''));
                        if (count($list) < 2) {
                            continue;
                        }
                        // "2241405 Solid NOS (QEI) +" — the "+" only joined it to the next one.
                        $v[$key] = [rtrim($list[0], " +")];
                        $notes[] = $label.': '.implode(', ', array_map(fn ($x) => rtrim($x, ' +'), array_slice($list, 1)));
                    }
                    if ($notes === []) {
                        continue;
                    }
                    $remarks = trim((string) ($v['remarks'] ?? ''));
                    $v['remarks'] = trim($remarks.($remarks === '' ? '' : "\n").implode('; ', $notes));
                    DB::table('os_orders')->where('id', $row->id)->update(['values' => json_encode($v, JSON_UNESCAPED_UNICODE)]);
                }
            });
    }

    public function down(): void
    {
        DB::table('os_columns')->whereIn('key', ['style_number_name', 'job_no'])->update(['multiple' => true, 'updated_at' => now()]);
    }
};
