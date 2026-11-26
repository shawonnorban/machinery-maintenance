<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Style & Order → Projection & Call-Off Order (AnnotechERP 06/07): a call-off
 * is an order drawn down against a projection order of the same style. A
 * projection with call-offs against it is not deleted.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('merch_orders', function (Blueprint $table): void {
            $table->foreignUlid('projection_id')->nullable()->constrained('merch_orders')->restrictOnDelete();
            $table->index(['company_id', 'projection_id']);
        });
    }

    public function down(): void
    {
        Schema::table('merch_orders', function (Blueprint $table): void {
            $table->dropIndex(['company_id', 'projection_id']);
            $table->dropConstrainedForeignId('projection_id');
        });
    }
};
