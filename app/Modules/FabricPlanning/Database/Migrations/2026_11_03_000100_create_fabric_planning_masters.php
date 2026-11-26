<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
 * Fabric Requirement → Master Data (AnnotechERP 13/01): how many days before
 * shipment or PCD a dye batch is planned, the standard hours a batch takes,
 * and which dye machine a fabric is routed to. A blank criterion is "any" —
 * the rule applies whatever that value is.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('fp_batch_lead_rules', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('company_id')->constrained('companies')->cascadeOnDelete();
            $table->string('colour_type', 16)->nullable(); // SOLID | MELANGE | YARN_DYED | AOP; null = all
            $table->string('finish_type', 16)->nullable(); // NORMAL | PEACH | BRUSH | SILICONE; null = any
            $table->string('wash_type', 16)->nullable(); // NO_WASH | ENZYME | STONE | GARMENT; null = any
            $table->unsignedInteger('lead_from');
            $table->unsignedInteger('lead_to');
            $table->unsignedInteger('volume_from');
            $table->unsignedInteger('volume_to');
            $table->unsignedInteger('production_lead_time');
            $table->string('basis', 8); // SHIPMENT | PCD
            $table->unsignedInteger('days_before_shipment')->nullable();
            $table->unsignedInteger('days_before_pcd')->nullable();
            $table->boolean('is_embellishment')->default(false);
            $table->timestamps();
        });

        Schema::create('fp_batch_hours', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('company_id')->constrained('companies')->cascadeOnDelete();
            $table->string('fiber_template', 16)->nullable(); // COTTON_100 | LYCRA_BLEND | CVC_BLEND; null = any
            $table->string('buyer_category', 4)->nullable(); // A | B | C; null = any
            $table->string('dyeing_method', 16); // EXHAUST | COLD_PAD_BATCH | CONTINUOUS | PIGMENT
            $table->decimal('batch_hours', 6, 2);
            $table->timestamps();
        });

        Schema::create('fp_machine_routes', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('company_id')->constrained('companies')->cascadeOnDelete();
            $table->string('fabric_group', 8)->nullable(); // BODY | COLLAR | LINING | RIB; null = any
            $table->string('fiber_template', 16)->nullable();
            $table->foreignUlid('buyer_id')->nullable()->constrained('buyers')->cascadeOnDelete();
            $table->foreignUlid('dye_machine_id')->constrained('dye_machines')->restrictOnDelete();
            $table->unsignedInteger('gsm_from')->nullable();
            $table->unsignedInteger('gsm_to')->nullable();
            $table->boolean('active')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        foreach (['fp_machine_routes', 'fp_batch_hours', 'fp_batch_lead_rules'] as $table) {
            Schema::dropIfExists($table);
        }
    }
};
