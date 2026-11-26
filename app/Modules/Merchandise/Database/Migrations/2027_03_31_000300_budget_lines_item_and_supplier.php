<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Order Budget Sheet → Bill of Material, the accessory groups (2026-11-17):
 * a line names its item from the trims master (its code kept as it was) and
 * the supplier its rate comes from.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('merch_budget_lines', function (Blueprint $table): void {
            $table->ulid('item_id')->nullable()->after('section');
            $table->string('item_code', 64)->nullable()->after('item_id');
            $table->ulid('supplier_id')->nullable()->after('rate');
        });
    }

    public function down(): void
    {
        Schema::table('merch_budget_lines', function (Blueprint $table): void {
            $table->dropColumn(['item_id', 'item_code', 'supplier_id']);
        });
    }
};
