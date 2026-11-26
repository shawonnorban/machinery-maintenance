<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Settings → System & Maintenance (AnnotechERP 03/04): the company's default
 * notification channels, the scheduler's run log, backups and restores, and
 * how long each kind of data is kept.
 *
 * scheduled_job_runs carries no company_id: one command runs for every
 * company, so its log belongs to none of them — and keeps no output for the
 * same reason.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('notification_event_defaults', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('company_id')->constrained('companies')->cascadeOnDelete();
            $table->string('event_type', 64);
            $table->boolean('email')->default(false);
            $table->boolean('sms')->default(false);
            $table->boolean('whatsapp')->default(false);
            $table->boolean('locked')->default(false);
            $table->timestamps();

            $table->unique(['company_id', 'event_type']);
        });

        Schema::create('scheduled_job_runs', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->string('command');
            $table->string('expression', 64);
            $table->string('status', 12);
            $table->integer('exit_code')->nullable();
            $table->timestamp('started_at');
            $table->timestamp('finished_at')->nullable();
            $table->unsignedInteger('duration_ms')->nullable();
            $table->timestamps();

            $table->index(['command', 'started_at']);
            $table->index('started_at');
        });

        Schema::create('company_backups', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('company_id')->constrained('companies')->cascadeOnDelete();
            $table->string('kind', 12)->default('MANUAL');
            $table->string('status', 12)->default('QUEUED');
            $table->string('file_path')->nullable();
            $table->unsignedBigInteger('size_bytes')->nullable();
            $table->string('checksum', 64)->nullable();
            $table->json('tables_json')->nullable();
            $table->foreignUlid('requested_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('finished_at')->nullable();
            $table->text('error')->nullable();
            $table->timestamps();

            $table->index(['company_id', 'created_at']);
        });

        Schema::create('company_restores', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('company_id')->constrained('companies')->cascadeOnDelete();
            $table->string('file_path');
            $table->string('original_name')->nullable();
            $table->string('status', 12);
            $table->json('manifest_json')->nullable();
            $table->json('preview_json')->nullable();
            $table->foreignUlid('pre_restore_backup_id')->nullable()->constrained('company_backups')->nullOnDelete();
            $table->foreignUlid('requested_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignUlid('applied_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('applied_at')->nullable();
            $table->text('error')->nullable();
            $table->timestamps();

            $table->index(['company_id', 'created_at']);
        });

        Schema::create('retention_policies', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('company_id')->constrained('companies')->cascadeOnDelete();
            $table->string('category', 24);
            $table->unsignedInteger('keep_days');
            $table->boolean('active')->default(true);
            $table->timestamp('last_purged_at')->nullable();
            $table->unsignedInteger('last_purged_count')->nullable();
            $table->timestamps();

            $table->unique(['company_id', 'category']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('retention_policies');
        Schema::dropIfExists('company_restores');
        Schema::dropIfExists('company_backups');
        Schema::dropIfExists('scheduled_job_runs');
        Schema::dropIfExists('notification_event_defaults');
    }
};
