<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Style & Order → Development Style Catalogue (AnnotechERP 06/07): a
 * development style's number (DEV-000001), where its approval stands (sent,
 * returned for revision or rejected — approved and draft follow its status),
 * the approval history with each note, its attachments (views, tech pack,
 * measurement sheet, swatch, artwork) and its packing details.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('merch_styles', function (Blueprint $table): void {
            $table->unsignedInteger('dev_no')->nullable();
            $table->string('approval_status', 16)->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->ulid('sent_by')->nullable();
            $table->unique(['company_id', 'dev_no']);
        });
        // The styles already raised are numbered in the order they were raised.
        DB::statement('update merch_styles s set dev_no = n.rn from (select id, row_number() over (partition by company_id order by created_at, id) as rn from merch_styles) n where n.id = s.id');

        Schema::create('merch_style_approvals', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('company_id')->constrained('companies')->cascadeOnDelete();
            $table->foreignUlid('style_id')->constrained('merch_styles')->cascadeOnDelete();
            $table->string('action', 16);
            $table->text('note')->nullable();
            $table->ulid('user_id')->nullable();
            $table->timestamps();
            $table->index(['company_id', 'style_id']);
        });

        Schema::create('merch_style_attachments', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('company_id')->constrained('companies')->cascadeOnDelete();
            $table->foreignUlid('style_id')->constrained('merch_styles')->cascadeOnDelete();
            $table->string('kind', 24);
            $table->ulid('file_id');
            $table->string('file_name', 255)->nullable();
            $table->timestamps();
            $table->unique(['style_id', 'kind']);
        });

        Schema::create('merch_style_packing_details', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('company_id')->constrained('companies')->cascadeOnDelete();
            $table->foreignUlid('style_id')->unique()->constrained('merch_styles')->cascadeOnDelete();
            $table->string('packing_method', 24)->nullable();
            $table->string('carton_type', 8)->nullable();
            $table->unsignedInteger('pcs_per_poly')->nullable();
            $table->unsignedInteger('pcs_per_carton')->nullable();
            $table->string('carton_size', 64)->nullable();
            $table->string('carton_marking', 128)->nullable();
            $table->boolean('barcode')->default(false);
            $table->boolean('hangtag')->default(false);
            $table->string('remarks', 1000)->nullable();
            $table->ulid('photo_id')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('merch_style_packing_details');
        Schema::dropIfExists('merch_style_attachments');
        Schema::dropIfExists('merch_style_approvals');
        Schema::table('merch_styles', function (Blueprint $table): void {
            $table->dropUnique(['company_id', 'dev_no']);
            $table->dropColumn(['dev_no', 'approval_status', 'sent_at', 'sent_by']);
        });
    }
};
