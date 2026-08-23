<?php

declare(strict_types=1);

namespace Tests\Feature\Settings;

use App\Modules\Audit\Models\AuditLog;
use App\Modules\Identity\Models\User;
use App\Modules\Settings\Models\Setting;
use App\Modules\Settings\Services\SettingsResolver;
use App\Modules\Tenancy\Models\Company;
use App\Modules\Tenancy\Models\Factory;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\TenantFixture;
use Tests\TestCase;

/**
 * How a company wants the product to behave (SRS 20, ADR-054).
 *
 * The part that matters is not the writing but the inheritance: a factory that
 * has not answered follows the company, and a factory that answered the same
 * way does not. Confusing the two is how one unit's availability quietly stops
 * matching another's.
 */
class CompanySettingsTest extends TestCase
{
    use RefreshDatabase;

    private Company $delta;

    private Factory $dhaka;

    private Factory $gazipur;

    private User $owner;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(DatabaseSeeder::class);

        $this->delta = TenantFixture::company('Delta Apparels Ltd', 'DAL');
        $this->dhaka = TenantFixture::factory($this->delta, 'Dhaka Unit 1', 'DHK');
        $this->gazipur = TenantFixture::factory($this->delta, 'Gazipur Unit 2', 'GAZ');
        TenantFixture::actingAsTenant($this->delta);

