<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Customs tariff codes with their duty and VAT rates (Master Data spec §8).
 *
 * Only imported items carry one; it feeds landed cost and, later, the
 * bonded-warehouse duty calculation. Rates are percentages, kept on the code
 * rather than on each item so a tariff change is one edit.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('hs_codes', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            // Null company_id = platform-seeded, visible to every tenant.
            $table->foreignUlid('company_id')->nullable()->constrained('companies')->cascadeOnDelete();
            $table->string('code', 16);
            $table->string('description');
            $table->decimal('duty_rate', 6, 2)->default(0);
            $table->decimal('vat_rate', 6, 2)->default(0);
            $table->boolean('active')->default(true);
            $table->timestamps();

            $table->unique(['company_id', 'code'], 'hs_codes_code_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('hs_codes');
    }
};
