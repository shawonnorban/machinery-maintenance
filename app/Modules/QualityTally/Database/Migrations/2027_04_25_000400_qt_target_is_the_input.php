<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/**
 * Sewing and quality have no input of their own — the line target is it
 * (user, 2026-11-25: a separate input would need its own manpower). Whatever
 * was given as line input becomes that day's target of the line and job,
 * where none was set.
 */
return new class extends Migration
{
    public function up(): void
    {
        $given = DB::table('qt_line_inputs')->select('company_id', 'input_date', 'section', 'line_id', 'job_id', DB::raw('sum(qty) as qty'), DB::raw('min(created_by) as created_by'))
            ->groupBy('company_id', 'input_date', 'section', 'line_id', 'job_id')->get();
        foreach ($given as $g) {
            $has = DB::table('qt_line_targets')->where('company_id', $g->company_id)->whereDate('target_date', $g->input_date)->where('section', $g->section)->where('line_id', $g->line_id)->where('job_id', $g->job_id)->exists();
            if (! $has) {
                DB::table('qt_line_targets')->insert([
                    'id' => strtolower((string) Str::ulid()), 'company_id' => $g->company_id, 'target_date' => $g->input_date, 'section' => $g->section, 'line_id' => $g->line_id, 'job_id' => $g->job_id,
                    'working_hours' => 8, 'target_qty' => (int) $g->qty, 'created_by' => $g->created_by, 'updated_by' => $g->created_by, 'created_at' => now(), 'updated_at' => now(),
                ]);
            }
        }
        Schema::dropIfExists('qt_line_inputs');
    }

    public function down(): void
    {
        Schema::create('qt_line_inputs', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('company_id')->constrained('companies')->cascadeOnDelete();
            $table->date('input_date');
            $table->string('section', 12);
            $table->ulid('line_id');
            $table->foreignUlid('job_id')->constrained('qt_jobs')->restrictOnDelete();
            $table->string('size', 32)->default('');
            $table->unsignedInteger('qty');
            $table->text('remarks')->nullable();
            $table->foreignUlid('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }
};
