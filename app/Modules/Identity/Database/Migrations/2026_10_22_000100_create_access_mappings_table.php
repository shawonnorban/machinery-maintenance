<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Access Control → Mappings (AnnotechERP 02/02): who may act where. One table
 * for Brickland's mapping screens — user × store, user × requisition type,
 * user × production category, user × business rule, menu × report, and later
 * the buyer mappings — because they share a shape: a subject, a target, a few
 * yes/no rights and at most an amount, an order or a default flag.
 * `MappingRegistry` says what each kind's subject, target and rights are.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('access_mappings', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('company_id')->constrained('companies')->cascadeOnDelete();
            $table->string('kind', 48);
            // A user, menu or buyer id; a store id, lookup code or report key.
            $table->string('subject_id', 64);
            $table->string('target_id', 96);
            $table->json('flags')->nullable();
            $table->decimal('amount', 18, 2)->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('is_default')->default(false);
            $table->timestamps();

            $table->unique(['company_id', 'kind', 'subject_id', 'target_id']);
            $table->index(['company_id', 'kind', 'target_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('access_mappings');
    }
};
