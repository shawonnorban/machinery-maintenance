<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
 * Assembly Floor → Finishing (AnnotechERP 20/02): a bundle out of sewing
 * received onto a finishing line (Company Management's FINISHING lines, by
 * Section > Floor > Line) — once, with what sewing sent out — and scanned
 * off it again with its Good, Reject and Rework, which always add up to
 * what went on. The rework is followed up (pending → processing →
 * completed) by a supervisor.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('asm_finishing_ins', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('company_id')->constrained('companies')->cascadeOnDelete();
            $table->foreignUlid('bundle_id')->constrained('cut_bundles')->restrictOnDelete();
            $table->foreignUlid('sewing_out_id')->constrained('asm_bundle_outs')->restrictOnDelete();
            $table->date('in_date');
            $table->foreignUlid('section_id')->nullable()->constrained('sections')->nullOnDelete();
            $table->foreignUlid('floor_id')->nullable()->constrained('floors')->nullOnDelete();
            $table->foreignUlid('line_id')->constrained('production_lines')->restrictOnDelete();
            $table->unsignedInteger('qty');
            $table->foreignUlid('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->unique('bundle_id');
            $table->unique('sewing_out_id');
            $table->index(['company_id', 'in_date']);
            $table->index(['company_id', 'line_id']);
        });

        Schema::create('asm_finishing_outs', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('company_id')->constrained('companies')->cascadeOnDelete();
            $table->foreignUlid('finishing_in_id')->constrained('asm_finishing_ins')->restrictOnDelete();
            $table->date('out_date');
            $table->unsignedInteger('good_qty')->default(0);
            $table->unsignedInteger('reject_qty')->default(0);
            $table->unsignedInteger('rework_qty')->default(0);
            $table->string('rework_status', 12)->nullable(); // PENDING | PROCESSING | COMPLETED, only when rework_qty > 0
            $table->timestamp('rework_started_at')->nullable();
            $table->foreignUlid('rework_started_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('rework_completed_at')->nullable();
            $table->foreignUlid('rework_completed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignUlid('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->unique('finishing_in_id');
            $table->index(['company_id', 'out_date']);
            $table->index(['company_id', 'rework_status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('asm_finishing_outs');
        Schema::dropIfExists('asm_finishing_ins');
    }
};
