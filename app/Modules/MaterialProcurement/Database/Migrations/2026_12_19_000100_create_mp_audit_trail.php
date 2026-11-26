<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Material Procurement → Control → Audit Trail (AnnotechERP 08/07): every
 * critical transaction in the module — masters, requisitions, quotations and
 * their revisions, price decisions, handovers, follow-ups and receipts — as
 * who, what, on which screen, the document and a one-line summary.
 * Permanent: rows are never edited or deleted.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('mp_audit_trail', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('company_id')->constrained('companies')->cascadeOnDelete();
            $table->foreignUlid('user_id')->nullable()->constrained('users')->nullOnDelete();
            // CREATE, UPDATE or DELETE.
            $table->string('action', 8);
            $table->string('screen', 48);
            $table->string('reference', 64)->nullable();
            $table->text('summary');
            $table->timestamp('created_at', 6)->useCurrent();
            $table->index(['company_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mp_audit_trail');
    }
};
