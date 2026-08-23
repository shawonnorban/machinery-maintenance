<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A company's own document number formats (SRS 52, Data Dictionary 6).
 *
 * Separate from `number_sequences`, which is a counter per period, because the
 * two answer different questions and change at different rates. A counter is
 * allocated thousands of times a month and belongs to one period; a format is
 * a decision somebody makes once, in a settings screen, and expects to hold.
 *
 * Storing the format on the counter alone would mean an edit either changed
 * nothing (the next period would take the default again) or changed numbers
 * halfway through a month. Neither is what "we want our work orders numbered
 * WO/DHK/25-08/0001" means.
 *
 * A row here is an override. No row means the platform default, which is what
 * every company starts with and most keep.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('number_sequence_formats', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('company_id')->constrained('companies')->cascadeOnDelete();

            $table->string('document_type', 64);
            $table->string('format', 128);
            $table->unsignedTinyInteger('padding');

            $table->foreignUlid('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            // One answer per company per document type. Two would mean the
            // number a work order gets depends on which row was read first.
            $table->unique(['company_id', 'document_type'], 'number_formats_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('number_sequence_formats');
    }
};
