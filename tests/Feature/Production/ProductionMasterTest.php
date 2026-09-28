<?php

declare(strict_types=1);

namespace Tests\Feature\Production;

use App\Modules\Api\Actions\IssueApiToken;
use App\Modules\Identity\Models\User;
use App\Modules\ItemMaster\Models\Item;
use App\Modules\Production\Models\CuttingProgram;
use App\Modules\Production\Models\StyleBomLine;
use App\Modules\Tenancy\Models\Company;
use App\Modules\Tenancy\Models\Factory;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\ItemFixture;
use Tests\Support\TenantFixture;
use Tests\TestCase;

/**
 * The minimal production master (Master Data spec §6; Phase 3B).
 *
 * Built here only because the factory has no merchandising system to read
 * it from. These tests pin the three things the rest of Phase 3B leans on:
 * a style's bill of materials is a single version at any moment, a cutting
 * programme cannot be for more than the style has left, and the store can
 * read the plan without being able to change it.
 */
class ProductionMasterTest extends TestCase
{
    use RefreshDatabase;

    private Company $delta;

    private Factory $dhaka;

    private User $planner;

    private User $storekeeper;

    private Item $fabric;

    private Item $button;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(DatabaseSeeder::class);

        $this->delta = TenantFixture::company('Delta Apparels Ltd', 'DAL');
        $this->dhaka = TenantFixture::factory($this->delta, 'Dhaka Unit 1', 'DHK');
        TenantFixture::actingAsTenant($this->delta);

        $this->planner = TenantFixture::user($this->delta, 'FACTORY_MANAGER', 'fm@delta.test');
        $this->storekeeper = TenantFixture::user($this->delta, 'STOREKEEPER', 'sk@delta.test');

