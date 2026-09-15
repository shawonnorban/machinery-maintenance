<?php

declare(strict_types=1);

namespace Tests\Feature\Inventory;

use App\Modules\Api\Actions\IssueApiToken;
use App\Modules\Identity\Models\User;
use App\Modules\Inventory\Actions\ReceiveStock;
use App\Modules\Inventory\Models\Bin;
use App\Modules\Inventory\Models\SparePart;
use App\Modules\Tenancy\Models\Company;
use App\Modules\Tenancy\Models\Factory;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\InventoryFixture;
use Tests\Support\TenantFixture;
use Tests\TestCase;

/**
 * Stock moving between factories, over the API (API 14, SRS 21).
 *
 * The interesting property under test is that stock is never in two places
 * and never nowhere: it leaves the source bin at dispatch, sits in an
 * in-transit bin, and only reaches the destination at receipt.
 */
class InventoryTransferApiTest extends TestCase
{
    use RefreshDatabase;

    private Company $delta;

    private Factory $dhaka;

    private Factory $narayanganj;

    private SparePart $part;

    private Bin $sourceBin;

    private Bin $destinationBin;

    private User $manager;

    private User $storekeeper;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(DatabaseSeeder::class);

        $this->delta = TenantFixture::company('Delta Apparels Ltd', 'DAL');
        $this->dhaka = TenantFixture::factory($this->delta, 'Dhaka Unit 1', 'DHK');
        $this->narayanganj = TenantFixture::factory($this->delta, 'Narayanganj Unit', 'NGJ');
        TenantFixture::actingAsTenant($this->delta);

        $this->manager = TenantFixture::user($this->delta, 'STORE_MANAGER', 'sm@delta.test');
        $this->storekeeper = TenantFixture::user($this->delta, 'STOREKEEPER', 'sk@delta.test');

        $this->part = InventoryFixture::part($this->delta);
        $this->sourceBin = InventoryFixture::bin($this->delta, $this->dhaka, 'DHK-A1');
        $this->destinationBin = InventoryFixture::bin($this->delta, $this->narayanganj, 'NGJ-A1');

