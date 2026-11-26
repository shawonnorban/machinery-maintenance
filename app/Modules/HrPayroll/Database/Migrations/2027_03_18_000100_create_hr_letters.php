<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
 * HR & Payroll → Letter Management (AnnotechERP 24/09):
 *
 * - `hr_letter_types`: the kinds of letter (Appointment, Rejoin, Promotion …
 *   and any added), each switched on or off.
 * - `hr_letter_templates`: a type's wording in versions — title, subject,
 *   reference format, salutation, main body, numbered clauses, closing,
 *   signature and footer, the gazette quoted and whether the gazette and
 *   company salary tables print (and after which clause). One version is
 *   active; a version a letter was generated from is never rewritten — a
 *   save makes the next version.
 * - `hr_letters`: every letter issued — reference number, batch, type and
 *   template version, the employee and their details as they stood (so the
 *   letter reprints exactly as issued), the letter and effective dates and
 *   probation, generated / printed and how often.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('hr_letter_types', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('company_id')->constrained('companies')->cascadeOnDelete();
            $table->string('code', 40);
            $table->string('name');
            $table->boolean('active')->default(true);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();
            $table->unique(['company_id', 'code'], 'hr_letter_types_unique');
        });

        Schema::create('hr_letter_templates', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('company_id')->constrained('companies')->cascadeOnDelete();
            $table->foreignUlid('letter_type_id')->constrained('hr_letter_types')->cascadeOnDelete();
            $table->unsignedSmallInteger('version');
            $table->string('title', 300)->default('');
            $table->string('subject', 300)->default('');
            $table->string('reference_format', 120)->default('');
            $table->text('salutation')->nullable();
            $table->text('opening_message')->nullable();
            $table->json('clauses');
            $table->text('closing_message')->nullable();
            $table->string('signature_title', 200)->nullable();
            $table->string('signature_company', 200)->nullable();
            $table->text('footer_note')->nullable();
            $table->boolean('show_salary_tables')->default(false);
            $table->unsignedSmallInteger('salary_tables_after')->default(0);
            $table->foreignUlid('gazette_id')->nullable()->constrained('hr_gazettes')->nullOnDelete();
            $table->date('effective_from')->nullable();
            $table->string('status', 12); // ACTIVE | SUPERSEDED | INACTIVE
            $table->foreignUlid('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignUlid('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->unique(['letter_type_id', 'version'], 'hr_letter_templates_unique');
        });

        Schema::create('hr_letters', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('company_id')->constrained('companies')->cascadeOnDelete();
            $table->string('ref_no', 80);
            $table->string('batch_no', 20)->nullable();
            $table->foreignUlid('letter_type_id')->constrained('hr_letter_types')->restrictOnDelete();
            $table->foreignUlid('template_id')->constrained('hr_letter_templates')->restrictOnDelete();
            $table->unsignedSmallInteger('template_version');
            $table->foreignUlid('employee_id')->constrained('hr_employees')->cascadeOnDelete();
            $table->date('letter_date');
            $table->date('effective_date')->nullable();
            $table->unsignedTinyInteger('probation_months')->default(0);
            $table->json('employee'); // the details the letter was merged with, as they stood
            $table->string('department')->nullable();
            $table->string('section')->nullable();
            $table->string('floor')->nullable();
            $table->string('status', 10)->default('GENERATED'); // GENERATED | PRINTED
            $table->unsignedSmallInteger('print_count')->default(0);
            $table->timestamp('printed_at')->nullable();
            $table->foreignUlid('generated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index(['company_id', 'letter_date']);
            $table->index(['company_id', 'ref_no']);
        });
    }

    public function down(): void
    {
        foreach (['hr_letters', 'hr_letter_templates', 'hr_letter_types'] as $table) {
            Schema::dropIfExists($table);
        }
    }
};
