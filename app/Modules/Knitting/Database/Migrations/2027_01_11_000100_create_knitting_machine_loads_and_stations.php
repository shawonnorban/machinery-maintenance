<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
 * Knit Production → Production (AnnotechERP 15/04):
 *
 * - `knit_machine_loads`: Knit Machine Loading Plan — which knit card runs on
 *   which machine, shift by shift. A machine runs one card per shift, so a
 *   cell (day × shift × machine) holds one row; planning it again replaces it.
 * - `knit_stations` + machines: Operator Roll Production Entry — an operator's
 *   station and the machines it shows as tabs (the station opens on the first).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('knit_machine_loads', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('company_id')->constrained('companies')->cascadeOnDelete();
            $table->date('plan_date');
            $table->foreignUlid('shift_id')->constrained('shifts')->restrictOnDelete();
            $table->foreignUlid('machine_id')->constrained('knit_machines')->cascadeOnDelete();
            $table->foreignUlid('card_id')->constrained('knit_cards')->cascadeOnDelete();
            $table->decimal('plan_qty', 12, 3)->nullable();
            $table->foreignUlid('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->unique(['company_id', 'plan_date', 'shift_id', 'machine_id'], 'knit_machine_loads_cell_unique');
            $table->index(['company_id', 'card_id']);
        });

        Schema::create('knit_stations', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('company_id')->constrained('companies')->cascadeOnDelete();
            $table->string('code', 24);
            $table->string('name', 120);
            $table->foreignUlid('operator_id')->nullable()->constrained('hr_employees')->nullOnDelete();
            $table->boolean('active')->default(true);
            $table->timestamps();
            $table->unique(['company_id', 'code']);
        });

        Schema::create('knit_station_machines', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('company_id')->constrained('companies')->cascadeOnDelete();
            $table->foreignUlid('station_id')->constrained('knit_stations')->cascadeOnDelete();
            $table->foreignUlid('machine_id')->constrained('knit_machines')->cascadeOnDelete();
            $table->unsignedSmallInteger('sort_order');
            $table->timestamps();
            $table->unique(['station_id', 'machine_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('knit_station_machines');
        Schema::dropIfExists('knit_stations');
        Schema::dropIfExists('knit_machine_loads');
    }
};
