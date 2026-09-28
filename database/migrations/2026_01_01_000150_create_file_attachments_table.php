<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Private file attachments (SRS 13.4, 37).
 *
 * Shared rather than owned by a module: work orders, checklist results and
 * breakdowns all attach evidence, and three parallel implementations would mean
 * three sets of storage rules to get wrong.
 *
 * Scoped down deliberately for now. Signed URLs, thumbnails and virus scanning
 * belong with the full storage workstream; what exists here is what checklist
 * execution needs to be honest: a safety item that demands a photo on failure
 * has somewhere to put one.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('file_attachments', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('company_id')->constrained('companies')->cascadeOnDelete();

            // What the file is evidence for. Kept as a loose pair rather than a
            // polymorphic relation with a morph map, because the owning record
            // may be deleted while the file is still referenced from an audit
            // entry, and a hard constraint would either block that or cascade
            // the evidence away with it.
            $table->string('attachable_type', 64);
            $table->ulid('attachable_id');

            $table->string('disk', 32)->default('local');
            // Never trusted for serving: the response always names the file from
            // original_name, and the path is generated here.
            $table->string('path');
            $table->string('original_name');
            $table->string('mime_type', 128);
            $table->unsignedBigInteger('size_bytes');
            // Hashed so a re-upload of the same photo is recognisable, and so a
            // stored file can be shown to be the one that was uploaded.
            $table->char('sha256', 64);

            $table->foreignUlid('uploaded_by')->nullable();
            $table->timestamps();

            $table->index(['company_id', 'attachable_type', 'attachable_id'], 'file_attachments_owner_idx');
            $table->index(['company_id', 'sha256'], 'file_attachments_hash_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('file_attachments');
    }
};
