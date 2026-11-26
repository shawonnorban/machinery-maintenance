<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Merchandise Desk → Inquiry (AnnotechERP 06/05): the buyer's inquiry and the
 * quoted FOB it moves through (draft → quotation sent → revised → confirmed or
 * cancelled), and the development style registered against a confirmed one —
 * its header and the size sets it is offered in.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('merch_inquiries', function (Blueprint $table): void {
            $this->base($table);
            $table->string('inquiry_no', 16);
            $table->foreignUlid('buyer_id')->constrained('buyers')->restrictOnDelete();
            $table->string('inco_term', 8)->nullable();
            $table->string('lc_term', 16)->nullable();
            $table->string('cons_unit', 8)->nullable();
            $table->string('calc_unit', 8)->nullable();
            $table->string('currency', 3)->nullable();
            $table->decimal('exchange_rate', 14, 4)->default(0);
            $table->string('style_no', 64);
            $table->string('style_ext', 32)->nullable();
            $table->string('style_desc');
            $table->date('inquiry_date');
            $table->decimal('quoted_fob', 14, 4)->nullable();
            $table->text('remarks')->nullable();
            $table->unsignedInteger('revision')->default(0);
            $table->string('status', 16)->default('DRAFT');
            $table->foreignUlid('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->unique(['company_id', 'inquiry_no']);
            $table->index(['company_id', 'buyer_id', 'status']);
        });

        Schema::create('merch_styles', function (Blueprint $table): void {
            $this->base($table);
            $table->foreignUlid('buyer_id')->constrained('buyers')->restrictOnDelete();
            $table->foreignUlid('buyer_office_id')->nullable()->constrained('buyer_offices')->nullOnDelete();
            $table->foreignUlid('inquiry_id')->nullable()->constrained('merch_inquiries')->restrictOnDelete();
            $table->string('style_ref', 64);
            $table->string('style_ext', 32)->nullable();
            $table->string('description');
            $table->boolean('is_set')->default(false);
            $table->boolean('is_model')->default(false);
            $table->boolean('is_combo')->default(false);
            $table->boolean('is_colour_pair')->default(false);
            $table->ulid('prev_style_id')->nullable();
            $table->string('qty_unit', 8)->default('PCS');
            $table->unsignedInteger('pcs_per_unit')->default(1);
            $table->string('season', 32)->nullable();
            $table->string('ref_no', 32)->nullable();
            $table->string('nature_of_work')->nullable();
            $table->foreignUlid('supplier_id')->nullable()->constrained('mp_suppliers')->nullOnDelete();
            $table->foreignUlid('origin_country_id')->nullable()->constrained('origin_countries')->nullOnDelete();
            $table->string('status', 16)->default('DEVELOPMENT');
            $table->foreignUlid('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->index(['company_id', 'buyer_id', 'status']);
        });
        // A self-reference is added once the table (and its primary key) exists.
        Schema::table('merch_styles', function (Blueprint $table): void {
            $table->foreign('prev_style_id')->references('id')->on('merch_styles')->nullOnDelete();
        });
        // One style per reference and extension; Postgres treats NULLs as distinct, so a missing extension is compared as blank.
        DB::statement("CREATE UNIQUE INDEX merch_styles_ref_unique ON merch_styles (company_id, style_ref, COALESCE(style_ext, ''))");

        Schema::create('merch_style_size_ranges', function (Blueprint $table): void {
            $this->base($table);
            $table->foreignUlid('style_id')->constrained('merch_styles')->cascadeOnDelete();
            $table->unsignedInteger('sl_no');
            $table->json('size_ids');
            $table->string('sub_style', 64)->nullable();
            $table->unique(['style_id', 'sl_no']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('merch_style_size_ranges');
        Schema::dropIfExists('merch_styles');
        Schema::dropIfExists('merch_inquiries');
    }

    private function base(Blueprint $table): void
    {
        $table->ulid('id')->primary();
        $table->foreignUlid('company_id')->constrained('companies')->cascadeOnDelete();
        $table->timestamps();
    }
};
