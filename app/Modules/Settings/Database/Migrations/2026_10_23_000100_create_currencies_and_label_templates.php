<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Settings → General Settings (AnnotechERP 03/03): the currencies a company
 * trades in with their rate to the base currency, and the label templates
 * barcodes are printed with.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('currencies', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('company_id')->constrained('companies')->cascadeOnDelete();
            $table->char('code', 3);
            $table->string('name');
            $table->string('symbol', 8)->nullable();
            $table->unsignedTinyInteger('decimal_places')->default(2);
            // How many base-currency units one unit of this currency buys.
            $table->decimal('exchange_rate', 18, 6)->default(1);
            $table->date('rate_date')->nullable();
            $table->boolean('active')->default(true);
            $table->timestamps();

            $table->unique(['company_id', 'code']);
        });

        Schema::create('label_templates', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('company_id')->constrained('companies')->cascadeOnDelete();
            $table->string('code', 48);
            $table->string('name');
            // What the label goes on: ITEM, ROLL, ASSET, BUNDLE, CARTON, LOCATION.
            $table->string('usage', 16);
            $table->string('symbology', 16);
            $table->decimal('width_mm', 6, 1);
            $table->decimal('height_mm', 6, 1);
            $table->unsignedTinyInteger('columns')->default(1);
            // The data printed beside the code, in order.
            $table->json('fields')->nullable();
            $table->string('printer_language', 8)->default('PDF');
            $table->boolean('active')->default(true);
            $table->timestamps();

            $table->unique(['company_id', 'code']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('label_templates');
        Schema::dropIfExists('currencies');
    }
};
