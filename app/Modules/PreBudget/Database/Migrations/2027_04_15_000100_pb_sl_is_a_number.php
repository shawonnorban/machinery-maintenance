<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * SL is a plain number (user, 2026-11-23): the requisition sheet's SL
 * column had been set up as a one-value-a-line list, so typing in it opened
 * the list dialog. It becomes a whole number, and a value kept as a list
 * keeps its first number (requisitions and drafts alike).
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::table('pb_columns')->where('key', 'sl')->where('type', 'multi')->update(['type' => 'number', 'multiple' => false, 'decimals' => 0]);

        DB::statement(<<<'SQL'
            update pb_requisitions
               set "values" = case
                   when n.first is null then "values" - 'sl'
                   else jsonb_set("values", '{sl}', to_jsonb(n.first))
               end
              from (select r.id, (select e::numeric from jsonb_array_elements_text(r."values"->'sl') e where e ~ '^\s*\d+(\.\d+)?\s*$' limit 1) as first
                      from pb_requisitions r where jsonb_typeof(r."values"->'sl') = 'array') n
             where n.id = pb_requisitions.id
            SQL);

        DB::table('pb_requisition_drafts')->orderBy('id')->select(['id', 'rows'])->chunk(200, function ($drafts): void {
            foreach ($drafts as $d) {
                $rows = json_decode((string) $d->rows, true);
                if (! is_array($rows)) {
                    continue;
                }
                $changed = false;
                foreach ($rows as &$r) {
                    if (is_array($r['values']['sl'] ?? null)) {
                        $first = collect($r['values']['sl'])->first(fn ($v) => is_numeric(trim((string) $v)));
                        $r['values']['sl'] = $first === null ? null : 0 + trim((string) $first);
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
        // A number stays a number; the column can be set to a list again in Column Setup.
    }
};
