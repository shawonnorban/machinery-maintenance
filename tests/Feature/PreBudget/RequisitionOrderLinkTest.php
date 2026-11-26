<?php

declare(strict_types=1);

namespace Tests\Feature\PreBudget;

use App\Modules\Merchandise\Models\Buyer;
use App\Modules\OrderSummary\Models\OsOrder;
use App\Modules\PreBudget\Models\PbRequisition;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Order Summary → Add Requisition (user, 2026-11-24): the order picker lists
 * the user's open orders with no requisition (not DROPPED, Cancelled or
 * shipped out), a picked order fills its cells on the row and they stay as
 * the order has them, one order gets one requisition (its rows: one per
 * part), and an order whose requisition is deleted can be picked again.
 */
class RequisitionOrderLinkTest extends TestCase
{
    use PreBudgetFixture;
    use RefreshDatabase;

    private int $ssl = 0;

    public function test_the_picker_lists_open_orders_and_a_picked_order_fills_and_locks_its_cells(): void
    {
        $open = $this->order(['po_wise_rfi_sd' => '2026-12-01']);
        $noJob = $this->order(['style_number_name' => ['2274448 Golf Pant'], 'job_no' => null, 'brand' => null, 'po_wise_rfi_sd' => '2026-12-20']);
        $this->order(['order_type' => 'DROPPED']);
        $this->order(['order_type' => 'Cancelled']);
        $this->order(['shipment_status' => 'Ship Out']);

        // Open orders only, the most urgent RFI first; style · season · JOB No for the list.
        $list = $this->as()->getJson('/api/v1/pre-budget/orders')->assertOk()->json('data.orders');
        $this->assertSame([$open->id, $noJob->id], array_column($list, 'id'));
        $this->assertSame(['2261582 Basic Tee', 'SS-26', '26NCL00415'], [$list[0]['style'], $list[0]['season'], $list[0]['job_no']]);
        $this->assertNull($list[1]['job_no']);

        // A picked order: its cells for the row (Order Booking Date stays typed).
        $pick = $this->as()->postJson('/api/v1/pre-budget/orders/pick', ['order_id' => $open->id])->assertOk()->json('data');
        $v = $pick['values'];
        $this->assertSame(['NCL', 'C&A', 'Clockhouse', 'SS-26', 'Mens', '2261582 Basic Tee', 'Confirmed PO'], [$v['factory'], $v['buyer'], $v['brand'], $v['season'], $v['dept'], $v['style'], $v['order_type']]);
        $this->assertSame(['26NCL00415'], $v['job_no']);
        $this->assertSame('NCL/C&A/001, NCL/C&A/002', $v['contract_no']);
        $this->assertSame(['2027-01-05', '2026-12-01', '2026-11-10'], [$v['delivery_date'], $v['po_wise_rfi_sd_date'], $v['yarn_booking_date']]);
        $this->assertSame(['T-Shirt', 'Normal Wash', 1000, 2, 3.5], [$v['item'], $v['wash_type'], $v['quantity_ticket'], $v['pac'], $v['fob_price']]);
        $this->assertSame('Knit', $v['entry_type']); // from the order's Fabric Category
        $this->assertArrayNotHasKey('order_booking_date', $v);
        $this->assertContains('brand', $pick['locked']);
        $this->assertNotContains('entry_type', $pick['locked']);
        $meta = $this->as()->getJson('/api/v1/pre-budget/meta')->json('data');
        $this->assertContains('quantity_ticket', $meta['order_keys']);
        $this->assertNotContains('order_booking_date', $meta['order_keys']);

        $closed = OsOrder::query()->where('values->order_type', 'DROPPED')->first();
        $this->as()->postJson('/api/v1/pre-budget/orders/pick', ['order_id' => $closed->id])->assertUnprocessable()
            ->assertJsonPath('errors.order_id.0', fn ($m) => str_contains($m, 'DROPPED'));

        // Complete: two rows (two parts) of the order. The order's cells are the order's, whatever the page sent.
        $row = fn (array $own) => ['order_id' => $open->id, 'values' => array_merge($this->knit(), $own)];
        $saved = $this->as()->postJson('/api/v1/pre-budget/requisitions', ['rows' => [$row(['style' => 'Typed over', 'pac' => 9]), $row(['parts' => 'Body'])]])
            ->assertCreated()->json('data.rows');
        $this->assertSame([$open->id, $open->id], array_column($saved, 'order_id'));
        $this->assertSame($open->serial_no, $saved[0]['order_ssl']);
        $this->assertSame('2261582 Basic Tee', $saved[0]['values']['style']);
        $this->assertEquals(2, $saved[0]['values']['pac']);
        $this->assertEquals(2000, $saved[0]['values']['order_qty']);
        $this->assertSame(['26NCL00415'], $saved[0]['values']['job_no']);
        // The order's quantity and price sit on its first row; its other part's row carries none (and need not).
        $this->assertArrayNotHasKey('quantity_ticket', $saved[1]['values']);
        $this->assertArrayNotHasKey('fob_value', $saved[1]['values']);
        $this->assertSame('2261582 Basic Tee', $saved[1]['values']['style']);
        $this->assertNotContains('pac', $saved[1]['order_locked']);
        $this->assertContains('pac', $saved[0]['order_locked']);

        // One order, one requisition: it leaves the list, and cannot be completed again.
        $this->assertSame([$noJob->id], array_column($this->as()->getJson('/api/v1/pre-budget/orders')->json('data.orders'), 'id'));
        $this->as()->postJson('/api/v1/pre-budget/requisitions', ['rows' => [$row([])]])->assertUnprocessable()
            ->assertJsonPath('errors', fn ($e) => str_contains(json_encode($e, JSON_UNESCAPED_UNICODE), 'already has a requisition'));

        // In All Requisition the order's cells stay locked; the others change as before.
        $r = PbRequisition::query()->orderBy('serial_no')->first();
        $this->as()->patchJson('/api/v1/pre-budget/requisitions', ['rows' => [['id' => $r->id, 'version' => $r->version, 'values' => ['style' => 'Changed']]]])
            ->assertUnprocessable()->assertJsonPath('errors', fn ($e) => str_contains(json_encode($e), 'comes from Order Summary (SSL '.$open->serial_no.')'));
        $this->as()->patchJson('/api/v1/pre-budget/requisitions', ['rows' => [['id' => $r->id, 'version' => $r->version, 'values' => ['style' => '2261582 Basic Tee', 'cutting_percent' => 7]]]])
            ->assertOk()->assertJsonPath('data.rows.0.values.cutting_percent', 7);

        // A cell the order leaves empty (Brand, JOB No not yet there) is typed on the requisition and stays open.
        $this->assertNotContains('brand', $this->as()->postJson('/api/v1/pre-budget/orders/pick', ['order_id' => $noJob->id])->json('data.locked'));
        $typed = $this->as()->postJson('/api/v1/pre-budget/requisitions', ['rows' => [['order_id' => $noJob->id, 'values' => ['job_no' => null] + $this->knit()]]])->assertCreated()->json('data.rows.0');
        $this->assertSame(['Clockhouse', '2274448 Golf Pant'], [$typed['values']['brand'], $typed['values']['style']]);
        $this->assertNotContains('brand', $typed['order_locked']);
        $this->assertContains('style', $typed['order_locked']);
        $t = PbRequisition::query()->whereKey($typed['id'])->first();
        $this->as()->patchJson('/api/v1/pre-budget/requisitions', ['rows' => [['id' => $t->id, 'version' => $t->version, 'values' => ['brand' => null]]]])
            ->assertUnprocessable()->assertJsonPath('errors', fn ($e) => str_contains(json_encode($e), 'Brand')); // still required, but its own to change
        $this->as()->deleteJson('/api/v1/pre-budget/requisitions', ['ids' => [$t->id]])->assertOk();

        // Its requisition deleted, the order can be picked again.
        $this->as()->deleteJson('/api/v1/pre-budget/requisitions', ['ids' => array_column($saved, 'id')])->assertOk();
        $this->assertSame([$open->id, $noJob->id], array_column($this->as()->getJson('/api/v1/pre-budget/orders')->json('data.orders'), 'id'));
    }

