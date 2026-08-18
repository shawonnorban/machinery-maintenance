<?php

declare(strict_types=1);

namespace Tests\Feature\Asset;

use App\Modules\Asset\Actions\ChangeAssetStatus;
use App\Modules\Asset\Actions\CreateAsset;
use App\Modules\Asset\Models\Asset;
use App\Modules\Asset\Models\AssetCategory;
use App\Modules\Asset\Models\AssetLocation;
use App\Modules\Asset\Models\AssetStatusHistory;
use App\Modules\Asset\Models\AssetType;
use App\Modules\Tenancy\Models\Company;
use App\Modules\Tenancy\Models\Factory;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\Support\TenantFixture;
use Tests\TestCase;

/**
 * Asset creation, status machine and identity rules
 * (SRS 6, Data Dictionary 3.3 and 5.1).
 */
class AssetLifecycleTest extends TestCase
{
    use RefreshDatabase;

    private Company $delta;

    private Factory $dhaka;

    private Factory $gazipur;

    private AssetLocation $line3;

    private AssetLocation $gazipurLine;

    private CreateAsset $create;

    private ChangeAssetStatus $changeStatus;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(DatabaseSeeder::class);

        $this->delta = TenantFixture::company('Delta Apparels Ltd', 'DAL');
        $this->dhaka = TenantFixture::factory($this->delta, 'Dhaka Unit 1', 'DHK');
        $this->gazipur = TenantFixture::factory($this->delta, 'Gazipur Unit 2', 'GAZ');

        TenantFixture::actingAsTenant($this->delta);

        $this->line3 = AssetLocation::create([
            'factory_id' => $this->dhaka->id, 'name' => 'Line 3', 'code' => 'DHK-L3',
        ]);
        $this->gazipurLine = AssetLocation::create([
            'factory_id' => $this->gazipur->id, 'name' => 'Line 1', 'code' => 'GAZ-L1',
        ]);

        $this->create = app(CreateAsset::class);
        $this->changeStatus = app(ChangeAssetStatus::class);
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function payload(array $overrides = []): array
    {
        $type = AssetType::where('code', 'SEWING')->firstOrFail();
        $category = AssetCategory::where('code', 'LOCKSTITCH')->firstOrFail();

        return array_merge([
            'asset_type_id' => $type->id,
            'asset_category_id' => $category->id,
            'asset_code' => 'SEW-DHK-00412',
            'name' => 'Juki DDL-9000C',
            'criticality' => 'MEDIUM',
            'current_factory_id' => $this->dhaka->id,
            'asset_location_id' => $this->line3->id,
        ], $overrides);
    }

    public function test_an_asset_is_created_with_an_opening_history_row(): void
    {
        $asset = $this->create->handle($this->payload());

        $this->assertSame('DRAFT', $asset->status);
        $this->assertSame($this->delta->id, $asset->company_id);
        $this->assertSame(1, $asset->version);

        // No gap between creation and the first transition.
        $history = AssetStatusHistory::where('asset_id', $asset->id)->get();
        $this->assertCount(1, $history);
        $this->assertNull($history->first()->from_status);
        $this->assertSame('DRAFT', $history->first()->to_status);
    }

    public function test_the_qr_token_is_server_generated_and_opaque(): void
    {
        $asset = $this->create->handle($this->payload(['qr_code' => 'CLIENTCHOICE']));

        $this->assertNotSame('CLIENTCHOICE', $asset->qr_code);
        $this->assertSame(12, strlen($asset->qr_code));

        // Crockford-style alphabet: no I, L, O or U, because those are the
        // characters misread off an oily label.
        $this->assertMatchesRegularExpression('/^[0-9ABCDEFGHJKMNPQRSTVWXYZ]{12}$/', $asset->qr_code);
    }

    public function test_qr_tokens_are_not_sequential(): void
    {
        $tokens = [];

        for ($i = 1; $i <= 5; $i++) {
            $tokens[] = $this->create->handle($this->payload(['asset_code' => "SEW-{$i}"]))->qr_code;
        }

        // A sequential code would let anyone photograph one label and
        // enumerate the whole fleet (Data Dictionary 5.1).
        $this->assertSame($tokens, array_unique($tokens));
        $sorted = $tokens;
        sort($sorted);
        $this->assertNotSame($sorted, $tokens, 'Tokens appear to be ordered, which suggests they are predictable.');
    }

    public function test_a_duplicate_asset_code_is_rejected_within_a_company(): void
    {
        $this->create->handle($this->payload());

        $this->expectException(QueryException::class);
        $this->create->handle($this->payload());
    }

    public function test_the_same_asset_code_is_allowed_in_a_different_company(): void
    {
        $this->create->handle($this->payload());

        $omega = TenantFixture::company('Omega Textiles Ltd', 'OTL');
        $narayanganj = TenantFixture::factory($omega, 'Narayanganj', 'NGJ');
        TenantFixture::actingAsTenant($omega);

        $location = AssetLocation::create([
            'factory_id' => $narayanganj->id, 'name' => 'Line 1', 'code' => 'NGJ-L1',
        ]);

        $asset = app(CreateAsset::class)->handle($this->payload([
            'current_factory_id' => $narayanganj->id,
            'asset_location_id' => $location->id,
        ]));

        $this->assertSame('SEW-DHK-00412', $asset->asset_code);
        $this->assertSame($omega->id, $asset->company_id);
    }

