<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Print Design Sheet (AnnotechERP 06/10): a line's artwork is an uploaded
 * image, kept privately like any other file, rather than a link typed in.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('merch_print_lines', function (Blueprint $table): void {
            $table->dropColumn('image_url');
            $table->ulid('artwork_file_id')->nullable();
            $table->string('artwork_name', 255)->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('merch_print_lines', function (Blueprint $table): void {
            $table->dropColumn(['artwork_file_id', 'artwork_name']);
            $table->string('image_url', 500)->nullable();
        });
    }
};
