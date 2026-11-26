<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
 * Colour Lab → Reports (AnnotechERP 16/02): Brickland's Colour Fastness
 * Statement reads light and perspiration grades, and its Lab Master List
 * counts each lab's tests — so a fabric test carries both, and its lab.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('lab_fabric_tests', function (Blueprint $table): void {
            $table->string('light', 8)->nullable()->after('rub_wet');
            $table->string('perspiration', 8)->nullable()->after('light');
            $table->foreignUlid('lab_floor_id')->nullable()->after('test_date')->constrained('floors')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('lab_fabric_tests', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('lab_floor_id');
            $table->dropColumn(['light', 'perspiration']);
        });
    }
};
