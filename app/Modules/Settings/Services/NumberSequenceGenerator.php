<?php

declare(strict_types=1);

namespace App\Modules\Settings\Services;

use App\Modules\Settings\Models\NumberSequence;
use App\Modules\Settings\Models\NumberSequenceFormat;
use App\Modules\Tenancy\Models\Factory;
use App\Shared\Scopes\TenantScope;
use App\Shared\Tenancy\TenantContext;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

/**
 * Race-safe document numbering (SRS 52, ADR-055).
 *
 * The obvious implementations, MAX(number) + 1 or a count query, both produce
 * duplicates under concurrent creation. That is guaranteed on a shop floor
 * where several people report breakdowns in the same second.
 *
 * Allocation takes a row lock and increments. Gaps are accepted as the cost of
 * correctness; duplicates are not, and a number is never reused after a record
 * is cancelled.
 */
class NumberSequenceGenerator
{
    /** Data Dictionary 6. */
    public const FORMATS = [
        'WORK_ORDER' => ['format' => 'WO-{FACTORY}-{YYYY}{MM}-{SEQ}', 'reset' => 'MONTHLY', 'padding' => 5],
        'BREAKDOWN' => ['format' => 'BD-{FACTORY}-{YYYY}{MM}-{SEQ}', 'reset' => 'MONTHLY', 'padding' => 5],
        'ASSET_TRANSFER' => ['format' => 'AT-{FACTORY}-{YYYY}-{SEQ}', 'reset' => 'YEARLY', 'padding' => 5],
        'INVENTORY_TRANSFER' => ['format' => 'IT-{FACTORY}-{YYYY}-{SEQ}', 'reset' => 'YEARLY', 'padding' => 5],
        'INVOICE' => ['format' => 'INV-{YYYY}-{SEQ}', 'reset' => 'YEARLY', 'padding' => 6],
        'WARRANTY_CLAIM' => ['format' => 'WC-{YYYY}-{SEQ}', 'reset' => 'YEARLY', 'padding' => 5],
        'SERVICE_CONTRACT' => ['format' => 'AMC-{YYYY}-{SEQ}', 'reset' => 'YEARLY', 'padding' => 4],
        'GOODS_RECEIPT' => ['format' => 'GRN-{FACTORY}-{YYYY}{MM}-{SEQ}', 'reset' => 'MONTHLY', 'padding' => 5],
        // Procurement (Inventory spec: Floor/Store Requisition §8, Shared Systems §4).
        'PURCHASE_REQUISITION' => ['format' => 'PR-{FACTORY}-{YYYY}-{SEQ}', 'reset' => 'YEARLY', 'padding' => 4],
        'FLOOR_REQUISITION' => ['format' => 'FR-{FACTORY}-{YYYY}-{SEQ}', 'reset' => 'YEARLY', 'padding' => 4],
        'PURCHASE_ORDER' => ['format' => 'PO-{FACTORY}-{YYYY}-{SEQ}', 'reset' => 'YEARLY', 'padding' => 4],
        'FABRIC_BOOKING' => ['format' => 'FB-{FACTORY}-{YYYY}-{SEQ}', 'reset' => 'YEARLY', 'padding' => 4],
        // Production planning (Master Data spec §6). An order carries the
        // buyer's own number, so only the cutting programme needs one of ours.
        'CUTTING_PROGRAM' => ['format' => 'CP-{FACTORY}-{YYYY}-{SEQ}', 'reset' => 'YEARLY', 'padding' => 4],
        // Fabric out for dyeing/printing/embroidery (Inventory spec §8).
        'FABRIC_INSPECTION' => ['format' => 'FI-{FACTORY}-{YYYY}-{SEQ}', 'reset' => 'YEARLY', 'padding' => 4],
        'SUBCONTRACT_CHALLAN' => ['format' => 'SC-{FACTORY}-{YYYY}-{SEQ}', 'reset' => 'YEARLY', 'padding' => 4],
        // Landed cost voucher (Procurement spec §11). 'LCV', not 'LC' —
        // an LC on a garment document means a letter of credit.
        'LANDED_COST' => ['format' => 'LCV-{FACTORY}-{YYYY}-{SEQ}', 'reset' => 'YEARLY', 'padding' => 4],
        // Goods back to the supplier, and the note that debits them
        // (Procurement spec §12). Two series: the return is ours, the
        // debit note is what their accounts department files.
        'PURCHASE_RETURN' => ['format' => 'PRT-{FACTORY}-{YYYY}-{SEQ}', 'reset' => 'YEARLY', 'padding' => 4],
        'DEBIT_NOTE' => ['format' => 'DN-{FACTORY}-{YYYY}-{SEQ}', 'reset' => 'YEARLY', 'padding' => 4],
        // Money claimed from a supplier, and money paid to one up front
        // (Procurement spec §12, §13).
        'SUPPLIER_CLAIM' => ['format' => 'CLM-{FACTORY}-{YYYY}-{SEQ}', 'reset' => 'YEARLY', 'padding' => 4],
        'SUPPLIER_ADVANCE' => ['format' => 'ADV-{FACTORY}-{YYYY}-{SEQ}', 'reset' => 'YEARLY', 'padding' => 4],
        // Import consignments (Procurement spec §10). The PI number is
        // the supplier's and the LC number is the bank's, so only the
        // shipment we track ourselves needs a series of ours.
        'SHIPMENT' => ['format' => 'SHP-{FACTORY}-{YYYY}-{SEQ}', 'reset' => 'YEARLY', 'padding' => 4],
        // Physical inventory (Inventory spec §12). 'CNT', not 'SC' — that
        // series is already the subcontract challan's.
        'STOCK_COUNT' => ['format' => 'CNT-{FACTORY}-{YYYY}-{SEQ}', 'reset' => 'YEARLY', 'padding' => 4],
        // A long-term price agreed with a supplier (Procurement §14).
        'RATE_CONTRACT' => ['format' => 'RC-{FACTORY}-{YYYY}-{SEQ}', 'reset' => 'YEARLY', 'padding' => 4],
        // Duty falling due on goods leaving the bond (Shared Systems
        // §3.4). The licence and UD numbers come from customs, so only
        // the assessment is a series of ours.
        'DUTY_ASSESSMENT' => ['format' => 'DA-{FACTORY}-{YYYY}-{SEQ}', 'reset' => 'YEARLY', 'padding' => 4],
        // A request written in the Approval Center (AnnotechERP 04/01).
        'APPROVAL_REQUEST' => ['format' => 'APR-{YYYY}-{SEQ}', 'reset' => 'YEARLY', 'padding' => 5],
        // Machinery records a page is addressed by (no ULIDs in URLs): a
        // plan's or a warranty's name repeats across machines and a meter has
        // none of its own, so each carries a number that never resets.
        'MAINTENANCE_PLAN' => ['format' => 'PM-{SEQ}', 'reset' => 'NEVER', 'padding' => 5],
        'WARRANTY' => ['format' => 'WTY-{SEQ}', 'reset' => 'NEVER', 'padding' => 5],
        'ASSET_METER' => ['format' => 'MTR-{SEQ}', 'reset' => 'NEVER', 'padding' => 5],
    ];

