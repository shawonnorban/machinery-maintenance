<?php

declare(strict_types=1);

namespace Tests\Feature\DevSample;

use App\Modules\Api\Actions\IssueApiToken;
use App\Modules\DevSample\Models\DevSample;
use App\Modules\DevSample\Models\DsChange;
use App\Modules\DevSample\Support\DsLegacy;
use App\Modules\Identity\Models\User;
use App\Modules\Merchandise\Models\Buyer;
use App\Modules\Merchandise\Models\BuyerUserPermission;
use App\Modules\Notification\Models\Notification;
use App\Modules\Tenancy\Models\Company;
use App\Shared\Scopes\TenantScope;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\Support\TenantFixture;
use Tests\TestCase;

/**
 * Dev Sample Status (AnnotechERP 31, from Norban; Column Setup 2026-11-21):
 * Order Summary's sheet on the samples — new rows numbered, changed rows
 * saved with their version (a stale one comes back as a conflict), a status
 * from its list only, who quoted, the summary cards and header filters, who
 * sees whose samples, Column Setup (a formula column worked out on every
 * sample), the Pivot Report, and a sample kept before Column Setup read
 * into the sheet's values.
 */
class DevSampleTest extends TestCase
{
    use RefreshDatabase;

    private Company $delta;

    private User $owner;

    private User $merchant;

    private User $manager;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(DatabaseSeeder::class);
        $this->delta = TenantFixture::company('Delta Apparels Ltd', 'DAL');
        TenantFixture::actingAsTenant($this->delta);
        $this->owner = TenantFixture::user($this->delta, 'COMPANY_OWNER', 'owner@delta.test');
        $this->manager = TenantFixture::user($this->delta, 'FACTORY_MANAGER', 'manager@delta.test');
        // A merchandiser: enters and edits samples, sees only their own.
        Role::query()->whereNull('company_id')->where('name', 'VIEWER')->firstOrFail()->givePermissionTo(['devsample.view', 'devsample.create', 'devsample.update']);
        app(PermissionRegistrar::class)->forgetCachedPermissions();
        $this->merchant = TenantFixture::user($this->delta, 'VIEWER', 'merchant@delta.test');

