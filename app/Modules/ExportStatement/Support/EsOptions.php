<?php

declare(strict_types=1);

namespace App\Modules\ExportStatement\Support;

/**
 * Export Statement's own short lists (Norban config/export_statement_index.php):
 * Ship Mode, Due Payment and Incoterms — offered as `es:<list>` sources. The
 * companies, buyers and banks come from AnnotechERP's masters (EsSheet).
 */
final class EsOptions
{
    public const LISTS = [
        'ship_mode' => ['Sea', 'Air', 'Sea-Air', 'Road'],
        'due_payment' => ['DUE', 'PAID'],
        'incoterms' => ['FOB', 'FCA', 'CPT', 'CNF (CFR)', 'CIF', 'EXW', 'DAP', 'DDP'],
    ];

    /** Days after the BL / ETD by which the documents must be submitted (⚡ Maximum docs submission). */
    public const DOCS_SUBMISSION_DAYS = 10;

    /** The cash incentive is claimed within this many days of the Realized Date. */
    public const CLAIM_DAYS = 180;

    /** Cash incentive rate (% of the net value), for the dashboard's "Incentive Potential". */
    public const INCENTIVE_RATE = 4;

    /** @return list<string> */
    public static function list(string $name): array
    {
        return self::LISTS[$name] ?? [];
    }
}