    public function test_a_location_from_another_factory_is_rejected(): void
    {
        // ERD 32 rule 23: a Dhaka asset cannot stand at a Gazipur workstation.
        $this->expectException(ValidationException::class);

        $this->create->handle($this->payload([
            'asset_location_id' => $this->gazipurLine->id,
        ]));
    }

    public function test_a_category_from_a_different_type_is_rejected(): void
    {
        $boiler = AssetCategory::where('code', 'BOILER')->firstOrFail();

        $this->expectException(ValidationException::class);

        $this->create->handle($this->payload(['asset_category_id' => $boiler->id]));
    }

    public function test_dates_must_be_in_order(): void
    {
        $this->expectException(ValidationException::class);

        $this->create->handle($this->payload([
            'purchase_date' => '2026-05-01',
            'installation_date' => '2026-04-01',
        ]));
    }

    public function test_an_asset_cannot_be_created_in_a_running_state(): void
    {
        // Later states are reached through an audited transition, never by
        // asserting them at creation.
        $this->expectException(ValidationException::class);

        $this->create->handle($this->payload(['status' => 'RUNNING']));
    }

    public function test_the_status_machine_allows_the_commissioning_path(): void
    {
        $asset = $this->create->handle($this->payload());

        foreach (['PURCHASED', 'INSTALLED', 'COMMISSIONED', 'RUNNING'] as $status) {
            $asset = $this->changeStatus->handle($asset, $status);
        }

        $this->assertSame('RUNNING', $asset->status);
        $this->assertSame(5, $asset->version);
        $this->assertCount(5, AssetStatusHistory::where('asset_id', $asset->id)->get());
    }

    public function test_an_illegal_transition_is_refused(): void
    {
        $asset = $this->create->handle($this->payload());

        // DRAFT straight to RUNNING skips purchase, installation and
        // commissioning, so every lifecycle date would be missing.
        $this->expectException(ValidationException::class);
        $this->changeStatus->handle($asset, 'RUNNING');
    }

    public function test_a_scrapped_asset_is_terminal(): void
    {
        $asset = $this->createRunningAsset();
        $asset = $this->changeStatus->handle($asset, 'BREAKDOWN');
        $asset = $this->changeStatus->handle($asset, 'UNDER_REPAIR');
        $asset = $this->changeStatus->handle($asset, 'SCRAPPED', reason: 'Beyond economic repair');

        $this->assertTrue($asset->isTerminal());
        $this->assertNotNull($asset->scrapped_at);

        $this->expectException(ValidationException::class);
        $this->changeStatus->handle($asset, 'RUNNING');
    }

    public function test_retiring_an_asset_requires_a_reason(): void
    {
        $asset = $this->createRunningAsset();

        $this->expectException(ValidationException::class);
        $this->changeStatus->handle($asset, 'RETIRED');
    }

    public function test_recommissioning_a_retired_asset_requires_elevation(): void
    {
        $asset = $this->createRunningAsset();
        $asset = $this->changeStatus->handle($asset, 'RETIRED', reason: 'End of life');

        try {
            $this->changeStatus->handle($asset, 'RUNNING');
            $this->fail('Recommissioning should require elevated permission.');
        } catch (ValidationException $e) {
            $this->assertSame(403, $e->status);
        }

        $asset = $this->changeStatus->handle($asset, 'RUNNING', isElevated: true);
        $this->assertSame('RUNNING', $asset->status);
    }

    public function test_a_system_driven_transition_bypasses_the_elevation_check(): void
    {
        $asset = $this->createRunningAsset();
        $asset = $this->changeStatus->handle($asset, 'RETIRED', reason: 'End of life');

        // A breakdown or work order enforces its own rules; the status change
        // it drives must not be blocked a second time here.
        $asset = $this->changeStatus->handle($asset, 'RUNNING', source: 'WORK_ORDER');

        $this->assertSame('RUNNING', $asset->status);
        $this->assertSame(
            'WORK_ORDER',
            AssetStatusHistory::where('asset_id', $asset->id)->orderByDesc('changed_at')->first()->source,
        );
    }

    public function test_repeating_the_current_status_is_a_no_op(): void
    {
        $asset = $this->create->handle($this->payload());
        $before = $asset->version;

        $asset = $this->changeStatus->handle($asset, 'DRAFT');

        $this->assertSame($before, $asset->version);
        $this->assertCount(1, AssetStatusHistory::where('asset_id', $asset->id)->get());
    }

    public function test_money_is_a_decimal_string_not_a_float(): void
    {
        $asset = $this->create->handle($this->payload([
            'acquisition_cost' => '285000.5',
            'currency' => 'BDT',
        ]));

        // IEEE 754 cannot represent every decimal exactly, and a cost that
        // drifts across a year of aggregation is not defensible.
        $this->assertSame('285000.5000', $asset->fresh()->acquisition_cost);
        $this->assertIsString($asset->fresh()->acquisition_cost);
    }

    private function createRunningAsset(): Asset
    {
        $asset = $this->create->handle($this->payload());

        foreach (['PURCHASED', 'INSTALLED', 'COMMISSIONED', 'RUNNING'] as $status) {
            $asset = $this->changeStatus->handle($asset, $status);
        }

        return $asset;
    }
}
