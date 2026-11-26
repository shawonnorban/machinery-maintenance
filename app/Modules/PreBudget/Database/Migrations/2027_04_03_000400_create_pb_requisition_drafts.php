<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/**
 * Pre-Budget → Draft Requisition, Norban's way (user, 2026-11-19): a draft is
 * the Add Requisition page as it was being typed — its rows kept as typed,
 * nothing checked — saved on its own every two minutes, on Save Draft and as
 * the page closes, opened again from Draft Requisition (by its number) and
 * dropped once Completed. The draft requisition rows made before (status
 * DRAFT) move into one draft per person.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pb_requisition_drafts', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('company_id')->constrained('companies')->cascadeOnDelete();
            $table->unsignedInteger('draft_no');
            $table->foreignUlid('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('client_key', 64)->nullable(); // the page's own key: its saves all land in one draft
            $table->jsonb('rows');
            $table->unsignedSmallInteger('row_count')->default(0);
            $table->timestamps();
            $table->unique(['company_id', 'draft_no']);
            $table->unique(['company_id', 'client_key']);
            $table->index(['company_id', 'user_id', 'updated_at']);
        });

        $old = DB::table('pb_requisitions')->where('status', 'DRAFT')->orderBy('created_at')->get();
        foreach ($old->groupBy(fn ($r) => $r->company_id.'|'.$r->created_by) as $group) {
            $first = $group->first();
            $no = (int) DB::table('pb_requisition_drafts')->where('company_id', $first->company_id)->max('draft_no') + 1;
            $rows = $group->map(function ($r) {
                $values = json_decode($r->values, true) ?: [];

                return ['values' => $values, 'artwork_url' => isset($values['artwork']) ? "/api/files/{$values['artwork']}/download" : null];
            })->values()->all();
            DB::table('pb_requisition_drafts')->insert([
                'id' => Str::lower((string) Str::ulid()), 'company_id' => $first->company_id, 'draft_no' => $no, 'user_id' => $first->created_by,
                'rows' => json_encode($rows), 'row_count' => count($rows), 'created_at' => $first->created_at, 'updated_at' => $group->max('updated_at'),
            ]);
        }
        DB::table('pb_changes')->where('subject', 'REQUISITION')->whereIn('subject_id', $old->pluck('id'))->delete();
        DB::table('pb_requisitions')->whereIn('id', $old->pluck('id'))->delete();
    }

    public function down(): void
    {
        Schema::dropIfExists('pb_requisition_drafts');
    }
};
