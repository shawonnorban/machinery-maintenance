<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Merchandise Desk → Trims & Accessories (AnnotechERP 06/06): the purchased
 * fabric indent — a requisition for bought-in woven and knit fabric against
 * an order, filled from the style's purchased fabrics, saved with its fabric
 * lines and taken Draft → Submitted → Approved or Rejected.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('merch_pfab_indents', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('company_id')->constrained('companies')->cascadeOnDelete();
            $table->string('req_no', 32);
            $table->date('req_date');
            $table->ulid('buyer_office_id')->nullable();
            $table->foreignUlid('order_id')->constrained('merch_orders')->restrictOnDelete();
            $table->ulid('supplier_id')->nullable();
            $table->date('ship_date')->nullable();
            $table->string('gsm', 40)->nullable();
            $table->string('dia', 40)->nullable();
            $table->text('remarks')->nullable();
            $table->string('status', 12)->default('DRAFT');
            $table->timestamp('submitted_at')->nullable();
            $table->timestamp('decided_at')->nullable();
            $table->ulid('decided_by')->nullable();
            $table->ulid('created_by')->nullable();
            $table->timestamps();
            $table->unique(['company_id', 'req_no']);
            $table->index(['company_id', 'order_id']);
        });

        Schema::create('merch_pfab_indent_lines', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('company_id')->constrained('companies')->cascadeOnDelete();
            $table->foreignUlid('indent_id')->constrained('merch_pfab_indents')->cascadeOnDelete();
            $table->ulid('style_fabric_id')->nullable();
            $table->ulid('fabric_type_id')->nullable();
            $table->string('description', 200)->nullable();
            $table->string('construction', 120)->nullable();
            $table->ulid('composition_id')->nullable();
            $table->ulid('colour_id')->nullable();
            $table->decimal('qty', 14, 4);
            $table->string('uom', 8);
            $table->string('gsm', 40)->nullable();
            $table->string('dia', 40)->nullable();
            $table->ulid('supplier_id')->nullable();
            $table->date('required_date')->nullable();
            $table->string('remarks', 200)->nullable();
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('merch_pfab_indent_lines');
        Schema::dropIfExists('merch_pfab_indents');
    }
};
