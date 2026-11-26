<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Merchandise Desk → T&A Followup (AnnotechERP 06/12): an order's time and
 * action calendar, raised from the TNA Master — each milestone's plan date
 * counted back from the shipment date, its actual date (entered here, or
 * read from the module that records the task) and remarks — and the order's
 * change history.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('merch_order_tna', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('company_id')->constrained('companies')->cascadeOnDelete();
            $table->foreignUlid('order_id')->constrained('merch_orders')->cascadeOnDelete();
            $table->foreignUlid('tna_task_id')->constrained('tna_tasks')->restrictOnDelete();
            $table->smallInteger('offset_days')->default(0);
            $table->date('plan_date')->nullable();
            $table->date('actual_date')->nullable();
            $table->boolean('required')->default(true);
            $table->boolean('auto')->default(false);
            $table->text('remarks')->nullable();
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->ulid('updated_by')->nullable();
            $table->timestamps();
            $table->unique(['order_id', 'tna_task_id']);
        });

        Schema::create('merch_order_tna_events', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('company_id')->constrained('companies')->cascadeOnDelete();
            $table->foreignUlid('order_id')->constrained('merch_orders')->cascadeOnDelete();
            $table->ulid('user_id')->nullable();
            $table->string('message', 500);
            $table->timestamps();
            $table->index(['order_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('merch_order_tna_events');
        Schema::dropIfExists('merch_order_tna');
    }
};
