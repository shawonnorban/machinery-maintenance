<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Prototype Desk → Prototype Material Requisition (AnnotechERP 07/03): the
 * fabric, trims, accessories and packaging a sample asks of the stores
 * (MR-2026-00001) — the sample programs it covers and its material lines —
 * walked through department approval, store verification and approval, and
 * the material issues released against it (MI-2026-00001).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('proto_material_requisitions', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('company_id')->constrained('companies')->cascadeOnDelete();
            $table->string('mr_no', 24);
            $table->foreignUlid('sample_id')->constrained('proto_samples')->restrictOnDelete();
            $table->date('req_date');
            $table->date('required_date')->nullable();
            $table->foreignUlid('department_id')->nullable()->constrained('departments')->nullOnDelete();
            $table->foreignUlid('requested_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('requisition_type', 120)->nullable();
            $table->foreignUlid('requisition_to_id')->constrained('stores')->restrictOnDelete();
            $table->foreignUlid('delivery_to_id')->constrained('stores')->restrictOnDelete();
            $table->text('remarks')->nullable();
            $table->string('state', 20)->default('DRAFT');
            $table->foreignUlid('dept_approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('dept_approved_at')->nullable();
            $table->foreignUlid('store_verified_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('store_verified_at')->nullable();
            $table->foreignUlid('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->text('rejection_reason')->nullable();
            $table->foreignUlid('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->unique(['company_id', 'mr_no']);
        });

        Schema::create('proto_mr_programs', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('company_id')->constrained('companies')->cascadeOnDelete();
            $table->foreignUlid('requisition_id')->constrained('proto_material_requisitions')->cascadeOnDelete();
            $table->foreignUlid('program_id')->constrained('merch_sample_programs')->restrictOnDelete();
            $table->unsignedSmallInteger('sort_order')->default(1);
            $table->timestamps();
        });

        Schema::create('proto_mr_lines', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('company_id')->constrained('companies')->cascadeOnDelete();
            $table->foreignUlid('requisition_id')->constrained('proto_material_requisitions')->cascadeOnDelete();
            $table->foreignUlid('item_id')->constrained('mp_items')->restrictOnDelete();
            $table->string('material_group', 24)->nullable();
            $table->foreignUlid('fabric_type_id')->nullable()->constrained('fabric_types')->nullOnDelete();
            $table->string('color', 96)->nullable();
            $table->string('size', 32)->nullable();
            $table->string('description', 255)->nullable();
            $table->string('specification', 255)->nullable();
            $table->decimal('req_qty', 14, 4);
            $table->string('unit', 16)->nullable();
            $table->decimal('available_qty', 14, 4)->nullable();
            $table->foreignUlid('warehouse_id')->nullable()->constrained('stores')->nullOnDelete();
            $table->decimal('issued_qty', 14, 4)->default(0);
            $table->string('remarks', 500)->nullable();
            $table->unsignedSmallInteger('sort_order')->default(1);
            $table->timestamps();
        });

        Schema::create('proto_material_issues', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('company_id')->constrained('companies')->cascadeOnDelete();
            $table->string('issue_no', 24);
            $table->foreignUlid('requisition_id')->constrained('proto_material_requisitions')->restrictOnDelete();
            $table->date('issue_date');
            $table->foreignUlid('store_id')->nullable()->constrained('stores')->nullOnDelete();
            $table->string('received_by', 255)->nullable();
            $table->text('remarks')->nullable();
            $table->foreignUlid('issued_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->unique(['company_id', 'issue_no']);
        });

        Schema::create('proto_material_issue_lines', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('company_id')->constrained('companies')->cascadeOnDelete();
            $table->foreignUlid('issue_id')->constrained('proto_material_issues')->cascadeOnDelete();
            $table->foreignUlid('line_id')->constrained('proto_mr_lines')->restrictOnDelete();
            $table->decimal('qty', 14, 4);
            $table->string('remarks', 500)->nullable();
            $table->unsignedSmallInteger('sort_order')->default(1);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('proto_material_issue_lines');
        Schema::dropIfExists('proto_material_issues');
        Schema::dropIfExists('proto_mr_lines');
        Schema::dropIfExists('proto_mr_programs');
        Schema::dropIfExists('proto_material_requisitions');
    }
};
