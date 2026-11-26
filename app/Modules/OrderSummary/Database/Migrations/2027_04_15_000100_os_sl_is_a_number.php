<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * SL is a plain number (user, 2026-11-23): Order Summary's SL column had
 * been set up as a one-value-a-line list (every company's sheet), so typing
 * in it opened the list dialog. It becomes a whole number; a value kept as a
 * list keeps its first number.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::table('os_columns')->where('key', 'sl')->where('type', 'multi')->update(['type' => 'number', 'multiple' => false, 'decimals' => 0]);

        DB::statement(<<<'SQL'
            update os_orders
               set "values" = case
                   when n.first is null then "values" - 'sl'
                   else jsonb_set("values", '{sl}', to_jsonb(n.first))
               end
              from (select o.id, (select e::numeric from jsonb_array_elements_text(o."values"->'sl') e where e ~ '^\s*\d+(\.\d+)?\s*$' limit 1) as first
                      from os_orders o where jsonb_typeof(o."values"->'sl') = 'array') n
             where n.id = os_orders.id
            SQL);
    }

    public function down(): void
    {
        // A number stays a number; the column can be set to a list again in Column Setup.
    }
};
