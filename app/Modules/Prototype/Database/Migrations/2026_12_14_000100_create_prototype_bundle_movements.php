<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Prototype Desk → Sample Bundle Tracking (AnnotechERP 07/03): every scan of a
 * bundle into one of the sample room's processes, with its operator, timing,
 * status and the reason for any delay; and on the card what of it has been
 * delivered or rejected (Sample Card).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('proto_bundle_movements', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('company_id')->constrained('companies')->cascadeOnDelete();
            $table->foreignUlid('bundle_id')->constrained('proto_bundles')->cascadeOnDelete();
            $table->foreignUlid('card_id')->constrained('proto_bundle_cards')->cascadeOnDelete();
            $table->string('process', 24);
            $table->date('movement_date');
            $table->timestamp('scanned_at');
            $table->foreignUlid('scanned_by')->nullable()->constrained('users')->nullOnDelete();
            $table->unsignedInteger('qty')->default(0);
            $table->foreignUlid('operator_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('received_at')->nullable();
            $table->timestamp('start_at')->nullable();
            $table->timestamp('end_at')->nullable();
            $table->decimal('expected_hours', 6, 2)->nullable();
            $table->string('status', 12)->default('PENDING');
            $table->string('delay_reason', 255)->nullable();
            $table->string('remarks', 500)->nullable();
            $table->timestamps();
            $table->unique(['bundle_id', 'process']);
            $table->index(['company_id', 'movement_date', 'process']);
        });

        Schema::table('proto_bundle_cards', function (Blueprint $table): void {
            $table->unsignedInteger('delivered_qty')->default(0)->after('remarks');
            $table->unsignedInteger('rejected_qty')->default(0)->after('delivered_qty');
        });
    }

    public function down(): void
    {
        Schema::table('proto_bundle_cards', function (Blueprint $table): void {
            $table->dropColumn(['delivered_qty', 'rejected_qty']);
        });
        Schema::dropIfExists('proto_bundle_movements');
    }
};