    public function test_a_draft_keeps_the_order_and_another_buyers_orders_are_not_listed(): void
    {
        $open = $this->order([]);
        $this->as()->postJson('/api/v1/pre-budget/drafts', ['client_key' => 'p', 'rows' => [
            ['values' => ['style' => '2261582 Basic Tee'], 'order' => ['id' => $open->id, 'ssl' => $open->serial_no, 'style' => '2261582 Basic Tee', 'job_no' => '26NCL00415', 'extra' => 'dropped']],
        ]])->assertOk();
        $kept = $this->as()->getJson('/api/v1/pre-budget/drafts/1')->json('data.rows.0.order');
        $this->assertSame(['id' => $open->id, 'ssl' => (string) $open->serial_no, 'style' => '2261582 Basic Tee', 'job_no' => '26NCL00415'], $kept);
        // Completed by the page's own key (a save whose number the page never saw): the draft goes all the same.
        $this->as()->postJson('/api/v1/pre-budget/drafts', ['client_key' => 'page-k', 'rows' => [['values' => ['style' => 'X-1']]]])->assertOk();
        $this->as()->postJson('/api/v1/pre-budget/requisitions', ['client_key' => 'page-k', 'rows' => [['values' => ['style' => 'X-1'] + $this->knit()]]])->assertCreated();
        $this->assertSame(0, \Illuminate\Support\Facades\DB::table('pb_requisition_drafts')->where('client_key', 'page-k')->whereNull('deleted_at')->count());
        $this->assertSame(1, \Illuminate\Support\Facades\DB::table('pb_requisition_drafts')->whereNull('deleted_at')->count()); // the other draft stays

        // An order value the requisition lists do not hold (a buyer missing from the master): said on pick, refused on Complete.
        $this->assertNull($this->as()->postJson('/api/v1/pre-budget/orders/pick', ['order_id' => $open->id])->json('data.warning'));
        $odd = $this->order(['buyer' => 'C&A Difuzed']);
        $this->assertStringContainsString('Buyer “C&A Difuzed”', $this->as()->postJson('/api/v1/pre-budget/orders/pick', ['order_id' => $odd->id])->assertOk()->json('data.warning'));
        $this->as()->postJson('/api/v1/pre-budget/requisitions', ['rows' => [['order_id' => $odd->id, 'values' => $this->knit()]]])->assertUnprocessable()
            ->assertJsonPath('errors', fn ($e) => str_contains(json_encode($e, JSON_UNESCAPED_UNICODE), 'Correct them in Order Summary'));

        // A merchandiser reaches only their buyers' orders, as in Order Summary.
        $merch = \Tests\Support\TenantFixture::user($this->delta, 'VIEWER', 'merch@delta.test');
        \Spatie\Permission\Models\Role::query()->whereNull('company_id')->where('name', 'VIEWER')->firstOrFail()->givePermissionTo(['prebudget.view', 'prebudget.create']);
        app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();
        $this->flushHeaders();
        $token = app(\App\Modules\Api\Actions\IssueApiToken::class)->forUser($merch, $this->delta->id, 'Test')['plain'];
        $this->withToken($token)->getJson('/api/v1/pre-budget/orders')->assertOk()->assertJsonCount(0, 'data.orders');
        $this->withToken($token)->postJson('/api/v1/pre-budget/orders/pick', ['order_id' => $open->id])->assertUnprocessable();
    }

