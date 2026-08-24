<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Whether an uploaded file has been checked (API 19.1 rule 3).
 *
 * "Uploads are virus-scanned before the file is marked usable; an unscanned
 * file returns 409 on download." Until now `VIRUS_SCAN_ENABLED` sat in the
 * environment template and nothing read it, which is worse than not having the
 * setting at all: an operator who set it to true would believe files were
 * being checked.
 *
 * Four states rather than a boolean, because "not looked at yet" and "looked
 * at and found something" must not be the same row. The first is a file that
 * will probably be fine in a moment; the second must never be served again,
 * and somebody has to be able to tell them apart a year later.
 *
 * Lives beside the migration that created the table rather than in a module,
 * because attachments are shared infrastructure and only `database/migrations`
 * is loaded for those.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('file_attachments', function (Blueprint $table): void {
            // PENDING | CLEAN | INFECTED | SKIPPED
            //
            // SKIPPED is honest rather than tidy: it records that a file was
            // never checked because scanning was off when it arrived. Marking
            // it CLEAN would claim a check that never happened.
            $table->string('scan_status', 16)->default('PENDING')->after('sha256');
            $table->timestamp('scanned_at')->nullable()->after('scan_status');
            $table->string('scan_result', 255)->nullable()->after('scanned_at');

            $table->index(['company_id', 'scan_status'], 'file_attachments_scan_index');
        });

        // Everything already stored was accepted before this column existed and
        // has been downloadable all along. Leaving those rows PENDING would make
        // every existing attachment in every tenant start answering 409.
        DB::table('file_attachments')->update(['scan_status' => 'SKIPPED']);
    }

    public function down(): void
    {
        Schema::table('file_attachments', function (Blueprint $table): void {
            $table->dropIndex('file_attachments_scan_index');
            $table->dropColumn(['scan_status', 'scanned_at', 'scan_result']);
        });
    }
};
