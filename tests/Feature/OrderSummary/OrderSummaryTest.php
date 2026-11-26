<?php

declare(strict_types=1);

namespace Tests\Feature\OrderSummary;

use App\Modules\Api\Actions\IssueApiToken;
use App\Modules\Identity\Models\MailService;
use App\Modules\Identity\Models\User;
use App\Modules\Merchandise\Models\Buyer;
use App\Modules\Merchandise\Models\BuyerUserPermission;
use App\Modules\Notification\Models\Notification;
use App\Modules\OrderSummary\Models\OsChange;
use App\Modules\OrderSummary\Models\OsColumnEvent;
use App\Modules\OrderSummary\Models\OsMailSetting;
use App\Modules\OrderSummary\Models\OsOrder;
use App\Modules\OrderSummary\Support\MailDigest;
use App\Modules\OrderSummary\Support\OrderEditDigestMail;
use App\Modules\OrderSummary\Support\Sheet;
use App\Modules\PreBudget\Support\MerchLists;
use App\Modules\Tenancy\Models\Company;
use App\Shared\Scopes\TenantScope;
use Carbon\CarbonImmutable;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use App\Shared\JobNo\JobNumber;
use Tests\Support\TenantFixture;
use Tests\TestCase;

/**
 * Order Summary (AnnotechERP 32, from Norban): the configurable sheet —
 * columns and formulas as data, live figures and the day-dependent ones,
 * saving changed rows with a version, Column Setup working every row out
 * again, the team scope, Bulk Update / Copy / Delete, the header filter, the
 * scheduled edit mail and the reports.
 */
class OrderSummaryTest extends TestCase
{
    use RefreshDatabase;

    private Company $delta;

    private User $owner;

    private User $merchA;

    private User $merchB;

    private User $teamHead;

    private User $deptHead;

    private string $hm;

