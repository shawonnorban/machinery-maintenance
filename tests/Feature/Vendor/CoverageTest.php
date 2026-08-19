<?php

declare(strict_types=1);

namespace Tests\Feature\Vendor;

use App\Modules\Asset\Models\Asset;
use App\Modules\Tenancy\Models\Company;
use App\Modules\Tenancy\Models\Factory;
use App\Modules\Vendor\Actions\DecideWarrantyClaim;
use App\Modules\Vendor\Actions\FileWarrantyClaim;
use App\Modules\Vendor\Actions\ManageServiceContract;
use App\Modules\Vendor\Actions\RecordWarranty;
use App\Modules\Vendor\Models\ServiceContract;
use App\Modules\Vendor\Models\Vendor;
use App\Modules\Vendor\Models\Warranty;
use App\Modules\Vendor\Services\AssetCoverage;
use Carbon\CarbonImmutable;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\Support\TenantFixture;
use Tests\Support\WorkOrderFixture;
use Tests\TestCase;

/**
 * Warranties, claims and AMC contracts (SRS 26).
 *
 * The question under test throughout is the one a factory asks at the machine:
 * is this repair already paid for? Getting it wrong costs real money in one
 * direction and a rejected claim in the other.
 */
class CoverageTest extends TestCase
{
    use RefreshDatabase;

    private Company $delta;

    private Factory $dhaka;

    private Asset $asset;

    private Vendor $juki;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(DatabaseSeeder::class);

        $this->delta = TenantFixture::company('Delta Apparels Ltd', 'DAL');
        $this->dhaka = TenantFixture::factory($this->delta, 'Dhaka Unit 1', 'DHK');
        TenantFixture::actingAsTenant($this->delta);

        $this->asset = WorkOrderFixture::runningAsset($this->delta, $this->dhaka);

        $this->juki = Vendor::create([
            'name' => 'Juki Bangladesh Ltd',
            'code' => 'JUKI-BD',
            'vendor_type' => 'BOTH',
            'status' => 'ACTIVE',
        ]);

