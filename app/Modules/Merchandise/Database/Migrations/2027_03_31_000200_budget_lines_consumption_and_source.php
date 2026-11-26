<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Order Budget Sheet → Bill of Material (2026-11-17): a line may carry its
 * consumption per dozen and wastage %, its quantity per piece then following
 * from them, and says where it was loaded from (the style's BOM or the
 * order's fabric booking) so loading again replaces only what it loaded.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('merch_budget_lines', function (Blueprint $table): void {
            $table->decimal('cons_dzn', 14, 4)->nullable()->after('uom');
            $table->decimal('wastage_pct', 7, 3)->nullable()->after('cons_dzn');
            $table->string('source', 16)->nullable()->after('rate');
        });
    }

    public function down(): void
    {
        Schema::table('merch_budget_lines', function (Blueprint $table): void {
            $table->dropColumn(['cons_dzn', 'wastage_pct', 'source']);
        });
    }
};
