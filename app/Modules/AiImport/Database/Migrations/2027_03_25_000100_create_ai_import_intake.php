<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
 * AI File & PO Import → Intake (AnnotechERP 26/01): the files that come in
 * and how far each has got; the signatures that recognise a document; the
 * rules that pull its fields out; the validations that check them; and the
 * approval steps a person signs off before anything is written to the ERP.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ai_intake_files', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('company_id')->constrained('companies')->cascadeOnDelete();
            $table->string('file', 255); // a file name, or an uploaded file's download path
            $table->string('arrived_by', 16)->default('MANUAL'); // EMAIL | PORTAL | WATCHED_FOLDER | SFTP | MANUAL
            $table->string('family', 20)->nullable(); // PURCHASE_ORDER … ARTWORK | UNRECOGNISED
            $table->foreignUlid('buyer_id')->nullable()->constrained('buyers')->nullOnDelete(); // null: not identified
            $table->date('received_on')->nullable();
            $table->decimal('size_mb', 8, 1)->nullable();
            $table->decimal('confidence', 5, 1)->default(0);
            $table->string('stage', 20)->default('QUEUED'); // QUEUED | RECOGNISING | EXTRACTING | VALIDATING | AWAITING_APPROVAL | POSTED | FAILED
            $table->timestamps();
        });

        Schema::create('ai_signatures', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('company_id')->constrained('companies')->cascadeOnDelete();
            $table->string('name', 160);
            $table->string('family', 20)->nullable();
            $table->foreignUlid('buyer_id')->nullable()->constrained('buyers')->nullOnDelete(); // null: any buyer
            $table->string('marker', 16)->nullable(); // HEADER_TEXT | LOGO_REGION | SHEET_NAME | COLUMN_RUN | FILE_NAMING | SENDER_ADDRESS
            $table->decimal('hit_rate', 5, 1)->default(0);
            $table->unsignedInteger('times_matched')->default(0);
            $table->date('last_matched')->nullable();
            $table->boolean('enabled')->default(true);
            $table->timestamps();
            $table->unique(['company_id', 'name'], 'ai_signatures_name_unique');
        });

        Schema::create('ai_extraction_rules', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('company_id')->constrained('companies')->cascadeOnDelete();
            $table->string('field', 120);
            $table->string('family', 20)->nullable();
            $table->string('found_by', 16)->nullable(); // LABELLED | TABLE_COLUMN | FIXED_REGION | REGEX | MODEL
            $table->string('erp_column', 80)->nullable(); // table.column
            $table->string('data_type', 10)->default('TEXT'); // TEXT | NUMBER | DATE | CURRENCY | CODE
            $table->decimal('accuracy', 5, 1)->default(0);
            $table->boolean('mandatory')->default(false);
            $table->timestamps();
        });

        Schema::create('ai_validations', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('company_id')->constrained('companies')->cascadeOnDelete();
            $table->string('name', 200);
            $table->string('kind', 16)->nullable(); // MASTER_LOOKUP | RANGE | FORMAT | CROSS_FIELD | DUPLICATE
            $table->string('applies_to', 20)->default('EVERY');
            $table->string('on_failure', 8)->default('WARN'); // BLOCK | WARN | REVIEW
            $table->unsignedInteger('triggered')->default(0);
            $table->decimal('pass_rate', 5, 1)->default(0);
            $table->boolean('enabled')->default(true);
            $table->timestamps();
        });

        Schema::create('ai_approval_steps', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('company_id')->constrained('companies')->cascadeOnDelete();
            $table->string('name', 200);
            $table->string('applies_to', 20)->default('EVERY');
            $table->string('approver', 16)->nullable(); // MERCHANDISER | PLANNING | COMMERCIAL | COSTING | QUALITY | DIVISION_HEAD
            $table->unsignedSmallInteger('sequence')->default(1);
            $table->decimal('auto_skip', 5, 1)->nullable(); // skipped when confidence is above this
            $table->unsignedSmallInteger('sla_hours')->nullable();
            $table->unsignedInteger('pending')->default(0);
            $table->boolean('enabled')->default(true);
            $table->timestamps();
            $table->unique(['company_id', 'applies_to', 'sequence'], 'ai_approval_steps_sequence_unique');
        });
    }

    public function down(): void
    {
        foreach (['ai_approval_steps', 'ai_validations', 'ai_extraction_rules', 'ai_signatures', 'ai_intake_files'] as $table) {
            Schema::dropIfExists($table);
        }
    }
};
