<?php

declare(strict_types=1);

namespace Tests\Feature\Requisition;

use App\Modules\Api\Actions\IssueApiToken;
use App\Modules\Identity\Models\User;
use App\Modules\ItemMaster\Models\Item;
use App\Modules\ItemMaster\Models\ItemDepartment;
use App\Modules\Notification\Models\Notification;
use App\Modules\Tenancy\Models\Company;
use App\Modules\Tenancy\Models\Factory;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\ItemFixture;
use Tests\Support\TenantFixture;
use Tests\TestCase;

/**
 * Floor requisitions over the API (Floor/Store Requisition spec §3, Phase 1).
 *
 * The form, and the two rules the spec is explicit about: the item
 * department dropdown shows every department whoever is asking, and every
 * line must come from the department the requisition targets.
 */
class FloorRequisitionApiTest extends TestCase
{
    use RefreshDatabase;

    private Company $delta;

    private Factory $dhaka;

    private User $technician;

    private User $storekeeper;

    private ItemDepartment $general;

    private ItemDepartment $chemical;

    private Item $bolt;

    private Item $dye;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(DatabaseSeeder::class);

        $this->delta = TenantFixture::company('Delta Apparels Ltd', 'DAL');
        $this->dhaka = TenantFixture::factory($this->delta, 'Dhaka Unit 1', 'DHK');
        TenantFixture::actingAsTenant($this->delta);

        $this->technician = TenantFixture::user($this->delta, 'TECHNICIAN', 'tech@delta.test');
        $this->storekeeper = TenantFixture::user($this->delta, 'STOREKEEPER', 'sk@delta.test');

        $this->general = ItemFixture::department($this->delta);
        $this->chemical = ItemFixture::department($this->delta, 'Chemical Store', 'CHEM', 'CHEMICAL');

        $this->bolt = ItemFixture::item($this->delta);
        $this->dye = ItemFixture::item($this->delta, 'CHM-DYE-BLK', 'Reactive black dye', [
            'item_department_id' => $this->chemical->id, 'item_type' => 'CHEMICAL',
        ]);
    }

    public function test_a_technician_raises_one_from_any_item_department_and_the_store_is_told(): void
    {
        $token = $this->tokenFor($this->technician);

        // Every department, not the technician's own.
        $this->withToken($token)->getJson('/api/v1/floor-requisitions/form-options')
            ->assertOk()
            ->assertJsonCount(2, 'data.item_departments');

        // A line from the wrong department is refused.
        $this->withToken($token)->postJson('/api/v1/floor-requisitions', [
            'factory_id' => $this->dhaka->id,
            'target_item_department_id' => $this->chemical->id,
            'lines' => [['item_id' => $this->bolt->id, 'requested_qty' => '5']],
        ])->assertStatus(422)->assertJsonValidationErrors(['lines.0.item_id']);

        $created = $this->withToken($token)->postJson('/api/v1/floor-requisitions', [
            'factory_id' => $this->dhaka->id,
            'target_item_department_id' => $this->chemical->id,
            'priority' => 'URGENT',
            'purpose' => 'Dyeing lot 12',
            'lines' => [['item_id' => $this->dye->id, 'requested_qty' => '2.5', 'purpose' => 'Shade matching']],
        ])->assertCreated()
            ->assertJsonPath('data.status', 'DRAFT')
            ->assertJsonPath('data.target_item_department.name', 'Chemical Store')
            ->assertJsonPath('data.lines.0.item_code', 'CHM-DYE-BLK')
            ->assertJsonPath('data.can_submit', true)
            ->json('data');

        $this->assertMatchesRegularExpression('/^FR-/', $created['fr_no']);

        $this->withToken($token)->postJson("/api/v1/floor-requisitions/{$created['id']}/submit")
            ->assertOk()->assertJsonPath('data.status', 'SUBMITTED')->assertJsonPath('data.can_edit', false);

        $this->assertSame(1, Notification::where('event_type', 'FLOOR_REQUISITION_SUBMITTED')
            ->where('user_id', $this->storekeeper->id)->count());
    }

    public function test_a_requester_sees_only_their_own_and_the_store_sees_everything(): void
    {
        $tech = $this->tokenFor($this->technician);
        $store = $this->tokenFor($this->storekeeper);

        $mine = $this->withToken($tech)->postJson('/api/v1/floor-requisitions', [
            'factory_id' => $this->dhaka->id, 'target_item_department_id' => $this->general->id,
            'lines' => [['item_id' => $this->bolt->id, 'requested_qty' => '4']],
        ])->json('data.id');
        $theirs = $this->withToken($store)->postJson('/api/v1/floor-requisitions', [
            'factory_id' => $this->dhaka->id, 'target_item_department_id' => $this->general->id,
            'lines' => [['item_id' => $this->bolt->id, 'requested_qty' => '1']],
        ])->json('data.id');

        $this->withToken($tech)->getJson('/api/v1/floor-requisitions')->assertOk()->assertJsonPath('meta.total', 1)->assertJsonPath('data.0.id', $mine);
        $this->withToken($tech)->getJson("/api/v1/floor-requisitions/{$theirs}")->assertNotFound();
        $this->withToken($tech)->postJson("/api/v1/floor-requisitions/{$theirs}/submit")->assertNotFound();

        $this->withToken($store)->getJson('/api/v1/floor-requisitions')->assertOk()->assertJsonPath('meta.total', 2);
        $this->withToken($store)->getJson('/api/v1/floor-requisitions?mine=1')->assertJsonPath('meta.total', 1);

        // Retired while in draft: the submit names it.
        $this->bolt->forceFill(['active' => false])->save();
        $this->withToken($tech)->postJson("/api/v1/floor-requisitions/{$mine}/submit")->assertStatus(409);

        $this->withToken($tech)->postJson("/api/v1/floor-requisitions/{$mine}/cancel", ['reason' => 'Not needed'])
            ->assertOk()->assertJsonPath('data.status', 'CANCELLED');
    }

    private function tokenFor(User $user): string
    {
        $companyId = $user->memberships()->latest()->value('company_id');

        ['plain' => $plain] = app(IssueApiToken::class)->forUser($user, $companyId, 'Test token');

        return $plain;
    }
}
