<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
 * Cutting Floor → Cutting Input and Output (the user's design of 2026-11-16):
 * the cutting master lays a cutting — PO, colour, cutting number, part, the
 * fabric lot and its marker (length, plies, size ratio) and the fabric it
 * takes; the output clerk then records what was really cut per size, the
 * rejects and the fabric used, and cuts the bundle cards from that output.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cut_inputs', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('company_id')->constrained('companies')->cascadeOnDelete();
            $table->string('input_no', 24);
            $table->date('cut_date');
            $table->foreignUlid('order_id')->constrained('merch_orders')->restrictOnDelete();
            $table->string('po_no', 64);
            $table->string('style', 120)->nullable();
            $table->foreignUlid('buyer_id')->nullable()->constrained('buyers')->nullOnDelete();
            $table->string('colour', 64);
            $table->unsignedInteger('cutting_no');
            $table->string('parts_name', 60);
            $table->string('batch_no', 40);
            $table->foreignUlid('lot_id')->nullable()->constrained('fin_store_lots')->nullOnDelete();
            $table->string('garment', 120)->nullable();
            $table->string('fabric_type', 60)->nullable();
            $table->string('gsm', 16)->nullable();
            $table->string('dia_width', 16)->nullable();
            $table->decimal('marker_length', 10, 3)->nullable(); // metres
            $table->unsignedInteger('plies');
            $table->decimal('fabric_kg', 12, 3); // the lay's fabric, from the marker or as weighed
            $table->string('status', 12)->default('INPUT'); // INPUT | OUTPUT | CANCELLED
            $table->decimal('used_kg', 12, 3)->nullable();
            $table->decimal('remnant_kg', 12, 3)->nullable();
            $table->text('remarks')->nullable();
            $table->text('output_remarks')->nullable();
            $table->foreignUlid('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignUlid('output_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('output_at')->nullable();
            $table->timestamps();
            $table->unique(['company_id', 'input_no']);
            $table->index(['company_id', 'order_id', 'colour']);
        });
        Schema::create('cut_input_sizes', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('company_id')->constrained('companies')->cascadeOnDelete();
            $table->foreignUlid('input_id')->constrained('cut_inputs')->cascadeOnDelete();
            $table->string('size', 32);
            $table->unsignedInteger('ratio');
            $table->unsignedInteger('planned_qty');
            $table->unsignedInteger('output_qty')->nullable();
            $table->unsignedInteger('reject_qty')->default(0);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
            $table->unique(['input_id', 'size']);
        });
        Schema::table('cut_bundles', function (Blueprint $table): void {
            $table->foreignUlid('input_id')->nullable()->after('order_id')->constrained('cut_inputs')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('cut_bundles', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('input_id');
        });
        Schema::dropIfExists('cut_input_sizes');
        Schema::dropIfExists('cut_inputs');
    }
};
