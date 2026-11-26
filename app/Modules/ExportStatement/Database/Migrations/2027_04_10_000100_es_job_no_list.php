<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * A statement's Job No holds several numbers, as Order Summary's does (user,
 * 2026-11-22): the column becomes a list, each number already entered becomes
 * that list's first line, and an edit still waiting for approval carries its
 * Job No the same way.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::table('es_columns')->where('key', 'job_no')->where('type', 'text')->update(['type' => 'multi', 'multiple' => true, 'source' => null, 'updated_at' => now()]);
        DB::statement(<<<'SQL'
            update es_statements set "values" = case when btrim("values"->>'job_no') = '' then "values" - 'job_no'
                else jsonb_set("values", '{job_no}', jsonb_build_array(btrim("values"->>'job_no'))) end
            where jsonb_typeof("values"->'job_no') = 'string'
            SQL);
        foreach (DB::table('es_edit_requests')->where('status', 'PENDING')->get(['id', 'diff']) as $r) {
            $diff = json_decode((string) $r->diff, true);
            if (! is_array($diff) || ! isset($diff['job_no']) || ! is_array($diff['job_no'])) {
                continue;
            }
            $diff['job_no'] = array_map(fn ($v) => is_string($v) ? (trim($v) === '' ? null : [trim($v)]) : $v, $diff['job_no']);
            DB::table('es_edit_requests')->where('id', $r->id)->update(['diff' => json_encode($diff)]);
        }
    }

    public function down(): void
    {
        DB::statement(<<<'SQL'
            update es_statements set "values" = jsonb_set("values", '{job_no}', to_jsonb(array_to_string(array(select jsonb_array_elements_text("values"->'job_no')), ', ')))
            where jsonb_typeof("values"->'job_no') = 'array'
            SQL);
        DB::table('es_columns')->where('key', 'job_no')->where('type', 'multi')->update(['type' => 'text', 'multiple' => false]);
    }
};
