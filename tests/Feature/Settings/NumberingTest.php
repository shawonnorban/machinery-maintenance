<?php

declare(strict_types=1);

namespace Tests\Feature\Settings;

use App\Modules\Identity\Models\User;
use App\Modules\Settings\Models\NumberSequence;
use App\Modules\Settings\Models\NumberSequenceFormat;
use App\Modules\Settings\Services\NumberSequenceGenerator;
use App\Modules\Tenancy\Models\Company;
use App\Modules\Tenancy\Models\Factory;
use App\Shared\Scopes\TenantScope;
use Carbon\CarbonImmutable;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Date;
use Tests\Support\TenantFixture;
use Tests\TestCase;

/**
 * Document numbering (SRS 52).
 *
 * A number is printed on a work order, quoted in an email and typed into
 * somebody else's ERP. Almost every rule here is a refusal, because a format
 * that produces a collision cannot be corrected for numbers already issued.
 */
class NumberingTest extends TestCase
{
    use RefreshDatabase;

    private Company $delta;

    private Factory $dhaka;

    private Factory $gazipur;

    protected function setUp(): void
    {
        parent::setUp();

        // Frozen, because half of what is asserted here is the shape of a
        // date inside a string, and a suite that passes in August and fails in
        // January is worse than no suite.
        Date::setTestNow(CarbonImmutable::parse('2026-08-24 10:00:00'));

        $this->seed(DatabaseSeeder::class);

        $this->delta = TenantFixture::company('Delta Apparels Ltd', 'DAL');
        $this->dhaka = TenantFixture::factory($this->delta, 'Dhaka Unit 1', 'DHK');
        $this->gazipur = TenantFixture::factory($this->delta, 'Gazipur Unit 2', 'GAZ');
        TenantFixture::actingAsTenant($this->delta);
    }

    protected function tearDown(): void
    {
        Date::setTestNow();

        parent::tearDown();
    }

    // -- The generator ------------------------------------------------------

    public function test_the_platform_default_is_used_when_a_company_has_said_nothing(): void
    {
        $number = app(NumberSequenceGenerator::class)->next('WORK_ORDER', $this->dhaka);

        $this->assertSame('WO-DHK-202608-00001', $number);
    }

    public function test_a_company_format_is_actually_used(): void
    {
        // The point of the whole feature. Before this, the screen could save a
        // format and the generator would carry on reading a constant.
        $this->saveFormat('WORK_ORDER', 'WO/{FACTORY}/{YYYY}-{MM}/{SEQ}', 4);

        $this->assertSame(
            'WO/DHK/2026-08/0001',
            app(NumberSequenceGenerator::class)->next('WORK_ORDER', $this->dhaka),
        );
    }

    public function test_a_format_changed_mid_period_does_not_split_the_period(): void
    {
        $numbers = app(NumberSequenceGenerator::class);

        $first = $numbers->next('WORK_ORDER', $this->dhaka);

        $this->saveFormat('WORK_ORDER', 'WO/{FACTORY}/{YYYY}-{MM}/{SEQ}', 4);

        $second = $numbers->next('WORK_ORDER', $this->dhaka);

        // August keeps one shape of number throughout. A month split into two
        // shapes is a month nobody can sort or search.
        $this->assertSame('WO-DHK-202608-00001', $first);
        $this->assertSame('WO-DHK-202608-00002', $second);
    }

    public function test_the_change_takes_effect_when_the_counter_next_restarts(): void
    {
        $numbers = app(NumberSequenceGenerator::class);

        $numbers->next('WORK_ORDER', $this->dhaka);
        $this->saveFormat('WORK_ORDER', 'WO/{FACTORY}/{YYYY}-{MM}/{SEQ}', 4);

        Date::setTestNow(CarbonImmutable::parse('2026-09-01 06:00:00'));

        // A new period is the only point at which a run of numbers can safely
        // restart, so it is the only point at which the shape may change.
        $this->assertSame(
            'WO/DHK/2026-09/0001',
            $numbers->next('WORK_ORDER', $this->dhaka),
        );
    }

    public function test_each_factory_counts_separately(): void
    {
        $numbers = app(NumberSequenceGenerator::class);

        $this->assertSame('WO-DHK-202608-00001', $numbers->next('WORK_ORDER', $this->dhaka));
        $this->assertSame('WO-GAZ-202608-00001', $numbers->next('WORK_ORDER', $this->gazipur));
        $this->assertSame('WO-DHK-202608-00002', $numbers->next('WORK_ORDER', $this->dhaka));
    }

    public function test_another_company_never_sees_this_company_s_format(): void
    {
        $this->saveFormat('WORK_ORDER', 'WO/{FACTORY}/{YYYY}-{MM}/{SEQ}', 4);

        $rival = TenantFixture::company('Rival Textiles Ltd', 'RTL');
        $savar = TenantFixture::factory($rival, 'Savar Unit', 'SAV');
        TenantFixture::actingAsTenant($rival);

        $this->assertSame(
            'WO-SAV-202608-00001',
            app(NumberSequenceGenerator::class)->next('WORK_ORDER', $savar),
        );
    }

    // -- What the screen refuses --------------------------------------------

