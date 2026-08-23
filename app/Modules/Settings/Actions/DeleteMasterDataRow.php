<?php

declare(strict_types=1);

namespace App\Modules\Settings\Actions;

use App\Modules\Settings\MasterData\MasterDataType;
use App\Shared\Scopes\TenantScope;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\ValidationException;

/**
 * Removes a reference row, but only while nothing has been filed against it
 * (SRS 6).
 *
 * The database would refuse anyway — these foreign keys restrict on delete —
 * but a 500 from a constraint violation tells the person nothing. The count is
 * checked first so the screen can say what is in the way and offer the thing
 * they actually want, which is to stop the row appearing in new work without
 * rewriting the history that used it.
 */
class DeleteMasterDataRow
{
    public function __construct(private readonly SaveMasterDataRow $save) {}

    public function handle(MasterDataType $type, Model $row): void
    {
        $this->save->assertOwned($row);

        $used = $this->usageCount($type, $row);

        if ($used > 0) {
            throw ValidationException::withMessages([
                'code' => __('masterdata.in_use', ['count' => $used]),
            ])->status(409);
        }

        $row->delete();
    }

    /**
     * How many records point at this row, across every table that can.
     */
    public function usageCount(MasterDataType $type, Model $row): int
    {
        $total = 0;

        foreach ($type->usedBy() as $model => $column) {
            // Without the tenant scope, because the question is whether
            // anything anywhere references this row — a company row can only be
            // referenced by its own company's records, and a platform row by
            // anyone's. Counting only the current tenant's would let one
            // company delete a row another is using.
            $total += $model::query()
                ->withoutGlobalScope(TenantScope::class)
                ->where($column, $row->getKey())
                ->count();
        }

        return $total;
    }
}
