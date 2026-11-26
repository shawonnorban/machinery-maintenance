<?php

declare(strict_types=1);

namespace Tests\Feature\WorkStudy;

use App\Modules\Api\Actions\IssueApiToken;
use App\Modules\Identity\Models\User;
use App\Modules\Tenancy\Models\Company;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\Support\TenantFixture;
use Tests\TestCase;

/**
 * Work Study → Section Profitability (AnnotechERP 14/08): a month's income
 * targets per section (Target Income = daily target × Avg Rate × WrkDay/Mn,
 * Brickland's 195,000), Update bringing another month across; a day's
 * results opening at the section's share of the month, Profit/Loss income
 * less the three costs (Brickland's 2,500) — both sheets printed and exported.
 */
class WorkStudySectionProfitTest extends TestCase
{
    use RefreshDatabase;

    private Company $delta;

    private User $owner;

    private string $cutting;

    private string $sewing;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(DatabaseSeeder::class);
        $this->delta = TenantFixture::company('Delta Apparels Ltd', 'DAL');
        $unit = TenantFixture::factory($this->delta, 'Dhaka Unit 1', 'FAC01');
        TenantFixture::actingAsTenant($this->delta);
        $this->owner = TenantFixture::user($this->delta, 'COMPANY_OWNER', 'owner@delta.test');
        $dept = (string) Str::ulid();
        DB::table('departments')->insert(['id' => $dept, 'company_id' => $this->delta->id, 'factory_id' => $unit->id, 'name' => 'Production', 'code' => 'PRD', 'created_at' => now(), 'updated_at' => now()]);
        DB::table('sections')->where('company_id', $this->delta->id)->delete();
        $section = fn (string $name, string $code) => tap((string) Str::ulid(), fn ($id) => DB::table('sections')->insert(['id' => $id, 'company_id' => $this->delta->id, 'department_id' => $dept, 'name' => $name, 'code' => $code, 'status' => 'ACTIVE', 'created_at' => now(), 'updated_at' => now()]));
        $this->cutting = $section('Cutting', 'CUT');
        $this->sewing = $section('Sewing', 'SEW');
    }

    public function test_income_targets_and_section_results(): void
    {
        $options = $this->as()->getJson('/api/v1/workstudy/section-pl/options')->assertOk()->json('data');
        $this->assertSame(['Cutting — Production', 'Sewing — Production'], array_column($options['sections'], 'label'));

        // New income target: Brickland's message without the target and rate; Target Income derived (195,000).
        $row = ['company' => $this->delta->id, 'year' => 2026, 'month' => 10, 'section_id' => $this->cutting, 'daily_target_inhouse' => 1000, 'daily_target_subcon' => 500, 'work_days' => 26];
        $this->as()->postJson('/api/v1/erp/ws-section-targets', $row)->assertUnprocessable()
            ->assertJsonPath('errors.daily_target.0', 'Daily production target and average rate are both required.')
            ->assertJsonPath('errors.avg_rate.0', 'Daily production target and average rate are both required.');
        $made = $this->as()->postJson('/api/v1/erp/ws-section-targets', ['daily_target' => 5000, 'avg_rate' => 1.5, 'target_income' => 1] + $row)->assertCreated()->json('data');
        $this->assertSame([195000.0, 'Cutting [BDT=]', 'October 2026'], [(float) $made['target_income'], $made['sub_section'], $made['period_label']]);
        $this->as()->postJson('/api/v1/erp/ws-section-targets', ['daily_target' => 10, 'avg_rate' => 1] + $row)->assertUnprocessable()->assertJsonValidationErrors('section_id');

        // The month's sheet: every section; a target without a rate is refused; a cleared target drops it.
        $sheet = $this->as()->getJson('/api/v1/workstudy/section-pl/targets?period=2026-10')->assertOk()->json('data.rows');
        $this->assertSame([['Cutting', 195000.0, 26], ['Sewing', 0.0, 26]], array_map(fn ($r) => [$r['section'], (float) $r['target_income'], $r['work_days']], $sheet));
        $sheet[1] = ['daily_target' => 2000] + $sheet[1];
        $this->as()->putJson('/api/v1/workstudy/section-pl/targets', ['period' => '2026-10', 'rows' => $sheet])->assertUnprocessable()->assertJsonValidationErrors('rows.1.avg_rate');
        $sheet[1]['avg_rate'] = 4;
        $sheet[1]['work_days'] = 25;
        $saved = $this->as()->putJson('/api/v1/workstudy/section-pl/targets', ['period' => '2026-10', 'rows' => $sheet])->assertOk()->json('data.rows');
        $this->assertSame([195000.0, 200000.0], array_map(fn ($r) => (float) $r['target_income'], $saved));

        // Update: November takes October's figures; a section already set in November is left alone.
        $this->as()->postJson('/api/v1/erp/ws-section-targets', ['month' => 11, 'daily_target' => 100, 'avg_rate' => 2] + $row)->assertCreated();
        $copied = $this->as()->postJson('/api/v1/workstudy/section-pl/targets/copy', ['period' => '2026-11', 'from' => '2026-10'])->assertOk()->json('data');
        $this->assertSame(1, $copied['copied']);
        $this->assertSame([5200.0, 200000.0], array_map(fn ($r) => (float) $r['target_income'], $copied['rows']));
        $this->as()->postJson('/api/v1/workstudy/section-pl/targets/copy', ['period' => '2026-11', 'from' => '2026-11'])->assertUnprocessable()->assertJsonValidationErrors('from');

        // Clearing Sewing's daily target in October drops it.
        $saved[1]['daily_target'] = null;
        $this->as()->putJson('/api/v1/workstudy/section-pl/targets', ['period' => '2026-10', 'rows' => $saved])->assertOk();
        $this->assertSame(1, DB::table('ws_section_targets')->where('period', '2026-10')->count());

        // A day: IncomeTarget opens at the day's share (5,000 × 1.5 = 7,500), Prod Target at 5,000; nothing posted yet.
        $day = $this->as()->getJson('/api/v1/workstudy/section-pl/results?date=2026-10-25')->assertOk()->json('data.rows');
        $this->assertSame([7500.0, 5000.0, null], [(float) $day[0]['income_target'], (float) $day[0]['prod_target'], $day[0]['id']]);
        $day[0] = ['income' => 8000, 'prod_qty' => 5200, 'oh_cost' => 2000, 'mat_cost' => 3000, 'expense' => 500, 'comments' => 'Good day'] + $day[0];
        $posted = $this->as()->putJson('/api/v1/workstudy/section-pl/results', ['date' => '2026-10-25', 'rows' => $day])->assertOk()->json('data.rows');
        $this->assertSame(2500.0, (float) $posted[0]['profit']);
        $this->assertSame(1, DB::table('ws_section_results')->count());
        $record = $this->as()->getJson('/api/v1/erp/ws-section-results')->json('data.0');
        $this->assertSame(['Cutting', 5500.0, 2500.0, 'PROFIT'], [$record['section'], (float) $record['total_cost'], (float) $record['profit'], $record['standing']]);

        // New section result: Brickland's message without a company; one a section a day; a loss.
        $result = ['section_id' => $this->sewing, 'result_date' => '2026-10-25', 'income' => 1000, 'oh_cost' => 1500];
        $this->as()->postJson('/api/v1/erp/ws-section-results', $result)->assertUnprocessable()->assertJsonPath('errors.company.0', 'Company is required.');
        $this->as()->postJson('/api/v1/erp/ws-section-results', ['company' => $this->delta->id] + $result)->assertCreated()->assertJsonPath('data.standing', 'LOSS');
        $this->as()->postJson('/api/v1/erp/ws-section-results', ['company' => $this->delta->id] + $result)->assertUnprocessable()->assertJsonValidationErrors('section_id');

        // Clearing what Cutting earned, made and spent takes its result away.
        $day = $this->as()->getJson('/api/v1/workstudy/section-pl/results?date=2026-10-25')->json('data.rows');
        $day[0] = ['income' => 0, 'prod_qty' => 0, 'oh_cost' => 0, 'mat_cost' => 0, 'expense' => 0, 'comments' => null] + $day[0];
        $this->as()->putJson('/api/v1/workstudy/section-pl/results', ['date' => '2026-10-25', 'rows' => $day])->assertOk();
        $this->assertSame([$this->sewing], DB::table('ws_section_results')->pluck('section_id')->all());

        foreach (['targets/pdf?period=2026-10', 'results/pdf?date=2026-10-25'] as $file) {
            $this->assertStringStartsWith('%PDF', $this->as()->get("/api/v1/workstudy/section-pl/export/{$file}")->assertOk()->getContent());
        }
        $this->as()->get('/api/v1/workstudy/section-pl/export/targets/xlsx?period=2026-10')->assertOk();

        // A line chief has no part in section profit.
        $chief = TenantFixture::user($this->delta, 'LINE_CHIEF', 'chief@delta.test');
        $this->as($chief)->getJson('/api/v1/workstudy/section-pl/targets?period=2026-10')->assertForbidden();
    }

    private function as(?User $user = null): static
    {
        ['plain' => $plain] = app(IssueApiToken::class)->forUser($user ?? $this->owner, $this->delta->id, 'Test token');

        return $this->withToken($plain);
    }
}