    public function test_a_format_without_the_counter_is_refused(): void
    {
        $owner = $this->owner();

        $this->actingAs($owner)
            ->from('/app/settings/numbering')
            ->patch('/app/settings/numbering/WORK_ORDER', [
                'format' => 'WO-{FACTORY}-{YYYY}{MM}',
                'padding' => 5,
            ])
            ->assertSessionHasErrors('format');

        // Without {SEQ} every document in the month would be given the same
        // number, and nothing downstream would notice until two work orders
        // could not be told apart.
        $this->assertSame(0, NumberSequenceFormat::withoutGlobalScope(TenantScope::class)->count());
    }

    public function test_a_factory_scoped_counter_must_keep_the_factory(): void
    {
        $this->actingAs($this->owner())
            ->from('/app/settings/numbering')
            ->patch('/app/settings/numbering/WORK_ORDER', [
                'format' => 'WO-{YYYY}{MM}-{SEQ}',
                'padding' => 5,
            ])
            ->assertSessionHasErrors('format');

        // Two factories would issue the same number on the same day, each
        // believing it was unique.
        $this->assertSame(0, NumberSequenceFormat::withoutGlobalScope(TenantScope::class)->count());
    }

    public function test_a_monthly_counter_must_name_the_month(): void
    {
        $this->actingAs($this->owner())
            ->from('/app/settings/numbering')
            ->patch('/app/settings/numbering/WORK_ORDER', [
                'format' => 'WO-{FACTORY}-{YYYY}-{SEQ}',
                'padding' => 5,
            ])
            ->assertSessionHasErrors('format');
    }

    public function test_a_yearly_counter_may_not_name_a_month_it_does_not_follow(): void
    {
        $this->actingAs($this->owner())
            ->from('/app/settings/numbering')
            ->patch('/app/settings/numbering/ASSET_TRANSFER', [
                'format' => 'AT-{FACTORY}-{YYYY}{MM}-{SEQ}',
                'padding' => 5,
            ])
            ->assertSessionHasErrors('format');
    }

    public function test_an_invented_placeholder_is_refused(): void
    {
        $this->actingAs($this->owner())
            ->from('/app/settings/numbering')
            ->patch('/app/settings/numbering/WORK_ORDER', [
                'format' => 'WO-{FACTORY}-{DEPARTMENT}-{YYYY}{MM}-{SEQ}',
                'padding' => 5,
            ])
            ->assertSessionHasErrors('format');
    }

    // -- The screen ---------------------------------------------------------

    public function test_the_screen_shows_what_the_next_number_will_be(): void
    {
        app(NumberSequenceGenerator::class)->next('WORK_ORDER', $this->dhaka);

        $this->actingAs($this->owner())
            ->get('/app/settings/numbering')
            ->assertOk()
            ->assertSee(__('numbering.types.WORK_ORDER'))
            // Answering with a real sample beats explaining the placeholders.
            ->assertSee('WO-DHK-202608-00002');
    }

    public function test_a_format_can_be_saved_and_put_back(): void
    {
        $owner = $this->owner();

        $this->actingAs($owner)
            ->patch('/app/settings/numbering/WORK_ORDER', [
                'format' => 'WO/{FACTORY}/{YYYY}-{MM}/{SEQ}',
                'padding' => 4,
            ])
            ->assertRedirect();

        $this->assertSame(1, NumberSequenceFormat::withoutGlobalScope(TenantScope::class)->count());

        $this->actingAs($owner)
            ->delete('/app/settings/numbering/WORK_ORDER')
            ->assertRedirect();

        // Deleted rather than rewritten with the default, so "we never changed
        // this" and "we changed it back" stay distinguishable.
        $this->assertSame(0, NumberSequenceFormat::withoutGlobalScope(TenantScope::class)->count());

        $this->assertSame(
            'WO-DHK-202608-00001',
            app(NumberSequenceGenerator::class)->next('WORK_ORDER', $this->dhaka),
        );
    }

    public function test_a_role_without_the_permission_cannot_reach_it(): void
    {
        $manager = TenantFixture::user($this->delta, 'MAINTENANCE_MANAGER', 'manager@delta.test');
        TenantFixture::actingAsTenant($this->delta);

        $this->flushSession();

        $this->actingAs($manager)->get('/app/settings/numbering')->assertForbidden();

        $this->actingAs($manager)
            ->patch('/app/settings/numbering/WORK_ORDER', [
                'format' => 'WO-{FACTORY}-{YYYY}{MM}-{SEQ}',
                'padding' => 3,
            ])
            ->assertForbidden();
    }

    public function test_numbers_already_issued_are_counted_for_the_person_about_to_change_one(): void
    {
        $numbers = app(NumberSequenceGenerator::class);
        $numbers->next('WORK_ORDER', $this->dhaka);
        $numbers->next('WORK_ORDER', $this->dhaka);

        $this->assertSame(2, (int) NumberSequence::withoutGlobalScope(TenantScope::class)
            ->where('document_type', 'WORK_ORDER')
            ->sum('current_value'));

        $this->actingAs($this->owner())
            ->get('/app/settings/numbering')
            ->assertOk()
            ->assertSee(trans_choice('numbering.already_issued', 2, ['count' => 2]));
    }

    private function saveFormat(string $type, string $format, int $padding): void
    {
        NumberSequenceFormat::create([
            'company_id' => $this->delta->id,
            'document_type' => $type,
            'format' => $format,
            'padding' => $padding,
        ]);
    }

    private function owner(): User
    {
        $owner = TenantFixture::user($this->delta, 'COMPANY_OWNER', 'owner@delta.test');
        TenantFixture::actingAsTenant($this->delta);

        return $owner;
    }
}
