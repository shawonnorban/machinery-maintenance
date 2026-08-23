<?php

declare(strict_types=1);

namespace Tests\Feature\Breakdown;

use App\Modules\Asset\Actions\ChangeAssetStatus;
use App\Modules\Asset\Models\Asset;
use App\Modules\Breakdown\Actions\ReportBreakdown;
use App\Modules\Breakdown\Actions\TransitionBreakdown;
use App\Modules\Breakdown\Models\Breakdown;
use App\Modules\Breakdown\Models\FailureCategory;
use App\Modules\Breakdown\Models\FailureCode;
use App\Modules\Breakdown\Models\RootCause;
use App\Modules\Breakdown\Services\RecurringFailureAnalyser;
use App\Modules\Tenancy\Models\Company;
use App\Modules\Tenancy\Models\Factory;
use Carbon\CarbonImmutable;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\TenantFixture;
use Tests\Support\WorkOrderFixture;
use Tests\TestCase;

/**
 * Failure and root cause analysis (SRS 16).
 *
 * The question that justifies buying the system: a machine repaired eleven
 * times in a quarter is not a maintenance problem, it is a replacement decision
 * nobody has made yet, and it is invisible in a list of individual breakdowns.
 */
class FailureAnalysisTest extends TestCase
{
    use RefreshDatabase;

    private Company $delta;

    private Factory $dhaka;

    private ReportBreakdown $report;

    private TransitionBreakdown $transition;

    private RecurringFailureAnalyser $analyser;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(DatabaseSeeder::class);

        $this->delta = TenantFixture::company('Delta Apparels Ltd', 'DAL');
        $this->dhaka = TenantFixture::factory($this->delta, 'Dhaka Unit 1', 'DHK');
        TenantFixture::actingAsTenant($this->delta);

