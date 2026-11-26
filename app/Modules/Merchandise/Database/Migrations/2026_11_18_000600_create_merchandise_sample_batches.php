<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Lab Dip & Sample → Sample Production Entry (AnnotechERP 06/10): a batch the
 * sample room opens on a date, the planned sample lines pulled into it, and
 * what each line sewed, delivered and rejected. A submitted batch is closed.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('merch_sample_batches', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('company_id')->constrained('companies')->cascadeOnDelete();
            $table->string('batch_no', 32);
            $table->date('batch_date');
            $table->string('card_format', 8)->default('LINDEX');
            $table->string('status', 16)->default('DRAFT');
            $table->timestamp('submitted_at')->nullable();
            $table->ulid('submitted_by')->nullable();
            $table->ulid('created_by')->nullable();
            $table->timestamps();
            $table->unique(['company_id', 'batch_no']);
        });

        Schema::table('merch_sample_lines', function (Blueprint $table): void {
            $table->ulid('batch_id')->nullable()->index();
            $table->decimal('sewn_qty', 12, 2)->default(0);
            $table->decimal('delivered_qty', 12, 2)->default(0);
            $table->decimal('rejected_qty', 12, 2)->default(0);
            $table->text('batch_remarks')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('merch_sample_lines', function (Blueprint $table): void {
            $table->dropColumn(['batch_id', 'sewn_qty', 'delivered_qty', 'rejected_qty', 'batch_remarks']);
        });
        Schema::dropIfExists('merch_sample_batches');
    }
};
