<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
 * Gate Pass → Master Data (AnnotechERP 11/01): what a gate pass is (main for
 * an employee, item for goods), where it goes, what goes with it, and why.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('gate_pass_categories', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('company_id')->constrained('companies')->cascadeOnDelete();
            $table->string('code', 32);
            $table->string('name');
            $table->string('kind', 8); // MAIN (employee) | ITEM (product / material)
            $table->text('description')->nullable();
            $table->boolean('active')->default(true);
            $table->timestamps();
            $table->unique(['company_id', 'code'], 'gate_pass_categories_code_unique');
        });

        Schema::create('gate_pass_location_types', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('company_id')->constrained('companies')->cascadeOnDelete();
            $table->string('code', 32);
            $table->string('name');
            $table->text('description')->nullable();
            $table->boolean('active')->default(true);
            $table->timestamps();
            $table->unique(['company_id', 'code'], 'gate_pass_location_types_code_unique');
        });

        Schema::create('gate_pass_locations', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('company_id')->constrained('companies')->cascadeOnDelete();
            $table->foreignUlid('location_type_id')->constrained('gate_pass_location_types')->restrictOnDelete();
            $table->string('code', 32);
            $table->string('name');
            $table->string('contact_person')->nullable();
            $table->string('mobile', 32)->nullable();
            $table->string('email')->nullable();
            $table->text('address')->nullable();
            $table->text('remarks')->nullable();
            $table->boolean('active')->default(true);
            $table->timestamps();
            $table->unique(['company_id', 'code'], 'gate_pass_locations_code_unique');
        });

        Schema::create('gate_pass_items', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('company_id')->constrained('companies')->cascadeOnDelete();
            $table->string('code', 32);
            $table->string('name');
            $table->string('item_category', 24);
            $table->string('unit', 8); // Brickland's own list: PCS, DZN, SET, KG …
            $table->text('description')->nullable();
            $table->boolean('active')->default(true);
            $table->timestamps();
            $table->unique(['company_id', 'code'], 'gate_pass_items_code_unique');
        });

        Schema::create('gate_pass_purposes', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('company_id')->constrained('companies')->cascadeOnDelete();
            $table->string('code', 32);
            $table->string('name');
            $table->text('description')->nullable();
            $table->boolean('active')->default(true);
            $table->timestamps();
            $table->unique(['company_id', 'code'], 'gate_pass_purposes_code_unique');
        });
    }

    public function down(): void
    {
        foreach (['gate_pass_purposes', 'gate_pass_items', 'gate_pass_locations', 'gate_pass_location_types', 'gate_pass_categories'] as $table) {
            Schema::dropIfExists($table);
        }
    }
};
