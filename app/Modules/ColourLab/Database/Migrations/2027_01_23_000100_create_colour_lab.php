<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
 * Colour Lab → Operations (AnnotechERP 16/01): the lab's certificates (their
 * types and the equipment they cover), the register every issued copy is
 * filed in, chemical trials with their lines, and in-house fabric tests.
 * Floors are Company Management's; buyers, orders, fabric types and fibre
 * compositions Merchandise Desk's; items and departments the item master's;
 * suppliers Material Procurement's; filed documents Company Management's.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('lab_cert_types', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('company_id')->constrained('companies')->cascadeOnDelete();
            $table->string('code', 32);
            $table->string('name');
            $table->string('issuing_body')->nullable();
            $table->boolean('active')->default(true);
            $table->timestamps();
            $table->unique(['company_id', 'code'], 'lab_cert_types_code_unique');
        });

        Schema::create('lab_equipment', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('company_id')->constrained('companies')->cascadeOnDelete();
            $table->string('code', 32);
            $table->string('name');
            $table->string('manufacturer')->nullable();
            $table->string('serial_no', 64)->nullable();
            $table->boolean('active')->default(true);
            $table->timestamps();
            $table->unique(['company_id', 'code'], 'lab_equipment_code_unique');
        });

        Schema::create('lab_certificates', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('company_id')->constrained('companies')->cascadeOnDelete();
            $table->foreignUlid('cert_type_id')->constrained('lab_cert_types')->restrictOnDelete();
            $table->foreignUlid('equipment_id')->constrained('lab_equipment')->restrictOnDelete();
            $table->string('document_ref', 64);
            $table->string('upload_doc');
            $table->foreignUlid('company_document_id')->nullable()->constrained('company_documents')->nullOnDelete();
            $table->date('apply_date');
            $table->foreignUlid('applied_by')->constrained('users')->restrictOnDelete();
            $table->date('issue_date');
            $table->date('valid_date');
            $table->text('mail_to')->nullable();
            $table->text('mail_cc')->nullable();
            $table->unsignedSmallInteger('notify_days')->default(30);
            $table->date('notified_for')->nullable(); // the Valid Date the reminder last went out for
            $table->foreignUlid('lab_floor_id')->constrained('floors')->restrictOnDelete();
            $table->boolean('active')->default(true);
            $table->text('remarks')->nullable();
            $table->timestamps();
            $table->unique(['company_id', 'document_ref'], 'lab_certificates_ref_unique');
        });

        Schema::create('lab_cert_register', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('company_id')->constrained('companies')->cascadeOnDelete();
            $table->string('register_no', 32);
            $table->foreignUlid('certificate_id')->constrained('lab_certificates')->restrictOnDelete();
            $table->date('entry_date');
            $table->string('action', 16)->default('ISSUED'); // ISSUED | HANDED_OVER | RETURNED | RENEWED | SURRENDERED
            $table->string('issued_to');
            $table->foreignUlid('buyer_id')->nullable()->constrained('buyers')->nullOnDelete();
            $table->unsignedSmallInteger('copies')->default(1);
            $table->date('due_date')->nullable();
            $table->date('return_date')->nullable();
            $table->foreignUlid('handed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('remarks')->nullable();
            $table->foreignUlid('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->unique(['company_id', 'register_no'], 'lab_cert_register_no_unique');
        });

        Schema::create('lab_trials', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('company_id')->constrained('companies')->cascadeOnDelete();
            $table->string('trial_no', 32);
            $table->foreignUlid('lab_floor_id')->constrained('floors')->restrictOnDelete();
            $table->date('trial_date');
            $table->foreignUlid('trial_by')->constrained('users')->restrictOnDelete();
            $table->string('status', 16)->default('PENDING'); // PENDING | IN_PROGRESS | COMPLETED | CANCELLED
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->foreignUlid('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->unique(['company_id', 'trial_no'], 'lab_trials_no_unique');
        });

        Schema::create('lab_trial_lines', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('company_id')->constrained('companies')->cascadeOnDelete();
            $table->foreignUlid('trial_id')->constrained('lab_trials')->cascadeOnDelete();
            $table->unsignedInteger('sort_order')->default(0);
            $table->foreignUlid('item_department_id')->nullable()->constrained('item_departments')->nullOnDelete();
            $table->foreignUlid('item_id')->constrained('items')->restrictOnDelete();
            $table->foreignUlid('supplier_id')->nullable()->constrained('mp_suppliers')->nullOnDelete();
            $table->string('brand', 64)->nullable();
            $table->foreignUlid('origin_country_id')->nullable()->constrained('origin_countries')->nullOnDelete();
            $table->decimal('price', 14, 4)->nullable();
            $table->decimal('claim_dose', 12, 4)->nullable();
            $table->string('dose_unit', 8)->default('%'); // % | g/l | ml/l | g
            $table->decimal('claim_cost', 14, 4)->nullable();
            $table->string('claim_ratio', 16)->nullable(); // liquor ratio, "1:8"
            $table->foreignUlid('cert_type_id')->nullable()->constrained('lab_cert_types')->nullOnDelete();
            $table->decimal('actual_dose', 12, 4)->nullable();
            $table->decimal('actual_cost', 14, 4)->nullable();
            $table->string('actual_ratio', 16)->nullable();
            $table->string('result', 16)->default('PENDING'); // PENDING | PASS | FAIL | RETRIAL
            $table->string('comments')->nullable();
            $table->decimal('sample_qty', 12, 3)->nullable();
            $table->date('required_by')->nullable();
            $table->timestamps();
        });

        Schema::create('lab_fabric_tests', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('company_id')->constrained('companies')->cascadeOnDelete();
            $table->string('test_no', 32);
            $table->date('test_date');
            $table->foreignUlid('buyer_id')->constrained('buyers')->restrictOnDelete();
            $table->foreignUlid('order_id')->nullable()->constrained('merch_orders')->nullOnDelete();
            $table->string('style_no', 64)->nullable();
            $table->foreignUlid('fabric_type_id')->nullable()->constrained('fabric_types')->nullOnDelete();
            $table->foreignUlid('composition_id')->nullable()->constrained('fiber_compositions')->nullOnDelete();
            $table->string('colour', 80)->nullable();
            $table->string('lot_no', 64)->nullable();
            $table->decimal('qty', 12, 3)->nullable();
            $table->string('drying', 16)->nullable(); // TUMBLE | LINE | FLAT | DRIP
            $table->string('summary', 16)->nullable(); // PASS | FAIL | CONDITIONAL
            $table->decimal('std_dia', 8, 2)->nullable();
            $table->decimal('act_dia', 8, 2)->nullable();
            $table->decimal('std_gsm', 8, 2)->nullable();
            $table->decimal('act_gsm', 8, 2)->nullable();
            $table->string('coil_no', 32)->nullable();
            $table->decimal('shrink_l', 6, 2)->nullable();
            $table->decimal('shrink_w', 6, 2)->nullable();
            $table->decimal('spirality', 6, 2)->nullable();
            $table->decimal('stitch_length', 6, 3)->nullable();
            $table->string('wash', 8)->nullable(); // a grey-scale grade, "4-5"
            $table->string('rub_dry', 8)->nullable();
            $table->string('rub_wet', 8)->nullable();
            $table->decimal('rh', 5, 2)->nullable(); // relative humidity of the conditioning room, %
            $table->text('remarks')->nullable();
            $table->string('status', 16)->default('DRAFT'); // DRAFT | SUBMITTED | APPROVED | REJECTED
            $table->string('decision_note')->nullable();
            $table->timestamp('decided_at')->nullable();
            $table->foreignUlid('decided_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignUlid('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->unique(['company_id', 'test_no'], 'lab_fabric_tests_no_unique');
        });
    }

    public function down(): void
    {
        foreach (['lab_fabric_tests', 'lab_trial_lines', 'lab_trials', 'lab_cert_register', 'lab_certificates', 'lab_equipment', 'lab_cert_types'] as $t) {
            Schema::dropIfExists($t);
        }
    }
};
