<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Brickland's "Selected Docs": a sub-contract quotation records every paper it
 * arrived with by name, not just one. The single `document` becomes a list.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('dye_sc_quotations', function (Blueprint $table) {
            $table->json('documents')->nullable();
        });
        DB::table('dye_sc_quotations')->whereNotNull('document')->orderBy('id')->each(function ($row) {
            DB::table('dye_sc_quotations')->where('id', $row->id)->update(['documents' => json_encode([$row->document])]);
        });
        Schema::table('dye_sc_quotations', function (Blueprint $table) {
            $table->dropColumn('document');
        });
    }

    public function down(): void
    {
        Schema::table('dye_sc_quotations', function (Blueprint $table) {
            $table->string('document')->nullable();
        });
        DB::table('dye_sc_quotations')->whereNotNull('documents')->orderBy('id')->each(function ($row) {
            DB::table('dye_sc_quotations')->where('id', $row->id)->update(['document' => mb_substr((string) (json_decode($row->documents, true)[0] ?? ''), 0, 255) ?: null]);
        });
        Schema::table('dye_sc_quotations', function (Blueprint $table) {
            $table->dropColumn('documents');
        });
    }
};
