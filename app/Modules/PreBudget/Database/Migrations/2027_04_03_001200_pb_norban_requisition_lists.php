<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Norban's own requisition lists (user, 2026-11-20): Item, Fabric Type,
 * Fabric Composition, Yarn Composition, Parts and Yarn Count become lists of
 * Pre-Budget's own (pb_list_values) instead of Merchandise / Knitting's
 * masters, as Norban keeps them (its compositions are free text, not fibre
 * splits). Each company's requisition columns that still read the old master
 * now read the list, and every value its requisitions already hold goes into
 * the list, so they stay valid. (Norban's values: erp:seed-masters.)
 */
return new class extends Migration
{
    /** column key => [old source, new list] */
    private const MOVES = [
        'item' => ['items', 'ITEM'],
        'fabric_type' => ['fabric_types', 'FABRIC_TYPE'],
        'fabric_composition' => ['compositions', 'FABRIC_COMPOSITION'],
        'composition' => ['compositions', 'YARN_COMPOSITION'],
        'parts' => ['garment_parts', 'PARTS'],
        'yarn_count' => ['yarn_counts', 'YARN_COUNT'],
    ];

    public function up(): void
    {
        foreach (self::MOVES as $key => [$old, $list]) {
            DB::table('pb_columns')->where('sheet', 'REQ')->where('key', $key)->where('source', $old)->update(['source' => "pb:{$list}"]);

            $used = DB::table('pb_requisitions')->selectRaw('company_id, trim("values"->>?) as v', [$key])
                ->whereRaw('coalesce(trim("values"->>?), \'\') <> \'\'', [$key])->distinct()->get();
            foreach ($used as $u) {
                $taken = DB::table('pb_list_values')->where('company_id', $u->company_id)->where('list', $list)
                    ->whereRaw('lower(name) = ?', [mb_strtolower($u->v)])->exists();
                if (! $taken) {
                    DB::table('pb_list_values')->insert([
                        'id' => strtolower((string) Str::ulid()), 'company_id' => $u->company_id, 'list' => $list, 'name' => mb_substr($u->v, 0, 255),
                        'sort_order' => 0, 'active' => true, 'created_at' => now(), 'updated_at' => now(),
                    ]);
                }
            }
        }
    }

    public function down(): void
    {
        foreach (self::MOVES as $key => [$old, $list]) {
            DB::table('pb_columns')->where('sheet', 'REQ')->where('key', $key)->where('source', "pb:{$list}")->update(['source' => $old]);
        }
    }
};
