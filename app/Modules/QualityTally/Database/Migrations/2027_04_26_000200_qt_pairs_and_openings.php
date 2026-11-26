<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/**
 * Quality Tally, the user's decisions of 2026-11-25:
 * - a paired part counts twice a piece (`qt_panels.per_piece`, Sleeve = 2);
 * - a running job starts from its opening pieces (`qt_jobs.opening_*`);
 * - cutting taps from before bundles were given are put right: those that
 *   named a bundle get that bundle's card, and a job whose sewing or quality
 *   had already checked more than that gives gets the difference as opening.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('qt_panels', function (Blueprint $table): void {
            $table->unsignedSmallInteger('per_piece')->default(1)->after('name');
        });
        DB::table('qt_panels')->where('name', 'ilike', '%sleeve%')->update(['per_piece' => 2, 'updated_at' => now()]);
        Schema::table('qt_jobs', function (Blueprint $table): void {
            $table->unsignedInteger('opening_cut')->default(0)->after('order_qty');
            $table->unsignedInteger('opening_sewing')->default(0)->after('opening_cut');
            $table->unsignedInteger('opening_quality')->default(0)->after('opening_sewing');
        });

        $jobs = DB::table('qt_entries')->where('section', 'CUTTING')->whereNull('cut_bundle_id')->distinct()->pluck('job_id');
        foreach (DB::table('qt_jobs')->whereIn('id', $jobs)->get() as $job) {
            $this->giveOldBundles($job);
            $this->open($job);
        }
    }

    public function down(): void
    {
        Schema::table('qt_panels', fn (Blueprint $table) => $table->dropColumn('per_piece'));
        Schema::table('qt_jobs', fn (Blueprint $table) => $table->dropColumn(['opening_cut', 'opening_sewing', 'opening_quality']));
    }

    /** The bundles the QCs typed on the tablet become given bundles, holding what was checked on them. */
    private function giveOldBundles(object $job): void
    {
        $taps = DB::table('qt_entries as e')->join('qt_panels as p', 'p.id', '=', 'e.panel_id')
            ->where('e.job_id', $job->id)->where('e.section', 'CUTTING')->whereNull('e.cut_bundle_id')->whereNotNull('e.bundle_no')
            ->get(['e.id', 'e.line_id', 'e.garment_type', 'e.size', 'e.cutting_no', 'e.batch_no', 'e.bundle_no', 'e.bundle_qty', 'e.bundle_serial', 'e.panel_id', 'e.action', 'e.production_date', 'e.user_id', 'p.per_piece', 'p.garment_type as panel_garment']);
        foreach ($taps->groupBy(fn ($t) => implode('|', [$t->line_id, $t->panel_garment, $t->size, $t->cutting_no, $t->batch_no, $t->bundle_no])) as $group) {
            $t = $group->first();
            $checked = $group->whereIn('action', ['RFT', 'DEFECT'])->groupBy('panel_id')->map(fn ($g) => (int) ceil($g->count() / max(1, $g->first()->per_piece)))->max();
            $serial = $group->pluck('bundle_serial')->filter()->first();
            [$from, $to] = $serial && preg_match('/^(\d+)-(\d+)$/', $serial, $m) ? [(int) $m[1], (int) $m[2]] : [null, null];
            $id = (string) Str::ulid();
            DB::table('qt_cut_bundles')->insert([
                'id' => $id, 'company_id' => $job->company_id, 'barcode' => Str::limit('OLD'.preg_replace('/[^A-Z0-9]/', '', strtoupper($job->job_no.$t->cutting_no.$t->bundle_no)), 30, '').strtoupper(Str::random(4)),
                'bundle_no' => $t->bundle_no, 'job_id' => $job->id, 'line_id' => $t->line_id, 'garment_type' => $t->garment_type ?? $t->panel_garment, 'panel_id' => null,
                'size' => (string) $t->size, 'serial_from' => $from, 'serial_to' => $to, 'qty' => max(1, (int) $group->max('bundle_qty'), (int) $checked),
                'cutting_no' => (string) $t->cutting_no, 'batch_no' => (string) $t->batch_no, 'input_date' => $group->min('production_date'), 'source' => 'MANUAL',
                'created_by' => $t->user_id, 'created_at' => now(), 'updated_at' => now(),
            ]);
            DB::table('qt_entries')->whereIn('id', $group->pluck('id'))->update(['cut_bundle_id' => $id]);
        }
    }

    /**
     * Opening pieces: what the plain (panel-less) cutting taps passed, and at
     * least what sewing and quality have already checked — nothing can have
     * been checked that the section before had not passed.
     */
    private function open(object $job): void
    {
        $n = fn (string $section, array $actions, bool $plain = false): int => DB::table('qt_entries')->where('job_id', $job->id)->where('section', $section)->whereIn('action', $actions)
            ->when($plain, fn ($q) => $q->whereNull('panel_id'))->count();
        $pass = fn (string $section, bool $plain = false): int => min($n($section, ['RFT', 'RECTIFY'], $plain), $n($section, ['RFT', 'DEFECT'], $plain));

        $sewing = max(0, $n('QUALITY', ['RFT', 'DEFECT']) - $pass('SEWING'));
        $cut = max($pass('CUTTING', true), $sewing + $n('SEWING', ['RFT', 'DEFECT']) - $this->pieces($job), $sewing);
        DB::table('qt_jobs')->where('id', $job->id)->update(['opening_cut' => $cut, 'opening_sewing' => $sewing, 'updated_at' => now()]);
    }

    /** Complete pieces out of the job's bundles: per size, the smallest panel pass. */
    private function pieces(object $job): int
    {
        $bundles = DB::table('qt_cut_bundles')->where('job_id', $job->id)->get(['id', 'size', 'garment_type']);
        $panels = DB::table('qt_panels')->where('company_id', $job->company_id)->where('active', true)->where('garment_type', $bundles->first()?->garment_type)->get(['id', 'per_piece']);
        if ($panels->isEmpty()) {
            return 0;
        }
        $total = 0;
        foreach ($bundles->groupBy('size') as $group) {
            $taps = DB::table('qt_entries')->whereIn('cut_bundle_id', $group->pluck('id'))->get(['panel_id', 'action'])->groupBy('panel_id');
            $total += (int) $panels->map(function ($p) use ($taps) {
                $mine = $taps->get($p->id, collect());
                $pass = min($mine->whereIn('action', ['RFT', 'RECTIFY'])->count(), $mine->whereIn('action', ['RFT', 'DEFECT'])->count());

                return intdiv($pass, max(1, (int) $p->per_piece));
            })->min();
        }

        return $total;
    }
};
