<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
 * Dye House → Master Data (AnnotechERP 17/01): dyeing methods, the dye
 * machines and the lists their form picks from, the people answerable for
 * finishing and grey-fabric quality, buyers' chemical audit checks, colour
 * group rates, and subcontract parties' quoted rates. Floors are Company
 * Management's; buyers, origin countries and rate lines Merchandise Desk's;
 * parties Material Procurement's suppliers; currencies Settings'.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('dye_methods', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('company_id')->constrained('companies')->cascadeOnDelete();
            $table->string('name');
            $table->string('short_name', 32);
            $table->string('sd', 8); // SINGLE | DOUBLE
            $table->boolean('is_default')->default(false);
            $table->boolean('active')->default(true);
            $table->timestamps();
            $table->unique(['company_id', 'name'], 'dye_methods_name_unique');
        });

        // The three lists the machine form picks from: machine types, brands and locations.
        Schema::create('dye_machine_lists', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('company_id')->constrained('companies')->cascadeOnDelete();
            $table->string('list', 16); // TYPE | BRAND | LOCATION
            $table->string('name');
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('active')->default(true);
            $table->timestamps();
            $table->unique(['company_id', 'list', 'name'], 'dye_machine_lists_unique');
        });

        Schema::create('dye_machines', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('company_id')->constrained('companies')->cascadeOnDelete();
            $table->string('machine_no', 32);
            $table->string('model', 64)->nullable();
            $table->foreignUlid('machine_type_id')->constrained('dye_machine_lists')->restrictOnDelete();
            $table->foreignUlid('brand_id')->constrained('dye_machine_lists')->restrictOnDelete();
            $table->foreignUlid('location_id')->nullable()->constrained('dye_machine_lists')->nullOnDelete();
            $table->foreignUlid('origin_country_id')->constrained('origin_countries')->restrictOnDelete();
            $table->decimal('capacity', 10, 2)->nullable();
            $table->string('capacity_unit', 8); // KG | LBS
            $table->decimal('efficiency', 5, 2)->nullable();
            $table->string('sl_no', 64);
            $table->unsignedSmallInteger('year')->nullable();
            $table->unsignedInteger('rpm')->nullable();
            $table->unsignedSmallInteger('temperature')->nullable();
            $table->unsignedSmallInteger('nozzles')->nullable();
            $table->boolean('is_sample')->default(false);
            $table->foreignUlid('floor_id')->constrained('floors')->restrictOnDelete();
            $table->string('mc_version', 32)->default('0');
            $table->string('prod_status', 20)->default('FREE'); // FREE | BUSY | UNDER_MAINTENANCE | BREAKDOWN | RESERVED
            $table->boolean('active')->default(true);
            $table->timestamps();
            $table->unique(['company_id', 'machine_no'], 'dye_machines_no_unique');
        });

        Schema::create('dye_responsible_employees', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('company_id')->constrained('companies')->cascadeOnDelete();
            $table->string('employee_type', 24); // DF_PROCESS_OPERATOR | DF_QUALITY_INSPECTION | GFAB_PROCESS_OPERATOR | GFAB_QUALITY_INSPECTION
            $table->foreignUlid('user_id')->constrained('users')->restrictOnDelete();
            $table->string('employee_name')->nullable();
            $table->string('department')->nullable();
            $table->string('designation')->nullable();
            // A keyed hash, never the code itself: enough to tell two people apart and to check a code typed at the machine.
            $table->string('pass_code_hash', 64);
            $table->timestamps();
            $table->unique(['company_id', 'pass_code_hash'], 'dye_responsible_pass_code_unique');
            $table->unique(['company_id', 'employee_type', 'user_id'], 'dye_responsible_person_unique');
        });

        Schema::create('dye_audit_checks', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('company_id')->constrained('companies')->cascadeOnDelete();
            $table->foreignUlid('buyer_id')->constrained('buyers')->cascadeOnDelete();
            $table->string('item_name');
            $table->string('alter_item_name')->nullable();
            $table->boolean('dropped')->default(false);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
            $table->unique(['buyer_id', 'item_name'], 'dye_audit_checks_unique');
        });

        Schema::create('dye_colour_groups', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('company_id')->constrained('companies')->cascadeOnDelete();
            $table->string('code', 16);
            $table->string('name');
            $table->boolean('labdip_required')->default(false);
            $table->decimal('water_cost', 12, 4)->default(0);
            $table->decimal('wash_cost', 12, 4)->default(0);
            $table->unsignedInteger('display_order')->default(0);
            $table->boolean('active')->default(true);
            $table->timestamps();
            $table->unique(['company_id', 'code'], 'dye_colour_groups_code_unique');
        });

        Schema::create('dye_sc_quotations', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('company_id')->constrained('companies')->cascadeOnDelete();
            $table->string('quotation_no', 32);
            $table->string('rate_group', 20); // KNITTING | YARN_DYEING | DYEING | DYEING_FINISHING | AOP | YARN_TWIST
            $table->foreignUlid('supplier_id')->constrained('mp_suppliers')->restrictOnDelete();
            $table->date('quotation_date');
            $table->char('currency', 3);
            $table->string('status', 12); // DRAFT | SUBMITTED | APPROVED | REJECTED | CANCELLED
            $table->text('remarks')->nullable();
            $table->string('document')->nullable();
            $table->foreignUlid('created_by')->nullable();
            $table->timestamps();
            $table->unique(['company_id', 'quotation_no'], 'dye_sc_quotations_no_unique');
        });

        Schema::create('dye_sc_quotation_lines', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('company_id')->constrained('companies')->cascadeOnDelete();
            $table->foreignUlid('quotation_id')->constrained('dye_sc_quotations')->cascadeOnDelete();
            // A head filed under the group in the Inquiry Rate Chart, or one typed where the group has none.
            $table->foreignUlid('rate_line_id')->nullable()->constrained('rate_lines')->nullOnDelete();
            $table->string('head');
            $table->string('uom', 16)->nullable();
            $table->decimal('rate', 14, 4);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        foreach (['dye_sc_quotation_lines', 'dye_sc_quotations', 'dye_colour_groups', 'dye_audit_checks', 'dye_responsible_employees', 'dye_machines', 'dye_machine_lists', 'dye_methods'] as $table) {
            Schema::dropIfExists($table);
        }
    }
};
