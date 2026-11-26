<?php

declare(strict_types=1);

namespace Tests\Feature\Merchandise;

use App\Modules\Api\Actions\IssueApiToken;
use App\Modules\Identity\Models\User;
use App\Modules\MaterialProcurement\Models\MaterialSupplier;
use App\Modules\Merchandise\Models\GarmentPart;
use App\Modules\Merchandise\Models\OriginCountry;
use App\Modules\Tenancy\Models\Company;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\TenantFixture;
use Tests\TestCase;

/**
 * Merchandise Desk → Inquiry (AnnotechERP 06/05): Price Quotation Entry — an
 * inquiry numbered and moved from draft through quotation sent and revised to
 * confirmed or cancelled, never back; Buyer Rates read from the rate chart in
 * force on the inquiry date — and Style Development Sheet — a style registered
 * against a confirmed inquiry of its buyer, with size range lines from Size
 * Master.
 */
class MerchandiseInquiryTest extends TestCase
{
    use RefreshDatabase;

    private Company $delta;

    private User $owner;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(DatabaseSeeder::class);
        $this->delta = TenantFixture::company('Delta Apparels Ltd', 'DAL');
        TenantFixture::factory($this->delta, 'Dhaka Unit 1', 'DHK');
        TenantFixture::actingAsTenant($this->delta);
        $this->owner = TenantFixture::user($this->delta, 'COMPANY_OWNER', 'owner@delta.test');
    }

    public function test_an_inquiry_is_numbered_and_moves_one_way(): void
    {
        [$buyer] = $this->buyer();
        $inquiry = ['buyer_id' => $buyer, 'style_no' => 'AN-01', 'style_desc' => 'Mens T Shirt', 'inquiry_date' => '2026-10-12', 'quoted_fob' => 3.45];

        // Brickland's message for a missing date.
        $this->as()->postJson('/api/v1/erp/merch-inquiries', array_diff_key($inquiry, ['inquiry_date' => 1]))->assertUnprocessable()->assertJsonPath('errors.inquiry_date.0', 'Inquiry DT is required.');
        $first = $this->as()->postJson('/api/v1/erp/merch-inquiries', $inquiry)->assertCreated()
            ->assertJsonPath('data.inquiry_no', 'INQ-'.now()->format('Y').'0001')
            ->assertJsonPath('data.status', 'DRAFT')->assertJsonPath('data.inco_term', 'FOB')->assertJsonPath('data.calc_unit', 'DZ')->assertJsonPath('data.buyer', 'LPP')
            ->json('data.id');
        $second = $this->as()->postJson('/api/v1/erp/merch-inquiries', ['style_no' => 'AN-02'] + $inquiry)->assertCreated()->assertJsonPath('data.inquiry_no', 'INQ-'.now()->format('Y').'0002')->json('data.id');

        $this->as()->postJson("/api/v1/merchandise/inquiries/{$first}/send")->assertOk()->assertJsonPath('data.inquiry.status', 'QUOTATION_SENT')->assertJsonPath('data.message', 'Quotation sent for INQ-'.now()->format('Y').'0001');
        $this->as()->postJson("/api/v1/merchandise/inquiries/{$first}/revise")->assertOk()->assertJsonPath('data.inquiry.status', 'REVISED')->assertJsonPath('data.inquiry.revision', 1);
        $this->as()->patchJson("/api/v1/erp/merch-inquiries/{$first}", ['quoted_fob' => 3.2])->assertOk()->assertJsonPath('data.quoted_fob', 3.2);
        $this->as()->postJson("/api/v1/merchandise/inquiries/{$first}/confirm")->assertOk()->assertJsonPath('data.inquiry.status', 'CONFIRMED');

        // Confirming is the point of no return: no revision, no cancelling, no resending, no editing.
        foreach (['revise', 'cancel', 'send', 'confirm'] as $move) {
            $this->as()->postJson("/api/v1/merchandise/inquiries/{$first}/{$move}")->assertUnprocessable()->assertJsonValidationErrors('status');
        }
        $this->as()->patchJson("/api/v1/erp/merch-inquiries/{$first}", ['quoted_fob' => 9])->assertUnprocessable();

        $this->as()->postJson("/api/v1/merchandise/inquiries/{$second}/cancel")->assertOk()->assertJsonPath('data.inquiry.status', 'CANCELLED');
        $this->as()->postJson("/api/v1/merchandise/inquiries/{$second}/confirm")->assertUnprocessable()->assertJsonPath('errors.status.0', 'INQ-'.now()->format('Y').'0002 is cancelled.');

        // The Quotation panel: buyers with inquiries, then one buyer's.
        $this->as()->getJson('/api/v1/merchandise/inquiries/buyers')->assertOk()->assertJsonPath('data.0.label', 'LPP')->assertJsonPath('data.0.count', 2);
        $this->assertCount(2, $this->as()->getJson("/api/v1/merchandise/inquiries/buyers/{$buyer}")->json('data'));
        $this->as()->postJson("/api/v1/merchandise/inquiries/{$first}/archive")->assertNotFound();
    }

    public function test_buyer_rates_are_the_chart_in_force_on_the_inquiry_date(): void
    {
        [$buyer, $office] = $this->buyer();
        $line = $this->as()->postJson('/api/v1/erp/rate-lines', ['rate_group' => 'KNITTING', 'description' => 'Annotech S/Jersey', 'uom' => 'Kg'])->assertCreated()->json('data.id');
        $this->as()->putJson("/api/v1/merchandise/rate-chart/{$office}", ['rates' => [['rate_line_id' => $line, 'rate' => 0.55, 'currency' => 'USD', 'effective_from' => '2026-09-01']]])->assertOk();
        // A later revision is not yet in force on the inquiry date.
        $this->as()->putJson("/api/v1/merchandise/rate-chart/{$office}", ['rates' => [['rate_line_id' => $line, 'rate' => 0.70, 'currency' => 'USD', 'effective_from' => '2026-11-01']]])->assertOk();
        $inquiry = $this->as()->postJson('/api/v1/erp/merch-inquiries', ['buyer_id' => $buyer, 'style_no' => 'AN-01', 'style_desc' => 'Tee', 'inquiry_date' => '2026-10-12'])->json('data.id');

        $rates = collect($this->as()->getJson("/api/v1/merchandise/inquiries/{$inquiry}/rates")->assertOk()->json('data.rates'));
        $row = $rates->firstWhere('description', 'Annotech S/Jersey');
        $this->assertSame(0.55, $row['rate']);
        $this->assertSame('2026-09-01', $row['effective_from']);
        $this->assertSame('LPP S.A.', $row['buyer_office']);
        $this->assertTrue($row['quoted']);
    }

    public function test_a_style_is_registered_against_a_confirmed_inquiry_with_size_ranges(): void
    {
        [$buyer, $office] = $this->buyer();
        $s = $this->as()->postJson('/api/v1/erp/sizes', ['size_group' => 'LETTER', 'code' => 'S', 'name' => 'S'])->assertCreated()->json('data.id');
        $xl = $this->as()->postJson('/api/v1/erp/sizes', ['size_group' => 'LETTER', 'code' => 'XL', 'name' => 'XL'])->assertCreated()->json('data.id');
        $draft = $this->as()->postJson('/api/v1/erp/merch-inquiries', ['buyer_id' => $buyer, 'style_no' => 'MOOD', 'style_desc' => 'Shirt', 'inquiry_date' => '2026-10-12'])->json('data.id');

        $style = [
            'buyer_id' => $buyer, 'buyer_office_id' => $office, 'style_ref' => 'MOOD SHIRT', 'style_ext' => '327632.01', 'description' => 'T-SHIRT',
            'is_set' => true, 'qty_unit' => 'SET', 'pcs_per_unit' => 2, 'season' => 'Spring/Summer '.now()->format('Y'), 'status' => 'DEVELOPMENT',
            'lines' => [['size_ids' => [$s, $xl]]],
        ];
        // A line with no size, and an inquiry still in draft, are refused.
        $this->as()->postJson('/api/v1/merchandise/styles', ['lines' => [['size_ids' => []]]] + $style)->assertUnprocessable()->assertJsonValidationErrors(['lines.0.size_ids' => 'Every size range line needs at least one size from Size Master.']);
        $this->as()->postJson('/api/v1/merchandise/styles', ['inquiry_id' => $draft] + $style)->assertUnprocessable()->assertJsonValidationErrors('inquiry_id');
        // Running is set on the sheet as orders run, not offered on a new style.
        $this->as()->postJson('/api/v1/merchandise/styles', ['status' => 'RUNNING'] + $style)->assertUnprocessable()->assertJsonValidationErrors('status');

        $this->as()->postJson("/api/v1/merchandise/inquiries/{$draft}/confirm")->assertOk();
        $id = $this->as()->postJson('/api/v1/merchandise/styles', ['inquiry_id' => $draft] + $style)->assertCreated()
            ->assertJsonPath('data.style_no', 'MOOD SHIRT-327632.01')->assertJsonPath('data.pcs_per_unit', 2)->assertJsonPath('data.buyer_office', 'LPP S.A.')
            ->assertJsonPath('data.inquiry_no', 'INQ-'.now()->format('Y').'0001')->assertJsonPath('data.lines.0.sizes', 'S, XL')->assertJsonPath('data.lines.0.style', 'MOOD SHIRT-327632.01')
            ->json('data.id');
        $this->as()->postJson('/api/v1/merchandise/styles', $style)->assertUnprocessable()->assertJsonPath('errors.style_ref.0', 'This style reference and extension are already registered.');

        // Size Range panel: add a line for a sub-style, then remove it.
        $lines = $this->as()->postJson("/api/v1/merchandise/styles/{$id}/size-ranges", ['size_ids' => [$xl], 'sub_style' => 'MOOD TOP'])->assertCreated()->json('data');
        $this->assertSame([1, 2], array_column($lines, 'sl_no'));
        $this->as()->deleteJson("/api/v1/merchandise/style-size-ranges/{$lines[1]['id']}")->assertNoContent();
        $this->as()->getJson("/api/v1/merchandise/styles/{$id}/size-ranges")->assertJsonCount(1, 'data');

        // The sheet may mark it running; the list filters by buyer and status.
        $this->as()->putJson("/api/v1/merchandise/styles/{$id}", ['status' => 'RUNNING', 'nature_of_work' => 'Basic', 'inquiry_id' => $draft] + $style)->assertOk()->assertJsonPath('data.status', 'RUNNING');
        $this->as()->getJson("/api/v1/merchandise/styles?buyer_id={$buyer}&status=RUNNING")->assertJsonCount(1, 'data');
        $this->as()->getJson('/api/v1/merchandise/styles?status=DRAFT')->assertJsonCount(0, 'data');

        // An inquiry a style is registered against cannot be removed; the style can.
        $this->as()->deleteJson("/api/v1/erp/merch-inquiries/{$draft}")->assertUnprocessable();
        $this->as()->deleteJson("/api/v1/merchandise/styles/{$id}")->assertNoContent();
        $this->as()->deleteJson("/api/v1/erp/merch-inquiries/{$draft}")->assertNoContent();
    }

    public function test_a_style_registers_its_items_for_one_or_more_styles(): void
    {
        [$buyer] = $this->buyer();
        $size = $this->as()->postJson('/api/v1/erp/sizes', ['size_group' => 'LETTER', 'code' => 'M', 'name' => 'M'])->json('data.id');
        $base = ['buyer_id' => $buyer, 'description' => 'KIDS HOODIE', 'qty_unit' => 'SET', 'is_set' => true, 'status' => 'DEVELOPMENT', 'lines' => [['size_ids' => [$size]]]];
        $style = $this->as()->postJson('/api/v1/merchandise/styles', ['style_ref' => '637PK'] + $base)->assertCreated()->json('data.id');
        $other = $this->as()->postJson('/api/v1/merchandise/styles', ['style_ref' => '638PK'] + $base)->assertCreated()->json('data.id');

        $item = [
            'for_style_ids' => [$style], 'set_style_id' => $style, 'item_group' => 'KNIT_TOP', 'item_type' => 'Hoodie', 'division' => 'KIDS', 'neck_type' => 'HOODED',
            'short_name' => 'hd', 'full_name' => 'hoodie', 'status' => 'ACTIVE', 'is_solid' => true, 'nature_of_work' => 'CUT_MAKE', 'smv_marketing' => 12.5,
            'care_labels' => [['garment_part' => 'Body', 'service' => 'WASH'], ['garment_part' => '', 'service' => null]],
            'certificates' => [['checked' => true, 'file_id' => null]],
        ];
        // A type must belong to its group; the names and the nature of work are required.
        $this->as()->postJson("/api/v1/merchandise/styles/{$style}/items", ['item_type' => 'Leggings'] + $item)->assertUnprocessable()->assertJsonValidationErrors('item_type');
        $this->as()->postJson("/api/v1/merchandise/styles/{$style}/items", ['nature_of_work' => null] + $item)->assertUnprocessable()->assertJsonPath('errors.nature_of_work.0', 'Nature of Work is required.');
        // "Other" has no types.
        $this->as()->postJson("/api/v1/merchandise/styles/{$style}/items", ['item_group' => 'OTHER', 'item_type' => null, 'short_name' => 'oth'] + $item)->assertCreated();

        $id = $this->as()->postJson("/api/v1/merchandise/styles/{$style}/items", $item)->assertCreated()
            ->assertJsonPath('data.short_name', 'HD')->assertJsonPath('data.full_name', 'HOODIE')->assertJsonPath('data.smv_marketing', 12.5)
            ->assertJsonPath('data.for_styles', ['637PK'])->assertJsonCount(1, 'data.care_labels')->assertJsonPath('data.care_labels.0.service', 'WASH')
            ->json('data.id');
        $this->as()->getJson("/api/v1/merchandise/styles/{$style}/items")->assertOk()->assertJsonCount(2, 'data.items')->assertJsonPath('data.sets.0.label', 'Set - KIDS HOODIE');
        $this->as()->getJson("/api/v1/merchandise/styles/{$other}/items")->assertJsonCount(0, 'data.items');

        // The item now also serves the second style.
        $this->as()->putJson("/api/v1/merchandise/style-items/{$id}", ['for_style_ids' => [$style, $other]] + $item)->assertOk()->assertJsonPath('data.for_styles', ['637PK', '638PK']);
        $this->as()->getJson("/api/v1/merchandise/styles/{$other}/items")->assertJsonCount(1, 'data.items');
        $this->as()->deleteJson("/api/v1/merchandise/style-items/{$id}")->assertNoContent();
        $this->as()->getJson("/api/v1/merchandise/styles/{$other}/items")->assertJsonCount(0, 'data.items');
    }

    public function test_a_style_maps_its_colours_as_one_grid(): void
    {
        [$buyer] = $this->buyer();
        $size = $this->as()->postJson('/api/v1/erp/sizes', ['size_group' => 'LETTER', 'code' => 'M', 'name' => 'M'])->json('data.id');
        $style = $this->as()->postJson('/api/v1/merchandise/styles', ['buyer_id' => $buyer, 'style_ref' => '637PK', 'description' => 'Hoodie', 'qty_unit' => 'PCS', 'status' => 'DEVELOPMENT', 'lines' => [['size_ids' => [$size]]]])->json('data.id');
        $navy = $this->as()->postJson('/api/v1/erp/colours', ['colour_type' => 'SOLID', 'name' => 'Navy', 'pantone_no' => '19-4024'])->assertCreated()->json('data.id');
        $grey = $this->as()->postJson('/api/v1/erp/colours', ['colour_type' => 'MELANGE', 'name' => 'Grey Melange'])->assertCreated()->json('data.id');

        $grid = $this->as()->getJson("/api/v1/merchandise/styles/{$style}/colours")->assertOk();
        $this->assertSame([], $grid->json('data.rows'));
        $this->assertContains('Navy', array_column($grid->json('data.colours'), 'label'));

        $rows = [['colour_id' => $navy, 'colour_type' => 'SOLID', 'is_gmt_colour' => true, 'pantone_no' => '19-4024', 'buyer_colour_name' => 'Dress Blue'], ['colour_id' => $grey, 'colour_type' => 'MELANGE', 'swatch' => true]];
        // A colour is mapped once; every line names one.
        $this->as()->putJson("/api/v1/merchandise/styles/{$style}/colours", ['rows' => [$rows[0], $rows[0]]])->assertUnprocessable();
        $this->as()->putJson("/api/v1/merchandise/styles/{$style}/colours", ['rows' => [['colour_id' => null]]])->assertUnprocessable();

        $saved = $this->as()->putJson("/api/v1/merchandise/styles/{$style}/colours", ['rows' => $rows])->assertOk()->json('data');
        $this->assertSame(['Navy', 'Grey Melange'], array_column($saved, 'colour'));
        $this->assertSame('Dress Blue', $saved[0]['buyer_colour_name']);
        $this->assertTrue($saved[1]['swatch']);

        // Saving the grid without a line drops it.
        $this->as()->putJson("/api/v1/merchandise/styles/{$style}/colours", ['rows' => [$rows[1]]])->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.colour', 'Grey Melange');
    }

    public function test_a_style_keeps_its_fabrication_and_knitting_instructions(): void
    {
        [$buyer] = $this->buyer();
        $size = $this->as()->postJson('/api/v1/erp/sizes', ['size_group' => 'LETTER', 'code' => 'M', 'name' => 'M'])->json('data.id');
        $style = $this->as()->postJson('/api/v1/merchandise/styles', ['buyer_id' => $buyer, 'style_ref' => '637PK', 'description' => 'Hoodie', 'qty_unit' => 'PCS', 'status' => 'DEVELOPMENT', 'lines' => [['size_ids' => [$size]]]])->json('data.id');
        $item = $this->as()->postJson("/api/v1/merchandise/styles/{$style}/items", ['for_style_ids' => [$style], 'item_group' => 'KNIT_TOP', 'item_type' => 'Hoodie', 'division' => 'KIDS', 'short_name' => 'HD', 'full_name' => 'HOODIE', 'status' => 'ACTIVE', 'nature_of_work' => 'CUT_MAKE'])->json('data.id');
        $type = $this->as()->postJson('/api/v1/erp/fabric-types', ['code' => 'SJ', 'name' => 'Single Jersey', 'short_name' => 'SJ', 'category' => 'KNIT', 'construction' => 'CIRCULAR', 'sort_order' => 1])->assertCreated()->json('data.id');
        $fiber = $this->as()->postJson('/api/v1/erp/fibers', ['code' => 'CO', 'name' => 'Cotton', 'sort_order' => 1])->assertCreated()->json('data.id');
        $comp = $this->as()->postJson('/api/v1/erp/fiber-compositions', ['code' => 'C100', 'items' => [['fiber_id' => $fiber, 'percent' => 100]]])->assertCreated()->json('data.id');
        $colour = $this->as()->postJson('/api/v1/erp/colours', ['colour_type' => 'SOLID', 'name' => 'Navy'])->json('data.id');

        $options = $this->as()->getJson("/api/v1/merchandise/styles/{$style}/fabrication")->assertOk()->json('data.options');
        $this->assertSame([['value' => $item, 'label' => 'HOODIE']], $options['items']);

        $fabric = [
            'item_ids' => [$item], 'fabric_category' => 'KNIT', 'source' => 'MANUFACTURED', 'fiber_composition_id' => $comp, 'spin_type' => 'COMBED', 'is_main' => true,
            'fabric_type_id' => $type, 'weight_from' => 180, 'weight_unit' => 'GSM', 'counts' => ['top' => ['count' => '30/1', 'pct' => 50], 'back' => ['count' => '30/1', 'pct' => 50]],
            'wash_types' => ['Enzyme'], 'finish_na' => true, 'finish_types' => ['Silicon'], 'description' => 'Knit Fabric — 100% Cotton Single Jersey · Combed · 180 GSM · Manufactured',
        ];
        // A fabric names the style's own items, its composition and its type.
        $this->as()->putJson("/api/v1/merchandise/styles/{$style}/fabrication", ['fabrics' => [['item_ids' => []] + $fabric], 'instructions' => []])->assertUnprocessable()->assertJsonValidationErrors('fabrics.0.item_ids');
        $this->as()->putJson("/api/v1/merchandise/styles/{$style}/fabrication", ['fabrics' => [['fabric_type_id' => null] + $fabric], 'instructions' => []])->assertUnprocessable();

        $saved = $this->as()->putJson("/api/v1/merchandise/styles/{$style}/fabrication", [
            'fabrics' => [$fabric],
            'instructions' => [['image_title' => 'Feeder stripe repeat', 'gmt_colour_ids' => [$colour], 'lines' => [['colour_id' => $colour, 'value' => '4', 'unit' => 'COURSE'], ['colour_id' => null, 'value' => '']]]],
        ])->assertOk()->json('data');
        $this->assertSame('Single Jersey', $saved['fabrics'][0]['fabric_type']);
        $this->assertSame([], $saved['fabrics'][0]['finish_types']);
        $this->assertSame('30/1', $saved['fabrics'][0]['counts']['top']['count']);
        $this->assertNull($saved['fabrics'][0]['counts']['binding']['count']);
        $this->assertCount(1, $saved['instructions'][0]['lines']);
    }

    public function test_a_style_keeps_its_colour_services_and_process_details(): void
    {
        [$buyer] = $this->buyer();
        $size = $this->as()->postJson('/api/v1/erp/sizes', ['size_group' => 'LETTER', 'code' => 'M', 'name' => 'M'])->json('data.id');
        $style = $this->as()->postJson('/api/v1/merchandise/styles', ['buyer_id' => $buyer, 'style_ref' => '637PK', 'description' => 'Hoodie', 'qty_unit' => 'PCS', 'status' => 'DEVELOPMENT', 'lines' => [['size_ids' => [$size]]]])->json('data.id');
        $navy = $this->as()->postJson('/api/v1/erp/colours', ['colour_type' => 'SOLID', 'name' => 'Navy'])->json('data.id');
        $this->as()->putJson("/api/v1/merchandise/styles/{$style}/colours", ['rows' => [['colour_id' => $navy, 'colour_type' => 'SOLID']]])->assertOk();
        $supplier = MaterialSupplier::query()->create(['code' => 'S01', 'name' => 'Fakir Washing', 'supplier_type' => 'LOCAL_NON_EPZ', 'active' => true])->id;

        $data = $this->as()->getJson("/api/v1/merchandise/styles/{$style}/services")->assertOk()->json('data');
        $this->assertSame('Navy', $data['colours'][0]['colour']);
        $this->assertNull($data['colours'][0]['print_type']);
        $this->assertSame([], $data['rows']);
        $this->assertContains('Fakir Washing', array_column($data['suppliers'], 'label'));
        $line = $data['colours'][0]['id'];

        // A service type comes from its own list; every process line names its process.
        $this->as()->putJson("/api/v1/merchandise/styles/{$style}/services", ['colours' => [['id' => $line, 'print_type' => 'STONE']], 'processes' => []])->assertUnprocessable();
        $this->as()->putJson("/api/v1/merchandise/styles/{$style}/services", ['colours' => [], 'processes' => [['process' => null]]])->assertUnprocessable()->assertJsonValidationErrors('processes.0.process');

        $saved = $this->as()->putJson("/api/v1/merchandise/styles/{$style}/services", [
            'colours' => [['id' => $line, 'print_type' => 'PLASTISOL', 'wash_type' => 'ENZYME']],
            'processes' => [['process' => 'WASH', 'type' => 'Enzyme wash', 'supplier_id' => $supplier], ['process' => 'PRINT', 'remarks' => ' ']],
        ])->assertOk()->json('data');
        $this->assertSame('PLASTISOL', $saved['colours'][0]['print_type']);
        $this->assertSame(['WASH', 'PRINT'], array_column($saved['rows'], 'process'));
        $this->assertNull($saved['rows'][1]['remarks']);

        // Saving the colour grid again keeps the services recorded on its colours.
        $this->as()->putJson("/api/v1/merchandise/styles/{$style}/colours", ['rows' => [['colour_id' => $navy, 'colour_type' => 'SOLID', 'remarks' => 'ok']]])->assertOk();
        $this->as()->getJson("/api/v1/merchandise/styles/{$style}/services")->assertJsonPath('data.colours.0.wash_type', 'ENZYME')->assertJsonCount(2, 'data.rows');
    }

    public function test_a_style_bom_is_numbered_versioned_and_approved(): void
    {
        [$buyer] = $this->buyer();
        $size = $this->as()->postJson('/api/v1/erp/sizes', ['size_group' => 'LETTER', 'code' => 'M', 'name' => 'M'])->json('data.id');
        $style = $this->as()->postJson('/api/v1/merchandise/styles', ['buyer_id' => $buyer, 'style_ref' => '637PK', 'description' => 'Hoodie', 'qty_unit' => 'PCS', 'status' => 'DEVELOPMENT', 'lines' => [['size_ids' => [$size]]]])->json('data.id');

        $empty = $this->as()->getJson("/api/v1/merchandise/styles/{$style}/bom")->assertOk()->json('data');
        $this->assertNull($empty['bom_no']);
        $this->assertSame([], $empty['lines']);
        $this->assertContains('SEWING_ACCESSORIES', $empty['options']['categories']);
        // Nothing saved yet, so nothing to approve.
        $this->as()->postJson("/api/v1/merchandise/styles/{$style}/bom/approve")->assertUnprocessable();

        $thread = ['item_name' => 'Sewing Thread 50/2', 'cons_type' => 'COLOUR', 'consumption' => 2, 'cons_unit' => 'MTR', 'per_gmt' => 1, 'purchase_unit' => 'CONE', 'conversion' => 4000, 'rate' => 0.51, 'category' => 'SEWING_ACCESSORIES', 'supplier' => ' Coats '];
        $label = ['item_name' => 'Care Label', 'cons_type' => 'COLOUR_SIZE', 'consumption' => 1, 'cons_unit' => 'PCS', 'rate' => 0.02, 'category' => 'LABELS', 'apply_addl' => true];
        $this->as()->putJson("/api/v1/merchandise/styles/{$style}/bom", ['order_qty' => 1000, 'addl_pct' => 3, 'lines' => [['item_name' => ' '] + $label]])->assertUnprocessable()->assertJsonValidationErrors('lines.0.item_name');
        $this->as()->putJson("/api/v1/merchandise/styles/{$style}/bom", ['lines' => [['cons_unit' => 'LTR'] + $label]])->assertUnprocessable();

        $v1 = $this->as()->putJson("/api/v1/merchandise/styles/{$style}/bom", ['order_qty' => 1000, 'addl_pct' => 3, 'lines' => [$thread, $label]])->assertOk()->json('data');
        $this->assertSame('BOM-000001', $v1['bom_no']);
        $this->assertSame([1, 'DRAFT', null, 1000], [$v1['version'], $v1['status'], $v1['in_use'], $v1['order_qty']]);
        $this->assertSame('Coats', $v1['lines'][0]['supplier']);
        $this->assertTrue($v1['lines'][1]['apply_addl']);

        // Saving a draft rewrites it in place.
        $this->as()->putJson("/api/v1/merchandise/styles/{$style}/bom", ['order_qty' => 1200, 'lines' => [$thread]])->assertOk()->assertJsonPath('data.version', 1)->assertJsonCount(1, 'data.lines');

        $approved = $this->as()->postJson("/api/v1/merchandise/styles/{$style}/bom/approve")->assertOk()->json('data');
        $this->assertSame(['APPROVED', 1], [$approved['status'], $approved['in_use']]);
        $this->as()->postJson("/api/v1/merchandise/styles/{$style}/bom/approve")->assertUnprocessable();

        // A change after approval opens V2 as a draft; V1 stays in use until V2 is approved.
        $v2 = $this->as()->putJson("/api/v1/merchandise/styles/{$style}/bom", ['order_qty' => 1200, 'lines' => [$thread, $label]])->assertOk()->json('data');
        $this->assertSame([2, 'DRAFT', 1, 'BOM-000001'], [$v2['version'], $v2['status'], $v2['in_use'], $v2['bom_no']]);
        $this->as()->postJson("/api/v1/merchandise/styles/{$style}/bom/approve")->assertOk()->assertJsonPath('data.in_use', 2);

        // An empty draft is not approved; another style takes the next number.
        $this->as()->putJson("/api/v1/merchandise/styles/{$style}/bom", ['lines' => []])->assertOk()->assertJsonPath('data.version', 3);
        $this->as()->postJson("/api/v1/merchandise/styles/{$style}/bom/approve")->assertUnprocessable();
        $other = $this->as()->postJson('/api/v1/merchandise/styles', ['buyer_id' => $buyer, 'style_ref' => '638PK', 'description' => 'Tee', 'qty_unit' => 'PCS', 'status' => 'DEVELOPMENT', 'lines' => [['size_ids' => [$size]]]])->json('data.id');
        $this->as()->putJson("/api/v1/merchandise/styles/{$other}/bom", ['lines' => [$label]])->assertOk()->assertJsonPath('data.bom_no', 'BOM-000002');
    }

    public function test_garment_parts_are_mapped_to_the_style_fabrics(): void
    {
        [$buyer] = $this->buyer();
        $size = $this->as()->postJson('/api/v1/erp/sizes', ['size_group' => 'LETTER', 'code' => 'M', 'name' => 'M'])->json('data.id');
        $style = $this->as()->postJson('/api/v1/merchandise/styles', ['buyer_id' => $buyer, 'style_ref' => '637PK', 'description' => 'Hoodie', 'qty_unit' => 'PCS', 'status' => 'DEVELOPMENT', 'lines' => [['size_ids' => [$size]]]])->json('data.id');
        $item = $this->as()->postJson("/api/v1/merchandise/styles/{$style}/items", ['for_style_ids' => [$style], 'item_group' => 'KNIT_TOP', 'item_type' => 'Hoodie', 'division' => 'KIDS', 'short_name' => 'HD', 'full_name' => 'HOODIE', 'status' => 'ACTIVE', 'nature_of_work' => 'CUT_MAKE'])->json('data.id');
        $type = $this->as()->postJson('/api/v1/erp/fabric-types', ['code' => 'SJ', 'name' => 'Single Jersey', 'short_name' => 'SJ', 'category' => 'KNIT', 'construction' => 'CIRCULAR', 'sort_order' => 1])->json('data.id');
        $fiber = $this->as()->postJson('/api/v1/erp/fibers', ['code' => 'CO', 'name' => 'Cotton', 'sort_order' => 1])->json('data.id');
        $comp = $this->as()->postJson('/api/v1/erp/fiber-compositions', ['code' => 'C100', 'items' => [['fiber_id' => $fiber, 'percent' => 100]]])->json('data.id');
        $body = GarmentPart::query()->create(['code' => 'BD', 'name' => 'Body', 'active' => true])->id;
        $navy = $this->as()->postJson('/api/v1/erp/colours', ['colour_type' => 'SOLID', 'name' => 'Navy'])->json('data.id');
        $grey = $this->as()->postJson('/api/v1/erp/colours', ['colour_type' => 'MELANGE', 'name' => 'Grey'])->json('data.id');
        $colourRows = [['colour_id' => $navy], ['colour_id' => $grey]];
        $lines = $this->as()->putJson("/api/v1/merchandise/styles/{$style}/colours", ['rows' => $colourRows])->assertOk()->json('data');
        $fabric = ['item_ids' => [$item], 'fabric_category' => 'KNIT', 'source' => 'MANUFACTURED', 'fiber_composition_id' => $comp, 'fabric_type_id' => $type, 'description' => 'Single Jersey 180 GSM'];
        $fabrics = $this->as()->putJson("/api/v1/merchandise/styles/{$style}/fabrication", ['fabrics' => [$fabric, ['description' => 'Rib'] + $fabric], 'instructions' => []])->assertOk()->json('data.fabrics');
        [$sj, $rib] = array_column($fabrics, 'id');

        $options = $this->as()->getJson("/api/v1/merchandise/styles/{$style}/fabric-mapping")->assertOk()->json('data.options');
        $this->assertSame(['Single Jersey 180 GSM', 'Rib'], array_column($options['fabrics'], 'label'));
        $this->assertSame(['Navy', 'Grey'], array_column($options['colours'], 'label'));

        $line = ['prod_categories' => ['BULK', 'DEVELOP'], 'item_id' => $item, 'garment_part_id' => $body, 'fabric_id' => $sj, 'gmt_colour_id' => $lines[0]['id'], 'ratio' => 70];
        // Each line names its colour (or all), and one part's ratios stay within 100%.
        $this->as()->putJson("/api/v1/merchandise/styles/{$style}/fabric-mapping", ['rows' => [['gmt_colour_id' => null] + $line]])->assertUnprocessable()->assertJsonValidationErrors('rows.0.gmt_colour_id');
        $this->as()->putJson("/api/v1/merchandise/styles/{$style}/fabric-mapping", ['rows' => [$line, ['fabric_id' => $rib, 'ratio' => 40] + $line]])->assertUnprocessable()->assertJsonValidationErrors('rows');
        $this->as()->putJson("/api/v1/merchandise/styles/{$style}/fabric-mapping", ['rows' => [['prod_categories' => []] + $line]])->assertUnprocessable();

        $saved = $this->as()->putJson("/api/v1/merchandise/styles/{$style}/fabric-mapping", ['rows' => [
            $line,
            ['fabric_id' => $rib, 'ratio' => 30, 'fab_colour_id' => $lines[1]['id']] + $line,
            ['all_colours' => true, 'gmt_colour_id' => null, 'prod_categories' => ['PROJECTION']] + $line,
        ]])->assertOk()->json('data');
        $this->assertSame(['Body', 'Body', 'Body'], array_column($saved, 'part'));
        $this->assertSame(['Navy', 'Navy', null], array_column($saved, 'gmt_colour'));
        $this->assertSame('Grey', $saved[1]['fab_colour']);
        $this->assertTrue($saved[2]['all_colours']);

        // Fabrication keeps its fabrics by id; a mapped fabric or colour is not dropped.
        $this->as()->putJson("/api/v1/merchandise/styles/{$style}/fabrication", ['fabrics' => [['id' => $sj, 'description' => 'SJ 200 GSM'] + $fabric, ['id' => $rib] + $fabric], 'instructions' => []])->assertOk()->assertJsonPath('data.fabrics.0.id', $sj);
        $this->as()->putJson("/api/v1/merchandise/styles/{$style}/fabrication", ['fabrics' => [['id' => $sj] + $fabric], 'instructions' => []])->assertUnprocessable()->assertJsonValidationErrors('fabrics');
        $this->as()->putJson("/api/v1/merchandise/styles/{$style}/colours", ['rows' => [$colourRows[0]]])->assertUnprocessable();
        $this->as()->getJson("/api/v1/merchandise/styles/{$style}/fabric-mapping")->assertJsonCount(3, 'data.rows')->assertJsonPath('data.rows.0.fabric', 'SJ 200 GSM');
    }

    public function test_a_style_packs_its_orders_by_sku_and_counts_the_packs(): void
    {
        [$buyer] = $this->buyer();
        $size = $this->as()->postJson('/api/v1/erp/sizes', ['size_group' => 'LETTER', 'code' => 'M', 'name' => 'M'])->json('data.id');
        $style = $this->as()->postJson('/api/v1/merchandise/styles', ['buyer_id' => $buyer, 'style_ref' => '529', 'description' => 'T-Shirt', 'qty_unit' => 'PCS', 'status' => 'STYLE_APPROVED', 'lines' => [['size_ids' => [$size]]]])->json('data.id');
        $item = $this->as()->postJson("/api/v1/merchandise/styles/{$style}/items", ['for_style_ids' => [$style], 'item_group' => 'KNIT_TOP', 'item_type' => 'T-Shirt', 'division' => 'MENS', 'short_name' => 'TS', 'full_name' => 'TOP T-SHIRT', 'status' => 'ACTIVE', 'nature_of_work' => 'CUT_MAKE'])->json('data.id');
        $white = $this->as()->postJson('/api/v1/erp/colours', ['colour_type' => 'SOLID', 'name' => 'White'])->json('data.id');
        $black = $this->as()->postJson('/api/v1/erp/colours', ['colour_type' => 'SOLID', 'name' => 'Black'])->json('data.id');
        $colours = $this->as()->putJson("/api/v1/merchandise/styles/{$style}/colours", ['rows' => [['colour_id' => $white], ['colour_id' => $black]]])->assertOk()->json('data');
        $order = $this->as()->postJson('/api/v1/merchandise/order-confirmation', ['buyer_id' => $buyer, 'po_no' => 'PO-1', 'order_date' => '2026-10-12', 'ship_date' => '2026-12-01', 'lines' => [['style_id' => $style, 'qty' => 1000]]])->assertCreated()->json('data.0.id');

        $options = $this->as()->getJson("/api/v1/merchandise/styles/{$style}/packing")->assertOk()->json('data.options');
        $this->assertSame(['SINGLE', 'MULTI_UNIFORM', 'MULTI_RANDOM'], $options['pack_types']);
        $this->assertSame(['White', 'Black'], array_column($options['colours'], 'label'));
        $this->assertCount(1, $options['orders']);

        $line = ['sku_ref' => 'SKU-W', 'pack_type' => 'SINGLE', 'pcs_per_pack' => 6, 'order_id' => $order, 'item_id' => $item, 'colour_id' => $colours[0]['id']];
        $this->as()->putJson("/api/v1/merchandise/styles/{$style}/packing", ['rows' => [['colour_id' => null] + $line]])->assertUnprocessable()->assertJsonValidationErrors('rows.0.colour_id');
        $this->as()->putJson("/api/v1/merchandise/styles/{$style}/packing", ['rows' => [['sku_ref' => ''] + $line]])->assertUnprocessable()->assertJsonValidationErrors('rows.0.sku_ref');

        // Without a breakdown the whole order is packed; a single-piece pack holds one.
        $rows = $this->as()->putJson("/api/v1/merchandise/styles/{$style}/packing", ['rows' => [$line, ['sku_ref' => 'SKU-ALL', 'pack_type' => 'MULTI_UNIFORM', 'pcs_per_pack' => 3, 'all_colours' => true, 'colour_id' => null] + $line]])->assertOk()->json('data');
        $this->assertSame([[1, 1000, 'White'], [3, 334, null]], array_map(fn ($r) => [$r['pcs_per_pack'], $r['qty'], $r['colour']], $rows));

        // With one, a colour packs its own pieces.
        $this->as()->putJson("/api/v1/merchandise/orders/{$order}/sizes", ['rows' => [['colour' => 'WHITE', 'size' => 'M', 'qty' => 600], ['colour' => 'Black', 'size' => 'M', 'qty' => 400]]])->assertOk();
        $rows = $this->as()->getJson("/api/v1/merchandise/styles/{$style}/packing")->json('data.rows');
        $this->assertSame([600, 334], array_column($rows, 'qty'));

        // A colour packed against stays mapped; the order confirmation counts the packing.
        $this->as()->putJson("/api/v1/merchandise/styles/{$style}/colours", ['rows' => [['colour_id' => $black]]])->assertUnprocessable();
        $this->assertSame(2, $this->as()->getJson("/api/v1/merchandise/order-confirmation/styles?buyer={$buyer}")->json('data.0.inherit.packing'));
    }

    public function test_a_technician_cannot_open_inquiries_or_styles(): void
    {
        $technician = TenantFixture::user($this->delta, 'TECHNICIAN', 'tech@delta.test');
        ['plain' => $token] = app(IssueApiToken::class)->forUser($technician, $this->delta->id, 'Test token');

        $this->withToken($token)->getJson('/api/v1/erp/merch-inquiries')->assertForbidden();
        $this->withToken($token)->getJson('/api/v1/merchandise/styles')->assertForbidden();
    }

    /** @return array{0: string, 1: string} the buyer and its office */
    private function buyer(): array
    {
        $office = $this->as()->postJson('/api/v1/erp/buyer-offices', ['name' => 'LPP S.A.', 'office_type' => 'HEAD_OFFICE'])->json('data.id');
        $country = OriginCountry::query()->firstOrCreate(['company_id' => $this->delta->id, 'code' => '1000'], ['name' => 'Bangladesh', 'active' => true])->id;
        $buyer = $this->as()->postJson('/api/v1/erp/buyers', ['code' => 'LPP', 'name' => 'LPP Spolka Akcyjna', 'short_name' => 'LPP', 'pi_address' => 'Gdansk', 'registered_from' => '2026-01-01', 'country_id' => $country, 'office_ids' => [$office]])
            ->assertCreated()->json('data.id');

        return [$buyer, $office];
    }

    private function as(): static
    {
        ['plain' => $plain] = app(IssueApiToken::class)->forUser($this->owner, $this->delta->id, 'Test token');

        return $this->withToken($plain);
    }
}