        foreach (['HM' => ['H&M Hennes & Mauritz', 'H&M'], 'NXT' => ['Next Retail', 'Next']] as $code => [$name, $short]) {
            Buyer::query()->create(['company_id' => $this->delta->id, 'code' => $code, 'name' => $name, 'short_name' => $short, 'pi_address' => 'EU', 'registered_from' => '2026-01-01', 'active' => true]);
        }
        foreach (['Polo Shirt', 'T-Shirt'] as $i => $item) {
            DB::table('item_types')->insert(['id' => (string) Str::ulid(), 'company_id' => $this->delta->id, 'item_group' => 'TOP', 'code' => "IT{$i}", 'name' => $item, 'sort_order' => $i, 'active' => true, 'created_at' => now(), 'updated_at' => now()]);
        }
    }

    public function test_new_samples_are_numbered_worked_out_and_remember_who_quoted(): void
    {
        $meta = $this->api($this->owner)->getJson('/api/v1/dev-sample/meta')->assertOk()->json('data');
        $keys = array_column($meta['columns'], 'key');
        foreach (['serial_no', 'buyer', 'artwork', 'items', 'sample_status', 'fob_value', 'last_update'] as $key) {
            $this->assertContains($key, $keys);
        }
        $this->assertContains('SUBMISSION PENDING', array_column($meta['sources']['ds:sample_status'], 'value'));
        // Who quoted shows inside Last Price Quote Date's cell (Norban), not in a column of its own.
        $this->assertNotContains('last_price_quote_by', $keys);

        $rows = $this->api($this->owner)->postJson('/api/v1/dev-sample/samples', ['rows' => [['values' => $this->sample(['last_price_quote_date' => '2026-11-10', 'last_price_quote_by' => '  Karim Hasan  '])], ['values' => $this->sample(['buyer' => 'Next'])]]])
            ->assertCreated()->json('data.rows');
        $this->assertSame([1, 2], array_column($rows, 'serial_no'));
        $v = $rows[0]['values'];
        // FOB Value = Possible QTY × FOB Price; the list's label is kept; who quoted is typed by hand (2026-11-21).
        $this->assertEqualsWithDelta(15000.0, $v['fob_value'], 0.001);
        $this->assertSame('SUBMISSION PENDING', $v['sample_status']);
        $this->assertSame(['Polo Shirt', 'T-Shirt'], $v['items']);
        $this->assertSame('Karim Hasan', $v['last_price_quote_by']);
        $this->assertArrayNotHasKey('last_price_quote_by', $rows[1]['values']);
        $moved = $this->api($this->owner)->patchJson('/api/v1/dev-sample/samples', ['rows' => [['id' => $rows[0]['id'], 'version' => 1, 'values' => ['last_price_quote_date' => '2026-11-15']]]])->assertOk()->json('data.rows.0.values');
        $this->assertSame('Karim Hasan', $moved['last_price_quote_by']);
        $this->assertSame(DB::table('buyers')->where('code', 'NXT')->value('id'), DevSample::query()->where('serial_no', 2)->value('buyer_id'));

        // Several colours with their pieces (the same colour twice adds up) and price levels: a formula reads the first level.
        $lines = $this->api($this->owner)->postJson('/api/v1/dev-sample/samples', ['rows' => [['values' => $this->sample([
            'colours' => [['label' => 'Navy', 'value' => 60], ['label' => 'White', 'value' => '40'], ['label' => 'navy', 'value' => 5], ['label' => '', 'value' => null]],
            'target_price' => 'BIO $2.48, CONV $2.30',
            'fob_price' => [['label' => 'BIO', 'value' => 3.2], ['label' => 'CONV', 'value' => 2.9]],
        ])]]])->assertCreated()->json('data.rows.0.values');
        $this->assertSame([['label' => 'Navy', 'value' => 65], ['label' => 'White', 'value' => 40]], $lines['colours']);
        $this->assertSame([['label' => 'BIO', 'value' => 2.48], ['label' => 'CONV', 'value' => 2.3]], $lines['target_price']);
        $this->assertEqualsWithDelta(16000.0, $lines['fob_value'], 0.001);
        $this->api($this->owner)->postJson('/api/v1/dev-sample/samples', ['rows' => [['values' => $this->sample(['colours' => [['label' => 'Navy', 'value' => 'lots']]])]]])->assertUnprocessable()->assertJsonValidationErrors('rows.0.values.colours');
        // A header filter on a colour finds the samples holding it.
        $this->assertSame(3, $this->api($this->owner)->getJson('/api/v1/dev-sample/samples?f[colours][]=Navy')->json('data.page.total'));
        $this->assertSame(0, $this->api($this->owner)->getJson('/api/v1/dev-sample/samples?f[colours][]=Beige')->json('data.page.total'));

        // Not from the list; a required column left empty.
        $this->api($this->owner)->postJson('/api/v1/dev-sample/samples', ['rows' => [['values' => $this->sample(['sample_status' => 'LOST'])]]])->assertUnprocessable()->assertJsonValidationErrors('rows.0.values.sample_status');
        $this->api($this->owner)->postJson('/api/v1/dev-sample/samples', ['rows' => [['values' => $this->sample(['buyer' => null])]]])->assertUnprocessable()->assertJsonValidationErrors('rows.0.values');
    }

    public function test_changed_samples_save_with_their_version_and_a_stale_one_comes_back(): void
    {
        $row = $this->api($this->owner)->postJson('/api/v1/dev-sample/samples', ['rows' => [['values' => $this->sample()]]])->json('data.rows.0');

        $saved = $this->api($this->owner)->patchJson('/api/v1/dev-sample/samples', ['rows' => [['id' => $row['id'], 'version' => 1, 'values' => ['sample_status' => 'SUBMITTED', 'possible_qty' => 1000]]]])
            ->assertOk()->json('data.rows.0');
        $this->assertSame(2, $saved['version']);
        $this->assertSame('SUBMITTED', $saved['values']['sample_status']);
        $this->assertEqualsWithDelta(3000.0, $saved['values']['fob_value'], 0.001);
        $this->assertSame('NW7431 OPT-1', $saved['values']['styling']);
        // Each changed cell is logged, the worked-out one too.
        $this->assertSame(['fob_value', 'possible_qty', 'sample_status'], DsChange::query()->where('kind', 'EDIT')->orderBy('column_key')->pluck('column_key')->all());

        // Saved meanwhile by someone else: it comes back as theirs, untouched.
        $back = $this->api($this->owner)->patchJson('/api/v1/dev-sample/samples', ['rows' => [['id' => $row['id'], 'version' => 1, 'values' => ['remarks' => 'late']]]])->assertOk()->json('data');
        $this->assertSame([], $back['rows']);
        $this->assertSame('SUBMITTED', $back['conflicts'][0]['values']['sample_status']);
        $this->assertArrayNotHasKey('remarks', DevSample::query()->find($row['id'])->values);
    }

    public function test_summary_cards_and_header_filters_follow_the_sheet(): void
    {
        $api = $this->api($this->owner);
        $api->postJson('/api/v1/dev-sample/samples', ['rows' => [
            ['values' => $this->sample(['possible_qty' => 100, 'sample_qty' => 3, 'fob_price' => 4, 'order_confirmation' => 'ORDER CONFIRMED'])],
            ['values' => $this->sample(['possible_qty' => 300, 'sample_qty' => 2, 'fob_price' => 2, 'sample_status' => 'SUBMITTED', 'order_confirmation' => 'SELF DROP'])],
            ['values' => $this->sample(['buyer' => 'Next', 'possible_qty' => null, 'fob_price' => null, 'order_confirmation' => 'DROP'])],
        ]])->assertCreated();

        $data = $api->getJson('/api/v1/dev-sample/samples')->assertOk()->json('data');
        $stats = $data['stats'];
        $this->assertSame(3, $stats['total_records']);
        $this->assertSame(1, $stats['submitted']);
        $this->assertSame(2, $stats['submission_pending']);
        $this->assertSame(2, $stats['order_dropped']);
        $this->assertSame(1, $stats['order_confirmed']);
        $this->assertSame(400, $stats['total_possible_qty']);
        // (100 × 4 + 300 × 2) / 400 = 2.5; FOB value 1,000; the totals row sums the same.
        $this->assertEqualsWithDelta(2.5, $stats['avg_fob_price'], 0.0001);
        $this->assertEqualsWithDelta(1000.0, $stats['total_fob_value'], 0.001);
        $this->assertEqualsWithDelta(1000.0, $data['totals']['fob_value'], 0.001);

        $next = $api->getJson('/api/v1/dev-sample/samples?f[buyer][]=Next')->assertOk()->json('data');
        $this->assertSame(1, $next['stats']['total_records']);
        $this->assertSame(1, $next['page']['total']);
        $this->assertSame(['H&M' => 2, 'Next' => 1], collect($api->getJson('/api/v1/dev-sample/samples/distinct?column=buyer')->json('data'))->pluck('count', 'value')->all());
        $this->assertSame(3, $api->getJson('/api/v1/dev-sample/samples?q=polo')->json('data.page.total'));
    }

    public function test_without_view_all_a_user_sees_and_changes_only_their_own_samples(): void
    {
        $mine = $this->api($this->owner)->postJson('/api/v1/dev-sample/samples', ['rows' => [['values' => $this->sample(['styling' => 'OWNER-1'])]]])->json('data.rows.0');
        $this->api($this->merchant)->postJson('/api/v1/dev-sample/samples', ['rows' => [['values' => $this->sample(['styling' => 'MERCH-1'])]]])->assertCreated()->assertJsonPath('data.rows.0.serial_no', 2);

        $this->assertSame(['MERCH-1'], array_column(array_column($this->api($this->merchant)->getJson('/api/v1/dev-sample/samples')->json('data.rows'), 'values'), 'styling'));
        $this->api($this->merchant)->patchJson('/api/v1/dev-sample/samples', ['rows' => [['id' => $mine['id'], 'version' => 1, 'values' => ['remarks' => 'x']]]])->assertUnprocessable();
        $this->api($this->merchant)->deleteJson('/api/v1/dev-sample/samples', ['ids' => [$mine['id']]])->assertForbidden();
        // The factory manager sees everyone's but may not write.
        $this->assertCount(2, $this->api($this->manager)->getJson('/api/v1/dev-sample/samples')->json('data.rows'));
        $this->api($this->manager)->postJson('/api/v1/dev-sample/samples', ['rows' => [['values' => $this->sample()]]])->assertForbidden();

        $this->api($this->owner)->deleteJson('/api/v1/dev-sample/samples', ['ids' => [$mine['id']]])->assertOk()->assertJsonPath('data.deleted', 1);
        $this->assertSoftDeleted('ds_samples', ['serial_no' => 1]);
        // A deleted number is never given again; Copy numbers on too.
        $copy = $this->api($this->owner)->postJson('/api/v1/dev-sample/samples/copy', ['ids' => [DevSample::query()->where('serial_no', 2)->value('id')]])->assertCreated()->json('data.rows.0');
        $this->assertSame(3, $copy['serial_no']);
        $this->assertSame('MERCH-1', $copy['values']['styling']);
    }

    public function test_column_setup_adds_a_formula_column_worked_out_on_every_sample(): void
    {
        $this->api($this->owner)->postJson('/api/v1/dev-sample/samples', ['rows' => [['values' => $this->sample()], ['values' => $this->sample(['sample_qty' => 10])]]])->assertCreated();
        $setup = $this->api($this->owner)->getJson('/api/v1/dev-sample/columns')->assertOk()->json('data');
        $this->assertTrue($setup['can_manage']);
        $group = collect($setup['groups'])->firstWhere('code', '03')['id'];

        $this->api($this->owner)->postJson('/api/v1/dev-sample/columns/check', ['formula' => '[Sample QTY (Buyer)] * [Nope]', 'type' => 'number'])->assertOk()->assertJsonPath('data.ok', false);
        $this->api($this->owner)->postJson('/api/v1/dev-sample/columns', ['label' => 'Sample Value', 'group_id' => $group, 'type' => 'money', 'decimals' => 2, 'formula' => '[Sample QTY (Buyer)] * [FOB Price ($)]', 'kpi' => 'SUM'])
            ->assertCreated()->assertJsonPath('data.recalculated', 2);
        $values = DevSample::query()->orderBy('serial_no')->get()->pluck('values');
        $this->assertEqualsWithDelta(12.0, $values[0]['sample_value'], 0.001);
        $this->assertEqualsWithDelta(30.0, $values[1]['sample_value'], 0.001);
        $this->assertEqualsWithDelta(42.0, $this->api($this->owner)->getJson('/api/v1/dev-sample/samples')->json('data.totals.sample_value'), 0.001);

        // A starting column is hidden, never deleted; the history keeps both.
        $this->api($this->owner)->deleteJson('/api/v1/dev-sample/columns/remarks')->assertUnprocessable();
        $this->api($this->owner)->patchJson('/api/v1/dev-sample/columns/remarks/visibility', ['hidden' => true])->assertOk();
        $this->assertNotContains('remarks', array_column($this->api($this->owner)->getJson('/api/v1/dev-sample/meta')->json('data.columns'), 'key'));
        $this->assertSame(['HIDE', 'ADD'], array_column($this->api($this->owner)->getJson('/api/v1/dev-sample/columns/history')->json('data'), 'action'));
        $this->api($this->merchant)->postJson('/api/v1/dev-sample/columns', ['label' => 'X', 'group_id' => $group, 'type' => 'text'])->assertForbidden();

        // Bold data, as on Order Summary (user, 2026-11-21): a Column Setup switch, starting off.
        $this->assertTrue($setup['bold']);
        $this->assertFalse(collect($this->api($this->owner)->getJson('/api/v1/dev-sample/meta')->json('data.columns'))->firstWhere('key', 'sample_value')['bold']);
        $this->api($this->owner)->patchJson('/api/v1/dev-sample/columns/sample_value', ['label' => 'Sample Value', 'group_id' => $group, 'type' => 'money', 'decimals' => 2, 'formula' => '[Sample QTY (Buyer)] * [FOB Price ($)]', 'kpi' => 'SUM', 'bold' => true])->assertOk();
        $this->assertTrue(collect($this->api($this->owner)->getJson('/api/v1/dev-sample/meta')->json('data.columns'))->firstWhere('key', 'sample_value')['bold']);
    }

    public function test_pivot_report_counts_by_status_codes_and_each_colour(): void
    {
        $api = $this->api($this->owner);
        $api->postJson('/api/v1/dev-sample/samples', ['rows' => [
            ['values' => $this->sample(['order_confirmation' => 'ORDER CONFIRMED', 'sample_status' => 'SUBMITTED'])],
            ['values' => $this->sample(['order_confirmation' => null, 'colours' => 'Red: 10'])],
        ]])->assertCreated();

        $report = $api->getJson('/api/v1/dev-sample/report?levels[]=color')->assertOk()->json('data');
        $hm = $report['summary']['rows'][0];
        $this->assertSame('H&M', $hm['buyer']);
        $this->assertSame(1, $hm['cells']['ORDER_CONFIRMED']);
        $this->assertSame(1, $hm['cells']['(Blank)']);
        $tree = collect($report['detail']['tree'])->firstWhere('label', 'H&M');
        $this->assertSame(1, $tree['counts']['SUBMITTED']);
        $this->assertSame(['Navy', 'Red', 'White'], array_column($tree['children'], 'label'));
        $this->api($this->merchant)->getJson('/api/v1/dev-sample/report')->assertForbidden();
    }

    public function test_a_sample_kept_before_column_setup_reads_into_the_sheet(): void
    {
        $hm = DB::table('buyers')->where('code', 'HM')->value('id');
        $row = (object) ([
            'season' => 'SS-26', 'buyer_id' => $hm, 'customer_name' => null, 'brand' => null, 'department' => 'Mens', 'division' => null, 'styling' => 'OLD-1', 'job_no' => 'J-1',
            'fabrication' => 'Pique', 'awb_tracking' => null, 'remarks' => null, 'image_file_id' => null,
            'items' => json_encode(['Polo Shirt']), 'colours' => json_encode([['name' => 'Navy', 'qty' => 60], ['name' => 'White', 'qty' => 40]]),
            'required_category' => 'SAMPLE_WITH_PRICE', 'sample_type' => 'PROTO', 'price_status' => 'OFFERED', 'sample_status' => 'SUBMITTED', 'order_confirmation' => null, 'current_order_situation' => null,
            'tp_rcv_date' => '2026-11-01', 'buyer_requested_date' => null, 'submission_deadline' => null, 'sub_plan_date' => null, 'last_price_quote_date' => null, 'last_price_quote_by' => null,
            'sample_qty' => 4, 'possible_qty' => 100, 'actual_order_qty' => null,
            'target_prices' => json_encode([['label' => 'BIO', 'price' => 2.48], ['label' => 'CONV', 'price' => 2.3]]), 'target_price' => 2.3,
            'fob_prices' => json_encode([['label' => 'A', 'price' => 3]]), 'fob_price' => 3,
        ] + array_fill_keys(['booking_date', 'knitting_plan_date', 'actual_grige_rcvd_date', 'finish_fabric_delivery_plan_date', 'finish_fabric_rcvd_date', 'sample_sewing_plan_date', 'file_handover_date', 'pattern_ready_date', 'cutting_date', 'print_emb_date', 'sewing_start_date', 'sewing_output_date', 'sample_submitted_date'], null));

        $v = DsLegacy::values($row);
        $this->assertSame('H&M', $v['buyer']);
        $this->assertSame([['label' => 'Navy', 'value' => 60], ['label' => 'White', 'value' => 40]], $v['colours']);
        $this->assertSame('SUBMITTED', $v['sample_status']);
        // Every price level is a line of the price (the first the summary price).
        $this->assertSame([['label' => 'BIO', 'value' => 2.48], ['label' => 'CONV', 'value' => 2.3]], $v['target_price']);
        $this->assertSame([['label' => 'A', 'value' => 3]], $v['fob_price']);
        $this->assertEqualsWithDelta(300.0, $v['fob_value'], 0.001);

        // A sample of the first Column Setup sheet (text colours, one price, levels in Price Levels).
        $p = DsLegacy::toPairs(['colours' => 'Black: 120, Beige: 80', 'target_price' => 2.48, 'fob_price' => 3.15, 'price_levels' => 'Target: BIO $2.48, CONV $2.3']);
        $this->assertSame([['label' => 'Black', 'value' => 120], ['label' => 'Beige', 'value' => 80]], $p['colours']);
        $this->assertSame([['label' => 'BIO', 'value' => 2.48], ['label' => 'CONV', 'value' => 2.3]], $p['target_price']);
        $this->assertSame([['label' => '', 'value' => 3.15]], $p['fob_price']);
        $this->assertArrayNotHasKey('price_levels', $p);
        $this->assertSame('2026-11-01', $v['tp_rcv_date']);
    }

    /** @param  array<string, mixed>  $over */
    public function test_the_dashboard_buckets_samples_to_submit_by_deadline(): void
    {
        $api = $this->api($this->owner);
        $day = fn (int $n) => now('Asia/Dhaka')->addDays($n)->toDateString();
        $api->postJson('/api/v1/dev-sample/samples', ['rows' => [
            ['values' => $this->sample(['submission_deadline' => $day(-2), 'possible_qty' => 100, 'fob_price' => 4])],
            ['values' => $this->sample(['submission_deadline' => $day(3), 'possible_qty' => 300, 'fob_price' => 2])],
            ['values' => $this->sample(['buyer' => 'Next', 'submission_deadline' => $day(-9), 'sample_status' => 'SUBMITTED', 'buyer_requested_date' => '2026-12-01'])],
        ]])->assertCreated();

        $d = $api->getJson('/api/v1/dev-sample/dashboard')->assertOk()->json('data');
        $this->assertSame(3, $d['stats']['total_records']);
        // Only the two still to submit; the submitted one is past its deadline but done.
        $this->assertSame(['overdue' => 1, 'week' => 1, 'month' => 0, 'later' => 0, 'none' => 0], $d['deadline']);
        $status = collect($d['by_status'])->pluck('samples', 'status');
        $this->assertSame(2, $status['SUBMISSION PENDING']);
        $this->assertSame(1, $status['SUBMITTED']);
        $hm = collect($d['by_buyer'])->firstWhere('buyer', 'H&M');
        $this->assertSame([2, 2, 8], [$hm['samples'], $hm['pending'], $hm['qty']]);
        $this->assertEqualsWithDelta(1000.0, $hm['value'], 0.001);
        $this->assertSame(['2026-11' => 2, '2026-12' => 1], collect($d['by_month'])->pluck('samples', 'month')->all());
        $this->assertSame('CREATE', $d['recent'][0]['kind']);
        $this->assertSame(3, $d['recent'][0]['samples']);
    }

    public function test_a_save_is_told_to_the_author_and_buyer_team_and_deadlines_each_morning(): void
    {
        $notes = fn (User $u, string $event) => Notification::withoutGlobalScope(TenantScope::class)->where('user_id', $u->id)->where('event_type', $event)->orderBy('created_at')->orderBy('id')->get();
        // The factory manager reads every sample and merchandises H&M.
        BuyerUserPermission::query()->create(['company_id' => $this->delta->id, 'kind' => 'MERCHANDISING', 'user_id' => $this->manager->id, 'buyer_id' => Buyer::query()->where('code', 'HM')->value('id')]);
        $day = fn (int $n) => now('Asia/Dhaka')->addDays($n)->toDateString();

        $rows = $this->api($this->merchant)->postJson('/api/v1/dev-sample/samples', ['rows' => [
            ['values' => $this->sample(['styling' => 'LATE-1', 'submission_deadline' => $day(-1)])],
            ['values' => $this->sample(['styling' => 'SOON-1', 'submission_deadline' => $day(1)])],
            ['values' => $this->sample(['styling' => 'LATER-1', 'submission_deadline' => $day(10)])],
        ]])->assertCreated()->json('data.rows');
        // The buyer's merchandiser with view_all hears of the new samples, once; the author does not hear of their own save.
        $heard = $notes($this->manager, 'DS_SAMPLES_CHANGED');
        $this->assertCount(1, $heard);
        $this->assertSame(3, $heard[0]->data_json['n']);
        $this->assertStringContainsString('added 3 sample(s)', $heard[0]->title);
        $this->assertCount(0, $notes($this->merchant, 'DS_SAMPLES_CHANGED'));
        $this->assertCount(0, $notes($this->owner, 'DS_SAMPLES_CHANGED'));

        // Someone else changes the merchandiser's sample: the author hears it.
        $this->api($this->owner)->patchJson('/api/v1/dev-sample/samples', ['rows' => [['id' => $rows[0]['id'], 'version' => $rows[0]['version'], 'values' => ['remarks' => 'Fabric late']]]])->assertOk();
        $mine = $notes($this->merchant, 'DS_SAMPLES_CHANGED');
        $this->assertCount(1, $mine);
        $this->assertSame('#1 LATE-1', $mine[0]->data_json['samples']);
        $this->assertSame(1, $mine[0]->data_json['cells']);

        // The morning: one past its deadline, one due tomorrow; the one in 10 days waits.
        $this->artisan('erp:notify-pending-work', ['--company' => 'DAL'])->assertSuccessful();
        $due = $notes($this->merchant, 'DS_DEADLINE_DUE');
        $this->assertCount(1, $due);
        $this->assertSame([1, 1, '#1, #2'], [$due[0]->data_json['overdue'], $due[0]->data_json['soon'], $due[0]->data_json['samples']]);
        $this->assertSame('CRITICAL', $due[0]->severity);
    }

    private function sample(array $over = []): array
    {
        return array_merge([
            'season' => 'SS-26', 'buyer' => 'H&M', 'department' => 'Mens', 'styling' => 'NW7431 OPT-1', 'job_no' => 'NCL-SMN-26-00390',
            'items' => ['Polo Shirt', 'T-Shirt'], 'required_category' => 'SAMPLE WITH PRICE', 'sample_type' => 'Proto/Initial Development', 'fabrication' => 'Pique, 210 GSM',
            'colours' => 'Navy: 60, White: 40', 'buyer_requested_date' => '2026-11-20', 'submission_deadline' => '2026-11-25',
            'sample_qty' => 4, 'possible_qty' => 5000, 'target_price' => 2.5, 'fob_price' => 3,
            'price_status' => 'OFFERED PRICE', 'sample_status' => 'SUBMISSION PENDING', 'order_confirmation' => 'CONFIRMATION PENDING', 'current_order_situation' => 'On Knitting Stage',
        ], $over);
    }

    private function api(User $user): static
    {
        $this->flushHeaders();
        ['plain' => $plain] = app(IssueApiToken::class)->forUser($user, $this->delta->id, 'Test token');

        return $this->withToken($plain);
    }
}
