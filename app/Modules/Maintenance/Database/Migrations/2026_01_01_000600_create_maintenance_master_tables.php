<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Maintenance master data: ERD Section 6, SRS 12.
 *
 * Templates are versioned because a checklist changes over its life while
 * historical work orders must keep reproducing the exact list that was
 * executed. A work order references a template VERSION, never a template.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('maintenance_types', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            // null = platform-seeded, shared by every tenant
            $table->foreignUlid('company_id')->nullable()
                ->constrained('companies')->cascadeOnDelete();
            $table->string('name');
            $table->string('code', 48);
            $table->string('default_priority', 16)->default('MEDIUM');
            $table->boolean('is_planned')->default(true);
            $table->boolean('active')->default(true);
            $table->timestamps();

            $table->unique(['company_id', 'code']);
        });

        Schema::create('maintenance_templates', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('company_id')->nullable()
                ->constrained('companies')->cascadeOnDelete();
            $table->foreignUlid('asset_type_id')->nullable()
                ->constrained('asset_types')->nullOnDelete();
            $table->foreignUlid('maintenance_type_id')->nullable()
                ->constrained('maintenance_types')->nullOnDelete();
            $table->string('name');
            $table->string('code', 64);
            $table->text('description')->nullable();
            $table->string('status', 32)->default('ACTIVE');
            $table->foreignUlid('created_by')->nullable();
            $table->timestamps();

            $table->unique(['company_id', 'code']);
            $table->index(['company_id', 'asset_type_id']);
        });

        Schema::create('maintenance_template_versions', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('company_id')->nullable()
                ->constrained('companies')->cascadeOnDelete();
            $table->foreignUlid('template_id')->constrained('maintenance_templates')->cascadeOnDelete();
            $table->unsignedInteger('version_number');
            // DRAFT is editable. PUBLISHED is frozen: editing it creates a new
            // version, so a historical work order still reproduces the exact
            // list its technician worked through (SRS 12).
            $table->string('status', 32)->default('DRAFT');
            $table->date('effective_from')->nullable();
            $table->date('effective_to')->nullable();
            $table->unsignedInteger('estimated_duration_minutes')->nullable();
            $table->text('instructions')->nullable();
            $table->foreignUlid('published_by')->nullable();
            $table->timestamp('published_at')->nullable();
            // Set the first time a work order snapshots this version. Even an
            // administrator cannot archive a version that has been executed.
            $table->timestamp('first_used_at')->nullable();
            $table->foreignUlid('supersedes_version_id')->nullable();
            $table->timestamps();

            // Named explicitly: the generated name exceeds MySQL's 64-character
            // identifier limit.
            $table->unique(['template_id', 'version_number'], 'template_versions_number_unique');
            $table->index(['company_id', 'template_id', 'status'], 'template_versions_status_idx');
        });

        Schema::create('checklist_items', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('company_id')->nullable()
                ->constrained('companies')->cascadeOnDelete();
            $table->foreignUlid('template_version_id')
                ->constrained('maintenance_template_versions')->cascadeOnDelete();
            $table->unsignedInteger('sequence');
            $table->string('label', 500);
            $table->text('help_text')->nullable();
            // PASS_FAIL | NUMERIC | TEXT | CHOICE | PHOTO | SIGNATURE
            $table->string('input_type', 24)->default('PASS_FAIL');
            $table->string('unit', 32)->nullable();
            $table->json('options_json')->nullable();
            $table->decimal('expected_value', 18, 4)->nullable();
            $table->decimal('tolerance_min', 18, 4)->nullable();
            $table->decimal('tolerance_max', 18, 4)->nullable();
            $table->boolean('required')->default(true);
            $table->boolean('allows_attachment')->default(true);
            // A safety item that fails needs evidence and a note, not a tick
            // in a box (Seed Catalog 9.1).
            $table->boolean('requires_attachment_on_fail')->default(false);
            $table->boolean('requires_note_on_fail')->default(false);
            $table->boolean('fail_creates_followup_work_order')->default(false);
            $table->boolean('is_safety_item')->default(false);
            $table->timestamps();

            $table->unique(['template_version_id', 'sequence'], 'checklist_items_sequence_unique');
            $table->index(['company_id', 'template_version_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('checklist_items');
        Schema::dropIfExists('maintenance_template_versions');
        Schema::dropIfExists('maintenance_templates');
        Schema::dropIfExists('maintenance_types');
    }
};
