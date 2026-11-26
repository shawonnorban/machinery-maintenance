<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
 * Work Study → Master Data (AnnotechERP 14/01): garment items and the parts
 * each is made of, who may set targets and post finishing on which floors,
 * the learning curve a line ramps along, who answers for each IE activity,
 * and what each line can run. Floors are Company Management's, garment parts
 * and buyers Merchandise Desk's, lines Capacity Planning's.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ws_garment_items', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('company_id')->constrained('companies')->cascadeOnDelete();
            $table->string('item_group');
            $table->string('name');
            $table->boolean('active')->default(true);
            $table->timestamps();
            $table->unique(['company_id', 'item_group', 'name'], 'ws_garment_items_unique');
        });

        Schema::create('ws_item_parts', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('company_id')->constrained('companies')->cascadeOnDelete();
            $table->foreignUlid('garment_item_id')->constrained('ws_garment_items')->cascadeOnDelete();
            $table->foreignUlid('garment_part_id')->constrained('garment_parts')->restrictOnDelete();
            $table->unsignedInteger('component_sl');
            $table->boolean('included')->default(true);
            $table->timestamps();
            $table->unique(['garment_item_id', 'garment_part_id'], 'ws_item_parts_unique');
        });

        // Floor-Wise Target Setup: one line per user.
        Schema::create('ws_target_access', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('company_id')->constrained('companies')->cascadeOnDelete();
            $table->foreignUlid('user_id')->constrained('users')->cascadeOnDelete();
            $table->json('floor_ids');
            $table->char('target_right', 1); // R | W
            $table->char('product_right', 1); // R | W
            $table->boolean('is_admin')->default(false);
            $table->timestamps();
            $table->unique(['company_id', 'user_id'], 'ws_target_access_user_unique');
        });

        // Finishing Floor Access Setup: one line per user and node.
        Schema::create('ws_finishing_access', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('company_id')->constrained('companies')->cascadeOnDelete();
            $table->foreignUlid('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('node_type', 8); // IRON | POLY
            $table->json('floor_ids');
            $table->timestamps();
            $table->unique(['company_id', 'user_id', 'node_type'], 'ws_finishing_access_unique');
        });

        Schema::create('ws_learning_points', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('company_id')->constrained('companies')->cascadeOnDelete();
            $table->string('fabric_class', 16); // FLEECE | PIQUE | SINGLE_JERSEY | TWILL
            $table->string('prod_type', 16); // BASIC | SEMI_CRITICAL | CRITICAL | HIGHLY_CRITICAL
            $table->unsignedSmallInteger('day');
            $table->decimal('efficiency', 5, 2);
            $table->timestamps();
            $table->unique(['company_id', 'fabric_class', 'prod_type', 'day'], 'ws_learning_points_unique');
        });

        Schema::create('ws_responsible_depts', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('company_id')->constrained('companies')->cascadeOnDelete();
            $table->string('name');
            $table->string('code', 16)->nullable();
            $table->unsignedInteger('sl')->default(0);
            $table->timestamps();
            $table->unique(['company_id', 'name'], 'ws_responsible_depts_unique');
        });

        Schema::create('ws_responsible_persons', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('company_id')->constrained('companies')->cascadeOnDelete();
            $table->foreignUlid('dept_id')->constrained('ws_responsible_depts')->cascadeOnDelete();
            $table->foreignUlid('user_id')->constrained('users')->cascadeOnDelete();
            // All floors / all buyers are stored, not read from an empty list, so
            // "everyone" and "not filled in yet" stay apart.
            $table->json('floor_ids');
            $table->boolean('all_floors')->default(false);
            $table->json('buyer_ids');
            $table->boolean('all_buyers')->default(false);
            $table->timestamps();
            $table->unique(['dept_id', 'user_id'], 'ws_responsible_persons_unique');
        });

        // What a line holds before it is loaded: its establishment, by machine type or overall.
        Schema::create('ws_line_profiles', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('company_id')->constrained('companies')->cascadeOnDelete();
            $table->foreignUlid('planning_line_id')->constrained('planning_lines')->cascadeOnDelete();
            $table->string('machine_type', 16)->nullable(); // null = the line overall
            $table->unsignedInteger('operators');
            $table->unsignedInteger('aso');
            $table->unsignedInteger('machines');
            $table->timestamps();
            $table->unique(['planning_line_id', 'machine_type'], 'ws_line_profiles_unique');
        });

        // Which lines run a criticality band, and the garment category each is preferred
        // for there. A line left unticked is not refused the band, only not preferred.
        Schema::create('ws_criticality_lines', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('company_id')->constrained('companies')->cascadeOnDelete();
            $table->string('criticality', 16);
            $table->foreignUlid('planning_line_id')->constrained('planning_lines')->cascadeOnDelete();
            $table->boolean('runs')->default(false);
            $table->string('preferred_group')->nullable();
            $table->timestamps();
            $table->unique(['criticality', 'planning_line_id'], 'ws_criticality_lines_unique');
        });
    }

    public function down(): void
    {
        foreach (['ws_criticality_lines', 'ws_line_profiles', 'ws_responsible_persons', 'ws_responsible_depts', 'ws_learning_points', 'ws_finishing_access', 'ws_target_access', 'ws_item_parts', 'ws_garment_items'] as $table) {
            Schema::dropIfExists($table);
        }
    }
};
