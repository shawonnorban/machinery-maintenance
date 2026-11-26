<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * A requisition's Job No holds several numbers, as Order Summary's does
 * (user, 2026-11-22): the column becomes a list, and each number already
 * entered (on requisitions and in the drafts) becomes that list's first line.
 * The BOM sheet's Job No (a system column showing the style's numbers) is left alone.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::table('pb_columns')->where('key', 'job_no')->where('type', 'text')->update(['type' => 'multi', 'multiple' => true, 'source' => null, 'updated_at' => now()]);
        DB::statement(<<<'SQL'
            update pb_requisitions set "values" = case when btrim("values"->>'job_no') = '' then "values" - 'job_no'
                else jsonb_set("values", '{job_no}', jsonb_build_array(btrim("values"->>'job_no'))) end
            where jsonb_typeof("values"->'job_no') = 'string'
            SQL);
        DB::table('pb_requisition_drafts')->orderBy('id')->select(['id', 'rows'])->chunk(200, function ($drafts): void {
            foreach ($drafts as $d) {
                $rows = json_decode((string) $d->rows, true);
                if (! is_array($rows)) {
                    continue;
                }
                $changed = false;
                foreach ($rows as &$r) {
                    $j = $r['values']['job_no'] ?? null;
                    if (is_string($j)) {
                        $r['values']['job_no'] = trim($j) === '' ? null : [trim($j)];
                        $changed = true;
                    }
                }
                unset($r);
                if ($changed) {
                    DB::table('pb_requisition_drafts')->where('id', $d->id)->update(['rows' => json_encode($rows)]);
                }
            }
        });
    }

    public function down(): void
    {
        DB::statement(<<<'SQL'
            update pb_requisitions set "values" = jsonb_set("values", '{job_no}', to_jsonb(array_to_string(array(select jsonb_array_elements_text("values"->'job_no')), ', ')))
            where jsonb_typeof("values"->'job_no') = 'array'
            SQL);
        DB::table('pb_columns')->where('key', 'job_no')->where('type', 'multi')->update(['type' => 'text', 'multiple' => false]);
    }
};