    public function test_order_summary_shows_each_orders_budget_status_and_filters_by_it(): void
    {
        $a = $this->order([]);
        $b = $this->order(['style_number_name' => ['2274448 Golf Pant']]);
        $status = fn () => collect($this->as()->getJson('/api/v1/order-summary/orders?sort=serial_no&dir=asc')->assertOk()->json('data.rows'))->mapWithKeys(fn ($r) => [$r['serial_no'] => $r['values']['budget_status'] ?? null])->all();
        $meta = collect($this->as()->getJson('/api/v1/order-summary/meta')->json('data.columns'))->keyBy('key');
        $this->assertSame('system', $meta['budget_status']['type']);
        $this->assertSame('Budget Status', $meta['budget_status']['label']);
        $this->assertSame([1 => null, 2 => null], $status());

        // A completed requisition: Requisition. Then the budget it goes into: Budget Draft.
        $ids = array_column($this->as()->postJson('/api/v1/pre-budget/requisitions', ['rows' => [['order_id' => $a->id, 'values' => $this->knit()], ['order_id' => $a->id, 'values' => $this->knit()]]])->assertCreated()->json('data.rows'), 'id');
        $this->as()->postJson('/api/v1/pre-budget/requisitions', ['rows' => [['order_id' => $b->id, 'values' => $this->knit()]]])->assertCreated();
        $this->assertSame([1 => 'Requisition', 2 => 'Requisition'], $status());
        $no = $this->as()->postJson('/api/v1/pre-budget/budgets', ['requisition_ids' => $ids])->assertCreated()->json('data.budget_no');
        $this->assertSame([1 => 'Budget Draft', 2 => 'Requisition'], $status());

        // Waiting for approval, approved; a revision in the approved one's place shows as its own draft.
        $budget = \App\Modules\PreBudget\Models\PbBudget::query()->where('budget_no', $no)->firstOrFail();
        $budget->forceFill(['status' => 'PENDING'])->save();
        $this->assertSame('Budget Submitted', $status()[1]);
        $budget->forceFill(['status' => 'APPROVED'])->save();
        $this->assertSame('Budget Approved', $status()[1]);

        // The header filter lists the statuses and narrows by one; the sheet sorts by it; the column is not typed into.
        $this->assertSame([['value' => 'Budget Approved', 'count' => 1], ['value' => 'Requisition', 'count' => 1]], $this->as()->getJson('/api/v1/order-summary/orders/distinct?column=budget_status')->json('data'));
        $this->assertSame([1], array_column($this->as()->getJson('/api/v1/order-summary/orders?f[budget_status][]=Budget+Approved')->json('data.rows'), 'serial_no'));
        $this->assertSame([2, 1], array_column($this->as()->getJson('/api/v1/order-summary/orders?sort=budget_status&dir=desc')->json('data.rows'), 'serial_no'));
        $row = OsOrder::query()->where('serial_no', 2)->first();
        $saved = $this->as()->patchJson('/api/v1/order-summary/orders', ['rows' => [['id' => $row->id, 'version' => $row->version, 'values' => ['budget_status' => 'Budget Approved', 'remarks' => 'checked']]]])->assertOk()->json('data.rows.0.values');
        $this->assertSame(['Requisition', 'checked'], [$saved['budget_status'], $saved['remarks']]);
        $this->assertArrayNotHasKey('budget_status', OsOrder::query()->find($row->id)->values);
    }

