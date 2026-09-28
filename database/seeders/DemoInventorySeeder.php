<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Modules\Identity\Models\Role;
use App\Modules\Identity\Models\User;
use App\Modules\Identity\Models\UserRole;
use App\Modules\Inventory\Models\Bin;
use App\Modules\Inventory\Models\UnitOfMeasure;
use App\Modules\ItemMaster\Actions\ManageSubcontractChallan;
use App\Modules\ItemMaster\Actions\MergeFabricRolls;
use App\Modules\ItemMaster\Actions\ReserveItemStock;
use App\Modules\ItemMaster\Actions\SplitFabricRoll;
use App\Modules\ItemMaster\Models\FabricRoll;
use App\Modules\ItemMaster\Models\Item;
use App\Modules\ItemMaster\Models\ItemCategory;
use App\Modules\ItemMaster\Models\ItemDepartment;
use App\Modules\ItemMaster\Models\ItemFabricAttributes;
use App\Modules\ItemMaster\Models\ItemLot;
use App\Modules\Procurement\Actions\ManageFabricBooking;
use App\Modules\Procurement\Actions\ManageGoodsReceipt;
use App\Modules\Procurement\Actions\ManagePurchaseOrder;
use App\Modules\Procurement\Actions\ManagePurchaseRequisition;
use App\Modules\Procurement\Models\GoodsReceipt;
use App\Modules\Requisition\Actions\FulfilFloorRequisition;
use App\Modules\Requisition\Actions\ManageFloorRequisition;
use App\Modules\Tenancy\Models\Company;
use App\Modules\Tenancy\Models\Factory;
use App\Modules\Vendor\Models\Vendor;
use App\Shared\Scopes\TenantScope;
use App\Shared\Tenancy\TenantContext;
use Carbon\CarbonImmutable;
use Illuminate\Database\Seeder;

/**
 * Demo data for the Inventory and Procurement modules.
 *
 * Separate from `DemoTenantSeeder`, which builds the maintenance side. This
 * one fills in what the store and the buying office actually look like on a
 * working day: stock with movements behind it, a purchase that went all the
 * way to the shelf, fabric rolls that have been inspected and cut, a lot of
 * dye that is about to expire, cloth out at a dyer, and a floor requisition
 * the store could only half fill.
 *
 * Everything is built through the real Actions rather than inserted, so the
 * demo carries the same ledger rows, claims and statuses a real week would
 * produce — a demo assembled with raw inserts looks right on the screen and
 * falls apart the moment somebody clicks something.
 *
 * Safe to re-run: it stops if its own vendor already exists.
 */
class DemoInventorySeeder extends Seeder
{
    private const MILL_CODE = 'DEMO-MILL';

    private const DYER_CODE = 'DEMO-DYER';

    public function run(): void
    {
        $company = Company::withoutGlobalScope(TenantScope::class)->orderBy('created_at')->first();

        if ($company === null) {
            $this->command?->warn('No company to attach demo inventory to.');

            return;
        }

        app(TenantContext::class)->forget();
        app(TenantContext::class)->set($company->id);

        if (Vendor::where('code', self::MILL_CODE)->exists()) {
            $this->command?->info('Demo inventory already present.');

            return;
        }

        $factory = Factory::where('company_id', $company->id)->orderBy('created_at')->first();
        $bin = Bin::whereHas('store.warehouse', fn ($q) => $q->where('factory_id', $factory?->id))
            ->where('active', true)
            ->orderBy('code')
            ->first();

        if ($factory === null || $bin === null) {
            $this->command?->warn('No factory or bin to attach demo inventory to — run DemoTenantSeeder first.');

            return;
        }

        $storekeeper = $this->userWithRole($company, 'STOREKEEPER');
        $storeManager = $this->userWithRole($company, 'STORE_MANAGER') ?? $storekeeper;
        $technician = $this->userWithRole($company, 'TECHNICIAN') ?? $storekeeper;

        $vendors = $this->vendors($company);
        $items = $this->items($company);

        $this->purchaseToShelf($factory, $bin, $items, $vendors['mill'], $storeManager, $storekeeper);
        $this->chemicalLots($factory, $bin, $items, $vendors['mill'], $storekeeper);
        $rolls = $this->fabricRolls($factory, $bin, $items, $vendors['mill'], $storekeeper);
        $this->splitAndMerge($rolls, $storekeeper);
        $this->subcontract($factory, $rolls, $vendors['dyer'], $storekeeper);
        $this->booking($factory, $items, $vendors['mill'], $storeManager);
        $this->floorRequisition($factory, $bin, $items, $technician, $storekeeper);
        $this->manualHold($items, $bin, $storeManager);

        $this->command?->info('Demo inventory and procurement data created.');
    }

