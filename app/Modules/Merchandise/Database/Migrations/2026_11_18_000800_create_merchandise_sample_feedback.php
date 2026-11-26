<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Lab Dip & Sample → Buyer Sample Feedback (AnnotechERP 06/10): the buyer's
 * verdict on a submitted sample — of which job / order and sample type, when
 * the batch was submitted, what the buyer said — and, as a separate
 * decision, whether the bulk shade is released.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('merch_sample_feedback', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('company_id')->constrained('companies')->cascadeOnDelete();
            $table->ulid('order_id')->nullable();
            $table->ulid('buyer_office_id')->nullable();
            $table->ulid('sample_type_id')->nullable();
            $table->ulid('batch_id')->nullable();
            $table->date('submit_date');
            $table->string('status', 16)->default('SUBMITTED');
            $table->boolean('bulk_shade_approved')->default(false);
            $table->text('comments')->nullable();
            $table->ulid('created_by')->nullable();
            $table->timestamps();
            $table->index(['company_id', 'buyer_office_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('merch_sample_feedback');
    }
};
