<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
 * Assembly Floor → Quality Management (AnnotechERP 20/03): a bundle out of
 * Finishing received onto a quality line (Company Management's QUALITY lines,
 * by Section > Floor > Line) with the good pieces finishing sent on; judged
 * there into Pass, Reject and Rework — round after round, the rework coming
 * back for re-inspection once a supervisor marks it completed — and released
 * to the next process with Quality OUT once nothing is left to judge.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('asm_quality_ins', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('company_id')->constrained('companies')->cascadeOnDelete();
            $table->foreignUlid('bundle_id')->constrained('cut_bundles')->restrictOnDelete();
            $table->foreignUlid('finishing_out_id')->constrained('asm_finishing_outs')->restrictOnDelete();
            $table->date('in_date');
            $table->foreignUlid('section_id')->nullable()->constrained('sections')->nullOnDelete();
            $table->foreignUlid('floor_id')->nullable()->constrained('floors')->nullOnDelete();
            $table->foreignUlid('line_id')->constrained('production_lines')->restrictOnDelete();
            $table->unsignedInteger('qty');
            $table->foreignUlid('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->unique('bundle_id');
            $table->unique('finishing_out_id');
            $table->index(['company_id', 'in_date']);
            $table->index(['company_id', 'line_id']);
        });

        Schema::create('asm_quality_inspections', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('company_id')->constrained('companies')->cascadeOnDelete();
            $table->foreignUlid('quality_in_id')->constrained('asm_quality_ins')->restrictOnDelete();
            $table->unsignedSmallInteger('round'); // 1 = the first inspection, 2.. = the rework judged again
            $table->date('inspected_on');
            $table->unsignedInteger('inspected_qty');
            $table->unsignedInteger('pass_qty')->default(0);
            $table->unsignedInteger('reject_qty')->default(0);
            $table->unsignedInteger('rework_qty')->default(0);
            $table->string('defect_type', 20)->nullable(); // only with reject or rework
            $table->string('remark', 300)->nullable();
            $table->string('rework_status', 12)->nullable(); // PENDING | PROCESSING | COMPLETED, only when rework_qty > 0
            $table->timestamp('rework_started_at')->nullable();
            $table->foreignUlid('rework_started_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('rework_completed_at')->nullable();
            $table->foreignUlid('rework_completed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignUlid('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->unique(['quality_in_id', 'round']);
            $table->index(['company_id', 'inspected_on']);
            $table->index(['company_id', 'rework_status']);
        });

        Schema::create('asm_quality_outs', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('company_id')->constrained('companies')->cascadeOnDelete();
            $table->foreignUlid('quality_in_id')->constrained('asm_quality_ins')->restrictOnDelete();
            $table->date('out_date');
            $table->unsignedInteger('pass_qty')->default(0);
            $table->unsignedInteger('reject_qty')->default(0);
            $table->unsignedInteger('rework_qty')->default(0);
            $table->string('final_status', 12); // PASS | REJECT
            $table->string('remarks', 300)->nullable();
            $table->foreignUlid('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->unique('quality_in_id');
            $table->index(['company_id', 'out_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('asm_quality_outs');
        Schema::dropIfExists('asm_quality_inspections');
        Schema::dropIfExists('asm_quality_ins');
    }
};