        $this->fabric = ItemFixture::item($this->delta, 'FAB-SJ-180', 'Single jersey 180', [
            'item_type' => 'FABRIC', 'uom_id' => ItemFixture::uomId($this->delta, 'MTR'),
        ]);
        $this->button = ItemFixture::item($this->delta, 'TRM-BTN-18L', 'Button 18L');
    }

    private function tokenFor(User $user): string
    {
        $companyId = $user->memberships()->latest()->value('company_id');

        ['plain' => $plain] = app(IssueApiToken::class)->forUser($user, $companyId, 'Test token');

        return $plain;
    }

    /**
     * @return array{order: array<string, mixed>, style_id: string}
     */
    private function orderWithStyle(string $token, string $quantity = '5000'): array
    {
        $order = $this->withToken($token)->postJson('/api/v1/production-orders', [
            'factory_id' => $this->dhaka->id,
            'order_no' => 'HM-4471-23',
            'buyer' => 'H&M',
            'order_qty' => $quantity,
            'ship_date' => '2026-12-01',
        ])->assertCreated()->json('data');

        $order = $this->withToken($token)->postJson("/api/v1/production-orders/{$order['id']}/styles", [
            'style_no' => 'ST-100',
            'description' => 'Crew neck tee',
            'color' => 'Navy',
            'quantity' => $quantity,
        ])->assertCreated()->json('data');

        return ['order' => $order, 'style_id' => $order['styles'][0]['id']];
    }

    public function test_an_order_carries_the_buyers_own_number_and_refuses_a_duplicate(): void
    {
        $token = $this->tokenFor($this->planner);

        ['order' => $order] = $this->orderWithStyle($token);

        $this->assertSame('HM-4471-23', $order['order_no']);
        $this->assertSame('CONFIRMED', $order['status']);
        $this->assertSame('ST-100', $order['styles'][0]['style_no']);

        $this->withToken($token)->postJson('/api/v1/production-orders', [
            'factory_id' => $this->dhaka->id,
            'order_no' => 'HM-4471-23',
            'buyer' => 'Somebody else',
            'order_qty' => '10',
        ])->assertStatus(422)->assertJsonValidationErrors(['order_no']);

        // The same style twice on one order is refused too.
        $this->withToken($token)->postJson("/api/v1/production-orders/{$order['id']}/styles", [
            'style_no' => 'ST-100', 'quantity' => '10',
        ])->assertStatus(422)->assertJsonValidationErrors(['style_no']);
    }

    public function test_a_bill_of_materials_is_replaced_wholesale_and_refuses_the_same_item_twice(): void
    {
        $token = $this->tokenFor($this->planner);
        ['order' => $order, 'style_id' => $styleId] = $this->orderWithStyle($token);

        $saved = $this->withToken($token)->putJson("/api/v1/production-styles/{$styleId}/bom", [
            'lines' => [
                ['item_id' => $this->fabric->id, 'consumption_per_unit' => '1.85', 'wastage_percent' => '8'],
                ['item_id' => $this->button->id, 'consumption_per_unit' => '6'],
            ],
        ])->assertOk()->json('data');

        $bom = $saved['styles'][0]['bom'];

        $this->assertCount(2, $bom);
        $this->assertSame('1.8500', $bom[0]['consumption_per_unit']);
        $this->assertSame('8.00', $bom[0]['wastage_percent']);
        // The snapshot every item line carries.
        $this->assertSame('FAB-SJ-180', $bom[0]['item_code']);

        // Wastage is part of the requirement, not a separate line:
        // 1.85 x 1000 = 1850, plus 8% = 1998.
        $line = StyleBomLine::where('item_id', $this->fabric->id)->firstOrFail();
        $this->assertSame('1998.0000', $line->requiredFor('1000'));

        // Saving again replaces the bill rather than adding to it.
        $again = $this->withToken($token)->putJson("/api/v1/production-styles/{$styleId}/bom", [
            'lines' => [['item_id' => $this->fabric->id, 'consumption_per_unit' => '1.90']],
        ])->assertOk()->json('data');

        $this->assertCount(1, $again['styles'][0]['bom']);
        $this->assertSame(1, StyleBomLine::where('style_id', $styleId)->count());

        $this->withToken($token)->putJson("/api/v1/production-styles/{$styleId}/bom", [
            'lines' => [
                ['item_id' => $this->button->id, 'consumption_per_unit' => '6'],
                ['item_id' => $this->button->id, 'consumption_per_unit' => '2'],
            ],
        ])->assertStatus(422)->assertJsonValidationErrors(['lines.1.item_id']);
    }

    public function test_cutting_programmes_cannot_add_up_to_more_than_the_style(): void
    {
        $token = $this->tokenFor($this->planner);
        ['style_id' => $styleId] = $this->orderWithStyle($token, '3000');

        $first = $this->withToken($token)->postJson("/api/v1/production-styles/{$styleId}/cutting-programs", [
            'program_qty' => '2000', 'cutting_date' => '2026-10-05',
        ])->assertCreated()->json('data');

        $this->assertStringStartsWith('CP-DHK-', $first['program_no']);
        $this->assertSame('CONFIRMED', $first['status']);

        // 2000 + 1500 > 3000.
        $this->withToken($token)->postJson("/api/v1/production-styles/{$styleId}/cutting-programs", [
            'program_qty' => '1500',
        ])->assertStatus(409);

        $this->withToken($token)->postJson("/api/v1/production-styles/{$styleId}/cutting-programs", [
            'program_qty' => '1000',
        ])->assertCreated();

        $this->assertSame(2, CuttingProgram::where('style_id', $styleId)->count());

        // A cancelled programme gives its quantity back.
        $this->withToken($token)->postJson("/api/v1/cutting-programs/{$first['id']}/status", [
            'status' => 'CANCELLED',
        ])->assertOk();

        $this->withToken($token)->postJson("/api/v1/production-styles/{$styleId}/cutting-programs", [
            'program_qty' => '2000',
        ])->assertCreated();
    }

    public function test_a_closed_order_cannot_be_changed(): void
    {
        $token = $this->tokenFor($this->planner);
        ['order' => $order, 'style_id' => $styleId] = $this->orderWithStyle($token);

        $this->withToken($token)->postJson("/api/v1/production-orders/{$order['id']}/status", [
            'status' => 'CLOSED',
        ])->assertOk()->assertJsonPath('data.status', 'CLOSED');

        $this->withToken($token)->patchJson("/api/v1/production-orders/{$order['id']}", [
            'buyer' => 'Someone else',
        ])->assertStatus(409);

        $this->withToken($token)->putJson("/api/v1/production-styles/{$styleId}/bom", [
            'lines' => [['item_id' => $this->button->id, 'consumption_per_unit' => '6']],
        ])->assertStatus(409);

        // And it cannot be closed twice.
        $this->withToken($token)->postJson("/api/v1/production-orders/{$order['id']}/status", [
            'status' => 'CLOSED',
        ])->assertStatus(409);
    }

    public function test_the_store_reads_the_plan_but_does_not_write_it(): void
    {
        $plannerToken = $this->tokenFor($this->planner);
        ['order' => $order, 'style_id' => $styleId] = $this->orderWithStyle($plannerToken);

        $storeToken = $this->tokenFor($this->storekeeper);

        // The store issues against a programme, so it has to see the plan.
        $this->withToken($storeToken)->getJson("/api/v1/production-orders/{$order['id']}")
            ->assertOk()
            ->assertJsonPath('data.order_no', 'HM-4471-23')
            ->assertJsonPath('data.can_manage', false);

        $this->withToken($storeToken)->getJson('/api/v1/cutting-programs')->assertOk();

        $this->withToken($storeToken)->postJson('/api/v1/production-orders', [
            'factory_id' => $this->dhaka->id,
            'order_no' => 'SK-1',
            'buyer' => 'Nobody',
            'order_qty' => '10',
        ])->assertForbidden();

        $this->withToken($storeToken)->putJson("/api/v1/production-styles/{$styleId}/bom", [
            'lines' => [['item_id' => $this->button->id, 'consumption_per_unit' => '6']],
        ])->assertForbidden();
    }
}
