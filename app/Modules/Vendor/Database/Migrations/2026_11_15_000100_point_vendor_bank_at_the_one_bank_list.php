<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * One bank list (the user's decision of 2026-11-15): Finance → Manage Bank and
 * Trade Export → Bank List already keep `tx_banks`; the old Settings → Master
 * Data → Banks (`banks`) goes, and a vendor's bank is picked from `tx_banks`.
 *
 * A vendor's bank is carried over by code where the company has that bank in
 * the one list; otherwise it is cleared (its branch, account and SWIFT stay).
 */
return new class extends Migration
{
    public function up(): void
    {
        $old = Schema::hasTable('banks')
            ? DB::table('vendors')->join('banks', 'banks.id', '=', 'vendors.bank_id')->get(['vendors.id', 'vendors.company_id', 'banks.code'])
            : collect();

        Schema::table('vendors', function (Blueprint $table): void {
            $table->dropForeign(['bank_id']);
        });
        DB::table('vendors')->update(['bank_id' => null]);

        foreach ($old as $row) {
            $bank = DB::table('tx_banks')->where('company_id', $row->company_id)->where('code', $row->code)->value('id');
            if ($bank !== null) {
                DB::table('vendors')->where('id', $row->id)->update(['bank_id' => $bank]);
            }
        }

        Schema::table('vendors', function (Blueprint $table): void {
            $table->foreign('bank_id')->references('id')->on('tx_banks')->nullOnDelete();
        });

        Schema::dropIfExists('banks');
    }

    public function down(): void
    {
        Schema::create('banks', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('company_id')->nullable()->constrained('companies')->cascadeOnDelete();
            $table->string('code', 32);
            $table->string('name');
            $table->boolean('active')->default(true);
            $table->timestamps();
            $table->unique(['company_id', 'code'], 'banks_code_unique');
        });

        Schema::table('vendors', function (Blueprint $table): void {
            $table->dropForeign(['bank_id']);
        });
        DB::table('vendors')->update(['bank_id' => null]);
        Schema::table('vendors', function (Blueprint $table): void {
            $table->foreign('bank_id')->references('id')->on('banks')->nullOnDelete();
        });
    }
};
