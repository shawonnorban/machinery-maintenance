<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Assets and their master data: ERD Section 4.
 *
 * asset_locations is the single addressable location entity (ADR-052). The
 * v1.0 polymorphic current_location_type/current_location_id pair is not
 * implemented: a polymorphic pointer cannot carry a foreign key, and transfer
 * history pointing at a deleted workstation would dangle.
 */
return new class extends Migration
{
    public function up(): void
    {
        $create = static function (string $table, Closure $definition): void {
            if (! Schema::hasTable($table)) {
                Schema::create($table, $definition);
            }
        };

        $create('asset_types', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            // null = platform-seeded type, shared by every tenant
            $table->foreignUlid('company_id')->nullable()
                ->constrained('companies')->cascadeOnDelete();
            $table->string('name');
            $table->string('code', 48);
            $table->string('default_criticality', 16)->default('MEDIUM');
            $table->boolean('active')->default(true);
            $table->timestamps();

            $table->unique(['company_id', 'code']);
        });

        $create('asset_categories', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('company_id')->nullable()
                ->constrained('companies')->cascadeOnDelete();
            $table->foreignUlid('asset_type_id')->constrained('asset_types')->restrictOnDelete();
            $table->string('name');
            $table->string('code', 48);
            $table->boolean('active')->default(true);
            $table->timestamps();

            $table->unique(['company_id', 'asset_type_id', 'code'], 'asset_categories_unique');
        });

        $create('manufacturers', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('company_id')->nullable()
                ->constrained('companies')->cascadeOnDelete();
            $table->string('name');
            $table->string('code', 48);
            $table->string('country', 2)->nullable();
            $table->boolean('active')->default(true);
            $table->timestamps();

            $table->unique(['company_id', 'code']);
        });

        $create('asset_models', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('company_id')->nullable()
                ->constrained('companies')->cascadeOnDelete();
            $table->foreignUlid('manufacturer_id')->constrained('manufacturers')->restrictOnDelete();
            $table->foreignUlid('asset_type_id')->constrained('asset_types')->restrictOnDelete();
            $table->string('model');
            $table->string('code', 64);
            $table->boolean('active')->default(true);
            $table->timestamps();

            $table->unique(['company_id', 'code']);
        });

        // The single addressable location entity (ADR-052). Everything above
        // factory is nullable, so a factory that does not model floors is not
        // forced to invent them.
        $create('asset_locations', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('company_id')->constrained('companies')->cascadeOnDelete();
            $table->foreignUlid('factory_id')->constrained('factories')->restrictOnDelete();
            $table->foreignUlid('building_id')->nullable()->constrained('buildings')->nullOnDelete();
            $table->foreignUlid('floor_id')->nullable()->constrained('floors')->nullOnDelete();
            $table->foreignUlid('department_id')->nullable()->constrained('departments')->nullOnDelete();
            $table->foreignUlid('section_id')->nullable()->constrained('sections')->nullOnDelete();
            $table->foreignUlid('production_line_id')->nullable()->constrained('production_lines')->nullOnDelete();
            $table->foreignUlid('workstation_id')->nullable()->constrained('workstations')->nullOnDelete();
            $table->string('name');
            $table->string('code', 64);
            $table->string('qr_code', 12)->nullable();
            // Denormalised display path, rebuilt when the hierarchy changes
            $table->string('full_path', 512)->nullable();
            $table->string('status', 32)->default('ACTIVE');
            $table->timestamps();

            $table->unique(['company_id', 'code']);
            $table->unique(['company_id', 'qr_code']);
            $table->index(['company_id', 'factory_id']);
        });

        $create('assets', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('company_id')->constrained('companies')->cascadeOnDelete();

            $table->foreignUlid('asset_type_id')->constrained('asset_types')->restrictOnDelete();
            $table->foreignUlid('asset_category_id')->constrained('asset_categories')->restrictOnDelete();
            $table->foreignUlid('manufacturer_id')->nullable()->constrained('manufacturers')->nullOnDelete();
            $table->foreignUlid('asset_model_id')->nullable()->constrained('asset_models')->nullOnDelete();
            $table->foreignUlid('parent_asset_id')->nullable()->constrained('assets')->nullOnDelete();

            $table->string('asset_code', 64);
            $table->string('serial_number', 128)->nullable();
            // Opaque, non-sequential. A printed floor label must not reveal an
            // id or let anyone enumerate the fleet (Data Dictionary 5.1).
            $table->string('qr_code', 12);
            $table->string('barcode', 64)->nullable();

            $table->string('name');
            $table->text('description')->nullable();
            $table->string('criticality', 16);
            $table->string('status', 32)->default('DRAFT');
            $table->string('country_of_origin', 2)->nullable();

            $table->date('purchase_date')->nullable();
            $table->date('installation_date')->nullable();
            $table->date('commissioning_date')->nullable();

            $table->decimal('acquisition_cost', 18, 4)->nullable();
            $table->decimal('installation_cost', 18, 4)->nullable();
            $table->decimal('current_value', 18, 4)->nullable();
            $table->decimal('capitalized_cost', 18, 4)->nullable();
            $table->decimal('salvage_value', 18, 4)->nullable();
            $table->unsignedInteger('useful_life_months')->nullable();
            $table->string('depreciation_method', 32)->nullable();
            $table->unsignedBigInteger('expected_life_cycles')->nullable();
            $table->char('currency', 3)->nullable();

            $table->date('warranty_start')->nullable();
            $table->date('warranty_end')->nullable();

            // FK constraints for these land with the modules that own the
            // tables: Vendor (build order 22) and Metering (14).
            $table->ulid('supplier_id')->nullable();
            $table->ulid('default_meter_type_id')->nullable();

            $table->foreignUlid('current_factory_id')->constrained('factories')->restrictOnDelete();
            $table->foreignUlid('asset_location_id')->constrained('asset_locations')->restrictOnDelete();

            $table->boolean('is_imported')->default(false);
            $table->ulid('imported_batch_id')->nullable();

            $table->timestamp('retired_at')->nullable();
            $table->timestamp('scrapped_at')->nullable();
            $table->decimal('disposal_value', 18, 4)->nullable();
            $table->string('disposal_reference')->nullable();
            $table->text('notes')->nullable();

            // Optimistic locking (ADR-025). A stale version returns 409.
            $table->unsignedInteger('version')->default(1);

            $table->foreignUlid('created_by')->nullable();
            $table->foreignUlid('updated_by')->nullable();
            $table->timestamps();
            $table->softDeletesDatetime(precision: 3);

            // A code must be reusable after archival (ADR-057), so deleted_at
            // has to participate in the unique index. It cannot participate
            // directly: MySQL treats NULLs as DISTINCT, so every live row
            // would compare unequal and the constraint would enforce nothing
            // at all.
            //
            // A regular marker avoids generated-column restrictions on shared
            // hosting. Live rows use one fixed value; deleted rows receive a
            // unique value from the model's deleting event.
            $table->string('deleted_marker', 40)->default('LIVE');

            $table->unique(['company_id', 'asset_code', 'deleted_marker'], 'assets_code_unique');
            $table->unique(['company_id', 'serial_number', 'deleted_marker'], 'assets_serial_unique');
            $table->unique(['company_id', 'qr_code'], 'assets_qr_unique');
            $table->unique(['company_id', 'barcode', 'deleted_marker'], 'assets_barcode_unique');

            $table->index(['company_id', 'status']);
            $table->index(['company_id', 'current_factory_id']);
            $table->index(['company_id', 'asset_location_id']);
            $table->index(['company_id', 'parent_asset_id']);
            $table->index(['company_id', 'criticality', 'status']);
        });

        $create('asset_status_histories', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('company_id')->constrained('companies')->cascadeOnDelete();
            $table->foreignUlid('asset_id')->constrained('assets')->cascadeOnDelete();
            $table->string('from_status', 32)->nullable();
            $table->string('to_status', 32);
            $table->foreignUlid('changed_by')->nullable();
            $table->timestamp('changed_at');
            $table->string('reason')->nullable();
            // Set when the change was driven by a breakdown or work order
            // rather than by a person.
            $table->string('source', 32)->default('MANUAL');

            $table->index(['company_id', 'asset_id', 'changed_at']);
        });

        $create('asset_transfer_history', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('company_id')->constrained('companies')->cascadeOnDelete();
            $table->foreignUlid('asset_id')->constrained('assets')->cascadeOnDelete();
            $table->string('transfer_number', 48);

            $table->foreignUlid('from_factory_id')->constrained('factories')->restrictOnDelete();
            $table->foreignUlid('from_location_id')->nullable()->constrained('asset_locations')->nullOnDelete();
            $table->foreignUlid('to_factory_id')->constrained('factories')->restrictOnDelete();
            $table->foreignUlid('to_location_id')->constrained('asset_locations')->restrictOnDelete();

            $table->string('status', 32)->default('REQUESTED');
            $table->string('reason');
            $table->text('notes')->nullable();

            $table->foreignUlid('requested_by');
            $table->timestamp('requested_at');
            $table->foreignUlid('approved_by')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->foreignUlid('received_by')->nullable();
            $table->timestamp('received_at')->nullable();
            $table->foreignUlid('rejected_by')->nullable();
            $table->timestamp('rejected_at')->nullable();
            $table->string('rejection_reason')->nullable();

            $table->timestamp('transfer_at');
            $table->foreignUlid('reverses_transfer_id')->nullable();
            $table->timestamp('created_at')->nullable();

            $table->unique(['company_id', 'transfer_number']);
            $table->index(['company_id', 'asset_id', 'transfer_at']);
            $table->index(['company_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('asset_transfer_history');
        Schema::dropIfExists('asset_status_histories');
        Schema::dropIfExists('assets');
        Schema::dropIfExists('asset_locations');
        Schema::dropIfExists('asset_models');
        Schema::dropIfExists('manufacturers');
        Schema::dropIfExists('asset_categories');
        Schema::dropIfExists('asset_types');
    }
};
