<?php

declare(strict_types=1);

namespace Tests\Feature\TradeExport;

use App\Modules\Api\Actions\IssueApiToken;
use App\Modules\Identity\Models\User;
use App\Modules\Merchandise\Models\Buyer;
use App\Modules\Settings\Models\Currency;
use App\Modules\Tenancy\Models\Company;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\TenantFixture;
use Tests\TestCase;

/**
 * Trade & Export → Master Data (AnnotechERP 22/01): the reference lists with
 * codes generated on save, notify parties and L/C applicants per buyer,
 * exchange rates taken from the currency's own rate, and the bank directory
 * with its branches — and who may change them.
 */
class TradeExportMasterTest extends TestCase
{
    use RefreshDatabase;

    private Company $delta;

    private User $owner;

    private Buyer $buyer;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(DatabaseSeeder::class);
        $this->delta = TenantFixture::company('Delta Apparels Ltd', 'DAL');
        TenantFixture::factory($this->delta, 'Dhaka Unit 1', 'FAC01');
        TenantFixture::actingAsTenant($this->delta);
        $this->owner = TenantFixture::user($this->delta, 'COMPANY_OWNER', 'owner@delta.test');
        $this->buyer = Buyer::query()->create(['company_id' => $this->delta->id, 'code' => 'LPP', 'name' => 'LPP S.A.', 'short_name' => 'LPP', 'pi_address' => 'Gdansk', 'registered_from' => '2026-01-01', 'active' => true]);
    }

    public function test_reference_lists_start_with_bricklands_entries_and_generate_codes(): void
    {
        $rows = collect($this->as()->getJson('/api/v1/erp/tx-reference-entries')->assertOk()->json('data'));
        $this->assertSame(['FOB', 'CIF', 'CFR', 'EXW', 'DDP'], $rows->where('list', 'INCO_TERM')->pluck('code')->values()->all());
        $this->assertCount(6, $rows->where('list', 'PAYMENT_TERM'));
        $this->assertCount(0, $rows->where('list', 'FORWARDER'));

        $entry = ['list' => 'FORWARDER', 'name' => 'Expo Freight Ltd', 'short_name' => 'Expo Freight', 'display_order' => 1];
        $this->as()->postJson('/api/v1/erp/tx-reference-entries', ['name' => null] + $entry)->assertUnprocessable()->assertJsonPath('errors.name.0', 'Name(EN) is required.');
        $id = $this->as()->postJson('/api/v1/erp/tx-reference-entries', $entry)->assertCreated()->assertJsonPath('data.code', 'EXPO-FREIGHT')->json('data.id');
        $this->as()->postJson('/api/v1/erp/tx-reference-entries', ['short_name' => 'expo freight'] + $entry)->assertUnprocessable()->assertJsonValidationErrors('short_name');
        $this->as()->postJson('/api/v1/erp/tx-reference-entries', ['list' => 'INCO_TERM', 'short_name' => 'Expo Freight'] + $entry)->assertCreated();
        $this->as()->patchJson("/api/v1/erp/tx-reference-entries/{$id}", ['active' => false])->assertOk()->assertJsonPath('data.active', false);
        $this->as()->patchJson("/api/v1/erp/tx-reference-entries/{$id}", ['list' => 'LC_TYPE'])->assertUnprocessable();
        $this->as()->deleteJson("/api/v1/erp/tx-reference-entries/{$id}")->assertStatus(405);
    }

    public function test_notify_parties_and_applicants_belong_to_a_buyer(): void
    {
        $party = ['buyer_id' => $this->buyer->id, 'name' => 'LPP Logistics', 'address' => 'Gdansk, Poland', 'email' => 'docs@lpp.test'];
        $this->as()->postJson('/api/v1/erp/tx-notify-parties', ['address' => null] + $party)->assertUnprocessable()->assertJsonPath('errors.address.0', 'Address is required.');
        $this->as()->postJson('/api/v1/erp/tx-notify-parties', ['email' => 'not-an-email'] + $party)->assertUnprocessable()->assertJsonValidationErrors('email');
        $this->as()->postJson('/api/v1/erp/tx-notify-parties', $party)->assertCreated()->assertJsonPath('data.code', 'NP-0001')->assertJsonPath('data.buyer', 'LPP');
        $this->as()->postJson('/api/v1/erp/tx-notify-parties', ['name' => 'LPP Retail'] + $party)->assertCreated()->assertJsonPath('data.code', 'NP-0002');

        $applicant = ['buyer_id' => $this->buyer->id, 'name' => 'LPP S.A.', 'address' => 'ul. Lakowa 39/44, Gdansk'];
        $this->as()->postJson('/api/v1/erp/tx-lc-applicants', ['buyer_id' => null] + $applicant)->assertUnprocessable()->assertJsonPath('errors.buyer_id.0', 'Buyer Name is required.');
        $this->as()->postJson('/api/v1/erp/tx-lc-applicants', $applicant)->assertCreated()->assertJsonPath('data.buyer', 'LPP');
        $this->as()->postJson('/api/v1/erp/tx-lc-applicants', ['name' => 'lpp s.a.'] + $applicant)->assertUnprocessable()->assertJsonValidationErrors('name');
    }

    public function test_exchange_rates_take_the_currencys_rate(): void
    {
        Currency::query()->create(['company_id' => $this->delta->id, 'code' => 'USD', 'name' => 'US Dollar', 'symbol' => '$', 'decimal_places' => 2, 'exchange_rate' => 119.5, 'active' => true]);
        Currency::query()->create(['company_id' => $this->delta->id, 'code' => 'EUR', 'name' => 'Euro', 'symbol' => '€', 'decimal_places' => 2, 'exchange_rate' => 0, 'active' => true]);
        $this->as()->getJson('/api/v1/tradeexport/lookups')->assertOk()->assertJsonPath('data.currencies.1.rate', '119.5');

        $rate = ['rate_for' => 'EXPORT_REALIZATION', 'currency' => 'USD', 'effective_date' => '2026-10-11', 'rate' => 1];
        $this->as()->postJson('/api/v1/erp/tx-exchange-rates', ['currency' => null] + $rate)->assertUnprocessable()->assertJsonPath('errors.currency.0', 'Rate By is required.');
        $this->as()->postJson('/api/v1/erp/tx-exchange-rates', ['currency' => 'EUR'] + $rate)->assertUnprocessable()->assertJsonValidationErrors('currency');
        $this->as()->postJson('/api/v1/erp/tx-exchange-rates', $rate)->assertCreated()->assertJsonPath('data.rate', '119.500000')->assertJsonPath('data.company', 'Delta Apparels Ltd');
        $this->as()->postJson('/api/v1/erp/tx-exchange-rates', $rate)->assertUnprocessable()->assertJsonValidationErrors('effective_date');
        $this->as()->postJson('/api/v1/erp/tx-exchange-rates', ['rate_for' => 'IMPORT_PAYMENT'] + $rate)->assertCreated();
    }

    public function test_banks_and_branches(): void
    {
        $banks = collect($this->as()->getJson('/api/v1/erp/tx-banks')->assertOk()->json('data'));
        $this->assertCount(26, $banks);
        $abn = $banks->firstWhere('code', 'ABN');
        $this->assertSame([], $abn['branches']);
        $country = $this->as()->postJson('/api/v1/erp/origin-countries', ['code' => '050', 'name' => 'Bangladesh'])->json('data.id');

        $bank = ['code' => 'scb', 'name' => 'Standard Chartered Bank', 'prefix' => 'SCB', 'country_id' => $country, 'swift_code' => 'scblbddx'];
        $this->as()->postJson('/api/v1/erp/tx-banks', ['code' => null] + $bank)->assertUnprocessable()->assertJsonPath('errors.code.0', 'Bank Code is required.');
        $this->as()->postJson('/api/v1/erp/tx-banks', ['swift_code' => null] + $bank)->assertUnprocessable()->assertJsonValidationErrors('swift_code');
        $id = $this->as()->postJson('/api/v1/erp/tx-banks', $bank)->assertCreated()->assertJsonPath('data.code', 'SCB')->assertJsonPath('data.swift_code', 'SCBLBDDX')->json('data.id');
        $this->as()->postJson('/api/v1/erp/tx-banks', ['code' => 'ABN'] + $bank)->assertUnprocessable()->assertJsonValidationErrors('code');

        $branch = ['bank_id' => $id, 'code' => 'GUL', 'name' => 'Gulshan Branch', 'routing_no' => '215261726'];
        $this->as()->postJson('/api/v1/erp/tx-bank-branches', ['code' => null] + $branch)->assertUnprocessable()->assertJsonPath('errors.code.0', 'Branch Code is required.');
        $this->as()->postJson('/api/v1/erp/tx-bank-branches', $branch)->assertCreated();
        $this->as()->postJson('/api/v1/erp/tx-bank-branches', ['code' => 'gul'] + $branch)->assertUnprocessable()->assertJsonValidationErrors('code');
        $this->as()->postJson('/api/v1/erp/tx-bank-branches', ['bank_id' => $abn['id']] + $branch)->assertCreated();
        $this->as()->getJson("/api/v1/erp/tx-banks/{$id}")->assertJsonCount(1, 'data.branches')->assertJsonPath('data.branches.0.name', 'Gulshan Branch');
    }

    public function test_who_may_change_the_trade_export_masters(): void
    {
        $manager = app(IssueApiToken::class)->forUser(TenantFixture::user($this->delta, 'FACTORY_ADMIN', 'manager@delta.test'), $this->delta->id, 'Test token')['plain'];
        $this->withToken($manager)->postJson('/api/v1/erp/tx-lc-applicants', ['buyer_id' => $this->buyer->id, 'name' => 'LPP', 'address' => 'Gdansk'])->assertCreated();

        $auditor = app(IssueApiToken::class)->forUser(TenantFixture::user($this->delta, 'AUDITOR', 'auditor@delta.test'), $this->delta->id, 'Test token')['plain'];
        $this->withToken($auditor)->getJson('/api/v1/erp/tx-banks')->assertOk();
        $this->withToken($auditor)->postJson('/api/v1/erp/tx-lc-applicants', ['buyer_id' => $this->buyer->id, 'name' => 'X', 'address' => 'Y'])->assertForbidden();

        $store = app(IssueApiToken::class)->forUser(TenantFixture::user($this->delta, 'STORE_MANAGER', 'store@delta.test'), $this->delta->id, 'Test token')['plain'];
        $this->withToken($store)->getJson('/api/v1/erp/tx-reference-entries')->assertForbidden();
    }

    private function as(): static
    {
        return $this->withToken(app(IssueApiToken::class)->forUser($this->owner, $this->delta->id, 'Test token')['plain']);
    }
}
