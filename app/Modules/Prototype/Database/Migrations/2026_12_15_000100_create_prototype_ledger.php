<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Prototype Desk → Dashboard (AnnotechERP 07/02): what happened to each
 * sample (its timeline and audit trail — the lifecycle stage and status it
 * was left in, so a change shows as From → To), the alerts the desk raises
 * and resolves, and the documents attached to a sample in versions.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('proto_events', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('company_id')->constrained('companies')->cascadeOnDelete();
            $table->foreignUlid('sample_id')->nullable()->constrained('proto_samples')->cascadeOnDelete();
            $table->string('action', 40);
            $table->json('params')->nullable();
            $table->string('doc_no', 32)->nullable();
            // The lifecycle stage and status the sample stood at after the event.
            $table->string('stage', 16)->nullable();
            $table->string('status', 16)->nullable();
            $table->string('by_name', 255)->nullable();
            $table->foreignUlid('by_user')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('happened_at');
            $table->timestamps();
            $table->index(['company_id', 'sample_id', 'happened_at']);
        });

        Schema::create('proto_alerts', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('company_id')->constrained('companies')->cascadeOnDelete();
            $table->string('kind', 24);
            $table->string('severity', 10);
            $table->foreignUlid('sample_id')->nullable()->constrained('proto_samples')->cascadeOnDelete();
            $table->json('params')->nullable();
            $table->string('for_desk', 24);
            $table->string('doc_no', 32)->nullable();
            $table->timestamp('raised_at');
            $table->timestamp('read_at')->nullable();
            $table->timestamp('resolved_at')->nullable();
            $table->foreignUlid('resolved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index(['company_id', 'kind', 'doc_no']);
        });

        Schema::create('proto_documents', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('company_id')->constrained('companies')->cascadeOnDelete();
            $table->string('doc_no', 24);
            $table->foreignUlid('sample_id')->constrained('proto_samples')->cascadeOnDelete();
            $table->string('doc_type', 24);
            $table->unsignedSmallInteger('version');
            $table->string('title', 255);
            // A path or link typed in, or the uploaded file (file_attachments) — one or the other.
            $table->string('file', 500)->nullable();
            $table->ulid('file_id')->nullable();
            $table->string('file_name', 255)->nullable();
            $table->unsignedBigInteger('file_size')->nullable();
            $table->text('remarks')->nullable();
            $table->foreignUlid('uploaded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->unique(['company_id', 'doc_no']);
            $table->unique(['sample_id', 'doc_type', 'version']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('proto_documents');
        Schema::dropIfExists('proto_alerts');
        Schema::dropIfExists('proto_events');
    }
};
