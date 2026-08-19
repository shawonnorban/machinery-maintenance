<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Report jobs: ERD Section 28, SRS 32, ADR-032.
 *
 * A report is a request, not a page. Small ones answer immediately; a year of
 * downtime across a fleet does not, and holding an HTTP connection open while
 * it runs turns a slow report into a failed one plus a half-written file.
 *
 * The row is written before the work starts, so a report that dies mid-run is
 * visible as a failure with its reason rather than as a request that vanished.
 *
 * Parameters are frozen on the row. A report re-read next week must show what
 * was asked for, not what the current defaults would ask for now — the same
 * reason an approval decision freezes the values it was made against.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('report_jobs', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('company_id')->constrained('companies')->cascadeOnDelete();
            $table->foreignUlid('user_id')->constrained('users')->cascadeOnDelete();

            $table->string('report_type', 64);
            $table->json('parameters_json')->nullable();
            $table->json('filters_json')->nullable();
            $table->string('format', 8);   // CSV | XLSX | PDF
            $table->string('locale', 8);   // Rendered in the requester's language.

            $table->string('status', 16)->default('QUEUED');
            $table->foreignUlid('file_id')->nullable()->constrained('file_attachments')->nullOnDelete();
            $table->unsignedInteger('row_count')->nullable();
            $table->text('error_message')->nullable();

            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            // Generated files are a copy of data that lives elsewhere. Keeping
            // them for ever means keeping a tenant's cost figures in a file
            // nobody remembers exists (SRS 35).
            $table->timestamp('expires_at');
            $table->timestamps();

            $table->index(['company_id', 'user_id', 'created_at'], 'report_jobs_owner_index');
            $table->index(['company_id', 'status'], 'report_jobs_status_index');
            $table->index('expires_at', 'report_jobs_expiry_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('report_jobs');
    }
};