    public function test_an_orders_change_follows_its_requisition_and_cancels_a_sent_budget(): void
    {
        $a = $this->order([]);
        $ids = array_column($this->as()->postJson('/api/v1/pre-budget/requisitions', ['rows' => [['order_id' => $a->id, 'values' => $this->knit()], ['order_id' => $a->id, 'values' => $this->knit()]]])->assertCreated()->json('data.rows'), 'id');
        $no = $this->as()->postJson('/api/v1/pre-budget/budgets', ['requisition_ids' => $ids])->assertCreated()->json('data.budget_no');
        $budget = fn () => \App\Modules\PreBudget\Models\PbBudget::query()->where('budget_no', explode('-R', $no)[0])->orderBy('revision_no')->get();
        $req = fn () => PbRequisition::query()->whereKey($ids[0])->first()->values;
        $edit = function (array $values, ?string $token = null) use ($a) {
            $o = OsOrder::query()->find($a->id);
            $this->flushHeaders();
            $call = $token ? $this->withToken($token) : $this->as();

            return $call->patchJson('/api/v1/order-summary/orders', ['rows' => [['id' => $o->id, 'version' => $o->version, 'values' => $values]]]);
        };

        // JOB No and dates follow the order at once, never cancelling.
        $edit(['job_no' => ['26NCL00999'], 'shipout_date' => '2027-02-01'])->assertOk()->assertJsonPath('data.notice', null);
        $this->assertSame(['26NCL00999'], $req()['job_no']);
        $this->assertSame('2027-02-01', $req()['delivery_date']);
        // A draft budget is not cancelled by a new PAC: it works with the new values.
        $edit(['pac' => 3])->assertOk()->assertJsonPath('data.notice', null);
        $this->assertEquals(3000, $req()['order_qty']);
        $this->assertSame(['DRAFT'], $budget()->pluck('status')->all());

        // Approved: a new FOB needs the permission to re-budget …
        $budget()->first()->forceFill(['status' => 'APPROVED'])->save();
        $clerk = \Tests\Support\TenantFixture::user($this->delta, 'VIEWER', 'clerk@delta.test');
        \Spatie\Permission\Models\Role::query()->whereNull('company_id')->where('name', 'VIEWER')->firstOrFail()->givePermissionTo(['ordersummary.view', 'ordersummary.view_all', 'ordersummary.update']);
        app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();
        $token = app(\App\Modules\Api\Actions\IssueApiToken::class)->forUser($clerk, $this->delta->id, 'Test')['plain'];
        $edit(['fob' => 3.8], $token)->assertUnprocessable()->assertJsonPath('errors', fn ($e) => str_contains(json_encode($e), 'cancels that budget and needs the permission to re-budget'));
        $this->assertEquals(3.5, $req()['fob_price']);

        // … and with it the approved budget is cancelled (kept, with why) and its revision made as a draft.
        $notice = $edit(['fob' => 3.8, 'remarks' => 'buyer raised the price'])->assertOk()->json('data.notice');
        $this->assertStringContainsString("Budget {$no} is cancelled; its revision {$no}-R1 is a draft", $notice);
        [$old, $rev] = $budget()->all();
        $this->assertSame(['CANCELLED', 'DRAFT'], [$old->status, $rev->status]);
        $this->assertStringContainsString('3.5 → 3.8 (Order Summary SSL '.$a->serial_no.', 2261582 Basic Tee)', $old->cancel_reason);
        $this->assertNotNull($old->cancelled_at);
        $this->assertEqualsCanonicalizing($ids, $rev->requisitions()->pluck('pb_requisitions.id')->all());
        $this->assertEquals(3.8, $req()['fob_price']);
        $this->assertSame('Budget Draft', $this->as()->getJson('/api/v1/order-summary/orders')->json('data.rows.0.values.budget_status'));
        $shown = $this->as()->getJson('/api/v1/pre-budget/budgets/'.rawurlencode($no))->assertOk()->json('data');
        $this->assertSame('CANCELLED', $shown['status']);
        $this->assertStringContainsString('3.5 → 3.8', $shown['cancelled']['reason']);
        $this->assertFalse($shown['editable']);

        // The order dropped while the revision is approved: cancelled, nothing left to budget again.
        $rev->forceFill(['status' => 'APPROVED'])->save();
        \App\Modules\PreBudget\Support\MerchLists::ensure($this->delta->id, 'ORDER_TYPE', 'DROPPED');
        $edit(['order_type' => 'DROPPED'])->assertOk()->assertJsonPath('data.notice', fn ($n) => str_contains($n, "{$no}-R1 is cancelled (nothing left"));
        $this->assertSame(['CANCELLED', 'CANCELLED'], $budget()->pluck('status')->all());
        $this->assertSame('Budget Cancelled', $this->as()->getJson('/api/v1/order-summary/orders')->json('data.rows.0.values.budget_status'));

        // An order with a requisition is not deleted.
        $this->as()->deleteJson('/api/v1/order-summary/orders', ['ids' => [$a->id]])->assertUnprocessable()
            ->assertJsonPath('errors.ids.0', fn ($m) => str_contains($m, 'SSL '.$a->serial_no.' has a requisition'));
    }

