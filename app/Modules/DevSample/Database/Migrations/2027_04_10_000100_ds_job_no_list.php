<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * A sample's Job No holds several numbers, as Order Summary's does (user,
 * 2026-11-22): the column becomes a list, and each number already entered
 * becomes that list's first line.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::table('ds_columns')->where('key', 'job_no')->where('type', 'text')->update(['type' => 'multi', 'multiple' => true, 'source' => null, 'updated_at' => now()]);
        DB::statement(<<<'SQL'
            update ds_samples set "values" = case when btrim("values"->>'job_no') = '' then "values" - 'job_no'
                else jsonb_set("values", '{job_no}', jsonb_build_array(btrim("values"->>'job_no'))) end
            where jsonb_typeof("values"->'job_no') = 'string'
            SQL);
    }

    public function down(): void
    {
        DB::statement(<<<'SQL'
            update ds_samples set "values" = jsonb_set("values", '{job_no}', to_jsonb(array_to_string(array(select jsonb_array_elements_text("values"->'job_no')), ', ')))
            where jsonb_typeof("values"->'job_no') = 'array'
            SQL);
        DB::table('ds_columns')->where('key', 'job_no')->where('type', 'multi')->update(['type' => 'text', 'multiple' => false]);
    }
};
