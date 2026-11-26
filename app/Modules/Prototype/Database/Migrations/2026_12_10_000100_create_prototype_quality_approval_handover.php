<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
 * Prototype Desk → Sample Quality & Approval and Sample Handover & Delivery
 * (AnnotechERP 07/03): the internal check a finished sample passes,
 * checkpoint by checkpoint; the merchandising review and buyer approval of
 * a QC-passed sample; what the buyer said and who has to act on it; and the
 * approved sample handed over or couriered to the received confirmation.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('proto_quality_checks', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('company_id')->constrained('companies')->cascadeOnDelete();
            $table->string('qc_no', 24);
            $table->foreignUlid('sample_id')->constrained('proto_samples')->restrictOnDelete();
            $table->unsignedSmallInteger('version');
            $table->foreignUlid('checked_by')->nullable()->constrained('users')->nullOnDelete();
            $table->date('check_date');
            $table->string('result', 20);
            $table->string('severity', 10)->nullable();
            $table->string('defect_type', 255)->nullable();
            $table->text('defect')->nullable();
            $table->text('correction')->nullable();
            $table->text('remarks')->nullable();
            $table->string('attachment', 500)->nullable();
            $table->timestamps();
            $table->unique(['company_id', 'qc_no']);
            $table->index(['sample_id', 'version']);
        });

        Schema::create('proto_quality_checkpoints', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('company_id')->constrained('companies')->cascadeOnDelete();
            $table->foreignUlid('quality_check_id')->constrained('proto_quality_checks')->cascadeOnDelete();
            $table->string('checkpoint', 16);
            $table->string('result', 10)->nullable();
            $table->string('defect', 500)->nullable();
            $table->string('severity', 10)->nullable();
            $table->string('remarks', 500)->nullable();
            $table->unsignedSmallInteger('sort_order');
            $table->timestamps();
            $table->unique(['quality_check_id', 'checkpoint']);
        });

        Schema::create('proto_approvals', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('company_id')->constrained('companies')->cascadeOnDelete();
            $table->string('approval_no', 24);
            $table->foreignUlid('sample_id')->constrained('proto_samples')->restrictOnDelete();
            $table->unsignedSmallInteger('version');
            $table->foreignUlid('quality_check_id')->nullable()->constrained('proto_quality_checks')->nullOnDelete();
            $table->string('step', 24);
            $table->date('submitted_date');
            $table->string('decision', 24)->default('PENDING');
            $table->date('approval_date')->nullable();
            $table->string('approval_by', 255)->nullable();
            $table->text('buyer_comment')->nullable();
            $table->text('internal_comment')->nullable();
            $table->string('attachment', 500)->nullable();
            $table->foreignUlid('decided_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->unique(['company_id', 'approval_no']);
            $table->index(['sample_id', 'version']);
        });

        Schema::create('proto_feedback', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('company_id')->constrained('companies')->cascadeOnDelete();
            $table->string('feedback_no', 24);
            $table->foreignUlid('sample_id')->constrained('proto_samples')->restrictOnDelete();
            $table->unsignedSmallInteger('version');
            $table->foreignUlid('approval_id')->nullable()->constrained('proto_approvals')->nullOnDelete();
            $table->date('feedback_date');
            $table->string('feedback_type', 16);
            $table->string('status', 16)->default('OPEN');
            $table->text('comment')->nullable();
            $table->text('measurement_change')->nullable();
            $table->text('fabric_change')->nullable();
            $table->text('color_change')->nullable();
            $table->text('construction_change')->nullable();
            $table->boolean('action_required')->default(false);
            $table->foreignUlid('assigned_to')->nullable()->constrained('users')->nullOnDelete();
            $table->date('due_date')->nullable();
            $table->string('attachment', 500)->nullable();
            $table->timestamps();
            $table->unique(['company_id', 'feedback_no']);
        });

        Schema::create('proto_handovers', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('company_id')->constrained('companies')->cascadeOnDelete();
            $table->string('handover_no', 24);
            $table->foreignUlid('sample_id')->constrained('proto_samples')->restrictOnDelete();
            $table->unsignedSmallInteger('version');
            $table->unsignedInteger('qty');
            $table->date('handover_date');
            $table->string('handover_to', 255);
            $table->string('status', 20)->default('READY');
            $table->string('courier', 120)->nullable();
            $table->string('tracking_no', 120)->nullable();
            $table->date('expected_delivery')->nullable();
            $table->date('actual_delivery')->nullable();
            $table->string('received_by', 255)->nullable();
            $table->date('received_date')->nullable();
            $table->text('remarks')->nullable();
            $table->string('attachment', 500)->nullable();
            $table->timestamps();
            $table->unique(['company_id', 'handover_no']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('proto_handovers');
        Schema::dropIfExists('proto_feedback');
        Schema::dropIfExists('proto_approvals');
        Schema::dropIfExists('proto_quality_checkpoints');
        Schema::dropIfExists('proto_quality_checks');
    }
};
