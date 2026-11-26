<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Prototype Desk → Sample Bundle Card Issue (AnnotechERP 07/03): a sample lay
 * cut on a cutting table (SBC-2026100001) and its bundles, each with its own
 * scan code (SBC-2026100001-01), issued to a team and followed on the floor.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('proto_bundle_cards', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('company_id')->constrained('companies')->cascadeOnDelete();
            $table->string('card_no', 24);
            $table->foreignUlid('sample_id')->nullable()->constrained('proto_samples')->nullOnDelete();
            $table->foreignUlid('buyer_id')->constrained('buyers')->restrictOnDelete();
            $table->foreignUlid('buyer_office_id')->nullable()->constrained('buyer_offices')->nullOnDelete();
            $table->foreignUlid('style_id')->nullable()->constrained('merch_styles')->nullOnDelete();
            $table->foreignUlid('order_id')->nullable()->constrained('merch_orders')->nullOnDelete();
            $table->string('cutting_no', 32);
            // The cutting table: a Company Management production line of the Cutting type.
            $table->foreignUlid('table_id')->constrained('production_lines')->restrictOnDelete();
            $table->foreignUlid('fabric_type_id')->nullable()->constrained('fabric_types')->nullOnDelete();
            $table->date('cutting_date');
            $table->string('status', 16)->default('DRAFT');
            $table->boolean('is_finalized')->default(false);
            $table->string('bundle_status', 16)->default('CREATED');
            $table->foreignUlid('department_id')->nullable()->constrained('departments')->nullOnDelete();
            $table->string('assigned_team', 120)->nullable();
            $table->foreignUlid('operator_id')->nullable()->constrained('users')->nullOnDelete();
            $table->date('issue_date')->nullable();
            $table->date('due_date')->nullable();
            $table->string('priority', 10)->nullable();
            $table->text('remarks')->nullable();
            $table->foreignUlid('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->unique(['company_id', 'card_no']);
            $table->unique(['company_id', 'table_id', 'buyer_id', 'cutting_no']);
        });

        Schema::create('proto_bundles', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('company_id')->constrained('companies')->cascadeOnDelete();
            $table->foreignUlid('card_id')->constrained('proto_bundle_cards')->cascadeOnDelete();
            $table->string('bundle_no', 32);
            $table->foreignUlid('order_id')->nullable()->constrained('merch_orders')->nullOnDelete();
            $table->foreignUlid('sample_type_id')->nullable()->constrained('sample_types')->nullOnDelete();
            $table->string('color', 96)->nullable();
            $table->string('size', 32)->nullable();
            $table->unsignedInteger('req_qty')->default(0);
            $table->unsignedInteger('cutting_qty')->default(0);
            // Where the bundle was last scanned (Sample Bundle Tracking); empty until it moves.
            $table->string('now_at', 32)->nullable();
            $table->string('remarks', 500)->nullable();
            $table->unsignedSmallInteger('sort_order')->default(1);
            $table->timestamps();
            $table->unique(['company_id', 'bundle_no']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('proto_bundles');
        Schema::dropIfExists('proto_bundle_cards');
    }
};
