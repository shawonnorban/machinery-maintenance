<?php

declare(strict_types=1);

namespace App\Modules\GeneralStore\Models;

use App\Shared\Concerns\BelongsToTenant;
use App\Shared\Models\BaseModel;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\DB;

/**
 * A store goods are kept in, where it is and who is in charge (Store / Location). General Store → Master Data (AnnotechERP 10/01).
 */
class GsStore extends BaseModel
{
    use BelongsToTenant;

    /**
     * The one store list (the user's decision of 2026-11-15): `stores`
     * (Warehouse → Store → Bin), shared with Machinery, Knitting and the rest.
     * A store added here goes in the company's first warehouse as a GENERAL
     * store unless told otherwise.
     */
    protected $table = 'stores';

    protected $fillable = ['company_id', 'warehouse_id', 'code', 'name', 'store_type', 'location', 'in_charge', 'is_default', 'active'];

    protected static function booted(): void
    {
        static::creating(function (self $store): void {
            $store->store_type ??= 'GENERAL';
            $store->warehouse_id ??= self::firstWarehouse((string) $store->company_id);
        });
    }

    /** The company's first warehouse; one is added in its first factory when it has none. */
    public static function firstWarehouse(string $companyId): ?string
    {
        $id = DB::table('warehouses')->where('company_id', $companyId)->where('active', true)->orderBy('created_at')->value('id');
        if ($id !== null) {
            return (string) $id;
        }
        $factory = DB::table('factories')->where('company_id', $companyId)->orderBy('created_at')->value('id');
        if ($factory === null) {
            return null;
        }
        $id = (string) Str::ulid();
        DB::table('warehouses')->insert(['id' => $id, 'company_id' => $companyId, 'factory_id' => $factory, 'name' => 'Main Warehouse', 'code' => 'WH', 'active' => true, 'created_at' => now(), 'updated_at' => now()]);

        return $id;
    }

    protected function casts(): array
    {
        return ['is_default' => 'boolean', 'active' => 'boolean'];
    }
}