    public function __construct(private readonly TenantContext $context) {}

    /**
     * `$companyId` is for a record numbering itself as it is created (see
     * AssignsDocumentNumber): it knows its company even where no request
     * context is set. Everything else leaves it to the context.
     */
    public function next(string $documentType, ?Factory $factory = null, ?CarbonImmutable $at = null, ?string $companyId = null): string
    {
        $config = self::FORMATS[$documentType]
            ?? throw new \InvalidArgumentException("Unknown document type [{$documentType}].");

        $at ??= CarbonImmutable::now($factory?->timezone ?? 'UTC');
        $companyId ??= $this->context->companyId();
        $periodKey = $this->periodKey($config['reset'], $at);

        // A company's own format, if it has set one. Read here and written on
        // to the period's counter row below, so the format is fixed for the
        // whole period the moment its first number is allocated.
        $config = $this->applyCompanyFormat($config, $companyId, $documentType);

        // The counter is committed in its own transaction so a rollback of the
        // parent operation cannot hand the same number to the next caller.
        $sequence = DB::transaction(function () use ($companyId, $factory, $documentType, $periodKey, $config): NumberSequence {
            $sequence = NumberSequence::withoutGlobalScope(TenantScope::class)
                ->where('company_id', $companyId)
                ->where('factory_id', $factory?->id)
                ->where('document_type', $documentType)
                ->where('period_key', $periodKey)
                ->lockForUpdate()
                ->first();

            if ($sequence === null) {
                $sequence = NumberSequence::withoutGlobalScope(TenantScope::class)->create([
                    'company_id' => $companyId,
                    'factory_id' => $factory?->id,
                    'document_type' => $documentType,
                    'format' => $config['format'],
                    'period_key' => $periodKey,
                    'reset_policy' => $config['reset'],
                    'padding' => $config['padding'],
                    'current_value' => 0,
                ]);

                $sequence = NumberSequence::withoutGlobalScope(TenantScope::class)
                    ->whereKey($sequence->id)
                    ->lockForUpdate()
                    ->first();
            }

            $sequence->current_value++;
            $sequence->save();

            return $sequence;
        });

        // Formatted from the counter row, not from the configuration read a
        // moment ago. The row is the record of what this period agreed to, so
        // a format changed halfway through a month cannot split the month into
        // two shapes of number — the change takes effect at the next period,
        // which is the only point where a run of numbers can safely restart.
        return $this->format(
            (string) $sequence->format,
            (int) $sequence->padding,
            (int) $sequence->current_value,
            $factory,
            $at,
        );
    }

