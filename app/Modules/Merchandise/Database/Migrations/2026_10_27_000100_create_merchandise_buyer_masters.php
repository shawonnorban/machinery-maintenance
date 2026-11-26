<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Merchandise Desk → Master Data, the buyer lists (AnnotechERP 06/01, step B),
 * and Prototype Desk's Sample Type Configuration (07/01) they depend on:
 * buyer offices and accounts and which office acts for which buyer, which
 * users may work which buyers, the samples and test labs each buyer requires,
 * buyer-wise trims, and the suppliers a buyer nominates.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('buyer_offices', function (Blueprint $table): void {
            $this->base($table);
            $table->string('name');
            $table->string('office_type', 16);
            $table->text('address')->nullable();
            $table->foreignUlid('country_id')->nullable()->constrained('country_configs')->nullOnDelete();
            $table->string('contact_person')->nullable();
            $table->string('email')->nullable();
            $table->string('phone', 32)->nullable();
            $table->boolean('active')->default(true);
            $table->unique(['company_id', 'name']);
        });

        Schema::create('buyers', function (Blueprint $table): void {
            $this->base($table);
            $table->string('code', 16);
            $table->string('name');
            $table->string('name_bn')->nullable();
            $table->string('short_name', 64);
            $table->text('profile')->nullable();
            $table->text('pi_address');
            $table->string('contact_person')->nullable();
            $table->string('bank_name')->nullable();
            $table->date('registered_from');
            $table->date('registered_to')->nullable();
            $table->unsignedInteger('display_order')->default(0);
            $table->boolean('active')->default(true);
            $table->unique(['company_id', 'code']);
        });

        Schema::create('buyer_office_links', function (Blueprint $table): void {
            $this->base($table);
            $table->foreignUlid('buyer_id')->constrained('buyers')->cascadeOnDelete();
            $table->foreignUlid('buyer_office_id')->constrained('buyer_offices')->cascadeOnDelete();
            $table->unique(['buyer_id', 'buyer_office_id']);
        });

        // Buyer Account Permission (MERCHANDISING) and Marketing Buyer Permission (MARKETING).
        Schema::create('buyer_user_permissions', function (Blueprint $table): void {
            $this->base($table);
            $table->string('kind', 16);
            $table->foreignUlid('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignUlid('buyer_id')->constrained('buyers')->cascadeOnDelete();
            $table->unique(['kind', 'user_id', 'buyer_id']);
        });

        // Prototype Desk → Sample Type Configuration.
        Schema::create('sample_types', function (Blueprint $table): void {
            $this->base($table);
            $table->string('code', 16);
            $table->string('name');
            $table->string('stage', 16);
            $table->boolean('order_required')->default(false);
            $table->boolean('po_required')->default(false);
            $table->boolean('buyer_approval_required')->default(true);
            $table->boolean('qc_required')->default(true);
            $table->boolean('material_required')->default(true);
            $table->boolean('revision_allowed')->default(true);
            $table->boolean('delivery_required')->default(true);
            $table->unsignedSmallInteger('lead_days')->default(7);
            $table->boolean('customised')->default(false);
            $table->boolean('active')->default(true);
            $table->unique(['company_id', 'code']);
        });

        Schema::create('buyer_sample_types', function (Blueprint $table): void {
            $this->base($table);
            $table->foreignUlid('buyer_id')->constrained('buyers')->cascadeOnDelete();
            $table->foreignUlid('sample_type_id')->constrained('sample_types')->cascadeOnDelete();
            $table->string('source', 16)->nullable();
            $table->string('size', 32)->nullable();
            $table->unsignedSmallInteger('quantity')->nullable();
            $table->unique(['buyer_id', 'sample_type_id']);
        });

        Schema::create('test_institutes', function (Blueprint $table): void {
            $this->base($table);
            $table->string('name');
            $table->string('name_bn')->nullable();
            $table->text('address')->nullable();
            $table->boolean('active')->default(true);
            $table->unique(['company_id', 'name']);
        });

        Schema::create('buyer_test_institutes', function (Blueprint $table): void {
            $this->base($table);
            $table->foreignUlid('buyer_id')->constrained('buyers')->cascadeOnDelete();
            $table->foreignUlid('test_institute_id')->constrained('test_institutes')->cascadeOnDelete();
            $table->unique(['buyer_id', 'test_institute_id']);
        });

        Schema::create('buyer_trim_items', function (Blueprint $table): void {
            $this->base($table);
            $table->foreignUlid('buyer_id')->constrained('buyers')->cascadeOnDelete();
            $table->string('item_code', 32);
            $table->string('item_name');
            $table->string('category')->nullable();
            $table->string('specification')->nullable();
            $table->string('material')->nullable();
            $table->string('color', 64)->nullable();
            $table->string('size', 64)->nullable();
            $table->string('uom', 16)->nullable();
            $table->text('description')->nullable();
            $table->string('approved_by')->nullable();
            $table->boolean('active')->default(true);
            $table->unique(['buyer_id', 'item_code']);
        });

        Schema::create('buyer_supplier_configs', function (Blueprint $table): void {
            $this->base($table);
            $table->foreignUlid('buyer_id')->constrained('buyers')->cascadeOnDelete();
            $table->text('remarks')->nullable();
            $table->boolean('active')->default(true);
            $table->unique(['company_id', 'buyer_id']);
        });

        Schema::create('buyer_supplier_config_vendors', function (Blueprint $table): void {
            $this->base($table);
            $table->foreignUlid('buyer_supplier_config_id')->constrained('buyer_supplier_configs')->cascadeOnDelete();
            $table->foreignUlid('vendor_id')->constrained('vendors')->cascadeOnDelete();
            $table->unique(['buyer_supplier_config_id', 'vendor_id']);
        });
    }

    public function down(): void
    {
        foreach (['buyer_supplier_config_vendors', 'buyer_supplier_configs', 'buyer_trim_items', 'buyer_test_institutes', 'test_institutes', 'buyer_sample_types', 'sample_types', 'buyer_user_permissions', 'buyer_office_links', 'buyers', 'buyer_offices'] as $table) {
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
