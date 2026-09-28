<?php

declare(strict_types=1);

namespace Tests\Feature\Vendor;

use App\Modules\Api\Actions\IssueApiToken;
use App\Modules\Finance\Models\Bank;
use App\Modules\Identity\Models\User;
use App\Modules\Tenancy\Models\Company;
use App\Modules\Vendor\Models\Vendor;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\TenantFixture;
use Tests\TestCase;

/**
 * Suppliers and service providers, over the API (API 17, SRS 26).
 */
class VendorApiTest extends TestCase
{
    use RefreshDatabase;

    private Company $delta;

    private User $storeManager;

    private User $technician;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(DatabaseSeeder::class);

        $this->delta = TenantFixture::company('Delta Apparels Ltd', 'DAL');
        TenantFixture::actingAsTenant($this->delta);

        $this->storeManager = TenantFixture::user($this->delta, 'STORE_MANAGER', 'sm@delta.test');
        $this->technician = TenantFixture::user($this->delta, 'TECHNICIAN', 'tech@delta.test');
    }

    public function test_a_vendor_can_be_created_updated_and_archived(): void
    {
        $created = $this->withToken($this->tokenFor($this->storeManager))
            ->postJson('/api/v1/vendors', [
                'name' => 'Zamil Spares Ltd',
                'code' => 'ZAMIL',
                'vendor_type' => 'SUPPLIER',
                'status' => 'ACTIVE',
                'country_of_origin' => 'Bangladesh',
            ])
            ->assertCreated()
            ->assertJsonPath('data.code', 'ZAMIL')
            ->assertJsonPath('data.country_of_origin', 'Bangladesh');

        $vendorId = $created->json('data.id');

        $this->withToken($this->tokenFor($this->storeManager))
            ->patchJson("/api/v1/vendors/{$vendorId}", [
                'name' => 'Zamil Spares & Service Ltd',
                'code' => 'ZAMIL',
                'vendor_type' => 'BOTH',
                'status' => 'ACTIVE',
                'country_of_origin' => 'Germany',
            ])
            ->assertOk()
            ->assertJsonPath('data.vendor_type', 'BOTH')
            ->assertJsonPath('data.country_of_origin', 'Germany');

        $this->withToken($this->tokenFor($this->storeManager))
            ->deleteJson("/api/v1/vendors/{$vendorId}")
            ->assertNoContent();

        $archived = Vendor::withTrashed()->find($vendorId);
        $this->assertNotNull($archived);
        $this->assertSame('INACTIVE', $archived->status);
        $this->assertSoftDeleted($archived);
    }

    /**
     * The supplier side of a vendor (Master Data spec §3): registration
     * numbers, where to pay them, what they supply, whether they are vetted.
     * Same row as the maintenance vendor, not a parallel supplier table.
     */
    public function test_a_supplier_carries_its_procurement_details(): void
    {
        $token = $this->tokenFor($this->storeManager);

        $bank = Bank::create(['company_id' => $this->delta->id, 'code' => 'DBBL', 'name' => 'Dutch-Bangla Bank', 'active' => true]);
        $paymentTypes = $this->withToken($token)->getJson('/api/v1/vendors/form-options')
            ->assertOk()
            ->assertJsonFragment(['id' => $bank->id, 'name' => 'Dutch-Bangla Bank'])
            ->assertJsonPath('data.supply_categories', Vendor::SUPPLY_CATEGORIES)
            ->json('data.payment_types');

        $lc = collect($paymentTypes)->firstWhere('code', 'LC');
        $this->assertNotNull($lc, 'LC is a platform-seeded payment type');

        $created = $this->withToken($token)
            ->postJson('/api/v1/vendors', [
                'name' => 'Pailung Machinery', 'code' => 'PAILUNG', 'vendor_type' => 'SUPPLIER', 'status' => 'ACTIVE',
                'bin' => '000123456-0101', 'trade_license_no' => 'TRAD/DNCC/1234',
                'bank_id' => $bank->id, 'bank_branch' => 'Gulshan', 'bank_account_no' => '1234567890', 'bank_swift' => 'DBBLBDDH',
                'supply_categories' => ['SPARE_PARTS', 'SERVICE', 'SPARE_PARTS'],
                'approval_status' => 'APPROVED', 'default_currency' => 'usd',
                'default_payment_type_id' => $lc['id'], 'default_payment_terms' => 'LC at sight',
            ])
            ->assertCreated()
            ->assertJsonPath('data.bank.name', 'Dutch-Bangla Bank')
            ->assertJsonPath('data.supply_categories', ['SPARE_PARTS', 'SERVICE'])
            ->assertJsonPath('data.approval_status', 'APPROVED')
            ->assertJsonPath('data.default_currency', 'USD')
            ->assertJsonPath('data.default_payment_type.name', 'Letter of credit')
            ->assertJsonPath('data.rating', null);

        $this->withToken($token)
            ->getJson('/api/v1/vendors/'.$created->json('data.id'))
            ->assertOk()
            ->assertJsonPath('data.bank_swift', 'DBBLBDDH');

        $this->withToken($token)
            ->postJson('/api/v1/vendors', [
                'name' => 'x', 'code' => 'X1', 'vendor_type' => 'SUPPLIER', 'status' => 'ACTIVE',
                'supply_categories' => ['FURNITURE'],
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors('supply_categories.0');

        // A vendor created the old way is simply un-vetted, never invalid.
        $this->withToken($token)
            ->postJson('/api/v1/vendors', ['name' => 'Plain', 'code' => 'PLAIN', 'vendor_type' => 'SERVICE', 'status' => 'ACTIVE'])
            ->assertCreated()
            ->assertJsonPath('data.approval_status', 'PENDING')
            ->assertJsonPath('data.supply_categories', []);
    }

    public function test_a_second_vendor_with_the_same_code_is_refused(): void
    {
        $this->withToken($this->tokenFor($this->storeManager))
            ->postJson('/api/v1/vendors', [
                'name' => 'Zamil Spares Ltd', 'code' => 'ZAMIL', 'vendor_type' => 'SUPPLIER', 'status' => 'ACTIVE',
            ])
            ->assertCreated();

        $this->withToken($this->tokenFor($this->storeManager))
            ->postJson('/api/v1/vendors', [
                'name' => 'Another Zamil', 'code' => 'ZAMIL', 'vendor_type' => 'SUPPLIER', 'status' => 'ACTIVE',
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors('code');
    }

    public function test_the_endpoints_are_closed_to_a_role_that_does_not_manage_vendors(): void
    {
        $this->withToken($this->tokenFor($this->technician))
            ->getJson('/api/v1/vendors')
            ->assertForbidden();

        $this->withToken($this->tokenFor($this->technician))
            ->postJson('/api/v1/vendors', [
                'name' => 'X', 'code' => 'X', 'vendor_type' => 'SUPPLIER', 'status' => 'ACTIVE',
            ])
            ->assertForbidden();
    }

    private function tokenFor(User $user): string
    {
        $companyId = $user->memberships()->latest()->value('company_id');

        ['plain' => $plain] = app(IssueApiToken::class)->forUser($user, $companyId, 'Test token');

        return $plain;
    }
}