    /**
     * @return array{mill: Vendor, dyer: Vendor}
     */
    private function vendors(Company $company): array
    {
        return [
            'mill' => Vendor::create([
                'company_id' => $company->id,
                'name' => 'Matin Textile Mills Ltd',
                'code' => self::MILL_CODE,
                'vendor_type' => 'SUPPLIER',
                'status' => 'ACTIVE',
                'default_currency' => 'BDT',
            ]),
            'dyer' => Vendor::create([
                'company_id' => $company->id,
                'name' => 'Savar Dyeing & Finishing',
                'code' => self::DYER_CODE,
                'vendor_type' => 'SUPPLIER',
                'status' => 'ACTIVE',
                'default_currency' => 'BDT',
            ]),
        ];
    }

    /**
     * One item of each tracking mode, because the three behave differently
     * everywhere downstream: a pooled quantity, a lot with an expiry, and
     * fabric that lives as individual rolls.
     *
     * @return array<string, Item>
     */
    private function items(Company $company): array
    {
        $department = ItemDepartment::where('active', true)->orderBy('name')->first()
            ?? ItemDepartment::create(['company_id' => $company->id, 'name' => 'General Store', 'code' => 'GEN', 'default_item_type' => 'GENERAL', 'active' => true]);

        $category = ItemCategory::whereNull('parent_id')->orderBy('name')->first();

        $uom = fn (string $code): ?string => UnitOfMeasure::availableTo($company->id)->where('code', $code)->value('id');

        $fabric = Item::create([
            'company_id' => $company->id,
            'item_department_id' => $department->id,
            'category_id' => $category?->id,
            'item_code' => 'DEMO-FAB-SJ180',
            'name' => 'Single jersey 180 GSM, navy',
            'item_type' => 'FABRIC',
            'tracking_mode' => 'ROLL',
            'uom_id' => $uom('MTR') ?? $uom('PCS'),
            'active' => true,
        ]);

        ItemFabricAttributes::create([
            'company_id' => $company->id,
            'item_id' => $fabric->id,
            'fabric_type' => 'KNIT',
            'gsm' => '180',
            'width' => '72',
            'composition' => '100% cotton',
        ]);

        return [
            'fabric' => $fabric,
            'dye' => Item::create([
                'company_id' => $company->id,
                'item_department_id' => $department->id,
                'category_id' => $category?->id,
                'item_code' => 'DEMO-CHM-BLK',
                'name' => 'Reactive black dye',
                'item_type' => 'CHEMICAL',
                'tracking_mode' => 'LOT',
                'uom_id' => $uom('KG') ?? $uom('PCS'),
                'reorder_level' => '20',
                'active' => true,
            ]),
            'button' => Item::create([
                'company_id' => $company->id,
                'item_department_id' => $department->id,
                'category_id' => $category?->id,
                'item_code' => 'DEMO-TRM-BTN18',
                'name' => 'Button 18L, 4-hole',
                'item_type' => 'TRIMS',
                'tracking_mode' => 'QUANTITY',
                'uom_id' => $uom('PCS'),
                'reorder_level' => '2000',
                'is_returnable' => false,
                'active' => true,
            ]),
            'gauge' => Item::create([
                'company_id' => $company->id,
                'item_department_id' => $department->id,
                'category_id' => $category?->id,
                'item_code' => 'DEMO-TOOL-GAUGE',
                'name' => 'Seam gauge (returnable)',
                'item_type' => 'GENERAL',
                'tracking_mode' => 'QUANTITY',
                'uom_id' => $uom('PCS'),
                // Lent to the floor and expected back (spec §7).
                'is_returnable' => true,
                'active' => true,
            ]),
        ];
    }

