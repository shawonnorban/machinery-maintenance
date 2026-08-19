<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Import and export jobs: ERD Section 21, SRS 33, ADR-031.
 *
 * A factory arriving on this system brings a spreadsheet of a few thousand
 * machines that somebody has been maintaining by hand for years. Getting it in
 * is the difference between a trial that starts on day one and one that never
 * starts at all.
 *
 * The job is a record of an attempt, not a queue entry. It survives the import
 * so that six months later there is an answer to "where did these three hundred
 * machines come from" — which file, whose upload, how many rows failed, and
 * why.
 *
 * Errors are rows in their own table rather than a blob on the job. A person
 * fixing a file needs to sort by row number and by column, and a JSON dump does
 * not let them.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('import_jobs', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('company_id')->constrained('companies')->cascadeOnDelete();
            $table->foreignUlid('user_id')->constrained('users')->cascadeOnDelete();

            $table->string('type', 48);
            $table->foreignUlid('file_id')->nullable()->constrained('file_attachments')->nullOnDelete();
            $table->string('original_name');

            // UPLOADED -> VALIDATING -> VALIDATED -> IMPORTING -> COMPLETED
            // with FAILED and CANCELLED as exits. Validation is a separate
            // state because the whole point is that a person sees what would
            // happen before it happens (SRS 33).
            $table->string('status', 16)->default('UPLOADED');

            $table->unsignedInteger('total_rows')->default(0);
            $table->unsignedInteger('valid_rows')->default(0);
            $table->unsignedInteger('success_rows')->default(0);
            $table->unsignedInteger('failed_rows')->default(0);
            $table->unsignedInteger('updated_rows')->default(0);

            $table->text('error_message')->nullable();
            $table->timestamp('validated_at')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();

            $table->index(['company_id', 'user_id', 'created_at'], 'import_jobs_owner_index');
            $table->index(['company_id', 'type', 'status'], 'import_jobs_type_index');
        });

        Schema::create('import_errors', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('company_id')->constrained('companies')->cascadeOnDelete();
            $table->foreignUlid('import_job_id')->constrained('import_jobs')->cascadeOnDelete();

            // The row number in the uploaded file, counting the header as row
            // 1. A person fixing the file is looking at a spreadsheet, and an
            // index that does not match what they see is worse than none.
            $table->unsignedInteger('row_number');
            $table->string('field', 64)->nullable();
            $table->text('error');
            $table->text('value')->nullable();
            $table->timestamp('created_at')->nullable();

            $table->index(['import_job_id', 'row_number'], 'import_errors_row_index');
        });

        Schema::create('export_jobs', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('company_id')->constrained('companies')->cascadeOnDelete();
            $table->foreignUlid('requested_by')->constrained('users')->cascadeOnDelete();

            $table->string('type', 48);
            $table->json('filters_json')->nullable();
            $table->string('format', 8);
            $table->string('status', 16)->default('QUEUED');
            $table->foreignUlid('file_id')->nullable()->constrained('file_attachments')->nullOnDelete();
            $table->unsignedInteger('row_count')->nullable();
            $table->text('error_message')->nullable();
            $table->timestamp('completed_at')->nullable();
            // Same retention as a report: a raw export of a company's asset
            // register is the same data with fewer columns (SRS 35).
            $table->timestamp('expires_at');
            $table->timestamps();

            $table->index(['company_id', 'requested_by', 'created_at'], 'export_jobs_owner_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('export_jobs');
        Schema::dropIfExists('import_errors');
        Schema::dropIfExists('import_jobs');
    }
};
