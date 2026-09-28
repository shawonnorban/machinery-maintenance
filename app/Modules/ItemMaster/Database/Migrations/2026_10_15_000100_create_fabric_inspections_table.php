<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The four-point fabric inspection (Inventory spec §4.3).
 *
 * Until now a roll carried a grade and a number somebody typed into a
 * "points" box. The mill floor does not work that way: the inspector
 * tallies each fault by how long it is, the roll's score falls out of a
 * formula, and — the part that was missing entirely — the **lot** is what
 * is accepted or rejected, on a sample, with the sample widening when the
 * first pass fails.
 *
 * So the header here is the lot, not the roll. A roll's percentage says
 * whether that roll is good; only the pooled figure across the inspected
 * yardage says whether the delivery is.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('fabric_inspections', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('company_id')->constrained('companies')->cascadeOnDelete();
            $table->foreignUlid('factory_id')->constrained('factories')->restrictOnDelete();
            $table->foreignUlid('goods_receipt_id')->constrained('goods_receipts')->restrictOnDelete();
            $table->foreignUlid('item_id')->constrained('items')->restrictOnDelete();
            $table->foreignUlid('vendor_id')->nullable()->constrained('vendors')->nullOnDelete();

            $table->string('inspection_no', 32);

            // Copied as text, not linked. Merchandising lives in another
            // system here, so the buyer, style and order are what the
            // paperwork says — and an inspection has to keep saying what
            // it said even after that system renames a style.
            $table->string('buyer_reference', 120)->nullable();
            $table->string('style_reference', 120)->nullable();
            $table->string('order_reference', 120)->nullable();
            $table->string('colour', 64)->nullable();
            $table->string('composition', 120)->nullable();
            $table->string('lot_no', 64)->nullable();

            // How much of the lot this pass looked at. FIRST/SECOND/FULL
            // are the 10% / 15% / 100% steps of the escalation.
            $table->string('stage', 16)->default('FIRST');
            $table->decimal('sampling_percent', 6, 2)->default(0);
            $table->integer('rolls_in_lot')->default(0);
            $table->decimal('yards_in_lot', 14, 2)->default(0);
            $table->integer('rolls_inspected')->default(0);
            $table->decimal('yards_inspected', 14, 2)->default(0);

            // Pooled across the inspected yardage, never the mean of the
            // per-roll percentages: a 20-yard roll and a 200-yard roll do
            // not carry the same weight in a lot's verdict.
            $table->decimal('total_points', 12, 2)->default(0);
            $table->decimal('penalty_percent', 8, 2)->default(0);
            // Copied onto the row, like a duty rate: raising the bar next
            // season must not silently re-decide last season's lots.
            $table->decimal('acceptance_threshold', 8, 2)->default(28);
            $table->string('result', 16)->nullable();

            $table->string('status', 24)->default('DRAFT');
            // The wider re-inspection after a failed pass points back at
            // the pass that caused it, so the escalation is readable.
            $table->ulid('supersedes_id')->nullable();

            $table->foreignUlid('inspected_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('inspected_at')->nullable();
            $table->foreignUlid('verified_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('verified_at')->nullable();
            $table->foreignUlid('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();

            $table->date('inspection_date');
            $table->text('remarks')->nullable();
            $table->timestamps();

            $table->unique(['company_id', 'inspection_no']);
            $table->index(['company_id', 'goods_receipt_id']);
            $table->index(['company_id', 'status']);
        });

        // Added after the table exists: Postgres will not accept a
        // self-referencing key inside the same CREATE, because the primary
        // key it points at is not there to match yet.
        Schema::table('fabric_inspections', function (Blueprint $table): void {
            $table->foreign('supersedes_id')->references('id')->on('fabric_inspections')->nullOnDelete();
        });

        Schema::table('fabric_rolls', function (Blueprint $table): void {
            $table->ulid('fabric_inspection_id')->nullable()->after('goods_receipt_line_id');

            // Measured at three places because a mill's width drifts along
            // the roll, and the narrowest point is what the marker has to
            // be laid to. `actual_width` stays as the figure the score is
            // computed from — the mean of these three.
            $table->decimal('width_start', 10, 2)->nullable()->after('actual_width');
            $table->decimal('width_middle', 10, 2)->nullable()->after('width_start');
            $table->decimal('width_end', 10, 2)->nullable()->after('width_middle');

            // What the supplier's ticket claims against what was measured
            // off the inspection table. Short rolls are paid for twice
            // when nobody writes both numbers down.
            $table->decimal('marked_length', 12, 2)->nullable()->after('width_end');
            $table->decimal('inspected_length', 12, 2)->nullable()->after('marked_length');

            // Nine results: three light sources against three comparisons
            // (approved swatch, end to end, side to centre). A single
            // boolean could not say that a roll matches under daylight and
            // goes green under the store's tubes, which is the whole
            // reason a shade is checked under more than one lamp.
            $table->json('shade_assessment')->nullable()->after('shade_match');
            $table->decimal('penalty_percent', 8, 2)->nullable()->after('inspection_points');

            $table->foreign('fabric_inspection_id')->references('id')->on('fabric_inspections')->nullOnDelete();
        });

        Schema::table('fabric_roll_defects', function (Blueprint $table): void {
            // A fault's score is its length, not its kind: under 3" is one
            // point, up to 6" two, up to 9" three, longer four — and a hole
            // is four whatever its size. So the tally is per point class,
            // and the same defect appears on a roll at several classes.
            $table->unsignedTinyInteger('point_class')->default(1)->after('fabric_defect_id');
            $table->unsignedInteger('occurrences')->default(1)->after('point_class');
        });
    }

    public function down(): void
    {
        Schema::table('fabric_roll_defects', function (Blueprint $table): void {
            $table->dropColumn(['point_class', 'occurrences']);
        });

        Schema::table('fabric_rolls', function (Blueprint $table): void {
            $table->dropForeign(['fabric_inspection_id']);
            $table->dropColumn([
                'fabric_inspection_id', 'width_start', 'width_middle', 'width_end',
                'marked_length', 'inspected_length', 'shade_assessment', 'penalty_percent',
            ]);
        });

        Schema::dropIfExists('fabric_inspections');
    }
};