    /**
     * The whole buying chain, so the demo has a requisition that became an
     * order that became stock — not three unrelated documents.
     *
     * @param  array<string, Item>  $items
     */
    private function purchaseToShelf(
        Factory $factory,
        Bin $bin,
        array $items,
        Vendor $mill,
        ?User $storeManager,
        ?User $storekeeper,
    ): void {
        $requisition = app(ManagePurchaseRequisition::class)->create(
            $factory,
            ['store_id' => $bin->store_id, 'priority' => 'NORMAL', 'notes' => 'Monthly trims top-up.'],
            [
                ['item_id' => $items['button']->id, 'requested_qty' => '5000', 'estimated_rate' => '2.5'],
                ['item_id' => $items['gauge']->id, 'requested_qty' => '10', 'estimated_rate' => '450'],
            ],
            $storeManager?->id,
        );

        app(ManagePurchaseRequisition::class)->submit($requisition, $storeManager?->id);

        $order = app(ManagePurchaseOrder::class)->createFromRequisition(
            $requisition->fresh(),
            $mill,
            ['store_id' => $bin->store_id],
            [
                ['pr_line_id' => $requisition->lines()->where('item_id', $items['button']->id)->value('id'), 'rate' => '2.40'],
                ['pr_line_id' => $requisition->lines()->where('item_id', $items['gauge']->id)->value('id'), 'rate' => '430'],
            ],
            $storeManager?->id,
        );

        app(ManagePurchaseOrder::class)->submit($order, $storeManager?->id);

        $receipt = app(ManageGoodsReceipt::class)->createFromOrder(
            $order->fresh(),
            $bin->store,
            ['vendor_id' => $mill->id, 'gate_pass_no' => 'GP-DEMO-001'],
            $order->fresh()->lines->map(fn ($line): array => [
                'po_line_id' => $line->id,
                'bin_id' => $bin->id,
            ])->all(),
            $storekeeper?->id,
        );

        app(ManageGoodsReceipt::class)->complete($receipt->fresh(), [], $storekeeper?->id);
    }

    /**
     * A lot-tracked chemical, with one batch deliberately close to its
     * expiry so the nearest-expiry suggestion and the daily sweep both have
     * something real to say the moment the demo is opened.
     *
     * @param  array<string, Item>  $items
     */
    private function chemicalLots(Factory $factory, Bin $bin, array $items, Vendor $mill, ?User $storekeeper): void
    {
        $today = CarbonImmutable::today();

        $batches = [
            // lot, quantity, rate, received, expiry
            ['DYE-2026-03', '40', '880', $today->subMonths(4), $today->addDays(12)],
            ['DYE-2026-08', '60', '910', $today->subMonth(), $today->addMonths(9)],
        ];

        foreach ($batches as [$lot, $quantity, $rate, $received, $expiry]) {
            $receipt = app(ManageGoodsReceipt::class)->create(
                $factory,
                $bin->store,
                ['vendor_id' => $mill->id, 'grn_date' => $received->toDateString()],
                [[
                    'item_id' => $items['dye']->id,
                    'received_qty' => $quantity,
                    'rate' => $rate,
                    'bin_id' => $bin->id,
                    'lot_no' => $lot,
                    'lot_expiry_date' => $expiry->toDateString(),
                    'lot_manufactured_date' => $received->subMonths(2)->toDateString(),
                    'supplier_lot_no' => 'MILL-'.substr($lot, -2),
                ]],
                $storekeeper?->id,
            );

            app(ManageGoodsReceipt::class)->complete($receipt->fresh(), [], $storekeeper?->id);
        }
    }

