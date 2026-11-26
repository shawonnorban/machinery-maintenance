<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Pre-Budget's Order Color holds each colour with its sizes (user,
 * 2026-11-22): lines of {color, sizes[]} — the colour needed, the sizes from
 * the Size master and optional — so the Budget Overview can show the Size
 * Range. A colour already typed becomes a line with no sizes yet (several
 * typed with commas, one line each); drafts too.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::table('pb_columns')->where('key', 'order_color')->update(['type' => 'colorsize', 'source' => 'colours', 'multiple' => false, 'updated_at' => now()]);
        DB::table('pb_columns')->where('key', 'order_color')->where('width', '<', 150)->update(['width' => 150]);
        foreach (DB::table('pb_requisitions')->whereRaw("jsonb_typeof(\"values\"->'order_color') = 'string'")->get(['id', 'values']) as $r) {
            $v = json_decode((string) $r->values, true);
            $v['order_color'] = self::lines((string) $v['order_color']);
            DB::table('pb_requisitions')->where('id', $r->id)->update(['values' => json_encode($v)]);
        }
        foreach (DB::table('pb_requisition_drafts')->get(['id', 'rows']) as $d) {
            $rows = json_decode((string) $d->rows, true) ?: [];
            $changed = false;
            foreach ($rows as $i => $row) {
                if (is_string($row['values']['order_color'] ?? null)) {
                    $rows[$i]['values']['order_color'] = self::lines($row['values']['order_color']);
                    $changed = true;
                }
            }
            if ($changed) {
                DB::table('pb_requisition_drafts')->where('id', $d->id)->update(['rows' => json_encode($rows)]);
            }
        }
    }

    public function down(): void
    {
        foreach (DB::table('pb_requisitions')->whereRaw("jsonb_typeof(\"values\"->'order_color') = 'array'")->get(['id', 'values']) as $r) {
            $v = json_decode((string) $r->values, true);
            $v['order_color'] = implode(', ', array_map(fn ($l) => $l['color'] ?? '', $v['order_color']));
            DB::table('pb_requisitions')->where('id', $r->id)->update(['values' => json_encode($v)]);
        }
        DB::table('pb_columns')->where('key', 'order_color')->update(['type' => 'text', 'source' => null]);
    }

    /** @return list<array{color: string, sizes: list<string>}> */
    private static function lines(string $text): array
    {
        return array_values(array_map(fn ($c) => ['color' => $c, 'sizes' => []], array_filter(array_map('trim', preg_split('/[,;\/]/', $text)))));
    }
};
