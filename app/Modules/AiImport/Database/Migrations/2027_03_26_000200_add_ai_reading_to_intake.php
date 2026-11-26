<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
 * AI File & PO Import → Intake read by an AI service (AnnotechERP 26/01, the
 * user's decision of 2026-11-12): who read the file and when, what it made of
 * it, the values it pulled out per extraction rule, and the Approval Center's
 * verdict on the import.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ai_intake_files', function (Blueprint $table): void {
            $table->string('read_by', 120)->nullable(); // "Claude · claude-opus-5-5"
            $table->timestamp('read_at')->nullable();
            $table->text('summary')->nullable(); // what the AI says the document is
            $table->text('read_note')->nullable(); // why it stopped where it did
            $table->string('approval_status', 16)->nullable(); // the Approval Center's verdict
        });

        Schema::create('ai_intake_values', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('company_id')->constrained('companies')->cascadeOnDelete();
            $table->foreignUlid('intake_file_id')->constrained('ai_intake_files')->cascadeOnDelete();
            $table->string('field', 120);
            $table->string('erp_column', 80)->nullable();
            $table->text('value')->nullable();
            $table->decimal('confidence', 5, 1)->default(0);
            $table->boolean('mandatory')->default(false);
            $table->timestamps();
            $table->index(['intake_file_id'], 'ai_intake_values_file_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_intake_values');
        Schema::table('ai_intake_files', function (Blueprint $table): void {
            $table->dropColumn(['read_by', 'read_at', 'summary', 'read_note', 'approval_status']);
        });
    }
};