    /**
     * Fabric received as rolls and inspected, since a ROLL item's stock is
     * its rolls — an uninspected roll is not on the shelf at all.
     *
     * @param  array<string, Item>  $items
     * @return list<FabricRoll>
     */
    private function fabricRolls(Factory $factory, Bin $bin, array $items, Vendor $mill, ?User $storekeeper): array
    {
        $today = CarbonImmutable::today();

        $receipt = app(ManageGoodsReceipt::class)->create(
            $factory,
            $bin->store,
            ['vendor_id' => $mill->id, 'grn_date' => $today->subDays(6)->toDateString()],
            [['item_id' => $items['fabric']->id, 'received_qty' => '420', 'rate' => '265']],
            $storekeeper?->id,
        );

        $receipt = $receipt->fresh();
        $lineId = $receipt->lines->first()->id;

        $entries = [
            ['DEMO-R-001', '120'],
            ['DEMO-R-002', '150'],
            ['DEMO-R-003', '90'],
            ['DEMO-R-004', '60'],
        ];

        app(ManageGoodsReceipt::class)->complete($receipt, [], $storekeeper?->id, [
            $lineId => array_map(fn (array $entry): array => [
                'roll_no' => $entry[0],
                'bin_id' => $bin->id,
                'lot_no' => 'FAB-LOT-1',
                'shade' => 'NAVY-01',
                'shade_band' => 'A',
                'nominal_width' => '72',
                'net_length' => $entry[1],
                'gross_length' => bcadd($entry[1], '1.5', 2),
            ], $entries),
        ]);

        $inspect = app(\App\Modules\ItemMaster\Actions\InspectFabricRoll::class);
        $rolls = [];

        foreach ($entries as $index => [$rollNo]) {
            $roll = FabricRoll::where('roll_no', $rollNo)->firstOrFail();

            // The last roll fails, so the demo has a rejection on it too.
            $rolls[] = $index === count($entries) - 1
                ? $inspect->handle($roll, ['grade' => 'REJECT', 'disposition' => 'DAMAGED', 'notes' => 'Oil stain through the roll.'], (string) $storekeeper?->id)
                : $inspect->handle($roll, ['grade' => 'A', 'shade_match' => true, 'actual_width' => '71.5'], (string) $storekeeper?->id);
        }

        return $rolls;
    }

    /**
     * One roll cut down and two joined, so both halves of spec §5–§6 are
     * visible in the demo rather than only described.
     *
     * @param  list<FabricRoll>  $rolls
     */
    private function splitAndMerge(array $rolls, ?User $storekeeper): void
    {
        $toSplit = FabricRoll::where('roll_no', 'DEMO-R-002')->first();

        if ($toSplit !== null && $toSplit->isOnShelf()) {
            app(SplitFabricRoll::class)->handle($toSplit, [
                ['roll_no' => 'DEMO-R-002-A', 'net_length' => '90'],
                ['roll_no' => 'DEMO-R-002-B', 'net_length' => '60'],
            ], $storekeeper?->id);
        }

        $first = FabricRoll::where('roll_no', 'DEMO-R-002-B')->first();
        $second = FabricRoll::where('roll_no', 'DEMO-R-003')->first();

        if ($first !== null && $second !== null) {
            app(MergeFabricRolls::class)->handle(
                [$first->id, $second->id],
                ['roll_no' => 'DEMO-R-M-01', 'notes' => 'Short ends joined for the next cut.'],
                $storekeeper?->id,
            );
        }
    }

    /**
     * Cloth out at a dyer, half of it already back — the state a subcontract
     * register is normally in.
     *
     * @param  list<FabricRoll>  $rolls
     */
    private function subcontract(Factory $factory, array $rolls, Vendor $dyer, ?User $storekeeper): void
    {
        $out = FabricRoll::whereIn('roll_no', ['DEMO-R-001', 'DEMO-R-002-A'])
            ->where('status', 'AVAILABLE')
            ->get();

        if ($out->count() < 2) {
            return;
        }

        $challans = app(ManageSubcontractChallan::class);

        $challan = $challans->create(
            $factory,
            [
                'vendor_id' => $dyer->id,
                'purpose' => 'DYEING',
                'expected_return_date' => CarbonImmutable::today()->addDays(9)->toDateString(),
                'notes' => 'Navy shade, match approved swatch.',
            ],
            $out->pluck('id')->all(),
            $storekeeper?->id,
        );

        $challan = $challans->send($challan, $storekeeper?->id);

        // One roll back with a little shrinkage; the other still out.
        $line = $challan->lines()->where('roll_no', 'DEMO-R-001')->first();

        if ($line !== null) {
            $challans->receiveReturn($challan, [[
                'line_id' => $line->id,
                'returned_qty' => '116',
                'wastage_qty' => '4',
                'quality_status' => 'Shade matched',
            ]], $storekeeper?->id);
        }
    }

