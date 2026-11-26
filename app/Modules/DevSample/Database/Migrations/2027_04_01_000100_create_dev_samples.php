<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Dev Sample Status (AnnotechERP 31, from the user's Norban ERP): one row per
 * development sample a buyer asked for — who, what, the sample's dates from
 * booking to submission, the quantities and the quoted prices, and where the
 * sample and the order stand. `serial_no` is the company's running number
 * the list shows as # and the address carries.
 *
 * Prices are kept as Norban keeps them: a list of "label – $" levels per kind
 * (`target_prices`, `fob_prices`); the first level is the summary price, the
 * second (else the first) is stored in `target_price` / `fob_price`.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ds_samples', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('company_id')->constrained('companies')->cascadeOnDelete();
            $table->unsignedInteger('serial_no');

            // Basic information
            $table->string('season', 64)->nullable();
            $table->foreignUlid('buyer_id')->nullable()->constrained('buyers')->nullOnDelete();
            $table->string('customer_name', 100)->nullable();
            $table->string('brand', 100)->nullable();
            $table->string('department', 100)->nullable();
            $table->string('division', 100)->nullable();
            $table->string('styling', 100)->nullable();
            $table->foreignUlid('image_file_id')->nullable()->constrained('file_attachments')->nullOnDelete();
            $table->string('job_no', 100)->nullable();

            // Sample details
            $table->json('items')->nullable();
            $table->string('required_category', 32)->nullable();
            $table->string('sample_type', 32)->nullable();
            $table->text('fabrication')->nullable();
            $table->json('colours')->nullable();

            // Dates
            $table->date('tp_rcv_date')->nullable();
            $table->date('buyer_requested_date')->nullable();
            $table->date('submission_deadline')->nullable();
            $table->date('sub_plan_date')->nullable();

            // Production & sample tracking
            $table->date('booking_date')->nullable();
            $table->date('knitting_plan_date')->nullable();
            $table->date('actual_grige_rcvd_date')->nullable();
            $table->date('finish_fabric_delivery_plan_date')->nullable();
            $table->date('finish_fabric_rcvd_date')->nullable();
            $table->date('sample_sewing_plan_date')->nullable();
            $table->date('file_handover_date')->nullable();
            $table->date('pattern_ready_date')->nullable();
            $table->date('cutting_date')->nullable();
            $table->date('print_emb_date')->nullable();
            $table->date('sewing_start_date')->nullable();
            $table->date('sewing_output_date')->nullable();
            $table->date('sample_submitted_date')->nullable();
            $table->string('awb_tracking', 255)->nullable();

            // Quantity & price
            $table->unsignedInteger('sample_qty')->nullable();
            $table->unsignedInteger('possible_qty')->nullable();
            $table->unsignedInteger('actual_order_qty')->nullable();
            $table->json('target_prices')->nullable();
            $table->json('fob_prices')->nullable();
            $table->decimal('target_price', 12, 4)->nullable();
            $table->decimal('fob_price', 12, 4)->nullable();
            $table->date('last_price_quote_date')->nullable();
            $table->foreignUlid('last_price_quote_by')->nullable()->constrained('users')->nullOnDelete();

            // Status
            $table->string('price_status', 32)->nullable();
            $table->string('sample_status', 32)->nullable();
            $table->string('order_confirmation', 32)->nullable();
            $table->string('current_order_situation', 32)->nullable();
            $table->text('remarks')->nullable();

            $table->foreignUlid('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignUlid('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['company_id', 'serial_no']);
            $table->index(['company_id', 'buyer_id']);
            $table->index(['company_id', 'season']);
            $table->index(['company_id', 'sample_status']);
            $table->index(['company_id', 'order_confirmation']);
            $table->index(['company_id', 'price_status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ds_samples');
    }
};
