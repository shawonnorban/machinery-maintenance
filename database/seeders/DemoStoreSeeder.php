<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Modules\Inventory\Models\Bin;
use App\Modules\Inventory\Models\Store;
use App\Modules\Inventory\Models\Warehouse;
use App\Modules\Tenancy\Models\Company;
use App\Modules\Tenancy\Models\Factory;
use App\Shared\Scopes\TenantScope;
use App\Shared\Tenancy\TenantContext;
use Illuminate\Database\Seeder;

/**
 * The stores a garment factory actually has (Master Data spec §5).
 *
 * `DemoTenantSeeder` seeds one — the spare parts store the maintenance side
 * needs — which is enough to demonstrate a ledger and nothing else. A real
 * factory keeps fabric, trims, yarn and chemicals apart, because they are
 * different rooms with different people and different racking, and a
 * requisition that cannot name which store it is drawing from is a
 * requisition somebody has to walk around the building to fill.
 *
 * The bonded store is here for the same reason it is in the spec: imported
 * fabric under bond cannot be mixed with duty-paid stock, and `store_type`
 * exists precisely so the difference is a property of the store rather than
 * a convention people remember.
 *
 * Idempotent, and safe to run on a database that already has stores: every
 * store and bin is keyed on its code, so re-running tops up what is missing
 * and touches nothing else.
 */
class DemoStoreSeeder extends Seeder
{
    /**
     * Code suffix, name, store type, and the racks inside it.
     *
     * Rack letters follow what each room is actually like: fabric goes on a
     * few long racks, trims on many small ones, chemicals in a bonded-height
     * cupboard. They are not meant to be exhaustive — a factory maps its own
     * racking — but an empty store is not usable in a demo, and one bin per
     * store would make every "which bin?" dropdown meaningless.
     *
     * @var list<array{0: string, 1: string, 2: string, 3: list<array{0: string, 1: string}>}>
     */
    private const STORES = [
        ['FAB', 'Fabric store', 'GENERAL', [
            ['F1', 'Fabric rack 1'],
            ['F2', 'Fabric rack 2'],
            ['F3', 'Fabric rack 3'],
        ]],
        ['TRM', 'Trims & accessories store', 'GENERAL', [
            ['T1', 'Trims rack 1'],
            ['T2', 'Trims rack 2'],
            ['T3', 'Trims rack 3'],
        ]],
        ['YRN', 'Yarn store', 'GENERAL', [
            ['Y1', 'Yarn rack 1'],
            ['Y2', 'Yarn rack 2'],
        ]],
        ['CHM', 'Chemical & dyes store', 'GENERAL', [
            ['C1', 'Chemical rack 1'],
            ['C2', 'Chemical rack 2'],
        ]],
        ['GEN', 'General & stationery store', 'GENERAL', [
            ['G1', 'General rack 1'],
            ['G2', 'General rack 2'],
        ]],
        // Imported fabric under bond, kept apart from duty-paid stock.
        ['BND', 'Bonded store', 'BONDED', [
            ['B1', 'Bonded rack 1'],
            ['B2', 'Bonded rack 2'],
        ]],
    ];

    public function run(): void
    {
        $companies = Company::withoutGlobalScope(TenantScope::class)->get();

        if ($companies->isEmpty()) {
            $this->command?->warn('No company to attach stores to.');

            return;
        }

        $created = 0;

        foreach ($companies as $company) {
            app(TenantContext::class)->forget();
            app(TenantContext::class)->set($company->id);

            foreach (Factory::where('company_id', $company->id)->get() as $factory) {
                $created += $this->storesFor($company, $factory);
            }
        }

        app(TenantContext::class)->forget();

        $this->command?->info("Stores seeded ($created new).");
    }

    private function storesFor(Company $company, Factory $factory): int
    {
        // One warehouse per factory — the building. Stores are the rooms in
        // it, which is the level a requisition actually names.
        $warehouse = Warehouse::firstOrCreate(
            ['company_id' => $company->id, 'code' => $factory->code.'-WH'],
            ['factory_id' => $factory->id, 'name' => $factory->name.' main store'],
        );

        $created = 0;

        foreach (self::STORES as [$suffix, $name, $type, $racks]) {
            $code = $factory->code.'-'.$suffix;

            $store = Store::where('company_id', $company->id)->where('code', $code)->first();

            if ($store === null) {
                $store = Store::create([
                    'company_id' => $company->id,
                    'warehouse_id' => $warehouse->id,
                    'name' => $name,
                    'code' => $code,
                    'store_type' => $type,
                    'active' => true,
                ]);

                $created++;
            }

            foreach ($racks as [$binSuffix, $binName]) {
                Bin::firstOrCreate(
                    ['company_id' => $company->id, 'code' => $factory->code.'-'.$binSuffix],
                    ['store_id' => $store->id, 'name' => $binName, 'active' => true],
                );
            }
        }

        return $created;
    }
}