    public function test_old_requisitions_are_linked_to_their_order_only_when_sure(): void
    {
        $sure = $this->order(['style_number_name' => ['2261582'], 'job_no' => ['26NCL00415']]);
        $this->order(['style_number_name' => ['2261582'], 'job_no' => ['26NCL00500']]); // the same style and season, another JOB No
        $this->order(['style_number_name' => ['2274448'], 'job_no' => null]);
        $this->order(['style_number_name' => ['2274448'], 'job_no' => null]); // two such orders: not guessed
        $make = fn (array $v) => $this->as()->postJson('/api/v1/pre-budget/requisitions', ['rows' => [['values' => $v + $this->knit()], ['values' => $v + $this->knit()]]])->assertCreated()->json('data.rows');
        $a = $make(['job_no' => ['26NCL00415']]);                 // style 2261582, season SS-26: one order with that JOB No
        $b = $make(['style' => '2274448']);                        // two orders, no JOB No to tell them apart
        $c = $make(['style' => 'NOT-IN-ORDERS']);

        $this->artisan('pb:link-orders', ['company' => 'DAL'])->assertSuccessful()->expectsOutputToContain('2 sure match(es) (run with --apply to link)');
        $this->assertSame(0, PbRequisition::query()->whereNotNull('os_order_id')->count());
        $this->artisan('pb:link-orders', ['company' => 'DAL', '--apply' => true])->assertSuccessful()->expectsOutputToContain('2 sure match(es) linked, 4 left as they are');
        $this->assertSame([$sure->id, $sure->id], PbRequisition::query()->whereKey(array_column($a, 'id'))->pluck('os_order_id')->all());
        $this->assertSame(0, PbRequisition::query()->whereKey([...array_column($b, 'id'), ...array_column($c, 'id')])->whereNotNull('os_order_id')->count());
        // Linked, the order leaves the picker and shows its status.
        $this->assertNotContains($sure->id, array_column($this->as()->getJson('/api/v1/pre-budget/orders')->json('data.orders'), 'id'));
        $this->assertSame('Requisition', collect($this->as()->getJson('/api/v1/order-summary/orders')->json('data.rows'))->firstWhere('id', $sure->id)['values']['budget_status']);
    }

