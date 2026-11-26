<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
 * Buyer Gateway → Order & Shipment Visibility (AnnotechERP 25/02): how much of
 * its orders a buyer sees, the samples and shipments as the buyer reads them,
 * and the documents released to the buyer.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bg_visibility_rules', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('company_id')->constrained('companies')->cascadeOnDelete();
            $table->foreignUlid('buyer_id')->constrained('buyers')->restrictOnDelete();
            $table->string('scope', 12)->default('CONFIRMED'); // ALL | CONFIRMED | SEASON | NAMED
            $table->string('depth', 12)->default('STATUS'); // STATUS | MILESTONES | QUANTITIES | FULL
            $table->string('delays', 12)->default('REVIEWED'); // ALWAYS | REVIEWED | NEVER
            $table->date('effective_from')->nullable();
            $table->foreignUlid('set_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('note')->nullable();
            $table->boolean('active')->default(true);
            $table->timestamps();
            $table->unique(['company_id', 'buyer_id'], 'bg_visibility_rules_buyer_unique');
        });

        Schema::create('bg_sample_progress', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('company_id')->constrained('companies')->cascadeOnDelete();
            $table->string('style', 60);
            $table->foreignUlid('buyer_id')->constrained('buyers')->restrictOnDelete();
            $table->string('sample_type', 12)->nullable(); // PROTO | FIT | SIZE_SET | PP | SHIPMENT | PHOTO
            $table->string('stage', 16)->default('IN_DEVELOPMENT'); // IN_DEVELOPMENT | SENT | COMMENTED | REVISED | APPROVED | REJECTED
            $table->date('sent_on')->nullable();
            $table->date('decision_due')->nullable();
            $table->unsignedSmallInteger('version')->default(1);
            $table->string('courier', 12)->nullable(); // DHL | FEDEX | ARAMEX | HAND | NOT_SENT
            $table->timestamps();
            $table->unique(['company_id', 'style', 'sample_type', 'version'], 'bg_sample_progress_unique');
        });

        Schema::create('bg_shipment_progress', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('company_id')->constrained('companies')->cascadeOnDelete();
            $table->string('order_no', 60);
            $table->foreignUlid('buyer_id')->constrained('buyers')->restrictOnDelete();
            $table->string('mode', 8)->nullable(); // SEA | AIR | SEA_AIR | ROAD
            $table->string('stage', 12)->default('BOOKED'); // BOOKED | GATE_OUT | ON_THE_WATER | ARRIVED | DELIVERED
            $table->date('agreed_date')->nullable();
            $table->date('actual_date')->nullable();
            $table->unsignedInteger('quantity')->default(0);
            $table->unsignedInteger('cartons')->default(0);
            $table->timestamps();
            $table->index(['company_id', 'order_no']);
        });

        Schema::create('bg_shared_documents', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('company_id')->constrained('companies')->cascadeOnDelete();
            $table->string('title', 160);
            $table->foreignUlid('buyer_id')->constrained('buyers')->restrictOnDelete();
            $table->string('kind', 16)->nullable(); // TECH_PACK | TEST_REPORT | INSPECTION | PACKING_LIST | INVOICE | CERTIFICATE
            $table->date('shared_on')->nullable();
            $table->foreignUlid('shared_by')->nullable()->constrained('users')->nullOnDelete();
            $table->decimal('size_mb', 8, 1)->nullable();
            $table->unsignedInteger('downloads')->default(0);
            $table->string('state', 10)->default('SHARED'); // SHARED | WITHDRAWN | EXPIRED
            $table->string('file_path', 255)->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        foreach (['bg_shared_documents', 'bg_shipment_progress', 'bg_sample_progress', 'bg_visibility_rules'] as $table) {
            Schema::dropIfExists($table);
        }
    }
};