    /**
     * A confirmed fabric booking, which is what makes the material
     * requirement subtract something rather than reading as all shortfall.
     *
     * @param  array<string, Item>  $items
     */
    private function booking(Factory $factory, array $items, Vendor $mill, ?User $storeManager): void
    {
        $bookings = app(ManageFabricBooking::class);

        $booking = $bookings->create(
            $factory,
            [
                'vendor_id' => $mill->id,
                'required_date' => CarbonImmutable::today()->addWeeks(4)->toDateString(),
                'notes' => 'Booked against next month’s forecast.',
            ],
            [['item_id' => $items['fabric']->id, 'booked_qty' => '600', 'tentative_rate' => '268']],
            $storeManager?->id,
        );

        $bookings->confirm($booking, $storeManager?->id);
    }

    /**
     * A floor requisition the store could only half fill, so the demo shows
     * the shortage, the automatic purchase requisition and the claim on the
     * stock that is coming.
     *
     * @param  array<string, Item>  $items
     */
    private function floorRequisition(Factory $factory, Bin $bin, array $items, ?User $technician, ?User $storekeeper): void
    {
        $requisition = app(ManageFloorRequisition::class)->create(
            $factory,
            [
                'target_item_department_id' => $items['button']->item_department_id,
                'priority' => 'HIGH',
                'purpose' => 'Line 3 — style ST-100 sewing.',
            ],
            [
                ['item_id' => $items['button']->id, 'requested_qty' => '6000', 'purpose' => 'Front placket'],
                ['item_id' => $items['gauge']->id, 'requested_qty' => '2', 'purpose' => 'Seam checks'],
            ],
            $technician?->id,
        );

        app(ManageFloorRequisition::class)->submit($requisition, $technician?->id);

        $requisition = $requisition->fresh();
        $lines = $requisition->lines()->get()->keyBy('item_id');

        // 5000 buttons were bought; 6000 were asked for. The rest becomes a
        // shortage, an automatic purchase requisition and an earmark.
        app(FulfilFloorRequisition::class)->issue($requisition, [
            ['line_id' => $lines[$items['button']->id]->id, 'quantity' => '5000', 'bin_id' => $bin->id],
            ['line_id' => $lines[$items['gauge']->id]->id, 'quantity' => '2', 'bin_id' => $bin->id],
        ], $storekeeper?->id);
    }

    /**
     * A hand-placed hold, so the item screen shows why stock that is
     * visibly on the shelf cannot be issued.
     *
     * @param  array<string, Item>  $items
     */
    private function manualHold(array $items, Bin $bin, ?User $storeManager): void
    {
        $available = app(\App\Modules\ItemMaster\Services\ItemLedger::class)->available($items['dye'], $bin, 'DYE-2026-08');

        if (bccomp($available, '10', 4) < 0) {
            return;
        }

        app(ReserveItemStock::class)->reserve($items['dye'], $bin, '10', [
            'reservation_type' => 'MANUAL',
            'lot_no' => 'DYE-2026-08',
            'reserved_by' => $storeManager?->id,
            'expires_at' => CarbonImmutable::now()->addWeeks(2),
            'override_reason' => 'Held for the navy re-dye trial.',
        ]);
    }

    /**
     * Whoever holds this role in the demo company. Read through `user_roles`
     * directly rather than a relation, the way `DemoTenantSeeder` writes it.
     */
    private function userWithRole(Company $company, string $roleCode): ?User
    {
        $role = Role::whereNull('company_id')->where('name', $roleCode)->first();

        if ($role === null) {
            return null;
        }

        $userId = UserRole::withoutGlobalScope(TenantScope::class)
            ->where('company_id', $company->id)
            ->where('role_id', $role->id)
            ->orderBy('created_at')
            ->value('user_id');

        return $userId === null
            ? null
            : User::withoutGlobalScope(TenantScope::class)->find($userId);
    }
}
