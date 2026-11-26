<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * A requisition no longer has "No of Style" (user, 2026-11-22): it was typed
 * 1 on a style's first requisition and 0 on the rest, and nothing reads it
 * since a budget counts its different styles itself. The column goes from
 * every company's sheet, and its values from the requisitions and drafts.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::table('pb_columns')->where('key', 'no_of_style')->delete();
        DB::statement(<<<'SQL'
            update pb_requisitions set "values" = "values" - 'no_of_style' where jsonb_exists("values", 'no_of_style')
            SQL);
        DB::table('pb_requisition_drafts')->orderBy('id')->select(['id', 'rows'])->chunk(200, function ($drafts): void {
            foreach ($drafts as $d) {
                $rows = json_decode((string) $d->rows, true);
                if (! is_array($rows)) {
                    continue;
                }
                $changed = false;
                foreach ($rows as &$r) {
                    if (is_array($r['values'] ?? null) && array_key_exists('no_of_style', $r['values'])) {
                        unset($r['values']['no_of_style']);
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
        // The typed values are not brought back; the column returns through Column Setup if wanted.
    }
};