    public function test_a_budget_counts_each_orders_quantity_and_value_once_however_many_parts(): void
    {
        $a = $this->order([]);
        // Three parts (Body, Rib, Pocket) of one order, and one typed row of another style.
        $linked = array_column($this->as()->postJson('/api/v1/pre-budget/requisitions', ['rows' => array_map(fn ($part) => ['order_id' => $a->id, 'values' => ['parts' => $part] + $this->knit()], ['Body', 'Body', 'Body'])])->assertCreated()->json('data.rows'), 'id');
        // A typed style: its first row with the quantity and price, a second part's row (copied) without them.
        $part = array_diff_key(['style' => 'OTHER-1', 'parts' => 'Body'] + $this->knit(), array_flip(['quantity_ticket', 'pac', 'fob_price']));
        $this->as()->postJson('/api/v1/pre-budget/requisitions', ['rows' => [['values' => $part]]])->assertUnprocessable(); // alone it lacks them
        [$typed, $typedPart] = array_column($this->as()->postJson('/api/v1/pre-budget/requisitions', ['rows' => [['values' => ['style' => 'OTHER-1'] + $this->knit()], ['values' => $part]]])->assertCreated()->json('data.rows'), 'id');
        // Edited later, the part's row still needs no quantity of its own.
        $pr = PbRequisition::query()->find($typedPart);
        $this->as()->patchJson('/api/v1/pre-budget/requisitions', ['rows' => [['id' => $pr->id, 'version' => $pr->version, 'values' => ['cutting_percent' => 6]]]])->assertOk();
        $no = $this->as()->postJson('/api/v1/pre-budget/budgets', ['requisition_ids' => [...$linked, $typed, $typedPart]])->assertCreated()->json('data.budget_no');
        $b = $this->as()->getJson('/api/v1/pre-budget/budgets/'.rawurlencode($no))->assertOk()->json('data');
        // The order's 2,000 pcs / $7,000 once (not three times), plus the typed row's own.
        $this->assertEquals(4000, $b['fob']['order_qty']);
        $this->assertEquals(14000, $b['fob']['gross']);
        $this->assertEquals(4000, $b['item_summary']['total']['order_qty']);
        $this->assertEquals([2000, 2000], array_column($b['costing']['styles'], 'order_qty'));
        // The Accessories Budget sees the same quantities.
        $acc = $this->as()->getJson('/api/v1/pre-budget/budgets/'.rawurlencode($no).'/accessories')->assertOk()->json('data.budget_styles');
        $this->assertEquals([2000, 2000], array_column($acc, 'order_qty'));
        $this->assertEquals([7000, 7000], array_column($acc, 'style_value'));
    }

    /** An Order Summary row (stored straight, as the sheet keeps it). */
    private function order(array $over): OsOrder
    {
        $values = array_merge([
            'factory' => 'NCL', 'buyer' => 'C&A', 'brand' => 'Clockhouse', 'season_quarter' => 'SS-26', 'department' => 'Mens', 'style_number_name' => ['2261582 Basic Tee'],
            'order_type' => 'Confirmed PO', 'job_no' => ['26NCL00415'], 'commercial_contract_number' => ['NCL/C&A/001', 'NCL/C&A/002'], 'shipout_date' => '2027-01-05',
            'po_wise_rfi_sd' => '2026-12-05', 'yarn_booking_date' => '2026-11-10', 'item' => 'T-Shirt', 'wash_info_at_garmenting' => 'Normal Wash',
            'quantity_ticket' => 1000, 'pac' => 2, 'quantity_pcs' => 2000, 'fob' => 3.5, 'fabric_category' => 'Knit', 'shipment_status' => 'Shipment Pending',
        ], $over);

        return OsOrder::query()->create([
            'serial_no' => ++$this->ssl, 'buyer_id' => Buyer::query()->where('short_name', 'C&A')->value('id'),
            'values' => array_filter($values, fn ($v) => $v !== null), 'version' => 1,
        ]);
    }
}
