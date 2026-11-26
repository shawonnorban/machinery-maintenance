<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/**
 * Units, stores and categories are kept in one list each, on General Store's
 * screens (the user's decision of 2026-11-15, "General Store's screen, one
 * table"):
 *
 * - Units → `units_of_measure` (the list every module's unit pick reads),
 * - Stores → `stores` (Warehouse → Store → Bin, which Machinery, Knitting,
 *   Merchandise, Prototype and the store mappings already use),
 * - Categories → `item_categories` (the category tree Access Control's
 *   category permission reads).
 *
 * General Store's own copies (`gs_units`, `gs_stores`, `gs_categories`) and
 * Machinery's `spare_part_categories` are folded in — matched by code, else
 * added — every column that pointed at them is pointed at the shared list,
 * and they are dropped. A data move: down() does not put them back.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('stores', function (Blueprint $table): void {
            $table->string('location')->nullable()->after('store_type');
            $table->string('in_charge')->nullable()->after('location');
            $table->boolean('is_default')->default(false)->after('in_charge');
        });
        Schema::table('item_categories', function (Blueprint $table): void {
            $table->text('description')->nullable()->after('code');
        });

        $maps = [
            'gs_units' => ['units_of_measure', $this->moveUnits()],
            'gs_categories' => ['item_categories', $this->moveCategories()],
            'gs_stores' => ['stores', $this->moveStores()],
            'spare_part_categories' => ['item_categories', $this->moveSparePartCategories()],
        ];

        foreach ($maps as $old => [$new, $map]) {
            $this->repoint($old, $new, $map);
        }

        foreach (array_keys($maps) as $old) {
            Schema::dropIfExists($old);
        }
    }

    public function down(): void
    {
        // A data move — restore the old tables from a backup if ever needed.
    }

    /** @return array<string, string> gs unit id => unit of measure id (the company's own, else the platform's, else added) */
    private function moveUnits(): array
    {
        $map = [];
        foreach (DB::table('gs_units')->get() as $row) {
            $code = strtoupper(trim((string) $row->code));
            $id = DB::table('units_of_measure')->where('company_id', $row->company_id)->where('code', $code)->value('id')
                ?? DB::table('units_of_measure')->whereNull('company_id')->where('code', $code)->value('id');
            if ($id === null) {
                $id = (string) Str::ulid();
                DB::table('units_of_measure')->insert(['id' => $id, 'company_id' => $row->company_id, 'code' => $code, 'name' => $row->name, 'active' => $row->active, 'created_at' => now(), 'updated_at' => now()]);
            }
            $map[$row->id] = $id;
        }

        return $map;
    }

    /** @return array<string, string> gs category id => item category id (top level when added) */
    private function moveCategories(): array
    {
        $map = [];
        foreach (DB::table('gs_categories')->get() as $row) {
            $map[$row->id] = $this->category($row->company_id, (string) $row->code, (string) $row->name, null, $row->description ?? null, (bool) $row->active);
        }

        return $map;
    }

    /**
     * Machinery's spare part categories (often platform rows) become each
     * using company's item categories.
     *
     * @return array<string, array<string, string>> old id => [company id => item category id]
     */
    private function moveSparePartCategories(): array
    {
        $map = [];
        $used = DB::table('spare_parts')->whereNotNull('category_id')->select('company_id', 'category_id')->distinct()->get();
        $rows = DB::table('spare_part_categories')->get()->keyBy('id');
        foreach ($used as $use) {
            $row = $rows[$use->category_id] ?? null;
            if ($row !== null) {
                $map[$row->id][$use->company_id] = $this->category($use->company_id, (string) $row->code, (string) $row->name, $row->name_bn, null, (bool) $row->active);
            }
        }
        // Each company's own categories move even when no part uses them yet.
        foreach ($rows->whereNotNull('company_id') as $row) {
            $map[$row->id][$row->company_id] ??= $this->category($row->company_id, (string) $row->code, (string) $row->name, $row->name_bn, null, (bool) $row->active);
        }

        return $map;
    }

    private function category(string $companyId, string $code, string $name, ?string $nameBn, ?string $description, bool $active): string
    {
        $code = strtoupper(trim($code));
        $id = DB::table('item_categories')->where('company_id', $companyId)->where('code', $code)->value('id');
        if ($id !== null) {
            if ($description !== null) {
                DB::table('item_categories')->where('id', $id)->whereNull('description')->update(['description' => $description]);
            }

            return (string) $id;
        }
        $id = (string) Str::ulid();
        DB::table('item_categories')->insert([
            'id' => $id, 'company_id' => $companyId, 'parent_id' => null, 'code' => $code, 'name' => $name, 'name_bn' => $nameBn,
            'description' => $description, 'active' => $active, 'created_at' => now(), 'updated_at' => now(),
        ]);

        return $id;
    }

    /** @return array<string, string> gs store id => store id (same code, else added in the company's first warehouse) */
    private function moveStores(): array
    {
        $map = [];
        foreach (DB::table('gs_stores')->get() as $row) {
            $code = strtoupper(trim((string) $row->code));
            $id = DB::table('stores')->where('company_id', $row->company_id)->where('code', $code)->value('id');
            if ($id !== null) {
                DB::table('stores')->where('id', $id)->update(['location' => $row->location, 'in_charge' => $row->in_charge, 'is_default' => $row->is_default]);
            } else {
                $id = (string) Str::ulid();
                DB::table('stores')->insert([
                    'id' => $id, 'company_id' => $row->company_id, 'warehouse_id' => $this->warehouse($row->company_id), 'code' => $code, 'name' => $row->name,
                    'store_type' => 'GENERAL', 'location' => $row->location, 'in_charge' => $row->in_charge, 'is_default' => $row->is_default,
                    'active' => $row->active, 'created_at' => now(), 'updated_at' => now(),
                ]);
            }
            $map[$row->id] = $id;
        }

        return $map;
    }

    /** The company's first warehouse — one is added in its first factory when it has none. */
    private function warehouse(string $companyId): string
    {
        $id = DB::table('warehouses')->where('company_id', $companyId)->orderBy('created_at')->value('id');
        if ($id !== null) {
            return (string) $id;
        }
        $factory = DB::table('factories')->where('company_id', $companyId)->orderBy('created_at')->value('id');
        $id = (string) Str::ulid();
        DB::table('warehouses')->insert(['id' => $id, 'company_id' => $companyId, 'factory_id' => $factory, 'name' => 'Main Warehouse', 'code' => 'WH', 'active' => true, 'created_at' => now(), 'updated_at' => now()]);

        return $id;
    }

    /**
     * Every foreign key on an old table moves to the shared one, its values
     * mapped. A map may be per company (old id => [company => new id]).
     */
    private function repoint(string $old, string $new, array $map): void
    {
        $keys = DB::select(<<<'SQL'
            select t.relname as table_name, a.attname as column_name, c.conname as constraint_name,
                case c.confdeltype when 'c' then 'CASCADE' when 'n' then 'SET NULL' when 'r' then 'RESTRICT' when 'd' then 'SET DEFAULT' else 'NO ACTION' end as delete_rule
            from pg_constraint c
            join pg_class t on t.oid = c.conrelid
            join pg_namespace n on n.oid = t.relnamespace and n.nspname = current_schema()
            join pg_class r on r.oid = c.confrelid and r.relname = ?
            join pg_attribute a on a.attrelid = c.conrelid and a.attnum = c.conkey[1]
            where c.contype = 'f' and r.relnamespace = n.oid
            SQL, [$old]);

        foreach ($keys as $key) {
            DB::statement("ALTER TABLE \"{$key->table_name}\" DROP CONSTRAINT \"{$key->constraint_name}\"");

            $rows = DB::table($key->table_name)->whereNotNull($key->column_name);
            $perCompany = Schema::hasColumn($key->table_name, 'company_id');
            foreach ($rows->select($key->column_name, ...($perCompany ? ['company_id'] : []))->distinct()->get() as $row) {
                $target = $map[$row->{$key->column_name}] ?? null;
                if (is_array($target)) {
                    $target = $perCompany ? ($target[$row->company_id] ?? null) : (reset($target) ?: null);
                }
                $q = DB::table($key->table_name)->where($key->column_name, $row->{$key->column_name});
                if ($perCompany) {
                    $q->where('company_id', $row->company_id);
                }
                $q->update([$key->column_name => $target]);
            }

            $onDelete = match ($key->delete_rule) {
                'CASCADE' => 'cascade',
                'SET NULL' => 'set null',
                default => 'restrict',
            };
            Schema::table($key->table_name, function (Blueprint $table) use ($key, $new, $onDelete): void {
                $table->foreign($key->column_name)->references('id')->on($new)->onDelete($onDelete);
            });
        }
    }
};
