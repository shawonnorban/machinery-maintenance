<?php

declare(strict_types=1);

namespace App\Modules\Erp\Support;

use App\Modules\DevSample\Models\DsColumn;
use App\Modules\DevSample\Models\DsGroup;
use App\Modules\DevSample\Support\DsCatalog;
use App\Modules\ExportStatement\Models\EsColumn;
use App\Modules\ExportStatement\Models\EsGroup;
use App\Modules\ExportStatement\Support\EsCatalog;
use App\Modules\OrderSummary\Models\OsColumn;
use App\Modules\OrderSummary\Models\OsGroup;
use App\Modules\OrderSummary\Support\ColumnCatalog;
use App\Modules\PreBudget\Models\PbColumn;
use App\Modules\PreBudget\Models\PbGroup;
use App\Modules\PreBudget\Models\PbUnitRule;
use App\Modules\PreBudget\Support\BomCatalog;
use App\Modules\PreBudget\Support\RequisitionCatalog;
use App\Shared\Support\ColumnSetupSnapshot;
use Throwable;

/**
 * The sheets whose Column Setup is kept in the code's JSON files, and the
 * saving of them. A Column Setup save writes the files again by itself once
 * the request is done (user 2026-11-21); `erp:save-column-setup` does it by hand.
 */
final class ColumnSetups
{
    /** @var array<string, true> companies whose setup is already due to be saved this request */
    private static array $queued = [];

    /** @return array<string, array{0: string, 1: class-string, 2: class-string, 3: ?string, 4: ?class-string}> */
    public static function sheets(): array
    {
        return [
            'Order Summary' => [ColumnCatalog::snapshotPath(), OsGroup::class, OsColumn::class, null, null],
            'Requisition' => [RequisitionCatalog::snapshotPath(), PbGroup::class, PbColumn::class, 'REQ', null],
            'Acc Budget' => [BomCatalog::snapshotPath(), PbGroup::class, PbColumn::class, BomCatalog::SHEET, PbUnitRule::class],
            'Dev Sample' => [DsCatalog::snapshotPath(), DsGroup::class, DsColumn::class, null, null],
            'Export Statement' => [EsCatalog::snapshotPath(), EsGroup::class, EsColumn::class, null, null],
        ];
    }

    /**
     * Writes one company's setup of every sheet it has; a sheet it has none of keeps its file.
     *
     * @return array<string, array<string, mixed>|null> each sheet's saved setup (null: left as it was)
     */
    public static function save(string $companyId): array
    {
        $out = [];
        foreach (self::sheets() as $name => [$path, $group, $column, $sheet, $rules]) {
            $data = ColumnSetupSnapshot::export($companyId, $group, $column, $sheet, $rules);
            if ($data['columns'] === []) {
                $out[$name] = null;

                continue;
            }
            $current = ColumnSetupSnapshot::read($path);
            if ($current !== $data) {
                ColumnSetupSnapshot::write($path, $data);
            }
            $out[$name] = $data;
        }

        return $out;
    }

    /** Saves the company's setup once the request (or command) is done — once, however many rows changed. */
    public static function saveLater(?string $companyId): void
    {
        if ($companyId === null || ! config('app.column_setup_snapshots', true) || isset(self::$queued[$companyId])) {
            return;
        }
        self::$queued[$companyId] = true;
        app()->terminating(function () use ($companyId): void {
            unset(self::$queued[$companyId]);
            try {
                self::save($companyId);
            } catch (Throwable $e) {
                // The setup itself is saved; only its copy in the code missed — logged, never the user's error.
                report($e);
            }
        });
    }
}
