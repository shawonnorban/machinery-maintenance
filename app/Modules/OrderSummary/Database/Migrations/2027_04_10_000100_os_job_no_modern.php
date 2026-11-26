<?php

declare(strict_types=1);

use App\Shared\JobNo\JobNumber;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Order Summary's JOB No in the company's form (user, 2026-11-22): the orders
 * brought over from Norban wrote it NCL-24-00815 or 24-NCL-00815; those become
 * 24NCL00815 like the ones entered from now on (year, factory, serial). Any
 * other text (notes, several numbers in one line) is left as it is.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::table('os_orders')->whereRaw("jsonb_typeof(\"values\"->'job_no') = 'array'")->orderBy('id')
            ->select(['id', 'values'])->chunk(500, function ($rows): void {
                foreach ($rows as $row) {
                    $values = json_decode((string) $row->values, true);
                    $old = $values['job_no'] ?? [];
                    $new = array_values(array_unique(array_map(fn ($v) => JobNumber::modern((string) $v), $old)));
                    if ($new !== $old) {
                        $values['job_no'] = $new;
                        DB::table('os_orders')->where('id', $row->id)->update(['values' => json_encode($values)]);
                    }
                }
            });
    }

    public function down(): void
    {
        // The older forms are not written back.
    }
};
