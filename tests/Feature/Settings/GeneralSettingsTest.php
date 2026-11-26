<?php

declare(strict_types=1);

namespace Tests\Feature\Settings;

use App\Modules\Api\Actions\IssueApiToken;
use App\Modules\Identity\Models\User;
use App\Modules\Settings\Services\SettingsResolver;
use App\Modules\Tenancy\Models\Company;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\TenantFixture;
use Tests\TestCase;

/**
 * Settings → General Settings (AnnotechERP 03/03): the new preference keys,
 * the currency master and label templates on the screen kit.
 */
class GeneralSettingsTest extends TestCase
{
    use RefreshDatabase;

    private Company $delta;

    private User $owner;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(DatabaseSeeder::class);

        $this->delta = TenantFixture::company('Delta Apparels Ltd', 'DAL');
        TenantFixture::factory($this->delta, 'Dhaka Unit 1', 'DHK');
        TenantFixture::actingAsTenant($this->delta);
        $this->owner = TenantFixture::user($this->delta, 'COMPANY_OWNER', 'owner@delta.test');
    }

    public function test_preferences_save_lists_trimmed_and_text_cleared(): void
    {
        $this->as()->putJson('/api/v1/settings/general.working_days', ['value' => 'SAT,SUN,MON,TUE,WED'])->assertOk();
        $this->as()->putJson('/api/v1/settings/general.working_days', ['value' => 'SAT,FUNDAY'])->assertUnprocessable();
        $this->as()->putJson('/api/v1/settings/print.signatures', ['value' => 'Prepared By,  Checked By , ,Approved By'])->assertOk();
        $this->as()->putJson('/api/v1/settings/print.header_title', ['value' => 'Delta Group'])->assertOk();
        // A cleared text field means empty, not an error.
        $this->as()->putJson('/api/v1/settings/print.header_title', ['value' => ''])->assertOk();
        $this->as()->putJson('/api/v1/settings/locale.date_format', ['value' => 'YYYY-MM-DD'])->assertOk();

        TenantFixture::actingAsTenant($this->delta);
        $resolver = app(SettingsResolver::class);
        $resolver->flush();
        $this->assertSame(['SAT', 'SUN', 'MON', 'TUE', 'WED'], $resolver->list('general.working_days'));
        $this->assertSame(['Prepared By', 'Checked By', 'Approved By'], $resolver->list('print.signatures'));
        $this->assertSame('', $resolver->string('print.header_title'));
        $this->assertSame('YYYY-MM-DD', $resolver->string('locale.date_format'));
        // Untouched keys still follow their defaults.
        $this->assertSame('7', $resolver->string('general.fiscal_year_start_month'));
    }

    public function test_the_base_currency_always_rates_one(): void
    {
        $this->as()->postJson('/api/v1/erp/currencies', ['code' => 'BDT', 'name' => 'Taka', 'symbol' => '৳', 'exchange_rate' => 5])
            ->assertCreated()
            ->assertJsonPath('data.exchange_rate', '1.000000')
            ->assertJsonPath('data.is_base', true);
        $this->as()->postJson('/api/v1/erp/currencies', ['code' => 'USD', 'name' => 'US Dollar', 'exchange_rate' => 121.5, 'rate_date' => '2026-10-10'])
            ->assertCreated()
            ->assertJsonPath('data.exchange_rate', '121.500000')
            ->assertJsonPath('data.is_base', false);
        $this->as()->postJson('/api/v1/erp/currencies', ['code' => 'USD', 'name' => 'Again', 'exchange_rate' => 1])->assertUnprocessable();
        $this->as()->postJson('/api/v1/erp/currencies', ['code' => 'XXX', 'name' => 'Nope', 'exchange_rate' => 1])->assertUnprocessable();
        $this->as()->postJson('/api/v1/erp/currencies', ['code' => 'EUR', 'name' => 'Euro', 'exchange_rate' => 0])->assertUnprocessable();
    }

    public function test_a_label_template_keeps_known_fields_in_order(): void
    {
        $this->as()->postJson('/api/v1/erp/label-templates', [
            'code' => 'lbl-roll', 'name' => 'Roll label', 'usage' => 'ROLL', 'symbology' => 'QR', 'width_mm' => 60, 'height_mm' => 40, 'fields' => 'code, LOT,color,lot',
        ])
            ->assertCreated()
            ->assertJsonPath('data.code', 'LBL-ROLL')
            ->assertJsonPath('data.fields', ['CODE', 'LOT', 'COLOR'])
            ->assertJsonPath('data.printer_language', 'PDF');

        $this->as()->postJson('/api/v1/erp/label-templates', [
            'code' => 'LBL-X', 'name' => 'X', 'usage' => 'ITEM', 'symbology' => 'CODE128', 'width_mm' => 50, 'height_mm' => 25, 'fields' => 'CODE,SHOE_SIZE',
        ])->assertUnprocessable()->assertJsonValidationErrors('fields');
    }

    private function as(): static
    {
        ['plain' => $plain] = app(IssueApiToken::class)->forUser($this->owner, $this->delta->id, 'Test token');

        return $this->withToken($plain);
    }
}
