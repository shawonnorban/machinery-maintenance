<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Access Control → Configuration and Operations (AnnotechERP 02/03, 02/04):
 * report templates, mail relays, business-rule violations, broadcasts to
 * every user, and the register of document deletion requests.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('report_templates', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('company_id')->constrained('companies')->cascadeOnDelete();
            $table->string('code', 48);
            $table->string('name');
            $table->string('report_path')->nullable();
            $table->string('output_format', 8);
            $table->string('orientation', 12)->nullable();
            $table->string('paper_size', 8)->nullable();
            $table->text('description')->nullable();
            $table->boolean('active')->default(true);
            $table->timestamps();

            $table->unique(['company_id', 'code']);
        });

        Schema::create('mail_services', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('company_id')->constrained('companies')->cascadeOnDelete();
            $table->string('service_name', 128);
            $table->string('module_code', 48)->nullable();
            $table->string('title')->nullable();
            $table->text('to_mail')->nullable();
            $table->text('cc_mail')->nullable();
            $table->text('bcc_mail')->nullable();
            $table->string('smtp_host');
            $table->unsignedInteger('port');
            $table->string('smtp_username');
            // Only the NAME of the environment variable holding the password —
            // the password itself is never stored (Brickland's own rule).
            $table->string('password_env', 64);
            $table->string('encryption', 12);
            $table->string('from_name');
            $table->string('from_address');
            $table->string('reply_to')->nullable();
            $table->text('remarks')->nullable();
            $table->boolean('is_default')->default(false);
            $table->boolean('active')->default(true);
            $table->timestamps();

            $table->unique(['company_id', 'service_name']);
        });

        Schema::create('rule_violations', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('company_id')->constrained('companies')->cascadeOnDelete();
            // A value of the BUSINESS_RULE lookup table (BR-001 …).
            $table->string('rule_code', 48);
            $table->string('document_reference', 128);
            $table->string('severity', 12);
            $table->string('status', 16)->default('OPEN');
            $table->text('detail');
            $table->foreignUlid('raised_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['company_id', 'rule_code']);
        });

        Schema::create('notification_broadcasts', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('company_id')->constrained('companies')->cascadeOnDelete();
            $table->string('title');
            $table->string('notification_type', 16);
            $table->text('message');
            $table->string('audience', 8);
            // The role or user for a ROLE or USER audience.
            $table->string('audience_id', 64)->nullable();
            $table->string('severity', 12)->nullable();
            $table->date('message_time');
            $table->date('offline_time')->nullable();
            $table->boolean('active')->default(true);
            $table->unsignedInteger('recipients_count')->default(0);
            $table->foreignUlid('sent_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('deletion_requests', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('company_id')->constrained('companies')->cascadeOnDelete();
            $table->string('document_type', 32);
            $table->string('document_no', 64);
            $table->text('reason');
            $table->string('status', 12)->default('DRAFT');
            $table->text('approval_remarks')->nullable();
            $table->foreignUlid('requested_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['company_id', 'document_type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('deletion_requests');
        Schema::dropIfExists('notification_broadcasts');
        Schema::dropIfExists('rule_violations');
        Schema::dropIfExists('mail_services');
        Schema::dropIfExists('report_templates');
    }
};