        $this->owner = TenantFixture::user($this->delta, 'COMPANY_OWNER', 'owner@delta.test');
        TenantFixture::actingAsTenant($this->delta);
    }

    private function resolver(): SettingsResolver
    {
        return app(SettingsResolver::class);
    }

    public function test_a_company_answer_applies_everywhere(): void
    {
        $this->actingAs($this->owner)
            ->post('/app/settings/company', [
                'key' => 'metrics.planned_downtime_counts_against_availability',
                'value' => '1',
            ])
            ->assertRedirect();

        $this->resolver()->flush();

        $this->assertTrue($this->resolver()->bool('metrics.planned_downtime_counts_against_availability'));
        $this->assertTrue($this->resolver()->bool(
            'metrics.planned_downtime_counts_against_availability',
            $this->dhaka->id,
        ));
    }

    /**
     * The distinction the screen exists to make visible.
     */
    public function test_a_factory_that_has_not_answered_follows_the_company(): void
    {
        // Dhaka answers for itself; Gazipur does not.
        $this->actingAs($this->owner)->post('/app/settings/company', [
            'key' => 'metrics.planned_downtime_counts_against_availability',
            'value' => '1',
            'factory_id' => $this->dhaka->id,
        ]);

        $this->actingAs($this->owner)->post('/app/settings/company', [
            'key' => 'metrics.planned_downtime_counts_against_availability',
            'value' => '0',
        ]);

        $this->resolver()->flush();

        // Dhaka keeps its own answer; Gazipur moved with the company.
        $this->assertTrue($this->resolver()->bool(
            'metrics.planned_downtime_counts_against_availability',
            $this->dhaka->id,
        ));
        $this->assertFalse($this->resolver()->bool(
            'metrics.planned_downtime_counts_against_availability',
            $this->gazipur->id,
        ));
    }

    public function test_a_factory_can_be_put_back_on_the_company_answer(): void
    {
        $this->actingAs($this->owner)->post('/app/settings/company', [
            'key' => 'metrics.planned_downtime_counts_against_availability',
            'value' => '1',
            'factory_id' => $this->dhaka->id,
        ]);

        $this->actingAs($this->owner)
            ->delete('/app/settings/company', [
                'key' => 'metrics.planned_downtime_counts_against_availability',
                'factory_id' => $this->dhaka->id,
            ])
            ->assertRedirect();

        $this->resolver()->flush();

        $this->assertSame(0, Setting::where('factory_id', $this->dhaka->id)
            ->where('key', 'metrics.planned_downtime_counts_against_availability')
            ->count());

        // Back to the platform default, because the company never answered.
        $this->assertFalse($this->resolver()->bool(
            'metrics.planned_downtime_counts_against_availability',
            $this->dhaka->id,
        ));
    }

    public function test_a_setting_that_belongs_to_the_company_cannot_be_answered_per_factory(): void
    {
        // Costing method is company-wide: two factories valuing stock
        // differently would make a group valuation meaningless.
        $this->actingAs($this->owner)
            ->from('/app/settings/company')
            ->post('/app/settings/company', [
                'key' => 'inventory.costing_method',
                'value' => 'FIFO',
                'factory_id' => $this->dhaka->id,
            ])
            ->assertSessionHasErrors('key');

        $this->assertSame(0, Setting::where('factory_id', $this->dhaka->id)
            ->where('key', 'inventory.costing_method')->count());
    }

    public function test_a_value_outside_the_allowed_set_is_refused(): void
    {
        $this->actingAs($this->owner)
            ->from('/app/settings/company')
            ->post('/app/settings/company', [
                'key' => 'inventory.costing_method',
                'value' => 'GUESSWORK',
            ])
            ->assertSessionHasErrors();

        // Configuration that silently accepts anything is how a typo becomes a
        // wrong valuation six months later.
        $this->assertSame(0, Setting::where('key', 'inventory.costing_method')->count());
    }

    public function test_an_unchecked_switch_means_false_rather_than_unchanged(): void
    {
        $this->actingAs($this->owner)->post('/app/settings/company', [
            'key' => 'notification.escalation_enabled',
            'value' => '1',
        ]);

        // A form posts nothing at all for an unchecked box.
        $this->actingAs($this->owner)->post('/app/settings/company', [
            'key' => 'notification.escalation_enabled',
        ]);

        $this->resolver()->flush();

        $this->assertFalse($this->resolver()->bool('notification.escalation_enabled'));
    }

    /**
     * Several of these change what a number means, so who changed one and when
     * has to be answerable.
     */
    public function test_a_change_is_audited(): void
    {
        $this->actingAs($this->owner)->post('/app/settings/company', [
            'key' => 'metrics.planned_downtime_counts_against_availability',
            'value' => '1',
        ]);

        $this->assertGreaterThanOrEqual(1, AuditLog::forCompany($this->delta->id)
            ->where('entity_type', 'settings')
            ->count());
    }

    public function test_the_screen_shows_where_each_answer_comes_from(): void
    {
        $this->actingAs($this->owner)->post('/app/settings/company', [
            'key' => 'metrics.planned_downtime_counts_against_availability',
            'value' => '1',
            'factory_id' => $this->dhaka->id,
        ]);

        $this->actingAs($this->owner)
            ->get('/app/settings/company?factory_id='.$this->dhaka->id)
            ->assertOk()
            ->assertSee(__('settings.source_factory'))
            ->assertSee(__('settings.follow_company_again'));

        // Gazipur answered nothing, so nothing there is its own.
        $this->actingAs($this->owner)
            ->get('/app/settings/company?factory_id='.$this->gazipur->id)
            ->assertOk()
            ->assertDontSee(__('settings.follow_company_again'));
    }

    public function test_another_companys_factory_cannot_be_configured(): void
    {
        $other = TenantFixture::company('Beta Textiles Ltd', 'BTL');
        $theirFactory = TenantFixture::factory($other, 'Their Unit', 'BTU');

        TenantFixture::actingAsTenant($this->delta);

        $this->actingAs($this->owner)
            ->post('/app/settings/company', [
                'key' => 'metrics.planned_downtime_counts_against_availability',
                'value' => '1',
                'factory_id' => $theirFactory->id,
            ])
            ->assertForbidden();
    }

    public function test_the_screen_is_closed_to_roles_that_do_not_configure(): void
    {
        $technician = TenantFixture::user($this->delta, 'TECHNICIAN', 'tech@delta.test');
        TenantFixture::actingAsTenant($this->delta);

        $this->actingAs($technician)->get('/app/settings/company')->assertForbidden();
        $this->actingAs($technician)
            ->post('/app/settings/company', [
                'key' => 'notification.escalation_enabled',
                'value' => '0',
            ])
            ->assertForbidden();
    }
}
