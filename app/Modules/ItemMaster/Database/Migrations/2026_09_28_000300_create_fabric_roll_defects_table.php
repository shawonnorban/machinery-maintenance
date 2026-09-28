<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Which defects an inspector observed on a roll (Inventory spec §4.3). */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('fabric_roll_defects', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('company_id')->constrained('companies')->cascadeOnDelete();
            $table->foreignUlid('fabric_roll_id')->constrained('fabric_rolls')->cascadeOnDelete();
            $table->foreignUlid('fabric_defect_id')->constrained('fabric_defects')->restrictOnDelete();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->unique(['fabric_roll_id', 'fabric_defect_id'], 'fabric_roll_defects_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('fabric_roll_defects');
    }
};
