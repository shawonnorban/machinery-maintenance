<?php

declare(strict_types=1);

namespace App\Modules\Compliance\Services;

use App\Modules\Compliance\Models\BondEntitlement;
use App\Modules\Compliance\Models\BondLicence;
use App\Modules\Compliance\Models\UtilisationDeclaration;
use App\Modules\Compliance\Models\UtilisationDeclarationLine;
use App\Modules\ItemMaster\Models\ItemStockTransaction;
use App\Modules\Procurement\Models\GoodsReceipt;
use App\Modules\Procurement\Models\GoodsReceiptLine;
use Carbon\CarbonImmutable;

/**
 * What has actually been used against the bond (Shared Systems §3).
 *
 * Both halves of this are computed rather than stored, and deliberately:
 * imports against a licence are a sum over goods receipts, and
 * consumption against a declaration is a sum over the stock ledger. A
 * running total kept alongside either would be a second answer to a
 * question that already has one, and would go wrong the first time a
 * receipt was cancelled or a movement reversed.
 *
 * The reconciliation customs cares about (§3.2) is **declared against
 * actual**, and it reads both ways: consuming far less than declared
 * leaves bonded goods with no export to justify them, while consuming
 * more is a shortfall somebody has to account for. So the report gives
 * the signed difference, not an absolute one.
 */
class BondUtilisation
{
    private const SCALE = 4;

    /**
     * How much of each entitlement has been imported.
     *
     * @return list<array<string, mixed>>
     */
    public function forLicence(BondLicence $licence): array
    {
        $licence->loadMissing('entitlements.uom:id,code');

        $rows = [];

        foreach ($licence->entitlements as $entitlement) {
            $imported = $this->importedQuantity($licence, $entitlement);
            $entitled = (string) $entitlement->entitled_quantity;
            $remaining = bcsub($entitled, $imported, self::SCALE);

            $rows[] = [
                'entitlement_id' => $entitlement->id,
                'item_id' => $entitlement->item_id,
                'item_code' => $entitlement->item_code,
                'item_name' => $entitlement->item_name,
                'uom' => $entitlement->uom?->code,
                'basis' => $entitlement->basis,
                'period_start' => $entitlement->period_start?->toDateString(),
                'period_end' => $entitlement->period_end?->toDateString(),
                'entitled_quantity' => $entitled,
                'imported_quantity' => $imported,
                'remaining_quantity' => $remaining,
                'used_percent' => bccomp($entitled, '0', self::SCALE) === 1
                    ? bcmul(bcdiv($imported, $entitled, 6), '100', 2)
                    : null,
                // Importing past the entitlement is the thing a bond
                // audit looks for, so it is a flag rather than a negative
                // number somebody has to notice.
                'exceeded' => bccomp($remaining, '0', self::SCALE) === -1,
            ];
        }

        return $rows;
    }

    /**
     * Declared against actually consumed, per line of a declaration.
     *
     * @return list<array<string, mixed>>
     */
    public function reconcile(UtilisationDeclaration $declaration): array
    {
        $declaration->loadMissing(['lines.uom:id,code']);

        $rows = [];

        foreach ($declaration->lines as $line) {
            $consumed = $this->consumedQuantity($declaration, $line);
            $declared = (string) $line->declared_quantity;
            $allowed = $line->allowedQuantity();
            $variance = bcsub($consumed, $declared, self::SCALE);

            $rows[] = [
                'line_id' => $line->id,
                'item_id' => $line->item_id,
                'item_code' => $line->item_code,
                'item_name' => $line->item_name,
                'uom' => $line->uom?->code,
                'declared_quantity' => $declared,
                'wastage_percent' => $line->wastage_percent,
                'allowed_quantity' => $allowed,
                'consumed_quantity' => $consumed,
                // Signed: under-consumption and over-consumption are
                // different problems, and an absolute figure hides which.
                'variance_quantity' => $variance,
                'variance_percent' => bccomp($declared, '0', self::SCALE) === 1
                    ? bcmul(bcdiv($variance, $declared, 6), '100', 2)
                    : null,
                // Past what customs allowed, wastage included.
                'exceeds_allowance' => bccomp($consumed, $allowed, self::SCALE) === 1,
            ];
        }

        return $rows;
    }

    /**
     * What has come in against this entitlement.
     *
     * Counted from completed goods receipts on orders flagged
     * `bond_applicable` for this licence: an import that was not declared
     * against the bond did not use it up.
     */
    private function importedQuantity(BondLicence $licence, BondEntitlement $entitlement): string
    {
        $receipts = GoodsReceipt::query()
            ->where('status', 'COMPLETED')
            ->where('factory_id', $licence->factory_id)
            ->whereHas('order', fn ($q) => $q
                ->where('bond_applicable', true)
                ->where('bond_licence_id', $licence->id));

        // An annual entitlement is measured over its own stated year.
        if ($entitlement->period_start !== null) {
            $receipts->whereDate('grn_date', '>=', $entitlement->period_start->toDateString());
        }

        if ($entitlement->period_end !== null) {
            $receipts->whereDate('grn_date', '<=', $entitlement->period_end->toDateString());
        }

        $total = GoodsReceiptLine::query()
            ->whereIn('goods_receipt_id', $receipts->select('id'))
            ->where('item_id', $entitlement->item_id)
            ->sum('received_qty');

        return $this->scale((string) $total);
    }

    /**
     * What the factory actually drew of this item while the declaration
     * was live.
     *
     * Measured from outbound stock movements rather than from production
     * records: the ledger is what the store can prove, and it is the
     * same figure a customs officer would be shown.
     */
    private function consumedQuantity(UtilisationDeclaration $declaration, UtilisationDeclarationLine $line): string
    {
        $from = $declaration->issue_date ?? CarbonImmutable::today()->subYear();
        $to = $declaration->closed_at?->toDateString()
            ?? $declaration->valid_to?->toDateString()
            ?? CarbonImmutable::today()->toDateString();

        $total = ItemStockTransaction::query()
            ->where('item_id', $line->item_id)
            // Drawn for work, not written off: a write-off against a bond
            // is a shortage, which is assessed for duty rather than
            // counted as consumption.
            ->whereIn('transaction_type', ['ISSUE', 'CONSUME'])
            ->whereDate('transaction_at', '>=', $from->toDateString())
            ->whereDate('transaction_at', '<=', $to)
            ->sum('quantity');

        return $this->scale((string) $total);
    }

    private function scale(string $number): string
    {
        return bcadd($number === '' ? '0' : $number, '0', self::SCALE);
    }
}