    /**
     * What this company's numbers look like right now, for one document type.
     *
     * The settings screen reads this rather than the constant, so what it
     * shows is what the next document will actually get.
     *
     * @return array{format: string, reset: string, padding: int}
     */
    public function configFor(string $documentType): array
    {
        $config = self::FORMATS[$documentType]
            ?? throw new \InvalidArgumentException("Unknown document type [{$documentType}].");

        return $this->applyCompanyFormat($config, $this->context->companyId(), $documentType);
    }

    /**
     * What a format would produce, without allocating anything.
     *
     * A format is easy to get subtly wrong and impossible to correct
     * afterwards for numbers already issued, so the screen shows the answer
     * before the decision rather than after it.
     */
    public function sample(string $format, int $padding, ?Factory $factory = null, int $value = 1): string
    {
        return $this->format($format, $padding, $value, $factory, CarbonImmutable::now());
    }

    /**
     * The company's override, where it has one.
     *
     * @param  array{format: string, reset: string, padding: int}  $config
     * @return array{format: string, reset: string, padding: int}
     */
    private function applyCompanyFormat(array $config, string $companyId, string $documentType): array
    {
        $override = NumberSequenceFormat::withoutGlobalScope(TenantScope::class)
            ->where('company_id', $companyId)
            ->where('document_type', $documentType)
            ->first();

        if ($override === null) {
            return $config;
        }

        return [
            'format' => $override->format,
            // Never overridden. The reset policy decides what a period *is*,
            // and changing it would leave two counters claiming the same
            // period from different directions.
            'reset' => $config['reset'],
            'padding' => $override->padding,
        ];
    }

    private function periodKey(string $resetPolicy, CarbonImmutable $at): string
    {
        return match ($resetPolicy) {
            'MONTHLY' => $at->format('Y-m'),
            'YEARLY' => $at->format('Y'),
            default => 'ALL',
        };
    }

    private function format(string $format, int $padding, int $value, ?Factory $factory, CarbonImmutable $at): string
    {
        return str_replace(
            ['{FACTORY}', '{YYYY}', '{MM}', '{SEQ}'],
            [
                strtoupper($factory?->code ?? 'GEN'),
                $at->format('Y'),
                $at->format('m'),
                str_pad((string) $value, $padding, '0', STR_PAD_LEFT),
            ],
            $format,
        );
    }
}
