<?php

declare(strict_types=1);

namespace Tests\Feature\Identity;

use App\Modules\Api\Actions\IssueApiToken;
use App\Modules\Identity\Models\User;
use App\Modules\Identity\Support\MappingRegistry;
use App\Modules\Inventory\Models\Store;
use App\Modules\ItemMaster\Models\ItemCategory;
use App\Modules\Merchandise\Models\Buyer;
use App\Modules\Merchandise\Models\TnaTask;
use App\Modules\Tenancy\Models\Company;
use App\Modules\Tenancy\Models\Factory;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\InventoryFixture;
use Tests\Support\TenantFixture;
use Tests\TestCase;

/**
 * Access Control → the buyer mappings (AnnotechERP 02/02) and TNA Task
 * Status Setup (02/04): a user's fabric rights for a buyer within a window,
 * a buyer's grey fabric transfer rules, defect points (1–4), the stores
 * holding a buyer's material (one default store per buyer), the item
 * categories a buyer nominates; and the TNA events with their raised
 * instances and the people notified.
 */
class AccessBuyerMappingTest extends TestCase
{
    use RefreshDatabase;

    private Company $delta;

    private Factory $dhaka;

    private User $owner;

    private User $keeper;

    private Buyer $lpp;

    private Buyer $roly;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(DatabaseSeeder::class);
        $this->delta = TenantFixture::company('Delta Apparels Ltd', 'DAL');
        $this->dhaka = TenantFixture::factory($this->delta, 'Dhaka Unit 1', 'DHK');
        TenantFixture::actingAsTenant($this->delta);
        $this->owner = TenantFixture::user($this->delta, 'COMPANY_OWNER', 'owner@delta.test');
        $this->keeper = TenantFixture::user($this->delta, 'STOREKEEPER', 'keeper@delta.test', $this->dhaka->id);
        $this->lpp = Buyer::query()->create(['company_id' => $this->delta->id, 'code' => 'LPP', 'name' => 'LPP', 'short_name' => 'LPP', 'pi_address' => 'Gdansk', 'registered_from' => '2026-01-01', 'active' => true]);
        $this->roly = Buyer::query()->create(['company_id' => $this->delta->id, 'code' => '110', 'name' => 'ROLY', 'short_name' => 'ROLY', 'pi_address' => 'Valencia', 'registered_from' => '2026-01-01', 'active' => true]);
    }

    public function test_fabric_rights_hold_within_their_window_and_grey_transfer_rules_are_per_buyer(): void
    {
        $options = $this->as()->getJson('/api/v1/access/mappings/user-buyer-fabric/options')->assertOk()->json('data');
        $this->assertSame(['issue', 'receive', 'transfer', 'return'], $options['flags']);
        $this->assertSame(['date_from'], $options['required']);
        $this->assertContains(['value' => $this->lpp->id, 'label' => 'LPP (LPP)'], $options['targets']);

        // As Brickland words it; the window must run forwards.
        $this->as()->postJson('/api/v1/access/mappings/user-buyer-fabric', [])->assertUnprocessable()
            ->assertJsonPath('errors.subject_id.0', 'User is required.')->assertJsonPath('errors.date_from.0', 'Effective From is required.');
        $row = ['subject_id' => $this->keeper->id, 'target_id' => $this->lpp->id, 'flag_issue' => true, 'flag_receive' => true, 'date_from' => now()->subDay()->toDateString()];
        $this->as()->postJson('/api/v1/access/mappings/user-buyer-fabric', ['date_to' => now()->subDays(5)->toDateString()] + $row)->assertUnprocessable()
            ->assertJsonPath('errors.date_to.0', 'Effective To cannot be before Effective From.');
        $this->as()->postJson('/api/v1/access/mappings/user-buyer-fabric', $row)->assertCreated()->assertJsonPath('data.date_from', now()->subDay()->toDateString());
        $this->as()->postJson('/api/v1/access/mappings/user-buyer-fabric', ['target_id' => $this->roly->id, 'date_from' => now()->addDays(3)->toDateString()] + $row)->assertCreated();

        $registry = app(MappingRegistry::class);
        TenantFixture::actingAsTenant($this->delta);
        $this->assertSame([$this->lpp->id], $registry->targetsFor('user-buyer-fabric', $this->keeper->id, 'issue'), 'ROLY starts in three days.');
        $this->assertSame([], $registry->targetsFor('user-buyer-fabric', $this->keeper->id, 'transfer'));

        // Grey fabric transfer rules: a buyer, from one booking category to another; an inactive rule is kept.
        $grey = $this->as()->getJson('/api/v1/access/mappings/buyer-grey-transfer/options')->json('data');
        $this->assertContains(['value' => 'FP01>FP02', 'label' => 'Sample Booking (FP01) → Bulk Booking (FP02)'], $grey['targets']);
        $this->assertSame('LPP', collect($grey['subjects'])->firstWhere('value', $this->lpp->id)['key']);
        $this->as()->putJson("/api/v1/access/mappings/buyer-grey-transfer/subjects/{$this->lpp->id}", ['rows' => [
            ['target_id' => 'FP01>FP02', 'selected' => true, 'flags' => ['active' => true]],
            ['target_id' => 'FP06>FP02', 'selected' => true, 'flags' => ['active' => false]],
            ['target_id' => 'FP02>FP01', 'selected' => false, 'flags' => ['active' => true]],
        ]])->assertOk();
        $rules = collect($this->as()->getJson('/api/v1/access/mappings/buyer-grey-transfer')->json('data'));
        $this->assertSame(['FP01>FP02' => true, 'FP06>FP02' => false], $rules->pluck('flag_active', 'target_id')->sortKeys()->all());
        $this->as()->putJson("/api/v1/access/mappings/buyer-grey-transfer/subjects/{$this->lpp->id}", ['rows' => [['target_id' => 'FP01>FP01', 'selected' => true]]])->assertUnprocessable();
        $this->as($this->keeper)->getJson('/api/v1/access/mappings/user-buyer-fabric')->assertForbidden();
    }

    public function test_defect_points_buyer_stores_and_nominated_categories(): void
    {
        $defects = $this->as()->getJson('/api/v1/access/mappings/buyer-defect/options')->assertOk()->json('data.targets');
        $this->assertCount(39, $defects);
        $this->assertSame(['value' => 'FD-21', 'label' => 'FD-21 — Needle Drop'], $defects[20]);

        // Penalty points: required, four-point system.
        $this->as()->postJson('/api/v1/access/mappings/buyer-defect', ['subject_id' => $this->lpp->id, 'target_id' => 'FD-21'])->assertUnprocessable()
            ->assertJsonPath('errors.amount.0', 'Penalty Points is required.');
        $this->as()->postJson('/api/v1/access/mappings/buyer-defect', ['subject_id' => $this->lpp->id, 'target_id' => 'FD-21', 'amount' => 5])->assertUnprocessable()
            ->assertJsonPath('errors.amount.0', 'Four-point system: 1 to 4 points per occurrence.');
        $this->as()->postJson('/api/v1/access/mappings/buyer-defect', ['subject_id' => $this->lpp->id, 'target_id' => 'FD-21', 'amount' => 4, 'flag_critical' => true, 'flag_active' => true, 'sort_order' => 2])->assertCreated();
        // The matrix: a ticked defect without points is refused, row by row.
        $this->as()->putJson("/api/v1/access/mappings/buyer-defect/subjects/{$this->lpp->id}", ['rows' => [
            ['target_id' => 'FD-21', 'selected' => true, 'amount' => 4, 'flags' => ['critical' => true, 'active' => true]],
            ['target_id' => 'FD-34', 'selected' => true, 'flags' => ['active' => true]],
        ]])->assertUnprocessable()->assertJsonValidationErrors(['rows.1.amount' => 'FD-34 — Slub: penalty points are required.']);
        $matrix = $this->as()->putJson("/api/v1/access/mappings/buyer-defect/subjects/{$this->lpp->id}", ['rows' => [
            ['target_id' => 'FD-21', 'selected' => true, 'amount' => 4, 'sort_order' => 1, 'flags' => ['critical' => true, 'active' => true]],
            ['target_id' => 'FD-34', 'selected' => true, 'amount' => 1, 'sort_order' => 2, 'flags' => ['active' => true]],
        ]])->assertOk()->json('data');
        $this->assertSame(['FD-21', 'FD-34'], collect($matrix)->where('selected', true)->pluck('target_id')->values()->all());

        // Buyer stores: one default store per buyer, across stores.
        $main = Store::findOrFail(InventoryFixture::bin($this->delta, $this->dhaka)->store_id);
        $trims = Store::create(['company_id' => $this->delta->id, 'warehouse_id' => $main->warehouse_id, 'name' => 'Trims store', 'code' => 'DHK-TRM', 'store_type' => 'GENERAL', 'active' => true]);
        $this->as()->putJson("/api/v1/access/mappings/store-buyer/subjects/{$main->id}", ['rows' => [
            ['target_id' => $this->lpp->id, 'selected' => true, 'is_default' => true], ['target_id' => $this->roly->id, 'selected' => true, 'is_default' => true],
        ]])->assertOk();
        $this->as()->postJson('/api/v1/access/mappings/store-buyer', ['subject_id' => $trims->id, 'target_id' => $this->lpp->id, 'is_default' => true])->assertCreated();
        $rows = collect($this->as()->getJson('/api/v1/access/mappings/store-buyer')->json('data'));
        $this->assertSame([[$main->code, 'LPP', false], [$main->code, 'ROLY', true], ['DHK-TRM', 'LPP', true]], $rows->map(fn ($r) => [
            str_contains($r['subject'], 'DHK-TRM') ? 'DHK-TRM' : $main->code, explode(' (', $r['target'])[0], $r['is_default'],
        ])->sortBy(fn ($r) => implode('|', [$r[0], $r[1]]))->values()->all());
        $this->assertSame($main->code, collect($this->as()->getJson('/api/v1/access/mappings/store-buyer/options')->json('data.subjects'))->firstWhere('value', $main->id)['key']);

        // Nominated categories: top-level ones, with a nomination code.
        $trimsCat = ItemCategory::query()->create(['company_id' => $this->delta->id, 'code' => 'TRIMS', 'name' => 'Trims & Accessories', 'active' => true]);
        ItemCategory::query()->create(['company_id' => $this->delta->id, 'code' => 'TRIMS-LBL', 'name' => 'Label', 'parent_id' => $trimsCat->id, 'active' => true]);
        $targets = collect($this->as()->getJson('/api/v1/access/mappings/buyer-item-category/options')->json('data.targets'))->pluck('label');
        $this->assertContains('Trims & Accessories', $targets);
        $this->assertNotContains('Label', $targets);
        $this->as()->postJson('/api/v1/access/mappings/buyer-item-category', ['subject_id' => $this->lpp->id, 'target_id' => $trimsCat->id, 'text' => ' HM-TRIM-2026 ', 'flag_sub_style' => true, 'flag_active' => true])
            ->assertCreated()->assertJsonPath('data.text', 'HM-TRIM-2026')->assertJsonPath('data.flag_sub_style', true);
        $this->as($this->keeper)->getJson('/api/v1/access/mappings/buyer-defect')->assertForbidden();
    }

    public function test_tna_events_their_instances_and_who_is_notified(): void
    {
        $order = TnaTask::query()->create(['company_id' => $this->delta->id, 'code' => 'MER-ORDCFM', 'name' => 'Rcv Order Confirm', 'sort_order' => 1, 'offset_days' => -90, 'active' => true]);
        $options = $this->as()->getJson('/api/v1/access/tna-events/options')->assertOk()->json('data');
        $this->assertSame(['PENDING', 'IN_PROGRESS', 'COMPLETED', 'DELAYED', 'CANCELLED'], $options['statuses']);
        $this->assertContains(['value' => $order->id, 'label' => 'Rcv Order Confirm (MER-ORDCFM)'], $options['parents']);

        // New TNA event, as Brickland words it.
        $this->as()->postJson('/api/v1/access/tna-events', [])->assertUnprocessable()
            ->assertJsonPath('errors.code.0', 'Event Code is required.')->assertJsonPath('errors.name.0', 'Event Name is required.')->assertJsonPath('errors.sort_order.0', 'Status Order is required.');
        $this->as()->postJson('/api/v1/access/tna-events', ['code' => 'mer-ordcfm', 'name' => 'Again', 'sort_order' => 2])->assertUnprocessable()
            ->assertJsonPath('errors.code.0', 'This event code is already in use.');
        $event = $this->as()->postJson('/api/v1/access/tna-events', [
            'code' => 'tna-030', 'name' => 'Lab Dip Approval', 'task_message' => 'Submit lab dips and secure buyer approval.', 'sort_order' => 3, 'parent_id' => $order->id,
            'assign_to' => $this->keeper->id, 'default_status' => 'IN_PROGRESS', 'offset_days' => -95, 'is_repeat' => true, 'is_buyer_feedback' => true,
        ])->assertCreated()->json('data');
        $this->assertSame('TNA-030', $event['code']);
        $this->as()->patchJson("/api/v1/access/tna-events/{$event['id']}", ['code' => 'TNA-030', 'name' => 'Lab Dip Approval', 'sort_order' => 3, 'parent_id' => $event['id']])->assertUnprocessable()
            ->assertJsonPath('errors.parent_id.0', 'An event cannot be its own parent.');

        // Raised instances: a new one starts in the event's status; completing one stamps today.
        $this->as()->putJson("/api/v1/access/tna-events/{$event['id']}/instances", ['rows' => [['reference' => '']]])->assertUnprocessable()
            ->assertJsonValidationErrors(['rows.0.reference' => 'Style / Reference is required.']);
        $saved = $this->as()->putJson("/api/v1/access/tna-events/{$event['id']}/instances", ['rows' => [
            ['reference' => 'STY-4471', 'owner_id' => $this->keeper->id, 'plan_date' => '2026-11-10'],
        ]])->assertOk()->json('data');
        $this->assertSame(['STY-4471', 'IN_PROGRESS', null], [$saved[0]['reference'], $saved[0]['status'], $saved[0]['actual_date']]);
        $done = $this->as()->putJson("/api/v1/access/tna-events/{$event['id']}/instances", ['rows' => [
            ['id' => $saved[0]['id'], 'reference' => 'STY-4471', 'owner_id' => $this->keeper->id, 'plan_date' => '2026-11-10', 'status' => 'COMPLETED'],
        ]])->assertOk()->json('data');
        $this->assertSame(['COMPLETED', now()->toDateString()], [$done[0]['status'], $done[0]['actual_date']]);

        // Mapped User List.
        $this->as()->putJson("/api/v1/access/tna-events/{$event['id']}/users", ['users' => [$this->keeper->id]])->assertOk()
            ->assertJsonFragment(['user_id' => $this->keeper->id, 'selected' => true])->assertJsonFragment(['user_id' => $this->owner->id, 'selected' => false]);

        // Records: in status order, with parent, owner, repeat / buyer feedback and how often raised.
        $records = collect($this->as()->getJson('/api/v1/access/tna-events')->json('data'));
        $lab = $records->firstWhere('code', 'TNA-030');
        $this->assertSame(['Rcv Order Confirm', 1, true, true], [$lab['parent'], $lab['raised'], $lab['is_repeat'], $lab['is_buyer_feedback']]);
        $this->assertSame(['MER-ORDCFM', 'TNA-030'], $records->pluck('code')->all());
        $this->as($this->keeper)->postJson('/api/v1/access/tna-events', ['code' => 'X', 'name' => 'X', 'sort_order' => 1])->assertForbidden();
    }

    private function as(?User $user = null): static
    {
        ['plain' => $plain] = app(IssueApiToken::class)->forUser($user ?? $this->owner, $this->delta->id, 'Test token');

        return $this->withToken($plain);
    }
}
