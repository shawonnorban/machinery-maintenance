<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
 * Knit Production → Master Data (AnnotechERP 15/01): the knitting machines and
 * the four lists they are described from, their capacity bands and operator
 * postings, yarn counts, label printers, and which factory each user may raise
 * knitting entries against. Floors and factories are Company Management's,
 * schedules Settings' shifts, origin countries Merchandise Desk's.
 */
return new class extends Migration
{
    public function up(): void
    {
        // Machine Reference Lists: M/C Type, Brand, M/C Dia and M/C GG.
        Schema::create('knit_ref_entries', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('company_id')->constrained('companies')->cascadeOnDelete();
            $table->string('list', 16); // MC_TYPE | BRAND | DIA | GG
            $table->string('code', 16);
            $table->string('name');
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('active')->default(true);
            $table->timestamps();
            $table->unique(['company_id', 'list', 'code'], 'knit_ref_entries_unique');
        });

        Schema::create('knit_machines', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('company_id')->constrained('companies')->cascadeOnDelete();
            $table->string('machine_no', 32);
            $table->foreignUlid('machine_type_id')->constrained('knit_ref_entries')->restrictOnDelete();
            $table->foreignUlid('brand_id')->constrained('knit_ref_entries')->restrictOnDelete();
            $table->string('model')->nullable();
            $table->foreignUlid('origin_country_id')->constrained('origin_countries')->restrictOnDelete();
            $table->string('serial_no')->nullable();
            $table->foreignUlid('dia_id')->nullable()->constrained('knit_ref_entries')->restrictOnDelete();
            $table->foreignUlid('gauge_id')->nullable()->constrained('knit_ref_entries')->restrictOnDelete();
            $table->json('supported_gauge_ids');
            $table->decimal('capacity_kg', 10, 2)->nullable();
            $table->unsignedInteger('feeders')->nullable();
            $table->unsignedInteger('rpm')->nullable();
            $table->decimal('efficiency', 5, 2)->nullable();
            $table->foreignUlid('floor_id')->constrained('floors')->restrictOnDelete();
            $table->unsignedSmallInteger('mfg_year')->nullable();
            $table->boolean('active')->default(true);
            $table->timestamps();
            $table->unique(['company_id', 'machine_no'], 'knit_machines_no_unique');
        });

        // Machine Capacity Hour Plan: bands that never overlap on a machine.
        Schema::create('knit_capacity_plans', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('company_id')->constrained('companies')->cascadeOnDelete();
            $table->foreignUlid('machine_id')->constrained('knit_machines')->cascadeOnDelete();
            $table->foreignUlid('shift_id')->nullable()->constrained('shifts')->nullOnDelete();
            $table->date('effective_from');
            $table->date('effective_to')->nullable();
            $table->decimal('plan_hours', 5, 2);
            $table->unsignedSmallInteger('running_days');
            $table->decimal('efficiency', 5, 2)->nullable();
            $table->decimal('capacity_per_hour', 10, 3);
            $table->text('remarks')->nullable();
            $table->boolean('active')->default(true);
            $table->timestamps();
            $table->index(['company_id', 'machine_id', 'effective_from']);
        });

        // Machine Operator Assignment: one live operator per machine per schedule.
        Schema::create('knit_operator_postings', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('company_id')->constrained('companies')->cascadeOnDelete();
            $table->foreignUlid('machine_id')->constrained('knit_machines')->cascadeOnDelete();
            $table->foreignUlid('user_id')->constrained('users')->restrictOnDelete();
            $table->foreignUlid('shift_id')->constrained('shifts')->restrictOnDelete();
            $table->timestamp('effective_at');
            $table->timestamp('until_at')->nullable();
            $table->boolean('active')->default(true);
            $table->timestamps();
            $table->index(['company_id', 'machine_id', 'shift_id']);
        });

        Schema::create('knit_yarn_counts', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('company_id')->constrained('companies')->cascadeOnDelete();
            $table->string('count_no', 32);
            $table->string('count_system', 8); // Ne | Nm | D | Tex | Dtex
            $table->string('description');
            $table->decimal('count_value', 10, 2)->nullable();
            $table->boolean('is_single')->default(true);
            $table->unsignedInteger('display_order')->default(0);
            $table->boolean('active')->default(true);
            $table->timestamps();
            $table->unique(['company_id', 'count_no'], 'knit_yarn_counts_unique');
        });

        Schema::create('knit_label_printers', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('company_id')->constrained('companies')->cascadeOnDelete();
            $table->string('name');
            $table->string('address');
            $table->json('user_ids'); // none = offered to everyone
            $table->boolean('auto_scale')->default(false);
            $table->unsignedInteger('display_order')->default(0);
            $table->boolean('active')->default(true);
            $table->timestamps();
            $table->unique(['company_id', 'name'], 'knit_label_printers_unique');
        });

        // User Company & Office Mapping: none ticked = unrestricted.
        Schema::create('knit_user_offices', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('company_id')->constrained('companies')->cascadeOnDelete();
            $table->foreignUlid('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignUlid('factory_id')->constrained('factories')->cascadeOnDelete();
            $table->boolean('is_primary')->default(false);
            $table->boolean('can_write')->default(true);
            $table->timestamps();
            $table->unique(['user_id', 'factory_id'], 'knit_user_offices_unique');
        });
    }

    public function down(): void
    {
        foreach (['knit_user_offices', 'knit_label_printers', 'knit_yarn_counts', 'knit_operator_postings', 'knit_capacity_plans', 'knit_machines', 'knit_ref_entries'] as $table) {
            Schema::dropIfExists($table);
        }
    }
};