        $this->report = app(ReportBreakdown::class);
        $this->transition = app(TransitionBreakdown::class);
        $this->analyser = app(RecurringFailureAnalyser::class);
    }

    private function asset(string $code): Asset
    {
        return WorkOrderFixture::runningAsset($this->delta, $this->dhaka, $code);
    }

    /** Reports and closes a breakdown, returning the machine to service. */
    private function failAndFix(Asset $asset, string $failureCode, CarbonImmutable $at): Breakdown
    {
        $breakdown = $this->report->handle([
            'asset_id' => $asset->id,
            'problem_description' => 'Stopped',
            'failure_at' => $at,
            'reported_at' => $at,
        ], 'user-a');

        $breakdown = $this->transition->acknowledge($breakdown, 'user-b', $at->addMinutes(5));
        $breakdown = $this->transition->startRepair($breakdown, 'user-c', $at->addMinutes(10));
        $breakdown = $this->transition->completeRepair($breakdown, 'user-c', $at->addMinutes(40));
        $breakdown = $this->transition->resumeProduction($breakdown, 'user-c', $at->addMinutes(45));

        $breakdown = $this->transition->close($breakdown, [
            'failure_code_id' => FailureCode::where('code', $failureCode)->firstOrFail()->id,
            'root_cause_id' => RootCause::where('code', 'NORMAL_WEAR')->firstOrFail()->id,
        ], 'user-b');

        // Back to running, so the next failure is an independent one rather than
        // a linked recurrence.
        $asset = Asset::find($asset->id);

        if ($asset->status !== 'RUNNING' && $asset->canTransitionTo('RUNNING')) {
            app(ChangeAssetStatus::class)->handle($asset, 'RUNNING', 'user-c', 'Back in service', 'BREAKDOWN');
        }

        return $breakdown;
    }

    public function test_the_seeded_taxonomy_covers_the_mill_vocabulary(): void
    {
        // Free text cannot be grouped, and "which failure keeps happening" is
        // the question the whole analysis half of the product answers.
        //
        // Floors rather than exact counts: the catalogue is meant to grow as
        // more of the mill is covered, and a test that has to be edited every
        // time a failure code is added stops being read.
        $this->assertGreaterThanOrEqual(5, FailureCategory::whereNull('company_id')->count());
        $this->assertGreaterThanOrEqual(45, FailureCode::whereNull('company_id')->count());
        $this->assertGreaterThanOrEqual(14, RootCause::whereNull('company_id')->count());

        // Every code hangs off a category, so the grouping is never partial.
        $this->assertSame(
            0,
            FailureCode::whereNull('company_id')->whereNull('failure_category_id')->count(),
        );
    }

    public function test_unknown_is_seeded_deliberately(): void
    {
        // Without an honest option a technician under pressure picks a wrong
        // code, and wrong data is worse than absent data (Seed Catalog 3.5).
        $this->assertNotNull(FailureCode::where('code', 'UNKNOWN')->first());
        $this->assertNotNull(RootCause::where('code', 'UNDETERMINED')->first());
    }

    public function test_every_seeded_code_carries_both_languages(): void
    {
        foreach (FailureCode::whereNull('company_id')->get() as $code) {
            // A technician reading the list in Bengali should not meet English
            // fragments in the middle of it.
            $this->assertNotEmpty($code->name, "{$code->code} has no English name.");
            $this->assertNotEmpty($code->name_bn, "{$code->code} has no Bengali name.");
        }
    }

    public function test_repeat_offenders_are_surfaced(): void
    {
        $bad = $this->asset('SEW-DHK-00001');
        $fine = $this->asset('SEW-DHK-00002');

        foreach ([1, 8, 15, 22] as $index => $day) {
            $this->failAndFix($bad, 'BEARING_FAILURE', CarbonImmutable::parse("2026-06-{$day} 09:00:00"));
        }

        $this->failAndFix($fine, 'NEEDLE_BREAK', CarbonImmutable::parse('2026-06-10 09:00:00'));

        $offenders = $this->analyser->repeatOffenders(
            CarbonImmutable::parse('2026-06-01'),
            CarbonImmutable::parse('2026-06-30'),
            minimumFailures: 3,
        );

        $this->assertCount(1, $offenders);
        $this->assertSame($bad->id, $offenders->first()->asset_id);
        $this->assertSame(4, (int) $offenders->first()->failure_count);
    }

    public function test_a_linked_recurrence_is_not_counted_as_a_separate_failure(): void
    {
        $asset = $this->asset('SEW-DHK-00003');

        // One stoppage, reported three times by three people.
        $this->report->handle([
            'asset_id' => $asset->id, 'problem_description' => 'Stopped',
            'failure_at' => '2026-06-01 09:00:00', 'reported_at' => '2026-06-01 09:00:00',
        ], 'user-a');

        foreach (['Still stopped', 'Nobody has come'] as $description) {
            $this->report->handle([
                'asset_id' => $asset->id, 'problem_description' => $description,
                'failure_at' => '2026-06-01 09:30:00', 'reported_at' => '2026-06-01 09:30:00',
            ], 'user-a');
        }

        $offenders = $this->analyser->repeatOffenders(
            CarbonImmutable::parse('2026-06-01'),
            CarbonImmutable::parse('2026-06-30'),
            minimumFailures: 2,
        );

        // Counting all three would show a machine that broke once as a repeat
        // offender and halve its MTBF (ERD Section 10 rule 4).
        $this->assertCount(0, $offenders);
        $this->assertSame(3, Breakdown::where('asset_id', $asset->id)->count());
    }

    public function test_failure_code_frequency_ranks_the_common_causes(): void
    {
        $a = $this->asset('SEW-DHK-00004');
        $b = $this->asset('SEW-DHK-00005');

        $this->failAndFix($a, 'NEEDLE_BREAK', CarbonImmutable::parse('2026-06-01 09:00:00'));
        $this->failAndFix($a, 'NEEDLE_BREAK', CarbonImmutable::parse('2026-06-05 09:00:00'));
        $this->failAndFix($b, 'NEEDLE_BREAK', CarbonImmutable::parse('2026-06-08 09:00:00'));
        $this->failAndFix($b, 'MOTOR_FAILURE', CarbonImmutable::parse('2026-06-12 09:00:00'));

        $frequency = $this->analyser->failureCodeFrequency(
            CarbonImmutable::parse('2026-06-01'),
            CarbonImmutable::parse('2026-06-30'),
        );

        $this->assertSame(3, (int) $frequency->first()->failure_count);
        $this->assertSame('NEEDLE_BREAK', $frequency->first()->failureCode->code);
    }

    public function test_the_unknown_share_is_measured_as_a_data_quality_figure(): void
    {
        $asset = $this->asset('SEW-DHK-00006');

        $this->failAndFix($asset, 'UNKNOWN', CarbonImmutable::parse('2026-06-01 09:00:00'));
        $this->failAndFix($asset, 'BEARING_FAILURE', CarbonImmutable::parse('2026-06-05 09:00:00'));
        $this->failAndFix($asset, 'BEARING_FAILURE', CarbonImmutable::parse('2026-06-09 09:00:00'));
        $this->failAndFix($asset, 'BEARING_FAILURE', CarbonImmutable::parse('2026-06-13 09:00:00'));

        $share = $this->analyser->unknownCauseShare(
            CarbonImmutable::parse('2026-06-01'),
            CarbonImmutable::parse('2026-06-30'),
        );

        // A rising share means the analysis is running on air, and the only way
        // to notice is to measure it.
        $this->assertSame(4, $share['closed']);
        $this->assertSame(1, $share['unknown']);
        $this->assertSame(25.0, $share['share']);
    }

    public function test_the_unknown_share_is_null_rather_than_zero_on_no_data(): void
    {
        $share = $this->analyser->unknownCauseShare(
            CarbonImmutable::parse('2026-06-01'),
            CarbonImmutable::parse('2026-06-30'),
        );

        // Zero percent unknown on nothing closed reads as excellent
        // record-keeping (SRS 31.2 rule 2).
        $this->assertNull($share['share']);
        $this->assertSame(0, $share['closed']);
    }

    public function test_mtbf_needs_two_failures_to_mean_anything(): void
    {
        $asset = $this->asset('SEW-DHK-00007');

        $this->failAndFix($asset, 'BELT_BROKEN', CarbonImmutable::parse('2026-06-01 09:00:00'));

        // With one failure there is no interval to average, and reporting the
        // age of the machine instead would be a different number wearing the
        // same name.
        $this->assertNull($this->analyser->mtbfHours(
            $asset->id, CarbonImmutable::parse('2026-06-01'), CarbonImmutable::parse('2026-06-30'),
        ));

        $this->failAndFix($asset, 'BELT_BROKEN', CarbonImmutable::parse('2026-06-03 09:00:00'));
        $this->failAndFix($asset, 'BELT_BROKEN', CarbonImmutable::parse('2026-06-05 09:00:00'));

        // Two intervals of 48 hours each.
        $this->assertSame(48.0, $this->analyser->mtbfHours(
            $asset->id, CarbonImmutable::parse('2026-06-01'), CarbonImmutable::parse('2026-06-30'),
        ));
    }

    public function test_a_cancelled_breakdown_is_excluded_from_the_analysis(): void
    {
        $asset = $this->asset('SEW-DHK-00008');

        $this->failAndFix($asset, 'NEEDLE_BREAK', CarbonImmutable::parse('2026-06-01 09:00:00'));
        $this->failAndFix($asset, 'NEEDLE_BREAK', CarbonImmutable::parse('2026-06-05 09:00:00'));

        $mistake = $this->report->handle([
            'asset_id' => $asset->id, 'problem_description' => 'Wrong machine',
            'failure_at' => '2026-06-09 09:00:00', 'reported_at' => '2026-06-09 09:00:00',
        ], 'user-a');

        $this->transition->cancel($mistake, 'Reported against the wrong machine', 'user-b');

        // A report entered in error is not a failure, and letting it into the
        // count would make the machine look worse than it is.
        $offenders = $this->analyser->repeatOffenders(
            CarbonImmutable::parse('2026-06-01'),
            CarbonImmutable::parse('2026-06-30'),
            minimumFailures: 3,
        );

        $this->assertCount(0, $offenders);
    }
}