    private string $next;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
        $this->delta = TenantFixture::company('Delta Apparels Ltd', 'DAL');
        TenantFixture::actingAsTenant($this->delta);
        $this->owner = TenantFixture::user($this->delta, 'COMPANY_OWNER', 'owner@delta.test');
        // Merchandisers and heads: work the sheet, see only their buyers.
        Role::query()->whereNull('company_id')->where('name', 'VIEWER')->firstOrFail()->givePermissionTo(['ordersummary.view', 'ordersummary.create', 'ordersummary.update', 'ordersummary.report']);
        app(PermissionRegistrar::class)->forgetCachedPermissions();
        $this->merchA = TenantFixture::user($this->delta, 'VIEWER', 'karim@delta.test');
        $this->merchB = TenantFixture::user($this->delta, 'VIEWER', 'rahim@delta.test');
        $this->teamHead = TenantFixture::user($this->delta, 'VIEWER', 'teamhead@delta.test');
        $this->deptHead = TenantFixture::user($this->delta, 'VIEWER', 'depthead@delta.test');
        $this->hm = Buyer::query()->create(['company_id' => $this->delta->id, 'code' => 'HM', 'name' => 'H&M Hennes & Mauritz', 'short_name' => 'H&M', 'pi_address' => 'EU', 'registered_from' => '2026-01-01', 'active' => true])->id;
        $this->next = Buyer::query()->create(['company_id' => $this->delta->id, 'code' => 'NXT', 'name' => 'Next Retail', 'short_name' => 'Next', 'pi_address' => 'UK', 'registered_from' => '2026-01-01', 'active' => true])->id;
        // The masters the sample rows pick from.
        $c = $this->delta->id;
        DB::table('item_types')->insert(['id' => (string) Str::ulid(), 'company_id' => $c, 'item_group' => 'KNIT_TOP', 'code' => 'TS', 'name' => 'T-Shirt', 'sort_order' => 1, 'active' => true, 'created_at' => now(), 'updated_at' => now()]);
        DB::table('colours')->insert(['id' => (string) Str::ulid(), 'company_id' => $c, 'colour_type' => 'SOLID', 'name' => 'Navy', 'active' => true, 'created_at' => now(), 'updated_at' => now()]);
        DB::table('fabric_types')->insert(['id' => (string) Str::ulid(), 'company_id' => $c, 'code' => 'SJ', 'name' => 'Single Jersey', 'short_name' => 'S/J', 'construction' => 'KNIT', 'sort_order' => 1, 'active' => true, 'created_at' => now(), 'updated_at' => now()]);
        // Order Type, Item, Wash, Fabric Type… come from the requisition sheet's lists (one list for both sheets).
        foreach (['ORDER_TYPE' => ['Confirmed PO', 'DROPPED'], 'ITEM' => ['T-Shirt', 'Polo'], 'WASH_TYPE' => ['No', 'Garments Wash'], 'FABRIC_TYPE' => ['Single Jersey', 'Woven']] as $list => $names) {
            foreach ($names as $i => $name) {
                DB::table('pb_list_values')->insert(['id' => (string) Str::ulid(), 'company_id' => $c, 'list' => $list, 'name' => $name, 'sort_order' => $i, 'active' => true, 'created_at' => now(), 'updated_at' => now()]);
            }
        }
    }

    public function test_the_sheet_starts_with_norbans_columns_and_works_formulas_out(): void
    {
        $meta = $this->api($this->owner)->getJson('/api/v1/order-summary/meta')->assertOk()->json('data');
        $this->assertCount(161, $meta['columns']); // with Budget Status (worked out from Pre-Budget)
        $this->assertCount(18, $meta['groups']);
        $fob = collect($meta['columns'])->firstWhere('key', 'fob_value');
        // FOB is per piece, as the requisition sheet has it (user, 2026-11-24).
        $this->assertSame('{fob} * {quantity_pcs}', $fob['formula']);
        $this->assertSame('view', $fob['access']);
        $this->assertTrue(collect($meta['columns'])->firstWhere('key', 'days_left_in_hand')['volatile']);

        $today = Sheet::today();
        $ldd = CarbonImmutable::parse($today)->addDays(20)->toDateString();
        $row = $this->api($this->owner)->postJson('/api/v1/order-summary/orders', ['rows' => [['values' => $this->order(['po_wise_ldd_etd' => $ldd, 'fob_value' => 1])]]])
            ->assertCreated()->json('data.rows.0');
        $this->assertSame(1, $row['serial_no']);
        $this->assertEquals(5680, $row['values']['quantity_pcs']);
        $this->assertEquals(9599.2, $row['values']['fob_value']);
        $this->assertSame(CarbonImmutable::parse($ldd)->subDays(10)->toDateString(), $row['values']['po_wise_rfi_sd']);
        $this->assertEquals(10, $row['values']['days_left_in_hand']);
        // Fabric Details is made as the requisition's Fabrication: type, composition, GSM.
        $this->assertSame('Single Jersey 160', $row['values']['fabric_details']);
        // The day-dependent figure is worked out when read, never stored.
        $stored = OsOrder::query()->first()->values;
        $this->assertArrayNotHasKey('days_left_in_hand', $stored);
        $this->assertEquals(5680, $stored['quantity_pcs']);
        // A formula column cannot be typed into; a value off its list is refused.
        $this->api($this->owner)->postJson('/api/v1/order-summary/orders', ['rows' => [['values' => ['buyer' => 'H&M', 'order_type' => 'Something Else']]]])->assertUnprocessable()->assertJsonValidationErrors('rows.0.values.order_type');
    }

    public function test_colors_hold_a_quantity_each_and_buyer_merchandiser_is_typed_in(): void
    {
        $meta = collect($this->api($this->owner)->getJson('/api/v1/order-summary/meta')->assertOk()->json('data.columns'))->keyBy('key');
        $this->assertSame(['pairs', 'SUM', 'Pcs'], [$meta['colors']['type'], $meta['colors']['pair_reduce'], $meta['colors']['suffix']]);
        $this->assertSame('text', $meta['buyer_merchandiser']['type']);

        // Lines from the editor, pasted text, or a colour alone; the same colour twice adds up.
        $rows = $this->api($this->owner)->postJson('/api/v1/order-summary/orders', ['rows' => [
            ['values' => $this->order(['colors' => [['label' => 'Navy', 'value' => '600'], ['label' => 'White', 'value' => 400], ['label' => 'navy', 'value' => 50]], 'buyer_merchandiser' => 'Ms. Anna (H&M Dhaka)'])],
            ['values' => $this->order(['buyer' => 'Next', 'colors' => 'Black: 120, Grey 80'])],
            ['values' => $this->order(['buyer' => 'Next', 'colors' => ['Red']])],
        ]])->assertCreated()->json('data.rows');
        $this->assertSame([['label' => 'Navy', 'value' => 650], ['label' => 'White', 'value' => 400]], $rows[0]['values']['colors']);
        $this->assertSame('Ms. Anna (H&M Dhaka)', $rows[0]['values']['buyer_merchandiser']);
        $this->assertSame([['label' => 'Black', 'value' => 120], ['label' => 'Grey', 'value' => 80]], $rows[1]['values']['colors']);
        $this->assertSame([['label' => 'Red', 'value' => null]], $rows[2]['values']['colors']);
        $this->api($this->owner)->postJson('/api/v1/order-summary/orders', ['rows' => [['values' => $this->order(['colors' => [['label' => 'Navy', 'value' => 'lots']]])]]])
            ->assertUnprocessable()->assertJsonValidationErrors('rows.0.values.colors');

        // The header filter lists each colour; filtering by one finds the rows holding it.
        $this->assertSame(['Black', 'Grey', 'Navy', 'Red', 'White'], collect($this->api($this->owner)->getJson('/api/v1/order-summary/orders/distinct?column=colors')->assertOk()->json('data'))->pluck('value')->all());
        $found = $this->api($this->owner)->getJson('/api/v1/order-summary/orders?'.http_build_query(['f' => ['colors' => ['White']]]))->assertOk()->json('data.rows');
        $this->assertCount(1, $found);

        // Excel writes a line per colour with its quantity.
        $file = $this->api($this->owner)->get('/api/v1/order-summary/orders/export')->assertOk();
        $ws = \PhpOffice\PhpSpreadsheet\IOFactory::load($file->baseResponse->getFile()->getPathname())->getActiveSheet();
        $texts = [];
        $last = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::columnIndexFromString($ws->getHighestDataColumn());
        for ($row = 1; $row <= 12; $row++) {
            for ($col = 1; $col <= $last; $col++) {
                $texts[] = (string) $ws->getCell([$col, $row])->getValue();
            }
        }
        $this->assertContains("Navy: 650 Pcs\nWhite: 400 Pcs", $texts);
    }

    public function test_changed_rows_save_together_and_a_row_saved_by_someone_else_comes_back(): void
    {
        $rows = $this->api($this->owner)->postJson('/api/v1/order-summary/orders', ['rows' => [['values' => $this->order()], ['values' => $this->order(['buyer' => 'Next'])]]])->assertCreated()->json('data.rows');
        [$a, $b] = $rows;

        // Someone saves row A first.
        $this->api($this->owner)->patchJson('/api/v1/order-summary/orders', ['rows' => [['id' => $a['id'], 'version' => $a['version'], 'values' => ['fob' => 2]]]])->assertOk();

        $res = $this->api($this->owner)->patchJson('/api/v1/order-summary/orders', ['rows' => [
            ['id' => $a['id'], 'version' => $a['version'], 'values' => ['fob' => 3]],
            ['id' => $b['id'], 'version' => $b['version'], 'values' => ['pac' => 3, 'remarks' => 'Repeat order']],
        ]])->assertOk()->json('data');
        $this->assertCount(1, $res['rows']);
        $this->assertEquals(8520, $res['rows'][0]['values']['quantity_pcs']);
        $this->assertSame(2, $res['rows'][0]['version']);
        $this->assertCount(1, $res['conflicts']);
        $this->assertEquals(2, $res['conflicts'][0]['values']['fob']);
        // Each changed cell is logged, formula results too.
        $cells = OsChange::query()->where('order_id', $b['id'])->where('kind', 'EDIT')->pluck('column_key')->sort()->values()->all();
        // FOB Value reads pieces (as the requisition sheet, user 2026-11-24), so a PAC change moves it — and Trims
        // Value with it, by the same factor, so Trims Value % stays.
        $this->assertSame(['cutting_req_quantity_pcs', 'fob_value', 'pac', 'quantity_pcs', 'remarks', 'trims_value'], $cells);
    }

    public function test_column_setup_adds_a_formula_column_and_works_every_row_out_again(): void
    {
        $this->api($this->owner)->postJson('/api/v1/order-summary/orders', ['rows' => [['values' => $this->order()], ['values' => $this->order(['fob' => 2])]]])->assertCreated();
        $groups = $this->api($this->owner)->getJson('/api/v1/order-summary/columns')->assertOk()->json('data.groups');
        $price = collect($groups)->firstWhere('code', '09')['id'];

        // Checked as typed: unknown name, a loop, and a good one with a sample result.
        $this->api($this->owner)->postJson('/api/v1/order-summary/columns/check', ['formula' => '[FOB] * [Nope]', 'type' => 'money'])->assertOk()->assertJsonPath('data.ok', false)->assertJsonPath('data.position', 8);
        $this->api($this->owner)->postJson('/api/v1/order-summary/columns/check', ['formula' => '[FOB Value] + 1', 'type' => 'money', 'key' => 'fob'])->assertOk()->assertJsonPath('data.ok', false);
        $this->api($this->owner)->postJson('/api/v1/order-summary/columns/check', ['formula' => 'ROUND([FOB Value] * 0.05, 2)', 'type' => 'money', 'decimals' => 2])->assertOk()
            ->assertJsonPath('data.ok', true)->assertJsonPath('data.reads', ['FOB Value']);

        $this->api($this->owner)->postJson('/api/v1/order-summary/columns', [
            'label' => 'Agent Commission', 'group_id' => $price, 'after' => 'fob_value', 'type' => 'money', 'decimals' => 2, 'prefix' => '$',
            'formula' => 'ROUND([FOB Value] * 0.05, 2)', 'kpi' => 'SUM', 'color_rules' => [['op' => 'gt', 'value' => '500', 'tone' => 'red']],
        ])->assertCreated()->assertJsonPath('data.key', 'agent_commission')->assertJsonPath('data.recalculated', 2);

        $orders = OsOrder::query()->orderBy('serial_no')->get();
        $this->assertEquals(479.96, $orders[0]->values['agent_commission']);
        $this->assertEquals(568, $orders[1]->values['agent_commission']);
        $meta = $this->api($this->owner)->getJson('/api/v1/order-summary/meta')->json('data.columns');
        $keys = array_column($meta, 'key');
        $this->assertSame('agent_commission', $keys[array_search('fob_value', $keys, true) + 1]);

        // Changing a starting column's formula works every row out again and is kept in the history.
        $col = collect($this->api($this->owner)->getJson('/api/v1/order-summary/columns')->json('data.columns'))->firstWhere('key', 'fob_value');
        $this->api($this->owner)->patchJson('/api/v1/order-summary/columns/fob_value', ['label' => $col['label'], 'group_id' => $col['group_id'], 'type' => 'money', 'decimals' => 2, 'formula' => '[FOB] * [Quantity (PCS)] * 2'])
            ->assertOk()->assertJsonPath('data.recalculated', 2);
        $this->assertEquals(19198.4, OsOrder::query()->orderBy('serial_no')->first()->values['fob_value']);
        $this->assertEquals(959.92, OsOrder::query()->orderBy('serial_no')->first()->values['agent_commission']);
        $event = OsColumnEvent::query()->where('column_key', 'fob_value')->latest('at')->first();
        $this->assertSame('FORMULA', $event->action);
        $this->assertSame(2, $event->recalculated);
        $this->assertSame(2, OsChange::query()->where('kind', 'RECALC')->where('column_key', 'fob_value')->distinct('order_id')->count('order_id'));

        // A starting column is not deleted; one a formula reads is not either.
        $this->api($this->owner)->deleteJson('/api/v1/order-summary/columns/fob')->assertUnprocessable();
        $this->api($this->owner)->deleteJson('/api/v1/order-summary/columns/agent_commission')->assertOk();
        // Merchandisers cannot change the sheet.
        $this->api($this->merchA)->postJson('/api/v1/order-summary/columns', ['label' => 'X', 'group_id' => $price, 'type' => 'text'])->assertForbidden();
    }

    public function test_a_system_column_saves_its_width_and_freeze_panes_move(): void
    {
        $col = collect($this->api($this->owner)->getJson('/api/v1/order-summary/columns')->json('data.columns'))->firstWhere('key', 'serial_no');
        $this->api($this->owner)->patchJson('/api/v1/order-summary/columns/serial_no', ['label' => 'Serial No', 'group_id' => $col['group_id'], 'type' => 'system', 'width' => 40])->assertOk();
        $this->assertSame(40, collect($this->api($this->owner)->getJson('/api/v1/order-summary/meta')->json('data.columns'))->firstWhere('key', 'serial_no')['width']);
        // "system" stays for system columns only.
        $this->api($this->owner)->patchJson('/api/v1/order-summary/columns/fob', ['label' => 'FOB', 'group_id' => $col['group_id'], 'type' => 'system'])->assertUnprocessable()->assertJsonValidationErrors('type');
        // A width down to 30, and one frozen column at a time.
        $this->api($this->owner)->patchJson('/api/v1/order-summary/columns/serial_no', ['label' => 'Serial No', 'group_id' => $col['group_id'], 'type' => 'system', 'width' => 29])->assertUnprocessable();
        $this->api($this->owner)->putJson('/api/v1/order-summary/columns/freeze', ['key' => 'buyer'])->assertOk();
        $frozen = collect($this->api($this->owner)->getJson('/api/v1/order-summary/meta')->json('data.columns'))->where('frozen', true)->pluck('key')->all();
        $this->assertSame(['buyer'], $frozen);
        $this->api($this->merchA)->putJson('/api/v1/order-summary/columns/freeze', ['key' => 'item'])->assertForbidden();
    }

    public function test_column_role_access_keeps_a_column_read_only(): void
    {
        $viewer = (string) Role::query()->whereNull('company_id')->where('name', 'VIEWER')->value('id');
        $col = collect($this->api($this->owner)->getJson('/api/v1/order-summary/columns')->json('data.columns'))->firstWhere('key', 'fob');
        $this->api($this->owner)->patchJson('/api/v1/order-summary/columns/fob', ['label' => 'FOB', 'group_id' => $col['group_id'], 'type' => 'money', 'decimals' => 2, 'prefix' => '$', 'role_access' => [$viewer => 'view']])->assertOk();
        $this->assignBuyers($this->merchA, [$this->hm]);
        $this->api($this->merchA)->postJson('/api/v1/order-summary/orders', ['rows' => [['values' => ['buyer' => 'H&M', 'fob' => 5]]]])->assertUnprocessable()->assertJsonValidationErrors('rows.0.values.fob');
        $this->assertSame('view', collect($this->api($this->merchA)->getJson('/api/v1/order-summary/meta')->json('data.columns'))->firstWhere('key', 'fob')['access']);
    }

    public function test_a_user_sees_the_orders_of_buyers_under_them(): void
    {
        $dept = (string) Str::ulid();
        $unit = TenantFixture::factory($this->delta, 'Dhaka Unit 1', 'FAC01');
        DB::table('departments')->insert(['id' => $dept, 'company_id' => $this->delta->id, 'factory_id' => $unit->id, 'name' => 'Merchandising', 'code' => 'MER', 'created_at' => now(), 'updated_at' => now()]);
        $this->api($this->owner)->postJson('/api/v1/order-summary/teams', ['name' => 'Team Alpha', 'department_id' => $dept, 'team_head_id' => $this->teamHead->id, 'members' => [
            ['user_id' => $this->merchA->id, 'buyer_ids' => [$this->hm]], ['user_id' => $this->merchB->id, 'buyer_ids' => [$this->next]],
        ]])->assertCreated();
        $this->api($this->owner)->putJson('/api/v1/order-summary/department-heads', ['department_id' => $dept, 'user_ids' => [$this->deptHead->id]])->assertOk();
        $this->api($this->owner)->postJson('/api/v1/order-summary/orders', ['rows' => [['values' => $this->order()], ['values' => $this->order(['buyer' => 'Next'])]]])->assertCreated();

        $buyers = fn (User $u) => collect($this->api($u)->getJson('/api/v1/order-summary/orders')->json('data.rows'))->pluck('values.buyer')->sort()->values()->all();
        $this->assertSame(['H&M'], $buyers($this->merchA));
        $this->assertSame(['Next'], $buyers($this->merchB));
        $this->assertSame(['H&M', 'Next'], $buyers($this->teamHead));
        $this->assertSame(['H&M', 'Next'], $buyers($this->deptHead));
        $this->assertSame(['H&M', 'Next'], $buyers($this->owner));
        // A merchandiser cannot put another merchandiser's buyer on an order.
        $this->api($this->merchA)->postJson('/api/v1/order-summary/orders', ['rows' => [['values' => ['buyer' => 'Next']]]])->assertUnprocessable()->assertJsonValidationErrors('rows.0.values.buyer');
        // The buyers also land in Merchandise Desk's Buyer Account Permission.
        $this->assertTrue(BuyerUserPermission::query()->where('kind', 'MERCHANDISING')->where('user_id', $this->merchB->id)->where('buyer_id', $this->next)->exists());
    }

    public function test_the_team_hears_of_each_save_and_of_the_mornings_pending_work(): void
    {
        $dept = (string) Str::ulid();
        $unit = TenantFixture::factory($this->delta, 'Dhaka Unit 1', 'FAC01');
        DB::table('departments')->insert(['id' => $dept, 'company_id' => $this->delta->id, 'factory_id' => $unit->id, 'name' => 'Merchandising', 'code' => 'MER', 'created_at' => now(), 'updated_at' => now()]);
        $this->api($this->owner)->postJson('/api/v1/order-summary/teams', ['name' => 'Team Alpha', 'department_id' => $dept, 'team_head_id' => $this->teamHead->id, 'members' => [
            ['user_id' => $this->merchA->id, 'buyer_ids' => [$this->hm]], ['user_id' => $this->merchB->id, 'buyer_ids' => [$this->next]],
        ]])->assertCreated();
        $this->api($this->owner)->putJson('/api/v1/order-summary/department-heads', ['department_id' => $dept, 'user_ids' => [$this->deptHead->id]])->assertOk();
        $notes = fn (User $u, string $event) => Notification::withoutGlobalScope(TenantScope::class)->where('user_id', $u->id)->where('event_type', $event)->orderBy('created_at')->orderBy('id')->get();

        // One save of two orders: each person hears once, of the orders of their buyers; the one who saved hears nothing.
        $ldd = now('Asia/Dhaka')->addDays(5)->toDateString(); // RFI/SD 10 days earlier: gone by; LDD within 14 days, fabric not booked
        $rows = $this->api($this->owner)->postJson('/api/v1/order-summary/orders', ['rows' => [['values' => $this->order(['po_wise_ldd_etd' => $ldd])], ['values' => $this->order(['buyer' => 'Next'])]]])->assertCreated()->json('data.rows');
        $this->assertSame(1, $notes($this->merchA, 'OS_ORDERS_CHANGED')->first()->data_json['n']);
        $this->assertSame('H&M', $notes($this->merchA, 'OS_ORDERS_CHANGED')->first()->data_json['buyers']);
        $this->assertSame('Next', $notes($this->merchB, 'OS_ORDERS_CHANGED')->first()->data_json['buyers']);
        $head = $notes($this->teamHead, 'OS_ORDERS_CHANGED');
        $this->assertCount(1, $head);
        $this->assertSame(2, $head[0]->data_json['n']);
        $this->assertSame('/order-summary/list', $head[0]->action_url);
        $this->assertCount(1, $notes($this->deptHead, 'OS_ORDERS_CHANGED'));
        $this->assertCount(0, $notes($this->owner, 'OS_ORDERS_CHANGED'));

        // A merchandiser's edit: the author and the heads hear it; the other buyer's merchandiser does not.
        $this->api($this->merchA)->patchJson('/api/v1/order-summary/orders', ['rows' => [['id' => $rows[0]['id'], 'version' => $rows[0]['version'], 'values' => ['fob' => 2]]]])->assertOk();
        $mine = $notes($this->owner, 'OS_ORDERS_CHANGED');
        $this->assertCount(1, $mine);
        $this->assertStringContainsString('updated 1 order(s)', $mine[0]->title);
        $this->assertSame('/order-summary/list?f[buyer][]=H%26M', $mine[0]->action_url);
        $this->assertCount(2, $notes($this->teamHead, 'OS_ORDERS_CHANGED'));
        $this->assertCount(1, $notes($this->merchA, 'OS_ORDERS_CHANGED'));
        $this->assertCount(1, $notes($this->merchB, 'OS_ORDERS_CHANGED'));

        // The morning: H&M's order is past RFI/SD and its fabric is not booked; Next's has no dates.
        $this->artisan('erp:notify-pending-work', ['--company' => 'DAL'])->assertSuccessful();
        $work = $notes($this->merchA, 'OS_PENDING_WORK');
        $this->assertCount(1, $work);
        $this->assertSame([1, 0, 1], [$work[0]->data_json['overdue'], $work[0]->data_json['week'], $work[0]->data_json['booking']]);
        $this->assertSame('WARNING', $work[0]->severity);
        $this->assertCount(0, $notes($this->merchB, 'OS_PENDING_WORK'));
    }

    public function test_bulk_update_copy_delete_and_the_header_filter(): void
    {
        $ids = collect($this->api($this->owner)->postJson('/api/v1/order-summary/orders', ['rows' => [['values' => $this->order()], ['values' => $this->order(['buyer' => 'Next'])], ['values' => $this->order(['item' => null])]]])->json('data.rows'))->pluck('id');

        $this->api($this->owner)->postJson('/api/v1/order-summary/orders/bulk-update', ['ids' => $ids->take(2)->all(), 'values' => ['shipment_status' => 'Ship Out', 'shipout_quantity_ticket' => 2000]])->assertOk()->assertJsonPath('data.updated', 2);
        $first = OsOrder::query()->orderBy('serial_no')->first()->values;
        $this->assertSame('Ship Out', $first['shipment_status']);
        $this->assertEquals(840, $first['short_excess_ticket']);

        $copy = $this->api($this->owner)->postJson('/api/v1/order-summary/orders/copy', ['ids' => [$ids[0]]])->assertCreated()->json('data.rows.0');
        $this->assertSame(4, $copy['serial_no']);
        $this->assertSame('Ship Out', $copy['values']['shipment_status']);

        $this->api($this->owner)->deleteJson('/api/v1/order-summary/orders', ['ids' => [$copy['id']]])->assertOk()->assertJsonPath('data.deleted', 1);
        $this->assertSoftDeleted('os_orders', ['id' => $copy['id']]);

        $distinct = collect($this->api($this->owner)->getJson('/api/v1/order-summary/orders/distinct?column=item')->assertOk()->json('data'))->pluck('count', 'value')->all();
        $this->assertSame(['' => 1, 'T-Shirt' => 2], $distinct);
        $this->assertSame(1, $this->api($this->owner)->getJson('/api/v1/order-summary/orders?f[item][]=')->json('data.page.total'));
        $this->assertSame(2, $this->api($this->owner)->getJson('/api/v1/order-summary/orders?f[shipment_status][]=Ship+Out')->json('data.page.total'));
        $this->assertSame(1, $this->api($this->owner)->getJson('/api/v1/order-summary/orders?f[colors][]=Navy&f[buyer][]=Next')->json('data.page.total'));
        $this->assertSame(3, $this->api($this->owner)->getJson('/api/v1/order-summary/orders?q=pique')->json('data.page.total'));
    }

    public function test_the_last_update_date_filter_lists_and_matches_each_rows_dhaka_day(): void
    {
        $ids = collect($this->api($this->owner)->postJson('/api/v1/order-summary/orders', ['rows' => [['values' => $this->order()], ['values' => $this->order()]]])->assertCreated()->json('data.rows'))->pluck('id');
        // updated_at is stored UTC: 18:43 UTC is already the next day in Dhaka (UTC+6).
        DB::table('os_orders')->where('id', $ids[0])->update(['updated_at' => '2026-11-18 10:00:00']);
        DB::table('os_orders')->where('id', $ids[1])->update(['updated_at' => '2026-11-18 18:43:46']);

        $distinct = collect($this->api($this->owner)->getJson('/api/v1/order-summary/orders/distinct?column=last_update')->assertOk()->json('data'))->pluck('count', 'value')->all();
        $this->assertSame(['2026-11-18' => 1, '2026-11-19' => 1], $distinct);
        $this->assertSame([$ids[1]], collect($this->api($this->owner)->getJson('/api/v1/order-summary/orders?f[last_update][]=2026-11-19')->json('data.rows'))->pluck('id')->all());
        $this->assertSame(0, $this->api($this->owner)->getJson('/api/v1/order-summary/orders?f[last_update][]=')->json('data.page.total'));
        $this->assertSame(1, $this->api($this->owner)->getJson('/api/v1/order-summary/orders?r[last_update][to]=2026-11-18')->json('data.page.total'));
    }

    public function test_the_totals_row_counts_every_filtered_row_and_the_excel_colours_are_set(): void
    {
        $this->api($this->owner)->postJson('/api/v1/order-summary/orders', ['rows' => [
            ['values' => $this->order()], ['values' => $this->order(['buyer' => 'Next', 'quantity_ticket' => 1000])], ['values' => $this->order(['quantity_ticket' => 500])],
        ]])->assertCreated();

        // Every filtered row, not just the page (Excel's SUBTOTAL).
        // Page 2 has no rows of its own, yet the totals are every filtered row's.
        $page2 = $this->api($this->owner)->getJson('/api/v1/order-summary/orders?per_page=25&page=2')->assertOk()->json('data');
        $this->assertSame([], $page2['rows']);
        $all = $page2['totals'];
        $this->assertEquals(4340, $all['quantity_ticket']);
        $this->assertEquals(8680, $all['quantity_pcs']);
        $this->assertArrayNotHasKey('fob', $all);
        $next = $this->api($this->owner)->getJson('/api/v1/order-summary/orders?f[buyer][]=Next')->json('data.totals');
        $this->assertEquals(1000, $next['quantity_ticket']);
        $this->assertEquals(3380, $next['fob_value']);

        // Excel's look as the starting settings: Delay headers cyan, sample groups pink, TP Rcvd orange text.
        $cols = $this->api($this->owner)->getJson('/api/v1/order-summary/columns')->assertOk()->json('data');
        $this->assertSame('cyan', collect($cols['groups'])->firstWhere('code', '04')['tone']);
        $this->assertSame('pink', collect($cols['groups'])->firstWhere('code', '13')['tone']);
        $tp = collect($cols['columns'])->firstWhere('key', 'tp_rcvd_date');
        $this->assertSame('orange', $tp['text_tone']);
        $this->assertSame('lookup:OS_MERCHANDISER', collect($cols['columns'])->firstWhere('key', 'marketing_merchandiser')['source']);

        // The text colour and the total are settings of Column Setup.
        $this->api($this->owner)->patchJson('/api/v1/order-summary/columns/tp_rcvd_date', ['label' => $tp['label'], 'group_id' => $tp['group_id'], 'type' => 'date', 'width' => 34, 'vertical' => true, 'text_tone' => 'pink'])
            ->assertUnprocessable()->assertJsonValidationErrors('text_tone');
        $this->api($this->owner)->patchJson('/api/v1/order-summary/columns/tp_rcvd_date', ['label' => $tp['label'], 'group_id' => $tp['group_id'], 'type' => 'date', 'width' => 34, 'vertical' => true, 'text_tone' => 'green'])->assertOk();
        $this->assertSame('green', collect($this->api($this->owner)->getJson('/api/v1/order-summary/meta')->json('data.columns'))->firstWhere('key', 'tp_rcvd_date')['text_tone']);
        $fob = collect($cols['columns'])->firstWhere('key', 'fob');
        $this->api($this->owner)->patchJson('/api/v1/order-summary/columns/fob', ['label' => 'FOB', 'group_id' => $fob['group_id'], 'type' => 'money', 'decimals' => 2, 'prefix' => '$', 'kpi' => 'AVG'])->assertOk();
        $this->assertEqualsWithDelta(1.69, $this->api($this->owner)->getJson('/api/v1/order-summary/orders')->json('data.totals.fob'), 0.0001);

        // Bold data (user, 2026-11-21): JOB No, both quantities, FOB and FOB Value start bold; a save without it keeps it, Column Setup switches it.
        $this->assertTrue($cols['bold']);
        $bold = fn () => collect($this->api($this->owner)->getJson('/api/v1/order-summary/meta')->json('data.columns'))->where('bold', true)->pluck('key')->sort()->values()->all();
        $this->assertSame(['fob', 'fob_value', 'job_no', 'quantity_pcs', 'quantity_ticket'], $bold());
        $this->api($this->owner)->patchJson('/api/v1/order-summary/columns/fob', ['label' => 'FOB', 'group_id' => $fob['group_id'], 'type' => 'money', 'decimals' => 2, 'prefix' => '$', 'bold' => false])->assertOk();
        $this->api($this->owner)->patchJson('/api/v1/order-summary/columns/tp_rcvd_date', ['label' => $tp['label'], 'group_id' => $tp['group_id'], 'type' => 'date', 'width' => 34, 'vertical' => true, 'bold' => true])->assertOk();
        $this->assertSame(['fob_value', 'job_no', 'quantity_pcs', 'quantity_ticket', 'tp_rcvd_date'], $bold());
    }

    public function test_imported_yarn_mills_and_merchandisers_join_their_lists(): void
    {
        $id = $this->api($this->owner)->postJson('/api/v1/order-summary/orders', ['rows' => [['values' => $this->order()]]])->assertCreated()->json('data.rows.0.id');
        // Text an import left that the lists do not hold yet.
        DB::table('os_orders')->where('id', $id)->update(['values' => DB::raw("\"values\" || '{\"yarn_mill_name\": \"Nahar Spinning\", \"woven_fabric_mill_name\": \"N/A\", \"marketing_merchandiser\": \"Wahida\", \"colors\": [\"Sky Blue\"]}'::jsonb")]);

        $this->artisan('ordersummary:fill-lists', ['--company' => 'DAL', '--user' => 'owner@delta.test'])->assertSuccessful();

        // Yarn Mill → Supplier Master; merchandiser → Order Summary's own lookup list; "N/A" and colours stay text.
        $supplier = DB::table('mp_suppliers')->where('company_id', $this->delta->id)->where('name', 'Nahar Spinning')->first();
        $this->assertNotNull($supplier);
        $this->assertSame('Yarn', $supplier->goods_service);
        $this->assertStringStartsWith('SUP-', $supplier->code);
        $this->assertFalse(DB::table('mp_suppliers')->where('name', 'N/A')->exists());
        $this->assertTrue(DB::table('lookup_values')->join('lookup_tables', 'lookup_tables.id', '=', 'lookup_values.lookup_table_id')
            ->where('lookup_tables.code', 'OS_MERCHANDISER')->where('lookup_values.name', 'Wahida')->where('lookup_values.company_id', $this->delta->id)->exists());
        $this->assertFalse(DB::table('colours')->where('name', 'Sky Blue')->exists());

        // Both now offered in the dropdowns.
        $sources = $this->api($this->owner)->getJson('/api/v1/order-summary/meta')->json('data.sources');
        $this->assertContains('Nahar Spinning', array_column($sources['suppliers'], 'value'));
        $this->assertContains('Wahida', array_column($sources['lookup:OS_MERCHANDISER'], 'value'));
    }

    public function test_the_edit_mail_goes_at_the_set_time_to_each_person_with_only_their_buyers(): void
    {
        Mail::fake();
        // The edits are made at 09:00 in the factory.
        CarbonImmutable::setTestNow(CarbonImmutable::now('Asia/Dhaka')->setTime(9, 0)->utc());
        \Illuminate\Support\Carbon::setTestNow(CarbonImmutable::now());
        $dept = (string) Str::ulid();
        $unit = TenantFixture::factory($this->delta, 'Dhaka Unit 1', 'FAC01');
        DB::table('departments')->insert(['id' => $dept, 'company_id' => $this->delta->id, 'factory_id' => $unit->id, 'name' => 'Merchandising', 'code' => 'MER', 'created_at' => now(), 'updated_at' => now()]);
        $this->api($this->owner)->postJson('/api/v1/order-summary/teams', ['name' => 'Team Alpha', 'department_id' => $dept, 'team_head_id' => $this->teamHead->id, 'members' => [
            ['user_id' => $this->merchA->id, 'buyer_ids' => [$this->hm]], ['user_id' => $this->merchB->id, 'buyer_ids' => [$this->next]],
        ]])->assertCreated();
        MailService::query()->create(['company_id' => $this->delta->id, 'service_name' => 'Office SMTP', 'smtp_host' => 'smtp.test', 'port' => 587, 'smtp_username' => 'erp@delta.test', 'password_env' => 'OS_TEST_SMTP_PASS', 'encryption' => 'tls', 'from_address' => 'erp@delta.test', 'from_name' => 'Delta ERP', 'is_default' => true, 'active' => true]);
        // The instant mail (JOB / PO / Shipment ID) is off here: this test is the timed edit mail alone.
        $this->api($this->owner)->putJson('/api/v1/order-summary/mail-settings', ['enabled' => true, 'times' => ['10:00', '18:00'], 'instant_enabled' => false])->assertOk();
        OsMailSetting::query()->update(['last_slot' => null]);

        $rows = $this->api($this->owner)->postJson('/api/v1/order-summary/orders', ['rows' => [['values' => $this->order()], ['values' => $this->order(['buyer' => 'Next'])]]])->json('data.rows');
        $this->api($this->owner)->patchJson('/api/v1/order-summary/orders', ['rows' => [['id' => $rows[0]['id'], 'version' => 1, 'values' => ['remarks' => 'Fabric late']], ['id' => $rows[1]['id'], 'version' => 1, 'values' => ['remarks' => 'On time']]]])->assertOk();

        // 09:30 Dhaka: nothing is due.
        $this->assertSame(0, MailDigest::run($this->delta->id, CarbonImmutable::now('Asia/Dhaka')->setTime(9, 30)->utc())['sent']);
        Mail::assertNothingSent();
        // 10:05 Dhaka: due — Karim (H&M), Rahim (Next) and the team head (both).
        $r = MailDigest::run($this->delta->id, CarbonImmutable::now('Asia/Dhaka')->setTime(10, 5)->utc());
        $this->assertSame(3, $r['sent']);
        Mail::assertSent(OrderEditDigestMail::class, fn ($m) => $m->hasTo('karim@delta.test') && str_contains($m->body, 'Fabric late') && ! str_contains($m->body, 'On time'));
        Mail::assertSent(OrderEditDigestMail::class, fn ($m) => $m->hasTo('teamhead@delta.test') && str_contains($m->body, 'Fabric late') && str_contains($m->body, 'On time'));
        Mail::assertNotSent(OrderEditDigestMail::class, fn ($m) => $m->hasTo('owner@delta.test'));
        $this->assertSame(0, OsChange::query()->where('kind', 'EDIT')->whereNull('mailed_at')->count());
        // The same slot does not go twice; created rows are never mailed.
        $this->assertSame(0, MailDigest::run($this->delta->id, CarbonImmutable::now('Asia/Dhaka')->setTime(10, 30)->utc())['sent']);
        $this->assertTrue(OsChange::query()->where('kind', 'CREATE')->whereNull('mailed_at')->exists());
    }

    public function test_job_po_and_shipment_numbers_added_or_changed_are_mailed_at_once(): void
    {
        Mail::fake();
        $dept = (string) Str::ulid();
        $unit = TenantFixture::factory($this->delta, 'Dhaka Unit 1', 'FAC01');
        DB::table('departments')->insert(['id' => $dept, 'company_id' => $this->delta->id, 'factory_id' => $unit->id, 'name' => 'Merchandising', 'code' => 'MER', 'created_at' => now(), 'updated_at' => now()]);
        $this->api($this->owner)->postJson('/api/v1/order-summary/teams', ['name' => 'Team Alpha', 'department_id' => $dept, 'team_head_id' => $this->teamHead->id, 'members' => [
            ['user_id' => $this->merchA->id, 'buyer_ids' => [$this->hm]], ['user_id' => $this->merchB->id, 'buyer_ids' => [$this->next]],
        ]])->assertCreated();
        // On by default, for the three columns.
        $set = $this->api($this->owner)->getJson('/api/v1/order-summary/mail-settings')->assertOk()->json('data');
        $this->assertTrue($set['instant_enabled']);
        $this->assertSame(['job_no', 'po_number', 'shipment_id_number'], $set['instant_columns']);
        // No mail service yet: nothing goes, nothing breaks.
        $this->api($this->owner)->postJson('/api/v1/order-summary/orders', ['rows' => [['values' => $this->order(['job_no' => ['J-0']])]]])->assertCreated();
        Mail::assertNothingSent();
        MailService::query()->create(['company_id' => $this->delta->id, 'service_name' => 'Office SMTP', 'smtp_host' => 'smtp.test', 'port' => 587, 'smtp_username' => 'erp@delta.test', 'password_env' => 'OS_TEST_SMTP_PASS', 'encryption' => 'tls', 'from_address' => 'erp@delta.test', 'from_name' => 'Delta ERP', 'is_default' => true, 'active' => true]);

        // A new H&M order (one JOB No, two POs): Karim (H&M) and the team head are told; Rahim (Next) and the owner who saved are not.
        $row = $this->api($this->owner)->postJson('/api/v1/order-summary/orders', ['rows' => [['values' => $this->order(['job_no' => ['J-101'], 'po_number' => ['PO-9', 'PO-8']])]]])->assertCreated()->json('data.rows.0');
        $this->assertSame(['J-101'], $row['values']['job_no']);
        Mail::assertSent(OrderEditDigestMail::class, fn ($m) => $m->hasTo('karim@delta.test') && str_contains($m->body, 'J-101') && str_contains($m->body, 'PO-9, PO-8') && str_contains((string) $m->subjectLine, 'JOB No'));
        Mail::assertSent(OrderEditDigestMail::class, fn ($m) => $m->hasTo('teamhead@delta.test'));
        Mail::assertNotSent(OrderEditDigestMail::class, fn ($m) => $m->hasTo('rahim@delta.test') || $m->hasTo('owner@delta.test'));
        $before = count(Mail::sent(OrderEditDigestMail::class));

        // Karim adds a shipment ID: the team head is told, Karim himself is not.
        $this->api($this->merchA)->patchJson('/api/v1/order-summary/orders', ['rows' => [['id' => $row['id'], 'version' => $row['version'], 'values' => ['shipment_id_number' => ['SH-1'], 'remarks' => 'x']]]])->assertOk();
        $mails = Mail::sent(OrderEditDigestMail::class);
        $this->assertCount($before + 1, $mails);
        $last = $mails->last();
        $this->assertTrue($last->hasTo('teamhead@delta.test'));
        $this->assertStringContainsString('SH-1', $last->body);
        $this->assertStringNotContainsString('Remarks', $last->body);

        // Another column alone is not mailed at once; turned off, nothing is.
        $this->api($this->owner)->patchJson('/api/v1/order-summary/orders', ['rows' => [['id' => $row['id'], 'version' => $row['version'] + 1, 'values' => ['remarks' => 'y']]]])->assertOk();
        $this->assertCount($before + 1, Mail::sent(OrderEditDigestMail::class));
        $this->api($this->owner)->putJson('/api/v1/order-summary/mail-settings', ['enabled' => false, 'times' => [], 'instant_enabled' => false])->assertOk();
        $this->api($this->owner)->patchJson('/api/v1/order-summary/orders', ['rows' => [['id' => $row['id'], 'version' => $row['version'] + 2, 'values' => ['po_number' => ['PO-9', 'PO-10']]]]])->assertOk();
        $this->assertCount($before + 1, Mail::sent(OrderEditDigestMail::class));
    }

    public function test_the_job_no_dialog_offers_this_years_next_serial_of_the_factory(): void
    {
        TenantFixture::factory($this->delta, 'Norban Comtex Ltd', 'NCL');
        TenantFixture::factory($this->delta, 'Norban Fashion Ltd', 'NFL');
        $this->travelTo(\Carbon\CarbonImmutable::parse('2026-11-22 10:00', 'Asia/Dhaka'));
        $rows = [
            $this->order(['factory' => 'NCL', 'job_no' => ['26NCL00007']]),
            $this->order(['factory' => 'NCL', 'job_no' => ['26NCL00012']]),
            $this->order(['factory' => 'NFL', 'job_no' => ['26NFL00030']]),
            $this->order(['factory' => 'NCL', 'job_no' => ['25NCL00099']]),
            $this->order(['factory' => 'NCL', 'job_no' => ['NCL-26-00500 note']]),
            $this->order(['factory' => 'NCL', 'job_no' => ['26NCL00040']]),
        ];
        $saved = $this->api($this->owner)->postJson('/api/v1/order-summary/orders', ['rows' => array_map(fn ($v) => ['values' => $v], $rows)])->assertCreated()->json('data.rows');
        // A deleted order's number is free again.
        DB::table('os_orders')->where('id', $saved[5]['id'])->update(['deleted_at' => now()]);

        // Over the whole company — a merchandiser without these buyers still gets the next free number.
        $ncl = $this->api($this->merchB)->getJson('/api/v1/order-summary/job-numbers?factory=ncl')->assertOk()->json('data');
        $this->assertSame(['year' => '26', 'factory' => 'NCL', 'prefix' => '26NCL', 'width' => 5, 'next' => 13, 'taken' => [7, 12]], $ncl);
        $this->assertSame(31, $this->api($this->owner)->getJson('/api/v1/order-summary/job-numbers?factory=NFL')->json('data.next'));
        // A factory that is not the company's: choose the Factory first.
        $this->api($this->owner)->getJson('/api/v1/order-summary/job-numbers?factory=XYZ')->assertUnprocessable()->assertJsonValidationErrors('factory');
        // No factory: every factory at once (the sheet loads them as it opens, so the dialog shows at once).
        $all = $this->api($this->owner)->getJson('/api/v1/order-summary/job-numbers')->assertOk()->json('data.factories');
        $this->assertSame(['NCL', 'NFL'], array_keys($all));
        $this->assertSame([13, 31], [$all['NCL']['next'], $all['NFL']['next']]);

        // One number per job in every module: the next serial is one past the highest anywhere (a budget's
        // requisition, the sample, the export); "already given" counts only the sheet's own module.
        $c = $this->delta->id;
        DB::table('pb_requisitions')->insert(['id' => (string) Str::ulid(), 'company_id' => $c, 'entry_type' => 'ORDER', 'values' => json_encode(['factory' => 'NCL', 'job_no' => '26NCL00020']), 'created_at' => now(), 'updated_at' => now()]);
        DB::table('ds_samples')->insert(['id' => (string) Str::ulid(), 'company_id' => $c, 'serial_no' => 1, 'values' => json_encode(['job_no' => '26NFL00045']), 'created_at' => now(), 'updated_at' => now()]);
        DB::table('es_statements')->insert(['id' => (string) Str::ulid(), 'company_id' => $c, 'serial_no' => 1, 'values' => json_encode(['company_name' => 'NCL', 'job_no' => '26NCL00012']), 'created_at' => now(), 'updated_at' => now()]);
        $this->assertSame(['next' => 21, 'taken' => [7, 12]], array_intersect_key($this->api($this->owner)->getJson('/api/v1/order-summary/job-numbers?factory=NCL')->json('data'), ['next' => 1, 'taken' => 1]));
        $this->assertSame(['next' => 21, 'taken' => [20]], array_intersect_key($this->api($this->owner)->getJson('/api/v1/pre-budget/job-numbers?factory=NCL')->json('data'), ['next' => 1, 'taken' => 1]));
        $this->assertSame(['next' => 46, 'taken' => [45]], array_intersect_key($this->api($this->owner)->getJson('/api/v1/dev-sample/job-numbers?factory=NFL')->json('data'), ['next' => 1, 'taken' => 1]));
        $this->assertSame([12], $this->api($this->owner)->getJson('/api/v1/export-statement/job-numbers')->assertOk()->json('data.factories.NCL.taken'));
        // A new year starts again from 1.
        $this->travelTo(\Carbon\CarbonImmutable::parse('2027-01-01 09:00', 'Asia/Dhaka'));
        $this->assertSame(['27NCL', 1], array_values(array_intersect_key($this->api($this->owner)->getJson('/api/v1/order-summary/job-numbers?factory=NCL')->json('data'), ['prefix' => 1, 'next' => 1])));

        // Norban's older forms in today's; anything else kept as it is.
        $this->assertSame('24NCL00815', JobNumber::modern('NCL-24-00815'));
        $this->assertSame('24NCL00815', JobNumber::modern('24-NCL-00815'));
        $this->assertSame('25NCL00068', JobNumber::modern('NCL-25-0068'));
        $this->assertSame('NCL-24-00815 / 24NCL00816', JobNumber::modern('NCL-24-00815 / 24NCL00816'));
        $this->assertSame(['year' => '26', 'factory' => 'NCL', 'serial' => 1], JobNumber::parse('26NCL00001'));
        $this->assertNull(JobNumber::parse('NCL-26-00001'));
    }

    public function test_pending_and_buyer_month_reports(): void
    {
        $ldd = CarbonImmutable::parse(Sheet::today())->addDays(5)->toDateString();
        $this->api($this->owner)->postJson('/api/v1/order-summary/orders', ['rows' => [
            ['values' => $this->order(['po_wise_ldd_etd' => $ldd])],
            ['values' => $this->order(['po_wise_ldd_etd' => $ldd, 'fabric_booking_date' => $ldd, 'fabric_status' => 'Fabric Delivery Done'])],
            ['values' => $this->order(['po_wise_ldd_etd' => $ldd, 'shipment_status' => 'Ship Out'])],
        ]])->assertCreated();
        $rep = $this->api($this->owner)->getJson('/api/v1/order-summary/reports/pending/ldd?type=fabric_booking_pending')->assertOk()->json('data');
        $this->assertCount(1, $rep['rows']);
        $this->assertSame('booking', $rep['rows'][0]['pending']);
        $this->assertSame('critical', $rep['rows'][0]['urgency']);

        $bm = $this->api($this->owner)->getJson('/api/v1/order-summary/reports/buyer-month/ldd?year='.substr($ldd, 0, 4))->assertOk()->json('data');
        $this->assertEquals(17040, $bm['total']['qty']);
        $this->assertSame('H&M', $bm['buyers'][0]['buyer']);
        $this->assertEquals(17040, $bm['buyers'][0]['months'][(int) substr($ldd, 5, 2)]['qty']);

        $this->api($this->owner)->getJson('/api/v1/order-summary/dashboard')->assertOk()->assertJsonPath('data.totals.orders', 3)->assertJsonPath('data.totals.shipped', 1);
        $this->api($this->owner)->getJson('/api/v1/order-summary/audit-log')->assertOk()->assertJsonPath('data.page.total', 3);
    }

    public function test_order_summary_picks_from_the_requisition_lists(): void
    {
        $meta = $this->api($this->owner)->getJson('/api/v1/order-summary/meta')->assertOk()->json('data');
        foreach (MerchLists::OS_COLUMNS as $key => $list) {
            $this->assertSame("pb:{$list}", collect($meta['columns'])->firstWhere('key', $key)['source'], $key);
        }
        $this->assertSame(['Confirmed PO', 'DROPPED'], array_column($meta['sources']['pb:ORDER_TYPE'], 'value'));

        // The list's spelling wins: case and spaces, then the approved old spellings (No Wash → No, Garment Wash → Garments Wash).
        $row = $this->api($this->owner)->postJson('/api/v1/order-summary/orders', ['rows' => [['values' => $this->order(['item' => 'POLO', 'wash_info_at_garmenting' => 'No Wash', 'fabric_type' => 'WOVEN'])]]])
            ->assertCreated()->json('data.rows.0.values');
        $this->assertSame(['Polo', 'No', 'Woven'], [$row['item'], $row['wash_info_at_garmenting'], $row['fabric_type']]);
        $this->api($this->owner)->postJson('/api/v1/order-summary/orders', ['rows' => [['values' => $this->order(['item' => 'Hoodie'])]]])
            ->assertUnprocessable()->assertJsonValidationErrors('rows.0.values.item');

        // A value the list lacks, brought in by the importer, joins the shared list once (an alias's target, not the old word).
        $this->assertSame('Garments Wash', MerchLists::ensure($this->delta->id, 'WASH_TYPE', 'garment  wash'));
        $this->assertSame('Physical BCI + Recycle', MerchLists::ensure($this->delta->id, 'SUSTAINABILITY', 'Phys BCI + Rec. Cot'));
        $this->assertSame('Physical BCI + Recycle', MerchLists::ensure($this->delta->id, 'SUSTAINABILITY', 'Physical BCI+Recycle'));
        $this->assertSame(1, DB::table('pb_list_values')->where('list', 'SUSTAINABILITY')->count());
        // Column Setup offers the shared lists.
        $sources = array_column($this->api($this->owner)->getJson('/api/v1/order-summary/columns')->json('data.sources'), 'value');
        $this->assertContains('pb:WASH_TYPE', $sources);
    }

    public function test_a_row_holds_one_style_and_one_job_no(): void
    {
        // User, 2026-11-23: an order row is one style with one JOB No; PO and shipment numbers stay lists.
        $this->api($this->owner)->postJson('/api/v1/order-summary/orders', ['rows' => [['values' => $this->order(['style_number_name' => ['A', 'B']])]]])
            ->assertUnprocessable()->assertJsonValidationErrors('rows.0.values.style_number_name');
        $this->api($this->owner)->postJson('/api/v1/order-summary/orders', ['rows' => [['values' => $this->order(['job_no' => ['J-1', 'J-2']])]]])
            ->assertUnprocessable()->assertJsonValidationErrors('rows.0.values.job_no');
        // Typed text is one value even with a comma in it.
        $row = $this->api($this->owner)->postJson('/api/v1/order-summary/orders', ['rows' => [['values' => $this->order(['style_number_name' => '2241405 Solid, NOS', 'po_number' => 'P-1, P-2'])]]])
            ->assertCreated()->json('data.rows.0.values');
        $this->assertSame(['2241405 Solid, NOS'], $row['style_number_name']);
        $this->assertSame(['P-1', 'P-2'], $row['po_number']);
    }

    public function test_days_left_buckets_count_styles_and_open_the_list_on_days_left_itself(): void
    {
        $ldd = fn (int $days) => CarbonImmutable::parse(Sheet::today())->addDays($days)->toDateString(); // RFI/SD is LDD − 10
        $this->api($this->owner)->postJson('/api/v1/order-summary/orders', ['rows' => [
            ['values' => $this->order(['style_number_name' => ['A'], 'po_wise_ldd_etd' => $ldd(15)])],
            ['values' => $this->order(['style_number_name' => ['A'], 'po_wise_ldd_etd' => $ldd(16)])],
            ['values' => $this->order(['style_number_name' => ['B'], 'po_wise_ldd_etd' => $ldd(15)])],
            ['values' => $this->order(['style_number_name' => ['C'], 'po_wise_ldd_etd' => $ldd(60)])],
            ['values' => $this->order(['style_number_name' => ['D'], 'po_wise_ldd_etd' => $ldd(15), 'shipment_status' => 'Ship Out'])],
        ]])->assertCreated();

        $dash = $this->api($this->owner)->getJson('/api/v1/order-summary/dashboard')->assertOk()->json('data');
        // Styles, not rows: A's two open rows count once; shipped D not at all.
        $this->assertSame(['overdue' => 0, 'week' => 2, 'month' => 0, 'later' => 1, 'none' => 0], $dash['days_left']);
        // Pending against LDD/ETD counts styles too (four open rows, three styles), as its report's summary.
        $this->assertSame(3, $dash['pending']['fabric_booking_pending']);
        $rep = $this->api($this->owner)->getJson('/api/v1/order-summary/reports/pending/ldd?type=fabric_booking_pending')->assertOk()->json('data');
        $this->assertCount(4, $rep['rows']);
        $this->assertSame(3, $rep['summary']['styles']);

        // The bucket's link filters on Days Left In Hand itself (worked out for today) and lists what it counted.
        $query = collect($dash['days_left_filters']['week'])->map(fn ($p) => urlencode($p[0]).'='.urlencode($p[1]))->implode('&');
        $this->assertStringContainsString('days_left_in_hand', urldecode($query));
        $page = $this->api($this->owner)->getJson("/api/v1/order-summary/orders?{$query}")->assertOk()->json('data.page');
        $this->assertSame(3, $page['total']);
        $this->assertSame(2, $page['styles']);

        // Day-dependent columns have a header filter like any other.
        $values = collect($this->api($this->owner)->getJson('/api/v1/order-summary/orders/distinct?column=days_left_in_hand')->assertOk()->json('data'))->pluck('count', 'value');
        $this->assertSame(3, $values['5']);
        $this->assertSame(1, $values['6']);
        $this->api($this->owner)->getJson('/api/v1/order-summary/orders?f[days_left_in_hand][]=6')->assertOk()->assertJsonPath('data.page.total', 1);
    }

    /** @param  array<string, mixed>  $over */
    public function test_view_all_exports_to_the_companys_book_layout(): void
    {
        $rows = $this->api($this->owner)->postJson('/api/v1/order-summary/orders', ['rows' => [['values' => $this->order()], ['values' => $this->order(['buyer' => 'Next', 'quantity_ticket' => 100])]]])
            ->assertCreated()->json('data.rows');

        $res = $this->api($this->owner)->get('/api/v1/order-summary/orders/export')->assertOk();
        $this->assertStringContainsString('spreadsheetml', (string) $res->headers->get('Content-Type'));
        $file = $res->baseResponse->getFile()->getPathname();
        $ws = \PhpOffice\PhpSpreadsheet\IOFactory::load($file)->getSheetByName('Total Order');
        // The book's own layout: title naming the buyers, headers on row 2, orders from row 4 (newest first, as listed).
        $this->assertSame('H&M, NEXT BUYER RUNNING ORDER SUMMARY', $ws->getCell('D1')->getValue());
        $this->assertSame('QUANTITY (TICKET)', str_replace("\n", ' ', (string) $ws->getCell('T2')->getValue()));
        $this->assertSame('Next', $ws->getCell('D4')->getValue());
        $this->assertEquals(100, $ws->getCell('T4')->getValue());
        $this->assertEquals(2840, $ws->getCell('T5')->getValue());
        // Quantity (PCS) is the sheet's own figure (ticket × PAC), not the book's first-row constant; Excel SL# and
        // Days Left stay live formulas; the totals cover every row.
        $this->assertEquals(5680, $ws->getCell('U5')->getValue());
        $this->assertSame('=ROW()-3', $ws->getCell('A5')->getValue());
        $this->assertSame('=W5-TODAY()', $ws->getCell('X5')->getValue());
        $this->assertSame('=SUBTOTAL(9,T4:T5)', $ws->getCell('T1')->getValue());

        // Only the ticked rows when some are named.
        $one = $this->api($this->owner)->get('/api/v1/order-summary/orders/export?ids[]='.$rows[0]['id'])->assertOk();
        $ws = \PhpOffice\PhpSpreadsheet\IOFactory::load($one->baseResponse->getFile()->getPathname())->getSheetByName('Total Order');
        $this->assertSame('H&M', $ws->getCell('D4')->getValue());
        $this->assertNull($ws->getCell('D5')->getValue());
    }

    private function order(array $over = []): array
    {
        return array_filter(array_merge([
            'buyer' => 'H&M', 'department' => 'Mens', 'order_type' => 'Confirmed PO', 'item' => 'T-Shirt', 'season_quarter' => 'SS-26',
            'style_number_name' => ['2241405 Solid'], 'job_no' => ['NCL-25-00068'], 'po_number' => ['100214800'], 'colors' => ['Navy'],
            'quantity_ticket' => 2840, 'pac' => 2, 'fob' => 1.69, 'cutting_percent' => 3, 'trims_cost_per_pc' => 0.1,
            'fabric_type' => 'Single Jersey', 'gsm' => '160', 'fabric_finish_type' => 'Peach Finish', 'fabric_description' => 'Pique',
        ], $over), fn ($v) => $v !== null);
    }

    private function assignBuyers(User $u, array $buyers): void
    {
        foreach ($buyers as $b) {
            BuyerUserPermission::query()->create(['company_id' => $this->delta->id, 'kind' => 'MERCHANDISING', 'user_id' => $u->id, 'buyer_id' => $b]);
        }
    }

    private function api(User $user): static
    {
        $this->flushHeaders();
        ['plain' => $plain] = app(IssueApiToken::class)->forUser($user, $this->delta->id, 'Test token');

        return $this->withToken($plain);
    }
}