        CarbonImmutable::setTestNow('2026-06-15 10:00:00');
    }

    protected function tearDown(): void
    {
        CarbonImmutable::setTestNow();

        parent::tearDown();
    }

    private function warranty(string $start = '2026-01-01', string $end = '2026-12-31'): Warranty
    {
        return app(RecordWarranty::class)->handle([
            'asset_id' => $this->asset->id,
            'vendor_id' => $this->juki->id,
            'warranty_type' => 'MANUFACTURER',
            'start_date' => $start,
            'end_date' => $end,
            'coverage' => 'Parts and labour',
        ], 'user-a');
    }

    public function test_a_machine_under_warranty_reads_as_covered(): void
    {
        $this->warranty();

        $coverage = app(AssetCoverage::class)->forAsset($this->asset);

        $this->assertTrue($coverage['covered']);
        $this->assertSame('Juki Bangladesh Ltd', $coverage['warranty']->vendor->name);
    }

    public function test_cover_is_decided_by_dates_not_by_status(): void
    {
        $warranty = $this->warranty('2025-01-01', '2026-06-01');

        // The daily sweep has not run yet, so the row still says ACTIVE while
        // the cover has in fact lapsed. A technician asking at 6am must not be
        // told the repair is paid for.
        $warranty->forceFill(['status' => 'ACTIVE'])->save();

        $this->assertFalse($warranty->fresh()->isActiveOn());
        $this->assertFalse(app(AssetCoverage::class)->forAsset($this->asset)['covered']);
    }

    public function test_recording_a_warranty_updates_the_machine_dates(): void
    {
        $this->warranty('2026-01-01', '2026-12-31');

        // The asset list and the register report read these two columns; leaving
        // them stale would have the machine screen and the warranty screen
        // disagreeing about the same machine.
        $this->assertSame('2026-12-31', $this->asset->fresh()->warranty_end->toDateString());

        $this->warranty('2027-01-01', '2028-12-31');

        $this->assertSame('2028-12-31', $this->asset->fresh()->warranty_end->toDateString());
    }

    public function test_a_warranty_ending_before_it_starts_is_refused(): void
    {
        $this->expectException(ValidationException::class);

        $this->warranty('2026-12-31', '2026-01-01');
    }

    public function test_a_claim_is_judged_on_the_failure_date_not_todays_date(): void
    {
        $warranty = $this->warranty('2025-01-01', '2026-05-31');

        // Cover lapsed two weeks ago, but the machine failed while it was still
        // in force. Refusing this would reject exactly the claims worth making.
        $claim = app(FileWarrantyClaim::class)->handle($warranty, [
            'claim_date' => '2026-06-15',
            'incident_date' => '2026-05-20',
            'description' => 'Rotary hook failed',
            'claimed_amount' => '2450',
        ], 'user-a');

        $this->assertSame('SUBMITTED', $claim->status);
        $this->assertStringStartsWith('WC-', $claim->claim_number);
    }

    public function test_a_claim_for_a_failure_outside_cover_is_refused(): void
    {
        $warranty = $this->warranty('2025-01-01', '2026-01-31');

        $this->expectException(ValidationException::class);

        app(FileWarrantyClaim::class)->handle($warranty, [
            'claim_date' => '2026-06-15',
            'incident_date' => '2026-06-01',
            'description' => 'Failed after cover ended',
        ], 'user-a');
    }

    public function test_a_claim_moves_through_its_states_and_refuses_shortcuts(): void
    {
        $warranty = $this->warranty();

        $claim = app(FileWarrantyClaim::class)->handle($warranty, [
            'claim_date' => '2026-06-15',
            'description' => 'Bearing failed',
            'claimed_amount' => '5000',
        ], 'user-a');

        $decide = app(DecideWarrantyClaim::class);

        $claim = $decide->handle($claim, 'ACKNOWLEDGED', [], 'user-b');
        $claim = $decide->handle($claim, 'APPROVED', [], 'user-b');
        $claim = $decide->handle($claim, 'SETTLED', ['settled_amount' => '4500'], 'user-b');

        $this->assertSame('SETTLED', $claim->status);
        $this->assertSame('4500.0000', $claim->settled_amount);

        // Nothing leaves a settled claim: agreeing and paying are months apart,
        // and reopening one would hide which is which.
        $this->expectException(ValidationException::class);
        $decide->handle($claim, 'APPROVED', [], 'user-b');
    }

    public function test_a_rejection_must_carry_a_reason(): void
    {
        $warranty = $this->warranty();

        $claim = app(FileWarrantyClaim::class)->handle($warranty, [
            'claim_date' => '2026-06-15',
            'description' => 'Motor burnt out',
        ], 'user-a');

        // A rejection without a reason is an argument nobody can have with the
        // vendor six months later.
        $this->expectException(ValidationException::class);

        app(DecideWarrantyClaim::class)->handle($claim, 'REJECTED', [], 'user-b');
    }

    public function test_a_factory_wide_contract_covers_a_machine_in_that_factory(): void
    {
        $contract = app(ManageServiceContract::class)->create([
            'vendor_id' => $this->juki->id,
            'factory_id' => $this->dhaka->id,
            'start_date' => '2026-01-01',
            'end_date' => '2026-12-31',
            'value' => '250000',
        ], 'user-a');

        $coverage = app(AssetCoverage::class)->forAsset($this->asset);

        // An AMC is as often written over a whole line as over one machine, and
        // the machine still has to know it is covered.
        $this->assertTrue($coverage['covered']);
        $this->assertTrue($contract->covers($this->asset));
        $this->assertSame($contract->id, $coverage['contracts']->first()->id);
    }

    public function test_a_contract_with_no_scope_is_refused(): void
    {
        $this->expectException(ValidationException::class);

        app(ManageServiceContract::class)->create([
            'vendor_id' => $this->juki->id,
            'start_date' => '2026-01-01',
            'end_date' => '2026-12-31',
        ], 'user-a');
    }

    public function test_a_parts_supplier_cannot_be_named_on_a_service_contract(): void
    {
        $supplier = Vendor::create([
            'name' => 'Parts Only Ltd',
            'code' => 'PARTS-ONLY',
            'vendor_type' => 'SUPPLIER',
            'status' => 'ACTIVE',
        ]);

        // A contract with a vendor who does not service machines is a contract
        // with nobody to call.
        $this->expectException(ValidationException::class);

        app(ManageServiceContract::class)->create([
            'vendor_id' => $supplier->id,
            'asset_id' => $this->asset->id,
            'start_date' => '2026-01-01',
            'end_date' => '2026-12-31',
        ], 'user-a');
    }

    public function test_renewing_creates_a_new_contract_and_keeps_the_old_one(): void
    {
        $manage = app(ManageServiceContract::class);

        $original = $manage->create([
            'vendor_id' => $this->juki->id,
            'factory_id' => $this->dhaka->id,
            'start_date' => '2025-01-01',
            'end_date' => '2025-12-31',
            'value' => '200000',
            'visits_per_year' => 4,
        ], 'user-a');

        $renewal = $manage->renew($original, [
            'start_date' => '2026-01-01',
            'end_date' => '2026-12-31',
            'value' => '250000',
        ], 'user-a');

        $original->refresh();

        // The whole value of an AMC history is showing what changed between
        // renewals — the price here went up by fifty thousand.
        $this->assertSame('RENEWED', $original->status);
        $this->assertSame('200000.0000', $original->value);
        $this->assertSame('250000.0000', $renewal->fresh()->value);
        $this->assertSame($original->id, $renewal->renewed_from_contract_id);
        // Terms not restated carry over rather than silently emptying.
        $this->assertSame(4, $renewal->visits_per_year);
    }

    public function test_a_cancelled_contract_cannot_be_renewed(): void
    {
        $manage = app(ManageServiceContract::class);

        $contract = $manage->create([
            'vendor_id' => $this->juki->id,
            'asset_id' => $this->asset->id,
            'start_date' => '2026-01-01',
            'end_date' => '2026-12-31',
        ], 'user-a');

        $manage->cancel($contract, 'Vendor stopped servicing this model', 'user-a');

        $this->expectException(ValidationException::class);

        $manage->renew($contract->fresh(), [
            'start_date' => '2027-01-01',
            'end_date' => '2027-12-31',
        ], 'user-a');
    }

    public function test_coverage_never_reaches_another_companys_contracts(): void
    {
        $other = TenantFixture::company('Beta Textiles Ltd', 'BTL');
        $otherFactory = TenantFixture::factory($other, 'Gazipur Unit', 'GAZ');

        TenantFixture::actingAsTenant($other);

        $theirVendor = Vendor::create([
            'name' => 'Their Vendor',
            'code' => 'THEIRS',
            'vendor_type' => 'SERVICE',
            'status' => 'ACTIVE',
        ]);

        app(ManageServiceContract::class)->create([
            'vendor_id' => $theirVendor->id,
            'factory_id' => $otherFactory->id,
            'start_date' => '2026-01-01',
            'end_date' => '2026-12-31',
        ], 'user-x');

        TenantFixture::actingAsTenant($this->delta);

        $this->assertFalse(app(AssetCoverage::class)->forAsset($this->asset)['covered']);
        $this->assertSame(0, ServiceContract::count());
    }
}
