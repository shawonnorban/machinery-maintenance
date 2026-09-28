<?php

declare(strict_types=1);

namespace Tests\Feature\ItemMaster;

use App\Modules\ItemMaster\Models\Item;
use App\Modules\ItemMaster\Models\ItemCategory;
use App\Modules\Tenancy\Models\Company;
use App\Modules\ItemMaster\Models\ItemDepartment;
use App\Modules\Tenancy\Models\Factory;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\TenantFixture;
use Tests\TestCase;

/**
 * The legacy item library migration (Master Data spec §2.8), on a slice of
 * the real 7,984-row sheet's shapes: dyed-yarn lots, reused item codes,
 * blank categories, units spelt the factory's way.
 */
class ImportLegacyItemsTest extends TestCase
{
    use RefreshDatabase;

    private Company $delta;

    private Factory $factory;

    private string $csv;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(DatabaseSeeder::class);

        $this->delta = TenantFixture::company('Delta Apparels Ltd', 'DAL');
        $this->factory = TenantFixture::factory($this->delta, 'Dhaka Unit 1', 'DHK');
        TenantFixture::actingAsTenant($this->delta);

        $this->csv = tempnam(sys_get_temp_dir(), 'items').'.csv';
        file_put_contents($this->csv, implode("\n", [
            'Company,Department,Category,Sub Category,Detail Category,Item Id,Item Code,Item Name,Item Description,Unit,Model,Brand,Origin',
            'Acme,Yarn Store,Yarn,CVC,,338001,YRN143,22/1 Carded CVC 51/49,,KG,,,',
            'Acme,Yarn Store,Yarn,Cotton,,338002,YRN143,24/1 Combed Compact Bio Cotton,,CONE,,,',
            'Acme,Yarn Dyeing,,,,338003,YRN900,30/1 Combed Cotton::Black::2430::OB010625/Le::25YD1,,KG,,,',
            'Acme,General Store,Mechanical,Bearing,Ball Bearing,338004,8941161105161,Ball bearing 6204,Sealed both sides,PCS,6204-2RS,SKF,Sweden',
            'Acme,General Store,Civil,,,338005,,Cement,,BAG,,,',
            'Acme,IT,,,,338006,IT-001,Tp-Link Router,,pcs,,TP-Link,',
            'Acme,Garments Store,Sewing Thread,,,338007,THR-1,Poly thread 40/2,,CONE4000,,Coats,',
            'Acme,Grocery Store,Food and Grocery,Oil,,338008,GRC-1,Soybean oil 5L,,BTL,,,',
            'Acme,Printing,Printing Chemicals,,,338009,PRC-1,Binder,,KG,,,',
            'Acme,General Store,Mechanical,,,338010,BAD-1,Weird unit item,,FURLONG,,,',
        ]));
    }

    protected function tearDown(): void
    {
        @unlink($this->csv);

        parent::tearDown();
    }

    public function test_the_library_is_imported_with_departments_categories_and_specs(): void
    {
        $this->artisan('itemmaster:import-legacy-items', [
            'file' => $this->csv, '--company' => 'DAL',
        ])->assertSuccessful();

        // Departments carry their default item type.
        $yarnStore = ItemDepartment::query()->where('code', 'YARN_STORE')->firstOrFail();
        $this->assertSame('YARN', $yarnStore->default_item_type);
        $this->assertSame('GENERAL', ItemDepartment::query()->where('code', 'IT')->value('default_item_type'));
        $this->assertSame('TRIMS', ItemDepartment::query()->where('code', 'GARMENTS_STORE')->value('default_item_type'));

        // Three-level categories, one row per name per parent.
        $mechanical = ItemCategory::query()->where('name', 'Mechanical')->whereNull('parent_id')->firstOrFail();
        $bearing = ItemCategory::query()->where('name', 'Bearing')->where('parent_id', $mechanical->id)->firstOrFail();
        $ball = ItemCategory::query()->where('name', 'Ball Bearing')->where('parent_id', $bearing->id)->firstOrFail();
        $this->assertSame(3, $ball->depth());
        $this->assertStringStartsWith('MECHANICAL-', $bearing->code);

        // Yarn: count and fibre parsed from the name, tracked by lot.
        $cvc = Item::query()->where('legacy_item_id', '338001')->with('yarnAttributes', 'itemDepartment')->firstOrFail();
        $this->assertSame('YRN143', $cvc->item_code);
        $this->assertSame('YARN', $cvc->item_type);
        $this->assertSame('LOT', $cvc->tracking_mode);
        $this->assertSame('22/1', $cvc->yarnAttributes->count);
        $this->assertSame('Carded CVC 51/49', $cvc->yarnAttributes->fiber);
        $this->assertSame($yarnStore->id, $cvc->itemDepartment->id);

        // The reused item code falls back to code + legacy id.
        $bio = Item::query()->where('legacy_item_id', '338002')->with('uom')->firstOrFail();
        $this->assertSame('YRN143-338002', $bio->item_code);
        $this->assertSame('CONE', $bio->uom->code);

        // A dyed-yarn lot becomes the yarn, with the lot detail kept as a note.
        $dyed = Item::query()->where('legacy_item_id', '338003')->with('category', 'yarnAttributes')->firstOrFail();
        $this->assertSame('30/1 Combed Cotton', $dyed->name);
        $this->assertSame('Yarn', $dyed->category->name);
        $this->assertStringContainsString('Legacy lot: Black / 2430 / OB010625/Le / 25YD1', $dyed->description);
        $this->assertSame('30/1', $dyed->yarnAttributes->count);

        // Brand, origin, model and description all land somewhere readable.
        $bearingPart = Item::query()->where('legacy_item_id', '338004')->with('detailCategory')->firstOrFail();
        $this->assertSame('8941161105161', $bearingPart->item_code);
        $this->assertSame('SKF', $bearingPart->brand);
        $this->assertSame('Sweden', $bearingPart->origin);
        $this->assertSame('Ball Bearing', $bearingPart->detailCategory->name);
        $this->assertStringContainsString('Sealed both sides', $bearingPart->description);
        $this->assertSame('6204-2RS', $bearingPart->model);
        $this->assertSame('GENERAL', $bearingPart->item_type);

        // No item code at all: the legacy id stands in.
        $this->assertSame('338005', Item::query()->where('legacy_item_id', '338005')->value('item_code'));

        // Blank category → Uncategorised; lowercase unit normalised.
        $router = Item::query()->where('legacy_item_id', '338006')->with('category', 'uom')->firstOrFail();
        $this->assertSame('Uncategorised', $router->category->name);
        $this->assertSame('PCS', $router->uom->code);

        $this->assertSame('CONE4000', Item::query()->where('legacy_item_id', '338007')->firstOrFail()->uom->code);
        $this->assertSame('TRIMS', Item::query()->where('legacy_item_id', '338007')->value('item_type'));
        $this->assertSame('CHEMICAL', Item::query()->where('legacy_item_id', '338009')->value('item_type'));

        // An unknown unit is reported and skipped, never guessed.
        $this->assertNull(Item::query()->where('legacy_item_id', '338010')->first());
        $this->assertSame(9, Item::query()->count());
    }

    public function test_a_rerun_updates_instead_of_duplicating_and_a_dry_run_writes_nothing(): void
    {
        $args = ['file' => $this->csv, '--company' => 'DAL'];

        $this->artisan('itemmaster:import-legacy-items', $args + ['--dry-run' => true])->assertSuccessful();
        $this->assertSame(0, Item::query()->count());
        $this->assertSame(0, ItemDepartment::query()->count());

        $this->artisan('itemmaster:import-legacy-items', $args)->assertSuccessful();
        $this->artisan('itemmaster:import-legacy-items', $args)->assertSuccessful();

        $this->assertSame(9, Item::query()->count());
        $this->assertSame(1, Item::query()->where('item_code', 'YRN143')->count());
        $this->assertSame(1, ItemCategory::query()->where('name', 'Mechanical')->count());
    }
}
