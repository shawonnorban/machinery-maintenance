<?php

declare(strict_types=1);

namespace Tests\Feature\PreBudget;

use App\Modules\Api\Actions\IssueApiToken;
use App\Modules\Identity\Models\User;
use App\Modules\Merchandise\Models\Buyer;
use App\Modules\PreBudget\Models\PbChange;
use App\Modules\PreBudget\Models\PbRequisition;
use App\Modules\Tenancy\Models\Company;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\Support\TenantFixture;
use Tests\TestCase;

/**
 * Pre-Budget → Requisition (AnnotechERP 33/34, plan 2026-11-19): one sheet
 * for Knit (kg) and Woven (yds) rows with Norban's formulas as data, a row a
 * Draft until Completed with every required column of its Entry Type (then
 * its serial number), the Knit-only columns kept off a Woven row, the totals
 * row, conflicts, Copy / Delete, who may write, and Column Setup working
 * every requisition out again.
 */
class RequisitionTest extends TestCase
{
    use PreBudgetFixture;
    use RefreshDatabase;

    public function test_a_knit_row_works_norbans_formulas_out_and_completes_with_its_required_columns(): void
    {
        $meta = $this->as()->getJson('/api/v1/pre-budget/meta')->assertOk()->json('data');
        $cols = collect($meta['columns'])->keyBy('key');
        $this->assertSame('KNIT', $cols['yarn_count']['applies_to']);
        $this->assertSame('WOVEN', $cols['fabric_mill']['applies_to']);
        $this->assertSame('{order_qty} * {fob_price}', $cols['fob_value']['formula']);
        $this->assertSame([['value' => 'Knit', 'label' => 'Knit'], ['value' => 'Woven', 'label' => 'Woven']], $meta['sources']['entry_types']);

        // Completing a row that lacks required columns is refused, naming them.
        $this->as()->postJson('/api/v1/pre-budget/requisitions', ['rows' => [['values' => ['entry_type' => 'Knit', 'style' => '2261582']]]])
            ->assertUnprocessable()->assertJsonPath('errors', fn ($e) => str_contains(json_encode($e), 'Row 1 (2261582)') && str_contains(json_encode($e), 'Yarn Count'));
        $this->as()->postJson('/api/v1/pre-budget/requisitions', ['status' => 'COMPLETE', 'rows' => [['values' => ['style' => 'x']]]])->assertUnprocessable()->assertJsonValidationErrors('rows.0.values.entry_type');

        $row = $this->as()->postJson('/api/v1/pre-budget/requisitions', ['status' => 'COMPLETE', 'rows' => [['values' => $this->knit()]]])->assertCreated()->json('data.rows.0');
        $this->assertSame(1, $row['serial_no']);
        $this->assertSame('COMPLETE', $row['status']);
        $this->assertSame('KNIT', $row['entry_type']);
        $v = $row['values'];
        $this->assertEquals(2000, $v['order_qty']);          // 1000 tickets × PAC 2
        $this->assertEquals(7000, $v['fob_value']);          // 2000 × $3.50
        $this->assertEquals(2100, $v['qty_with_percent']);   // 2000 pcs + 5 %
        $this->assertEquals(5.5, $v['cad_with_loss']);       // 5 kg/dz + 10 %
        $this->assertEquals(962.5, $v['bulk_qty']);          // 5.5 / 12 × 100 % × 2100
        $this->assertEquals(982.5, $v['total_req']);         // + 20 kg sample
        $this->assertNotNull(PbRequisition::query()->first()->completed_at);
    }

    public function test_a_woven_row_has_no_yarn_columns(): void
    {
        $woven = ['entry_type' => 'Woven', 'yarn_count' => '30/1', 'yarn_type' => '100% Cotton', 'composition' => '100% Cotton', 'fabric_mill' => 'Huatai Textile', 'width' => '58 inch'] + $this->knit();
        $row = $this->as()->postJson('/api/v1/pre-budget/requisitions', ['rows' => [['values' => $woven]]])->assertCreated()->json('data.rows.0');
        $this->assertSame('WOVEN', $row['entry_type']);
        // The yarn columns are Knit columns: a Woven row keeps none of them, and does not need them to complete.
        $this->assertArrayNotHasKey('yarn_count', $row['values']);
        $this->assertArrayNotHasKey('composition', $row['values']);
        $this->assertSame('Huatai Textile', $row['values']['fabric_mill']);
        $this->assertEquals(982.5, $row['values']['total_req']); // yards, the same formulas
        // Fabrication builds itself from Fabric Type, Fabric Composition and GSM, as in Norban.
        $this->assertSame('Single Jersey 160 GSM', $row['values']['fabrication']);
        $all = $this->as()->getJson('/api/v1/pre-budget/requisitions')->assertOk()->json('data');
        $this->assertEquals(982.5, $all['totals']['total_req']);
        $this->assertEquals(7000, $all['totals']['fob_value']);
    }

