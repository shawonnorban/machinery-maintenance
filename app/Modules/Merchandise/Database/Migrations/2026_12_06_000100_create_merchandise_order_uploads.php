<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Merchandise Desk → Master Data → Style & Order Upload (AnnotechERP 06/01):
 * a buyer's style and order spreadsheet, uploaded as one batch once every row
 * verified — the batch and each order line it brought in, against the style,
 * order and colour × size line it made.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('merch_order_uploads', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('company_id')->constrained('companies')->cascadeOnDelete();
            $table->string('batch_no', 20);
            $table->string('file_type', 32)->default('STYLE_AND_ORDER_DETAIL');
            $table->string('file_name', 255);
            $table->unsignedInteger('rows')->default(0);
            $table->unsignedInteger('styles')->default(0);
            $table->unsignedInteger('orders')->default(0);
            $table->ulid('uploaded_by')->nullable();
            $table->timestamps();
            $table->unique(['company_id', 'batch_no']);
        });

        Schema::create('merch_order_upload_lines', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('company_id')->constrained('companies')->cascadeOnDelete();
            $table->foreignUlid('upload_id')->constrained('merch_order_uploads')->cascadeOnDelete();
            $table->unsignedInteger('row_no');
            $table->string('buyer_ac_group', 120)->nullable();
            $table->string('buyer_ac', 120)->nullable();
            $table->string('buyer', 120);
            $table->string('style', 96);
            $table->string('job_no', 64);
            $table->string('sub_style', 96)->nullable();
            $table->string('po_no', 60);
            $table->date('confirm_date')->nullable();
            $table->date('ship_date')->nullable();
            $table->string('ship_country', 120)->nullable();
            $table->string('item', 128)->nullable();
            $table->string('gmt_type', 128)->nullable();
            $table->string('colour', 120);
            $table->string('size', 60);
            $table->unsignedInteger('qty');
            $table->decimal('rate', 14, 4)->nullable();
            $table->decimal('total', 16, 2)->nullable();
            $table->date('production_date')->nullable();
            $table->ulid('style_id')->nullable();
            $table->ulid('order_id')->nullable();
            $table->timestamps();
            $table->index(['company_id', 'upload_id']);
            $table->index(['company_id', 'order_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('merch_order_upload_lines');
        Schema::dropIfExists('merch_order_uploads');
    }
};
