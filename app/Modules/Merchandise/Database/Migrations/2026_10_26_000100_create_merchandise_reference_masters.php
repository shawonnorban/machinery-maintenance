<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Merchandise Desk → Master Data, the reference lists (AnnotechERP 06/01, step
 * A): countries of origin and country configuration, colours, sizes, garment
 * parts, service types, fabric types and the parts each may be cut for,
 * fibers, fiber compositions, and the GSM-wise yarn count chart.
 *
 * Every list is the company's own; codes are unique within a company.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('origin_countries', function (Blueprint $table): void {
            $this->base($table);
            $table->string('code', 16);
            $table->string('name');
            $table->string('name_bn')->nullable();
            $table->string('region', 16)->nullable();
            $table->boolean('active')->default(true);
            $table->unique(['company_id', 'code']);
        });

        Schema::create('country_configs', function (Blueprint $table): void {
            $this->base($table);
            $table->string('code', 16);
            $table->string('name');
            $table->string('region', 16)->nullable();
            $table->string('currency', 8)->nullable();
            $table->string('dial_code', 8)->nullable();
            $table->boolean('is_ship_to')->default(false);
            $table->boolean('active')->default(true);
            $table->unique(['company_id', 'code']);
        });

        Schema::create('colours', function (Blueprint $table): void {
            $this->base($table);
            $table->string('code', 32)->nullable();
            $table->string('colour_type', 16);
            $table->string('name');
            $table->string('pantone_no', 32)->nullable();
            $table->string('version_no', 16)->nullable();
            $table->boolean('swatch_provided')->default(false);
            $table->boolean('active')->default(true);
            $table->unique(['company_id', 'name', 'colour_type']);
        });

        Schema::create('sizes', function (Blueprint $table): void {
            $this->base($table);
            $table->string('size_group', 16)->nullable();
            $table->string('code', 16)->nullable();
            $table->string('name', 64);
            $table->string('name_bn', 64)->nullable();
            $table->string('comp_size_group', 16)->nullable();
            $table->unsignedInteger('display_order')->default(0);
            $table->unsignedInteger('comp_display_order')->default(0);
            $table->boolean('active')->default(true);
            $table->unique(['company_id', 'size_group', 'name']);
        });

        Schema::create('garment_parts', function (Blueprint $table): void {
            $this->base($table);
            $table->string('code', 32);
            $table->string('name');
            $table->string('fabric_group', 16)->nullable();
            $table->boolean('is_circular')->default(false);
            $table->boolean('is_process_group')->default(false);
            $table->boolean('is_body')->default(false);
            $table->boolean('active')->default(true);
            $table->unique(['company_id', 'code']);
        });

        Schema::create('service_types', function (Blueprint $table): void {
            $this->base($table);
            $table->string('service_for', 16);
            $table->string('code', 32);
            $table->string('name');
            $table->string('remarks')->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('active')->default(true);
            $table->unique(['company_id', 'service_for', 'code']);
        });

        Schema::create('fabric_types', function (Blueprint $table): void {
            $this->base($table);
            $table->string('code', 32);
            $table->string('name');
            $table->string('short_name', 32);
            $table->string('fabric_group', 16)->nullable();
            $table->string('category', 16)->nullable();
            $table->string('construction', 8);
            $table->boolean('is_base')->default(false);
            $table->ulid('parent_id')->nullable();
            $table->boolean('is_rib')->default(false);
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('active')->default(true);
            $table->unique(['company_id', 'code']);
        });
        Schema::table('fabric_types', function (Blueprint $table): void {
            $table->foreign('parent_id')->references('id')->on('fabric_types')->nullOnDelete();
        });

        // GARM Part List: the garment parts a fabric type may be cut for.
        Schema::create('fabric_type_garment_parts', function (Blueprint $table): void {
            $this->base($table);
            $table->foreignUlid('fabric_type_id')->constrained('fabric_types')->cascadeOnDelete();
            $table->foreignUlid('garment_part_id')->constrained('garment_parts')->cascadeOnDelete();
            $table->unique(['fabric_type_id', 'garment_part_id']);
        });

        Schema::create('fibers', function (Blueprint $table): void {
            $this->base($table);
            $table->string('code', 32);
            $table->string('name');
            $table->string('short_name', 32)->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('active')->default(true);
            $table->unique(['company_id', 'code']);
        });

        Schema::create('fiber_compositions', function (Blueprint $table): void {
            $this->base($table);
            $table->string('code', 32);
            $table->string('description');
            $table->boolean('active')->default(true);
            $table->unique(['company_id', 'code']);
        });

        Schema::create('fiber_composition_items', function (Blueprint $table): void {
            $this->base($table);
            $table->foreignUlid('fiber_composition_id')->constrained('fiber_compositions')->cascadeOnDelete();
            $table->foreignUlid('fiber_id')->constrained('fibers')->restrictOnDelete();
            $table->decimal('percent', 5, 2);
            $table->unique(['fiber_composition_id', 'fiber_id']);
        });

        Schema::create('yarn_count_configs', function (Blueprint $table): void {
            $this->base($table);
            $table->foreignUlid('fabric_type_id')->constrained('fabric_types')->restrictOnDelete();
            $table->string('template', 24)->nullable();
            $table->string('gauge', 16);
            $table->unsignedSmallInteger('gsm_min');
            $table->unsignedSmallInteger('gsm_max');
            $table->string('count_top', 32)->nullable();
            $table->string('count_binding', 32)->nullable();
            $table->string('count_back', 32)->nullable();
            $table->index(['company_id', 'fabric_type_id']);
        });
    }

    public function down(): void
    {
        foreach (['yarn_count_configs', 'fiber_composition_items', 'fiber_compositions', 'fibers', 'fabric_type_garment_parts', 'fabric_types', 'service_types', 'garment_parts', 'sizes', 'colours', 'country_configs', 'origin_countries'] as $table) {
            Schema::dropIfExists($table);
        }
    }

    private function base(Blueprint $table): void
    {
        $table->ulid('id')->primary();
        $table->foreignUlid('company_id')->constrained('companies')->cascadeOnDelete();
        $table->timestamps();
    }
};
