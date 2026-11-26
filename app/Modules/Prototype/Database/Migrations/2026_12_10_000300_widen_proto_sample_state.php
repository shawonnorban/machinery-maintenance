<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** A sample's state now reaches Approval's REVISION_REQUIRED. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('proto_samples', function (Blueprint $table): void {
            $table->string('state', 24)->default('APPROVED')->change();
        });
    }

    public function down(): void
    {
        Schema::table('proto_samples', function (Blueprint $table): void {
            $table->string('state', 16)->default('APPROVED')->change();
        });
    }
};
