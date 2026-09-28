<?php

declare(strict_types=1);

namespace Tests\Feature\Procurement;

use App\Modules\Inventory\Models\UnitOfMeasure;
use App\Modules\ItemMaster\Models\Item;
use App\Modules\Procurement\Models\GoodsReceipt;
use App\Modules\Procurement\Models\GoodsReceiptLine;
use App\Modules\Settings\Services\NumberSequenceGenerator;
use App\Modules\Tenancy\Models\Company;
use App\Modules\Tenancy\Models\Factory;
use App\Modules\Vendor\Models\Vendor;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\InventoryFixture;
use Tests\Support\TenantFixture;
use Tests\TestCase;

/**
 * Phase 0 of the procurement spec lays the GRN schema and the document
 * number series; the receiving workflow itself arrives with purchase orders
 * in Phase 1. This pins the shape the workflow will be written against.
 */
class GoodsReceiptSchemaTest extends TestCase
{
    use RefreshDatabase;

    private Company $delta;

    private Factory $factory;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(DatabaseSeeder::class);

        $this->delta = TenantFixture::company('Delta Apparels Ltd', 'DAL');
        $this->factory = TenantFixture::factory($this->delta, 'Dhaka Unit 1', 'DHK');
        TenantFixture::actingAsTenant($this->delta);
    }

    public function test_a_receipt_holds_lines_with_the_rate_at_the_time(): void
    {
        $bin = InventoryFixture::bin($this->delta, $this->factory);
        $kg = UnitOfMeasure::availableTo($this->delta->id)->where('code', 'KG')->firstOrFail();
        $yarn = Item::create([
            'company_id' => $this->delta->id, 'item_code' => 'YRN-30-1', 'name' => '30/1 combed cotton',
            'item_type' => 'YARN', 'tracking_mode' => 'LOT', 'uom_id' => $kg->id,
        ]);
        $vendor = Vendor::create(['company_id' => $this->delta->id, 'name' => 'Square Yarns', 'code' => 'SQY', 'vendor_type' => 'SUPPLIER', 'status' => 'ACTIVE']);

        $receipt = GoodsReceipt::create([
            'company_id' => $this->delta->id,
            'factory_id' => $this->factory->id,
            'store_id' => $bin->store_id,
            'vendor_id' => $vendor->id,
            'grn_no' => app(NumberSequenceGenerator::class)->next('GOODS_RECEIPT', $this->factory),
            'grn_date' => '2026-09-21',
            'grn_type' => 'LOCAL',
        ]);

        $receipt->lines()->create([
            'company_id' => $this->delta->id, 'item_id' => $yarn->id, 'received_qty' => '500', 'uom_id' => $kg->id,
            'lot_no' => 'L-0921-A', 'rate' => '312.5000', 'currency' => 'BDT',
        ]);
        $receipt->lines()->create([
            'company_id' => $this->delta->id, 'item_id' => $yarn->id, 'received_qty' => '250', 'uom_id' => $kg->id,
            'lot_no' => 'L-0921-B', 'rate' => '318.0000', 'currency' => 'BDT',
        ]);

        $fresh = GoodsReceipt::with('lines.item', 'vendor')->findOrFail($receipt->id);

        $this->assertSame('DRAFT', $fresh->status);
        $this->assertFalse($fresh->isCompleted());
        $this->assertStringStartsWith('GRN-DHK-', $fresh->grn_no);
        $this->assertSame('Square Yarns', $fresh->vendor->name);
        $this->assertCount(2, $fresh->lines);
        $this->assertSame('PENDING', $fresh->lines[0]->inspection_status);
        $this->assertSame('L-0921-B', $fresh->lines[1]->lot_no);
        $this->assertSame('YARN', $fresh->lines[0]->item->item_type);
        $this->assertSame(1, GoodsReceiptLine::where('lot_no', 'L-0921-A')->count());
    }

    /** The procurement document types the spec asks for exist as first-class number series. */
    public function test_the_procurement_number_series_are_registered(): void
    {
        $numbers = app(NumberSequenceGenerator::class);

        foreach (['PURCHASE_REQUISITION' => 'PR', 'FLOOR_REQUISITION' => 'FR', 'PURCHASE_ORDER' => 'PO', 'FABRIC_BOOKING' => 'FB'] as $type => $prefix) {
            $this->assertArrayHasKey($type, NumberSequenceGenerator::FORMATS);
            $this->assertSame("{$prefix}-DHK-2026-0001", $numbers->next($type, $this->factory, \Carbon\CarbonImmutable::parse('2026-09-21')));
            $this->assertSame("{$prefix}-DHK-2026-0002", $numbers->next($type, $this->factory, \Carbon\CarbonImmutable::parse('2026-09-21')));
            $this->assertNotSame('', __('numbering.types.'.$type));
        }
    }
}