    public function test_add_requisition_keeps_itself_as_a_draft_until_completed(): void
    {
        // An auto-save: the page's rows as typed — half done and wrong values too; rows with nothing typed are dropped.
        $first = $this->as()->postJson('/api/v1/pre-budget/drafts', ['client_key' => 'page-1', 'rows' => [
            ['values' => ['entry_type' => 'Knit', 'style' => '2261582', 'brand' => 'Not a brand', 'pac' => 2], 'artwork_url' => '/api/files/x/download'],
            ['values' => ['style' => '', 'pac' => null]],
        ]])->assertOk()->json('data');
        $this->assertSame(1, $first['draft_no']);
        // The same page again (its key): the same draft; nothing typed is not saved.
        $this->as()->postJson('/api/v1/pre-budget/drafts', ['client_key' => 'page-1', 'rows' => [['values' => ['entry_type' => 'Knit', 'style' => '2261582'], 'artwork_url' => null], ['values' => ['style' => '2261513']]]])
            ->assertOk()->assertJsonPath('data.draft_no', 1);
        $this->as()->postJson('/api/v1/pre-budget/drafts', ['rows' => [['values' => ['style' => '']]]])->assertUnprocessable();
        $data = $this->as()->getJson('/api/v1/pre-budget/drafts')->assertOk()->json('data');
        $this->assertTrue($data['all']); // the owner sees every draft, with whose it is
        $list = $data['drafts'];
        $this->assertCount(1, $list);
        $this->assertSame($this->owner->name, $list[0]['owner']);
        $this->assertSame(2, $list[0]['row_count']);
        $this->assertSame(['2261582', '2261513'], $list[0]['styles']);
        $this->assertSame('2261513', $this->as()->getJson('/api/v1/pre-budget/drafts/1')->assertOk()->json('data.rows.1.values.style'));

        // Another person does not see it; a draft they open by number is not found.
        $other = app(IssueApiToken::class)->forUser(TenantFixture::user($this->delta, 'VIEWER', 'merch@delta.test'), $this->delta->id, 'Test')['plain'];
        \Spatie\Permission\Models\Role::query()->whereNull('company_id')->where('name', 'VIEWER')->firstOrFail()->givePermissionTo(['prebudget.view', 'prebudget.create']);
        app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();
        $this->flushHeaders();
        $this->withToken($other)->getJson('/api/v1/pre-budget/drafts')->assertOk()->assertJsonCount(0, 'data.drafts')->assertJsonPath('data.all', false);
        $this->withToken($other)->getJson('/api/v1/pre-budget/drafts/1')->assertNotFound();

        // Complete with the draft's number: the requisitions are saved and the draft is gone.
        $this->as()->postJson('/api/v1/pre-budget/requisitions', ['draft_no' => 1, 'rows' => [['values' => $this->knit()]]])->assertCreated()->assertJsonPath('data.rows.0.serial_no', 1);
        $this->assertSame(0, DB::table('pb_requisition_drafts')->whereNull('deleted_at')->count());
        // A save still on its way to the completed draft is not brought back.
        $this->as()->postJson('/api/v1/pre-budget/drafts', ['draft_no' => 1, 'client_key' => 'page-1', 'rows' => [['values' => ['style' => 'late']]]])->assertStatus(409);

        // A draft can be deleted.
        $no = $this->as()->postJson('/api/v1/pre-budget/drafts', ['client_key' => 'page-2', 'rows' => [['values' => ['style' => 'x']]]])->json('data.draft_no');
        $this->assertSame(2, $no);
        $this->as()->deleteJson("/api/v1/pre-budget/drafts/{$no}")->assertOk();
        $this->as()->getJson("/api/v1/pre-budget/drafts/{$no}")->assertNotFound();
    }

