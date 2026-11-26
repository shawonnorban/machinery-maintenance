<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Merchandise Desk → Trims & Accessories (AnnotechERP 06/06): the six month
 * closings (purchased fabric, finished fabric, trims, batch fabric, grey and
 * finished leftover) — one row per kind and month, each Month Close run
 * rebuilding the documents still standing in its way; with none, the month
 * closes and its documents can no longer be saved or finalised.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('merch_stock_closings', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('company_id')->constrained('companies')->cascadeOnDelete();
            $table->string('kind', 24);
            $table->string('period', 7);
            $table->string('status', 8)->default('OPEN');
            $table->timestamp('checked_at')->nullable();
            $table->unsignedInteger('exceptions')->default(0);
            $table->timestamp('closed_at')->nullable();
            $table->ulid('closed_by')->nullable();
            $table->text('remarks')->nullable();
            $table->timestamps();
            $table->unique(['company_id', 'kind', 'period']);
        });

        Schema::create('merch_stock_closing_exceptions', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('company_id')->constrained('companies')->cascadeOnDelete();
            $table->foreignUlid('closing_id')->constrained('merch_stock_closings')->cascadeOnDelete();
            $table->string('document', 40);
            $table->string('doc_no', 40);
            $table->date('doc_date');
            $table->string('status', 16);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('merch_stock_closing_exceptions');
        Schema::dropIfExists('merch_stock_closings');
    }
};