        app(ReceiveStock::class)->handle($this->part, $this->sourceBin, '20', '150.00', $this->manager->id);
    }

    /**
     * STORE_MANAGER holds `inventory.transfer.create` but not `settings.
     * factory.manage` (`RoleSeeder`'s `$storeManager` never merges from
     * `$factoryManager`) — the "request a transfer" form's factory and bin
     * dropdowns have to be their own lookup, same reasoning as Teams'
     * `formOptions()`. Bins carry `factory_id` here so the from-bin picker
     * can be narrowed once a from-factory is chosen.
     */
    public function test_a_store_manager_can_reach_form_options_without_factory_management_permission(): void
    {
        $this->assertFalse($this->manager->can('settings.factory.manage'));
        $this->assertTrue($this->manager->can('inventory.transfer.create'));

        $response = $this->withToken($this->tokenFor($this->manager))
            ->getJson('/api/v1/inventory-transfers/form-options')
            ->assertOk()
            ->assertJsonStructure(['data' => ['factories', 'bins']]);

        $bin = collect($response->json('data.bins'))->firstWhere('id', $this->sourceBin->id);
        $this->assertSame($this->dhaka->id, $bin['factory_id']);
    }

    public function test_a_transfer_moves_stock_from_source_to_destination_through_transit(): void
    {
        $created = $this->withToken($this->tokenFor($this->manager))
            ->postJson('/api/v1/inventory-transfers', [
                'from_factory_id' => $this->dhaka->id,
                'to_factory_id' => $this->narayanganj->id,
                'items' => [[
                    'spare_part_id' => $this->part->id,
                    'from_bin_id' => $this->sourceBin->id,
                    'quantity' => 5,
                ]],
            ])
            ->assertCreated()
            ->assertJsonPath('data.status', 'REQUESTED');

        $transferId = $created->json('data.id');

        $this->withToken($this->tokenFor($this->manager))
            ->postJson("/api/v1/inventory-transfers/{$transferId}/approve")
            ->assertOk()
            ->assertJsonPath('data.status', 'APPROVED');

        $this->withToken($this->tokenFor($this->manager))
            ->postJson("/api/v1/inventory-transfers/{$transferId}/dispatch")
            ->assertOk()
            ->assertJsonPath('data.status', 'IN_TRANSIT');

        // Gone from the source, not yet at the destination.
        $this->assertSame('15.0000', $this->sourceBin->fresh()->balances()->first()?->quantity_on_hand ?? '0.0000');

        $itemId = $created->json('data.items.0.id');

        $this->withToken($this->tokenFor($this->storekeeper))
            ->postJson("/api/v1/inventory-transfers/{$transferId}/receive", [
                'bins' => [$itemId => $this->destinationBin->id],
            ])
            ->assertOk()
            ->assertJsonPath('data.status', 'RECEIVED')
            ->assertJsonPath('data.items.0.quantity_received', '5.0000');
    }

    /**
     * The web decides which action buttons to render via `@can(...)`
     * against the same side/status combination `assertSendingSide()`/
     * `assertReceivingSide()` enforce on write — a client needs the same
     * answer, so it travels as `can_*` flags on the detail response.
     */
    public function test_the_detail_response_says_which_actions_the_caller_can_take(): void
    {
        $transferId = $this->withToken($this->tokenFor($this->manager))
            ->postJson('/api/v1/inventory-transfers', [
                'from_factory_id' => $this->dhaka->id,
                'to_factory_id' => $this->narayanganj->id,
                'items' => [[
                    'spare_part_id' => $this->part->id,
                    'from_bin_id' => $this->sourceBin->id,
                    'quantity' => 5,
                ]],
            ])
            ->json('data.id');

        // STORE_MANAGER sits on the sending side (Dhaka) and can approve a
        // freshly-requested transfer, but not dispatch or receive it yet.
        $this->withToken($this->tokenFor($this->manager))
            ->getJson("/api/v1/inventory-transfers/{$transferId}")
            ->assertOk()
            ->assertJsonPath('data.can_approve', true)
            ->assertJsonPath('data.can_dispatch', false)
            ->assertJsonPath('data.can_receive', false);

        $this->withToken($this->tokenFor($this->manager))
            ->postJson("/api/v1/inventory-transfers/{$transferId}/approve")
            ->assertOk();

        // Dispatch only opens up once approved.
        $this->withToken($this->tokenFor($this->manager))
            ->getJson("/api/v1/inventory-transfers/{$transferId}")
            ->assertOk()
            ->assertJsonPath('data.can_approve', false)
            ->assertJsonPath('data.can_dispatch', true);
    }

    public function test_a_receiving_side_storekeeper_cannot_approve_or_dispatch(): void
    {
        $transferId = $this->withToken($this->tokenFor($this->manager))
            ->postJson('/api/v1/inventory-transfers', [
                'from_factory_id' => $this->dhaka->id,
                'to_factory_id' => $this->narayanganj->id,
                'items' => [[
                    'spare_part_id' => $this->part->id,
                    'from_bin_id' => $this->sourceBin->id,
                    'quantity' => 5,
                ]],
            ])
            ->json('data.id');

        // The storekeeper role holds inventory.transfer.receive only, not
        // .approve or .dispatch (RoleSeeder's matrix).
        $this->withToken($this->tokenFor($this->storekeeper))
            ->postJson("/api/v1/inventory-transfers/{$transferId}/approve")
            ->assertForbidden();
    }

    public function test_a_transfer_cannot_be_dispatched_before_approval(): void
    {
        $transferId = $this->withToken($this->tokenFor($this->manager))
            ->postJson('/api/v1/inventory-transfers', [
                'from_factory_id' => $this->dhaka->id,
                'to_factory_id' => $this->narayanganj->id,
                'items' => [[
                    'spare_part_id' => $this->part->id,
                    'from_bin_id' => $this->sourceBin->id,
                    'quantity' => 5,
                ]],
            ])
            ->json('data.id');

        $this->withToken($this->tokenFor($this->manager))
            ->postJson("/api/v1/inventory-transfers/{$transferId}/dispatch")
            ->assertStatus(409);
    }

    public function test_a_transfer_to_the_same_factory_is_refused(): void
    {
        $this->withToken($this->tokenFor($this->manager))
            ->postJson('/api/v1/inventory-transfers', [
                'from_factory_id' => $this->dhaka->id,
                'to_factory_id' => $this->dhaka->id,
                'items' => [[
                    'spare_part_id' => $this->part->id,
                    'from_bin_id' => $this->sourceBin->id,
                    'quantity' => 5,
                ]],
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors('to_factory_id');
    }

    private function tokenFor(User $user): string
    {
        $companyId = $user->memberships()->latest()->value('company_id');

        ['plain' => $plain] = app(IssueApiToken::class)->forUser($user, $companyId, 'Test token');

        return $plain;
    }
}
