<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A spare part's manufacturer becomes a reference into the same
 * `manufacturers` catalogue Asset already picks from, instead of free text —
 * so "Juki" typed once on an asset and once on a part don't quietly become
 * two different, unsearchable strings, and a part's own edit form can offer
 * the same dropdown the asset form already has.
 *
 * The old `manufacturer` column is dropped outright rather than kept
 * alongside the new one: nothing had ever been written to it (no seeder, no
 * production data), so there's nothing to migrate.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('spare_parts', function (Blueprint $table): void {
            $table->dropColumn('manufacturer');
            $table->foreignUlid('manufacturer_id')->nullable()->after('brand')
                ->constrained('manufacturers')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('spare_parts', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('manufacturer_id');
            $table->string('manufacturer')->nullable()->after('brand');
        });
    }
};
