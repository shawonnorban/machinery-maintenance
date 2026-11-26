<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
 * Merchandise Desk → Master Data, matched again to Brickland (AnnotechERP
 * 06/01): a buyer's country, advising/lien bank and supplier; a colour's
 * colour group; a trim item's item category; the buyer-wise supplier
 * configuration as Brickland keeps it now — per item category, a nominated
 * and an alternative supplier from Material Procurement's supplier master
 * (it named the old project's vendors before); and who approved and who
 * prepared a T&A template.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('buyers', function (Blueprint $table): void {
            $table->foreignUlid('country_id')->nullable()->after('contact_person')->constrained('origin_countries')->nullOnDelete();
            $table->foreignUlid('bank_id')->nullable()->after('bank_name')->constrained('tx_banks')->nullOnDelete();
            $table->foreignUlid('supplier_id')->nullable()->after('bank_id')->constrained('mp_suppliers')->nullOnDelete();
        });
        Schema::table('colours', function (Blueprint $table): void {
            $table->foreignUlid('colour_group_id')->nullable()->after('version_no')->constrained('dye_colour_groups')->nullOnDelete();
        });
        Schema::table('buyer_trim_items', function (Blueprint $table): void {
            $table->foreignUlid('item_category_id')->nullable()->after('category')->constrained('item_categories')->nullOnDelete();
        });
        Schema::table('buyer_supplier_configs', function (Blueprint $table): void {
            $table->dropUnique(['company_id', 'buyer_id']);
            $table->foreignUlid('item_category_id')->nullable()->after('buyer_id')->constrained('item_categories')->nullOnDelete();
            $table->foreignUlid('nominated_supplier_id')->nullable()->after('item_category_id')->constrained('mp_suppliers')->nullOnDelete();
            $table->foreignUlid('alternative_supplier_id')->nullable()->after('nominated_supplier_id')->constrained('mp_suppliers')->nullOnDelete();
        });
        Schema::dropIfExists('buyer_supplier_config_vendors');
        Schema::table('tna_templates', function (Blueprint $table): void {
            $table->foreignUlid('approved_by')->nullable()->after('description')->constrained('users')->nullOnDelete();
            $table->foreignUlid('prepared_by')->nullable()->after('approved_by')->constrained('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('tna_templates', fn (Blueprint $t) => $t->dropConstrainedForeignId('prepared_by'));
        Schema::table('tna_templates', fn (Blueprint $t) => $t->dropConstrainedForeignId('approved_by'));
        Schema::create('buyer_supplier_config_vendors', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('company_id')->constrained('companies')->cascadeOnDelete();
            $table->foreignUlid('buyer_supplier_config_id')->constrained('buyer_supplier_configs')->cascadeOnDelete();
            $table->foreignUlid('vendor_id')->constrained('vendors')->cascadeOnDelete();
            $table->timestamps();
        });
        Schema::table('buyer_supplier_configs', function (Blueprint $table): void {
            foreach (['alternative_supplier_id', 'nominated_supplier_id', 'item_category_id'] as $c) {
                $table->dropConstrainedForeignId($c);
            }
            $table->unique(['company_id', 'buyer_id']);
        });
        Schema::table('buyer_trim_items', fn (Blueprint $t) => $t->dropConstrainedForeignId('item_category_id'));
        Schema::table('colours', fn (Blueprint $t) => $t->dropConstrainedForeignId('colour_group_id'));
        Schema::table('buyers', function (Blueprint $table): void {
            foreach (['supplier_id', 'bank_id', 'country_id'] as $c) {
                $table->dropConstrainedForeignId($c);
            }
        });
    }
};
