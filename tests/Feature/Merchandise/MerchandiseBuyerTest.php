<?php

declare(strict_types=1);

namespace Tests\Feature\Merchandise;

use App\Modules\Api\Actions\IssueApiToken;
use App\Modules\Identity\Models\User;
use App\Modules\Merchandise\Models\OriginCountry;
use App\Modules\Tenancy\Models\Company;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\TenantFixture;
use Tests\TestCase;

/**
 * Merchandise Desk → Master Data, the buyer lists (AnnotechERP 06/01 step B),
 * and Prototype Desk's Sample Type Configuration: offices and accounts linked
 * both ways, buyer permissions per user, sample and test-lab mappings,
 * buyer-wise trims and nominated suppliers — and no reaching another company.
 */
class MerchandiseBuyerTest extends TestCase
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

    public function test_a_buyer_is_linked_to_offices_both_ways(): void
    {
        $lpp = $this->as()->postJson('/api/v1/erp/buyer-offices', ['name' => 'lpp s.a.', 'office_type' => 'HEAD_OFFICE'])->assertCreated()->assertJsonPath('data.name', 'LPP S.A.')->json('data.id');
        $tex = $this->as()->postJson('/api/v1/erp/buyer-offices', ['name' => 'TEX EBO', 'office_type' => 'BUYING_HOUSE'])->assertCreated()->json('data.id');
        $this->as()->postJson('/api/v1/erp/buyer-offices', ['name' => 'LPP S.A.', 'office_type' => 'AGENT'])->assertUnprocessable();

        $buyer = ['code' => 'lpp', 'name' => 'LPP Spolka Akcyjna', 'short_name' => 'LPP', 'pi_address' => 'Gdansk, Poland', 'registered_from' => '2026-01-01', 'country_id' => $this->country()];
        $this->as()->postJson('/api/v1/erp/buyers', $buyer)->assertUnprocessable()->assertJsonValidationErrors('office_ids');
        // Brickland's form: Country is required, and the office it picks is the buyer's first link.
        $this->as()->postJson('/api/v1/erp/buyers', array_diff_key($buyer, ['country_id' => 1]) + ['office_id' => $lpp])->assertUnprocessable()->assertJsonPath('errors.country_id.0', 'Country is required.');
        $this->as()->postJson('/api/v1/erp/buyers', ['code' => 'X3'] + $buyer + ['office_id' => $tex])->assertCreated()->assertJsonPath('data.offices', ['TEX EBO'])->assertJsonPath('data.office_id', $tex)->assertJsonPath('data.country', 'Bangladesh');
        $id = $this->as()->postJson('/api/v1/erp/buyers', $buyer + ['office_ids' => [$lpp]])
            ->assertCreated()->assertJsonPath('data.code', 'LPP')->assertJsonPath('data.offices', ['LPP S.A.'])->json('data.id');
        $this->as()->postJson('/api/v1/erp/buyers', ['registered_to' => '2025-01-01'] + $buyer + ['code' => 'X2', 'office_ids' => [$lpp]])->assertUnprocessable();

        // Link to Office from the buyer's side, Buyer Acc from the office's.
        $this->as()->putJson("/api/v1/merchandise/mappings/buyer-offices/{$id}", ['ids' => [$lpp, $tex]])->assertOk();
        // X3 picked TEX EBO as its office on the form, so it is assigned there too.
        $this->assertSame(['LPP (LPP)', 'LPP (X3)'], collect($this->as()->getJson("/api/v1/merchandise/mappings/office-buyers/{$tex}")->json('data'))->where('assigned', true)->pluck('label')->all());
        $this->as()->putJson("/api/v1/merchandise/mappings/office-buyers/{$tex}", ['ids' => []])->assertOk();
        $this->as()->getJson('/api/v1/erp/buyers')->assertJsonPath('data.0.offices', ['LPP S.A.']);
        $this->as()->getJson('/api/v1/merchandise/mappings/buyer-offices/subjects')->assertOk()->assertJsonPath('data.0.label', 'LPP (LPP)');
    }

    public function test_buyer_permissions_are_kept_per_user_and_purpose(): void
    {
        $buyer = $this->buyer();
        $merchant = TenantFixture::user($this->delta, 'FACTORY_MANAGER', 'merchant@delta.test');

        $this->as()->putJson("/api/v1/merchandise/mappings/user-buyers/{$merchant->id}", ['ids' => [$buyer]])->assertOk();
        $this->as()->getJson('/api/v1/merchandise/mappings/user-buyers')->assertJsonCount(1, 'data')->assertJsonPath('data.0.items', ['LPP (LPP)']);
        $this->as()->getJson('/api/v1/merchandise/mappings/user-buyers-marketing')->assertJsonCount(0, 'data');

        // A user of another company is not a subject here, nor is a buyer of theirs an item.
        $other = TenantFixture::company('Other Ltd', 'OTH');
        $stranger = TenantFixture::user($other, 'COMPANY_OWNER', 'stranger@other.test');
        TenantFixture::actingAsTenant($this->delta);
        $this->as()->putJson("/api/v1/merchandise/mappings/user-buyers/{$stranger->id}", ['ids' => [$buyer]])->assertNotFound();
        $this->as()->putJson("/api/v1/merchandise/mappings/user-buyers/{$merchant->id}", ['ids' => ['01ARZ3NDEKTSV4RRFFQ69G5FAV']])->assertUnprocessable();
        $this->as()->getJson('/api/v1/merchandise/mappings/unknown-kind')->assertNotFound();
    }

    public function test_sample_types_start_on_defaults_and_buyers_map_them_with_overrides(): void
    {
        $types = collect($this->as()->getJson('/api/v1/erp/sample-types')->assertOk()->json('data'));
        $this->assertCount(11, $types);
        $this->assertFalse($types->firstWhere('code', 'PP')['customised']);

        $pp = $types->firstWhere('code', 'PP')['id'];
        $this->as()->patchJson("/api/v1/erp/sample-types/{$pp}", ['lead_days' => 10, 'qc_required' => false])->assertOk()->assertJsonPath('data.customised', true)->assertJsonPath('data.lead_days', 10);

        $buyer = $this->buyer();
        $fit = $types->firstWhere('code', 'FIT')['id'];
        $this->as()->putJson("/api/v1/merchandise/buyers/{$buyer}/sample-types", ['samples' => [['sample_type_id' => $pp, 'quantity' => 3, 'size' => 'M', 'source' => 'IN_HOUSE'], ['sample_type_id' => $fit]]])->assertOk();
        $rows = collect($this->as()->getJson("/api/v1/merchandise/buyers/{$buyer}/sample-types")->json('data'))->keyBy('code');
        $this->assertTrue($rows['PP']['assigned']);
        $this->assertSame(3, $rows['PP']['quantity']);
        $this->assertFalse($rows['TOP']['assigned']);
        $this->as()->putJson("/api/v1/merchandise/buyers/{$buyer}/sample-types", ['samples' => [['sample_type_id' => $pp, 'source' => 'MOON']]])->assertUnprocessable();

        // A type buyers still require cannot be deleted.
        $this->as()->deleteJson("/api/v1/erp/sample-types/{$pp}")->assertUnprocessable();
        $this->as()->getJson('/api/v1/erp/buyers')->assertJsonPath('data.0.sample_count', 2);
    }

    public function test_test_labs_trims_and_suppliers_belong_to_their_buyer(): void
    {
        $buyer = $this->buyer();
        $sgs = $this->as()->postJson('/api/v1/erp/test-institutes', ['name' => 'SGS Bangladesh'])->assertCreated()->json('data.id');
        $this->as()->postJson('/api/v1/erp/test-institutes', ['name' => 'sgs bangladesh'])->assertUnprocessable();
        $this->as()->putJson("/api/v1/merchandise/mappings/buyer-institutes/{$buyer}", ['ids' => [$sgs]])->assertOk();

        $this->as()->postJson('/api/v1/erp/buyer-trim-items', ['buyer_id' => $buyer, 'item_code' => 'lbl-main', 'item_name' => 'Main Label', 'specification' => 'Woven, 50 x 20 mm'])
            ->assertCreated()->assertJsonPath('data.item_code', 'LBL-MAIN')->assertJsonPath('data.buyer', 'LPP');
        $this->as()->postJson('/api/v1/erp/buyer-trim-items', ['buyer_id' => $buyer, 'item_code' => 'LBL-MAIN', 'item_name' => 'Again'])->assertUnprocessable();

        // One supplier configuration per buyer and item category; a blank category is the buyer's default.
        $this->as()->postJson('/api/v1/erp/buyer-supplier-configs', ['buyer_id' => $buyer, 'remarks' => 'Zippers from YKK only'])->assertCreated()->assertJsonPath('data.item_category', null);
        $this->as()->postJson('/api/v1/erp/buyer-supplier-configs', ['buyer_id' => $buyer])->assertUnprocessable()->assertJsonValidationErrors('buyer_id');
    }

    private function buyer(): string
    {
        $office = $this->as()->postJson('/api/v1/erp/buyer-offices', ['name' => 'LPP S.A.', 'office_type' => 'HEAD_OFFICE'])->json('data.id');

        return $this->as()->postJson('/api/v1/erp/buyers', ['code' => 'LPP', 'name' => 'LPP Spolka Akcyjna', 'short_name' => 'LPP', 'pi_address' => 'Gdansk', 'registered_from' => '2026-01-01', 'country_id' => $this->country(), 'office_ids' => [$office]])
            ->assertCreated()->json('data.id');
    }

    private function as(): static
    {
        ['plain' => $plain] = app(IssueApiToken::class)->forUser($this->owner, $this->delta->id, 'Test token');

        return $this->withToken($plain);
    }

    private function country(): string
    {
        return OriginCountry::query()->firstOrCreate(['company_id' => $this->delta->id, 'code' => '1000'], ['name' => 'Bangladesh', 'active' => true])->id;
    }
}