    public function test_changed_rows_save_with_a_version_and_a_completed_row_keeps_its_required_columns(): void
    {
        [$a, $b] = $this->as()->postJson('/api/v1/pre-budget/requisitions', ['status' => 'COMPLETE', 'rows' => [['values' => $this->knit()], ['values' => ['style' => '2261513'] + $this->knit()]]])->json('data.rows');

        $this->as()->patchJson('/api/v1/pre-budget/requisitions', ['rows' => [['id' => $a['id'], 'version' => $a['version'], 'values' => ['pac' => 3]]]])->assertOk()
            ->assertJsonPath('data.rows.0.values.order_qty', 3000);
        // Someone saved row A since; B still goes through.
        $res = $this->as()->patchJson('/api/v1/pre-budget/requisitions', ['rows' => [
            ['id' => $a['id'], 'version' => $a['version'], 'values' => ['pac' => 4]],
            ['id' => $b['id'], 'version' => $b['version'], 'values' => ['fob_price' => 4]],
        ]])->assertOk()->json('data');
        $this->assertCount(1, $res['conflicts']);
        $this->assertEquals(8000, $res['rows'][0]['values']['fob_value']);
        // A completed requisition cannot lose a required column.
        $this->as()->patchJson('/api/v1/pre-budget/requisitions', ['rows' => [['id' => $b['id'], 'version' => $b['version'] + 1, 'values' => ['gsm' => null]]]])->assertUnprocessable();

        // Copy keeps it completed under the next serial; Delete removes it.
        $copy = $this->as()->postJson('/api/v1/pre-budget/requisitions/copy', ['ids' => [$a['id']]])->assertCreated()->json('data.rows.0');
        $this->assertSame(3, $copy['serial_no']);
        $this->as()->deleteJson('/api/v1/pre-budget/requisitions', ['ids' => [$copy['id']]])->assertOk()->assertJsonPath('data.deleted', 1);
        $this->assertSoftDeleted('pb_requisitions', ['id' => $copy['id']]);

        // Header filter values and a filter.
        $distinct = collect($this->as()->getJson('/api/v1/pre-budget/requisitions/distinct?column=style')->assertOk()->json('data'))->pluck('count', 'value')->all();
        $this->assertSame(['2261513' => 1, '2261582' => 1], $distinct);
        $this->assertSame(1, $this->as()->getJson('/api/v1/pre-budget/requisitions?f[style][]=2261513')->json('data.page.total'));
    }

    public function test_who_may_write_and_column_setup_works_every_requisition_out_again(): void
    {
        $this->as()->postJson('/api/v1/pre-budget/requisitions', ['status' => 'COMPLETE', 'rows' => [['values' => $this->knit()]]])->assertCreated();

        $store = app(IssueApiToken::class)->forUser(TenantFixture::user($this->delta, 'STOREKEEPER', 'store@delta.test'), $this->delta->id, 'Test')['plain'];
        $this->withToken($store)->getJson('/api/v1/pre-budget/requisitions')->assertForbidden();
        $this->withToken($store)->postJson('/api/v1/pre-budget/requisitions', ['rows' => [['values' => $this->knit()]]])->assertForbidden();
        $this->withToken($store)->postJson('/api/v1/pre-budget/drafts', ['rows' => [['values' => ['style' => 'x']]]])->assertForbidden();
        $manager = app(IssueApiToken::class)->forUser(TenantFixture::user($this->delta, 'FACTORY_MANAGER', 'manager@delta.test'), $this->delta->id, 'Test')['plain'];
        $this->withToken($manager)->getJson('/api/v1/pre-budget/requisitions')->assertOk()->assertJsonPath('data.page.total', 1);
        $this->withToken($manager)->postJson('/api/v1/pre-budget/columns', ['label' => 'X', 'group_id' => 'x', 'type' => 'text'])->assertForbidden();

        // Column Setup: Total Req without the sample yarn — every requisition worked out again.
        $col = collect($this->as()->getJson('/api/v1/pre-budget/columns')->assertOk()->json('data.columns'))->firstWhere('key', 'total_req');
        $this->as()->patchJson('/api/v1/pre-budget/columns/total_req', ['label' => 'Total Req', 'group_id' => $col['group_id'], 'type' => 'number', 'decimals' => 3, 'formula' => '[Bulk Qty]'])
            ->assertOk()->assertJsonPath('data.recalculated', 1);
        $this->assertEquals(962.5, PbRequisition::query()->first()->values['total_req']);
        // A column can be made a Woven-only one.
        $width = collect($this->as()->getJson('/api/v1/pre-budget/columns')->json('data.columns'))->firstWhere('key', 'order_color');
        $this->as()->patchJson('/api/v1/pre-budget/columns/order_color', ['label' => 'Order Color', 'group_id' => $width['group_id'], 'type' => 'text', 'applies_to' => 'WOVEN'])->assertOk();
        $this->assertSame('WOVEN', collect($this->as()->getJson('/api/v1/pre-budget/meta')->json('data.columns'))->firstWhere('key', 'order_color')['applies_to']);
        // Bold data, as on Order Summary (user, 2026-11-21): a Column Setup switch; a save without it keeps it.
        $this->assertTrue($this->as()->getJson('/api/v1/pre-budget/columns')->json('data.bold'));
        $this->as()->patchJson('/api/v1/pre-budget/columns/order_color', ['label' => 'Order Color', 'group_id' => $width['group_id'], 'type' => 'text', 'applies_to' => 'WOVEN', 'bold' => true])->assertOk();
        $this->as()->patchJson('/api/v1/pre-budget/columns/order_color', ['label' => 'Order Color', 'group_id' => $width['group_id'], 'type' => 'text', 'applies_to' => 'WOVEN'])->assertOk();
        $this->assertTrue(collect($this->as()->getJson('/api/v1/pre-budget/meta')->json('data.columns'))->firstWhere('key', 'order_color')['bold']);
        $this->assertFalse(collect($this->as()->getJson('/api/v1/pre-budget/meta')->json('data.columns'))->firstWhere('key', 'style')['bold']);
    }
}
