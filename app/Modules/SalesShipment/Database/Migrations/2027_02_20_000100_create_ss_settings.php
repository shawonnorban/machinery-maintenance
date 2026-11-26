<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
 * Sales & Shipment → Operation → Sales & Shipment Settings (AnnotechERP
 * 21/01): one row per company — the rules the business decides (ship only
 * what has been delivered; require export documents before confirming a
 * shipment), the alert windows (due, certificate expiry), the document
 * number prefixes of the module's nine series and the sequence width they
 * print with (SHP-2026-000001). No row means Brickland's defaults.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ss_settings', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('company_id')->unique()->constrained('companies')->cascadeOnDelete();
            $table->boolean('ship_against_delivered')->default(false);
            $table->boolean('require_export_docs')->default(true);
            $table->unsignedSmallInteger('due_alert_days')->default(7);
            $table->unsignedSmallInteger('cert_alert_days')->default(30);
            $table->string('prefix_amendment', 6)->default('SOA');
            $table->string('prefix_challan_return', 6)->default('DCR');
            $table->string('prefix_sc_return', 6)->default('SCRT');
            $table->string('prefix_sc_reconciliation', 6)->default('SCRC');
            $table->string('prefix_shipment', 6)->default('SHP');
            $table->string('prefix_booking', 6)->default('BKG');
            $table->string('prefix_invoice', 6)->default('INV');
            $table->string('prefix_packing_list', 6)->default('PL');
            $table->string('prefix_handover', 6)->default('DH');
            $table->unsignedTinyInteger('seq_width')->default(6);
            $table->foreignUlid('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ss_settings');
    }
};
