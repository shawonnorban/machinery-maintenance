<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Lab Dip & Sample → Sample Delivery Challan (AnnotechERP 06/10): a challan
 * covering samples couriered to the buyer — its date, buyer office, vehicle
 * and carrier — and the items on it.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('merch_sample_challans', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('company_id')->constrained('companies')->cascadeOnDelete();
            $table->string('challan_no', 32);
            $table->date('challan_date');
            $table->ulid('buyer_office_id')->nullable();
            $table->string('vehicle_no', 32)->nullable();
            $table->string('carrier', 64)->nullable();
            $table->string('status', 16)->default('DRAFT');
            $table->text('remarks')->nullable();
            $table->ulid('created_by')->nullable();
            $table->timestamps();
            $table->unique(['company_id', 'challan_no']);
        });

        Schema::create('merch_sample_challan_items', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('company_id')->constrained('companies')->cascadeOnDelete();
            $table->foreignUlid('challan_id')->constrained('merch_sample_challans')->cascadeOnDelete();
            $table->string('description', 255);
            $table->ulid('sample_type_id')->nullable();
            $table->decimal('qty', 12, 2);
            $table->string('uom', 16)->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('merch_sample_challan_items');
        Schema::dropIfExists('merch_sample_challans');
    }
};
