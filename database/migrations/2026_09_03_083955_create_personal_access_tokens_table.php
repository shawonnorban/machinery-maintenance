<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('personal_access_tokens', function (Blueprint $table) {
            $table->id();
            // ulidMorphs, not morphs: every primary key `tokenable` can point
            // at in this schema (starting with User) is a ULID, not a bigint.
            $table->ulidMorphs('tokenable');
            $table->text('name');
            $table->string('token', 64)->unique();
            $table->text('abilities')->nullable();
            $table->timestamp('last_used_at')->nullable();
            $table->timestamp('expires_at')->nullable()->index();
            $table->timestamps();

            // Which company this token reads as (API 3): a token is minted
            // for exactly one, the same invariant the legacy ApiToken table
            // enforces, so tenant context can be derived from the token
            // alone rather than a client-supplied header.
            $table->ulid('company_id')->nullable();
            $table->foreign('company_id')->references('id')->on('companies')->cascadeOnDelete();

            // Not part of Sanctum's own schema: the last four characters of
            // the plaintext, so somebody revoking one of six tokens on the
            // account screen can tell which is which without ever being
            // shown one in full again.
            $table->string('last_four', 4)->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('personal_access_tokens');
    }
};
