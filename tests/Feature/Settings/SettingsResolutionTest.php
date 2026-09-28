<?php

declare(strict_types=1);

namespace Tests\Feature\Settings;

use App\Modules\Settings\Actions\SetSetting;
use App\Modules\Settings\Services\SettingsResolver;
use App\Modules\Tenancy\Models\Company;
use App\Modules\Tenancy\Models\Factory;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;
use Tests\Support\TenantFixture;
use Tests\TestCase;

/**
 * Resolution order and validation: SRS 53.1, ADR-054.
 */
class SettingsResolutionTest extends TestCase
{
    use RefreshDatabase;

    private Company $delta;

    private Company $rival;

    private Factory $dhaka;

    private Factory $gazipur;

    private SettingsResolver $resolver;

    private SetSetting $set;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(DatabaseSeeder::class);

        $this->delta = TenantFixture::company('Delta Apparels Ltd', 'DAL');
        $this->rival = TenantFixture::company('Rival Garments Ltd', 'RGL');
        $this->dhaka = TenantFixture::factory($this->delta, 'Dhaka Unit 1', 'DHK');
        $this->gazipur = TenantFixture::factory($this->delta, 'Gazipur Unit 2', 'GAZ');

        TenantFixture::actingAsTenant($this->delta);

        $this->resolver = app(SettingsResolver::class);
        $this->set = app(SetSetting::class);
        $this->resolver->flush();
    }

    public function test_an_unset_key_returns_the_platform_default(): void
    {
        $resolved = $this->resolver->resolve('maintenance.schedule_generation_horizon_days');

        $this->assertSame(90, $resolved['value']);
        $this->assertSame('PLATFORM', $resolved['level']);
    }

    public function test_a_company_value_overrides_the_platform_default(): void
    {
        $this->set->handle('maintenance.schedule_generation_horizon_days', 120);

        $resolved = $this->resolver->resolve('maintenance.schedule_generation_horizon_days');

        $this->assertSame(120, $resolved['value']);
        $this->assertSame('COMPANY', $resolved['level']);
    }

    public function test_a_factory_override_beats_the_company_value(): void
    {
        $this->set->handle('metrics.downtime_uses_shift_calendar', true);
        $this->set->handle('metrics.downtime_uses_shift_calendar', false, factoryId: $this->dhaka->id);

        // The overridden factory sees false...
        $this->assertFalse($this->resolver->bool('metrics.downtime_uses_shift_calendar', $this->dhaka->id));
        $this->assertSame(
            'FACTORY',
            $this->resolver->resolve('metrics.downtime_uses_shift_calendar', $this->dhaka->id)['level'],
        );

        // ...while every other factory still sees the company value.
        $this->assertTrue($this->resolver->bool('metrics.downtime_uses_shift_calendar', $this->gazipur->id));
        $this->assertSame(
            'COMPANY',
            $this->resolver->resolve('metrics.downtime_uses_shift_calendar', $this->gazipur->id)['level'],
        );
    }

    public function test_removing_an_override_falls_back_to_the_next_level_up(): void
    {
        $this->set->handle('work_order.approval_cost_threshold', '50000');
        $setting = $this->set->handle(
            'work_order.approval_cost_threshold',
            '10000',
            factoryId: $this->dhaka->id,
        );

        $this->assertSame(
            '10000.0000',
            $this->resolver->get('work_order.approval_cost_threshold', $this->dhaka->id),
        );

        $setting->delete();
        $this->resolver->flush();

        $this->assertSame(
            '50000.0000',
            $this->resolver->get('work_order.approval_cost_threshold', $this->dhaka->id),
        );
    }

    public function test_settings_do_not_leak_across_companies(): void
    {
        $this->set->handle('maintenance.schedule_generation_horizon_days', 120);

        TenantFixture::actingAsTenant($this->rival);
        app(SettingsResolver::class)->flush();

        // Rival must see the platform default, not Delta's value.
        $this->assertSame(90, app(SettingsResolver::class)->int('maintenance.schedule_generation_horizon_days'));
    }

    public function test_an_unknown_key_is_rejected(): void
    {
        $this->expectException(InvalidArgumentException::class);

        $this->set->handle('made.up.key', 'anything');
    }

    public function test_a_value_of_the_wrong_type_is_rejected(): void
    {
        $this->expectException(ValidationException::class);

        $this->set->handle('maintenance.schedule_generation_horizon_days', 'not-a-number');
    }

    public function test_a_value_outside_the_allowed_set_is_rejected(): void
    {
        $this->expectException(ValidationException::class);

        $this->set->handle('maintenance.non_working_day_policy', 'SOMETHING_ELSE');
    }

    public function test_a_key_cannot_be_set_at_a_level_it_does_not_allow(): void
    {
        // This key is COMPANY-only.
        $this->expectException(ValidationException::class);

        $this->set->handle(
            'maintenance.schedule_generation_horizon_days',
            30,
            factoryId: $this->dhaka->id,
        );
    }

    public function test_list_values_round_trip(): void
    {
        $this->assertSame(
            ['CRITICAL', 'HIGH'],
            $this->resolver->list('work_order.require_verification_for_criticality'),
        );

        $this->set->handle('work_order.require_verification_for_criticality', ['CRITICAL']);

        $this->assertSame(
            ['CRITICAL'],
            $this->resolver->list('work_order.require_verification_for_criticality'),
        );
    }

    public function test_boolean_false_is_stored_and_not_treated_as_unset(): void
    {
        // The classic bug: storing false and reading back the default true.
        $this->set->handle('metrics.downtime_uses_shift_calendar', false);

        $resolved = $this->resolver->resolve('metrics.downtime_uses_shift_calendar');

        $this->assertFalse($resolved['value']);
        $this->assertSame('COMPANY', $resolved['level']);
    }

    public function test_all_returns_every_definition_with_its_level(): void
    {
        $this->set->handle('inventory.allow_negative_stock', true);

        $all = $this->resolver->all();

        $this->assertArrayHasKey('inventory.allow_negative_stock', $all);
        $this->assertSame('COMPANY', $all['inventory.allow_negative_stock']['level']);
        $this->assertSame('PLATFORM', $all['notification.escalation_enabled']['level']);
    }
}
