<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
 * Finishing Line → Master Data (AnnotechERP 18/01): the processes a batch is
 * booked against, filed under groups and sub-groups; the finishing machines;
 * which process each machine may run, per dia; and each machine's target per
 * shift. Floors are Company Management's, origin countries Merchandise
 * Desk's, and a machine's brand and location come from Dye House's machine
 * lists — Brickland keeps one library for both.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('fin_process_groups', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('company_id')->constrained('companies')->cascadeOnDelete();
            $table->string('code', 24);
            $table->string('name');
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('active')->default(true);
            $table->timestamps();
            $table->unique(['company_id', 'code'], 'fin_process_groups_unique');
        });

        Schema::create('fin_process_subgroups', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('company_id')->constrained('companies')->cascadeOnDelete();
            $table->foreignUlid('group_id')->constrained('fin_process_groups')->cascadeOnDelete();
            $table->string('code', 24);
            $table->string('name');
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('active')->default(true);
            $table->timestamps();
            $table->unique(['company_id', 'code'], 'fin_process_subgroups_unique');
        });

        Schema::create('fin_process_types', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('company_id')->constrained('companies')->cascadeOnDelete();
            $table->string('code', 24);
            $table->string('name');
            $table->string('short_name', 16);
            $table->foreignUlid('group_id')->constrained('fin_process_groups')->restrictOnDelete();
            $table->foreignUlid('subgroup_id')->nullable()->constrained('fin_process_subgroups')->restrictOnDelete();
            $table->ulid('link_process_id')->nullable();
            $table->string('wip_stage', 24);
            $table->decimal('rate', 10, 2);
            $table->decimal('loss_pct', 5, 2);
            $table->unsignedInteger('display_order');
            $table->boolean('active')->default(true);
            $table->timestamps();
            $table->unique(['company_id', 'code'], 'fin_process_types_unique');
        });
        // The process this one normally follows — a key onto its own table, added once the table has its primary key.
        Schema::table('fin_process_types', function (Blueprint $table): void {
            $table->foreign('link_process_id')->references('id')->on('fin_process_types')->nullOnDelete();
        });

        Schema::create('fin_machines', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('company_id')->constrained('companies')->cascadeOnDelete();
            $table->string('machine_no', 32);
            $table->string('name');
            $table->string('model', 64)->nullable();
            $table->foreignUlid('origin_country_id')->constrained('origin_countries')->restrictOnDelete();
            $table->foreignUlid('brand_id')->constrained('dye_machine_lists')->restrictOnDelete();
            $table->decimal('capacity', 10, 2)->nullable();
            $table->foreignUlid('location_id')->nullable()->constrained('dye_machine_lists')->nullOnDelete();
            $table->string('mou', 8);
            $table->decimal('efficiency', 5, 2)->nullable();
            $table->string('sl_no', 64)->nullable();
            $table->string('machine_type', 24);
            $table->unsignedInteger('rpm')->nullable();
            $table->unsignedSmallInteger('year')->nullable();
            $table->string('status', 24)->default('IDLE');
            $table->unsignedSmallInteger('temperature')->nullable();
            $table->foreignUlid('floor_id')->constrained('floors')->restrictOnDelete();
            $table->boolean('is_sample')->default(false);
            $table->string('key_image')->nullable();
            $table->boolean('active')->default(true);
            $table->timestamps();
            $table->unique(['company_id', 'machine_no'], 'fin_machines_no_unique');
        });

        // Machine-Process Mapping: once per machine, process and dia.
        Schema::create('fin_machine_processes', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('company_id')->constrained('companies')->cascadeOnDelete();
            $table->string('dia_type', 8); // OPEN | TUBE | BOTH
            $table->foreignUlid('machine_id')->constrained('fin_machines')->cascadeOnDelete();
            $table->foreignUlid('process_id')->constrained('fin_process_types')->restrictOnDelete();
            $table->unsignedInteger('sequence');
            $table->string('batch_status', 24);
            $table->string('next_status', 24);
            $table->boolean('is_default')->default(false);
            $table->boolean('active')->default(true);
            $table->timestamps();
            $table->unique(['machine_id', 'process_id', 'dia_type'], 'fin_machine_processes_unique');
        });

        // Machine-Wise Target Setup: one per machine, process (or any), dia and effective date.
        Schema::create('fin_machine_targets', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('company_id')->constrained('companies')->cascadeOnDelete();
            $table->foreignUlid('machine_id')->constrained('fin_machines')->cascadeOnDelete();
            $table->foreignUlid('process_id')->nullable()->constrained('fin_process_types')->restrictOnDelete();
            $table->string('dia_type', 8);
            $table->date('effective_from');
            $table->decimal('target_qty', 12, 2);
            $table->decimal('shift_hours', 5, 2);
            $table->text('remarks')->nullable();
            $table->boolean('active')->default(true);
            $table->timestamps();
            $table->index(['company_id', 'machine_id', 'effective_from']);
        });
    }

    public function down(): void
    {
        foreach (['fin_machine_targets', 'fin_machine_processes', 'fin_machines', 'fin_process_types', 'fin_process_subgroups', 'fin_process_groups'] as $table) {
            Schema::dropIfExists($table);
        }
    }
};
