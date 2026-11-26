<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Material Procurement → Control → Supplier Portal Access (AnnotechERP 08/07):
 * a supplier's portal login — an external account in this company, holding
 * only the Supplier role — linked to the supplier it quotes for. Every
 * supplier with an active login sees each submitted requirement.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('mp_supplier_logins', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('company_id')->constrained('companies')->cascadeOnDelete();
            $table->foreignUlid('supplier_id')->constrained('mp_suppliers')->restrictOnDelete();
            $table->foreignUlid('user_id')->constrained('users')->restrictOnDelete();
            $table->string('contact_name', 255);
            $table->boolean('active')->default(true);
            $table->foreignUlid('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->unique(['company_id', 'user_id']);
            $table->index(['company_id', 'supplier_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mp_supplier_logins');
    }
};
