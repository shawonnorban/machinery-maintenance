<?php

declare(strict_types=1);

namespace App\Modules\AiImport\Support;

use App\Shared\Tenancy\TenantContext;
use Illuminate\Support\Facades\DB;

/**
 * AI File & PO Import (AnnotechERP 26): the lists its screens choose from,
 * and the company's buyers by name.
 */
final class ImportBook
{
    /** What a document is: the families the engine recognises. */
    public const FAMILIES = ['PURCHASE_ORDER', 'TECH_PACK', 'INQUIRY', 'BOM', 'COST_SHEET', 'COMMERCIAL', 'ARTWORK', 'PRODUCTION_REPORT'];

    /** The families fields are pulled out of (extraction, validation, approval). */
    public const DATA_FAMILIES = ['PURCHASE_ORDER', 'TECH_PACK', 'INQUIRY', 'BOM', 'COST_SHEET', 'COMMERCIAL'];

    /** An intake file is one of the first seven families, or not recognised at all. */
    public const INTAKE_FAMILIES = ['PURCHASE_ORDER', 'TECH_PACK', 'INQUIRY', 'BOM', 'COST_SHEET', 'COMMERCIAL', 'ARTWORK', 'UNRECOGNISED'];

    /** A validation or approval step covers one data family, or every family. */
    public const SCOPES = ['PURCHASE_ORDER', 'TECH_PACK', 'INQUIRY', 'BOM', 'COST_SHEET', 'COMMERCIAL', 'EVERY'];

    public const ARRIVALS = ['EMAIL', 'PORTAL', 'WATCHED_FOLDER', 'SFTP', 'MANUAL'];

    public const STAGES = ['QUEUED', 'RECOGNISING', 'EXTRACTING', 'VALIDATING', 'AWAITING_APPROVAL', 'POSTED', 'FAILED'];

    /** The stages a file reaches only once it is recognised. */
    public const READ_STAGES = ['EXTRACTING', 'VALIDATING', 'AWAITING_APPROVAL', 'POSTED'];

    public const MARKERS = ['HEADER_TEXT', 'LOGO_REGION', 'SHEET_NAME', 'COLUMN_RUN', 'FILE_NAMING', 'SENDER_ADDRESS'];

    public const FOUND_BY = ['LABELLED', 'TABLE_COLUMN', 'FIXED_REGION', 'REGEX', 'MODEL'];

    public const DATA_TYPES = ['TEXT', 'NUMBER', 'DATE', 'CURRENCY', 'CODE'];

    public const KINDS = ['MASTER_LOOKUP', 'RANGE', 'FORMAT', 'CROSS_FIELD', 'DUPLICATE'];

    public const ON_FAILURE = ['BLOCK', 'WARN', 'REVIEW'];

    public const APPROVERS = ['MERCHANDISER', 'PLANNING', 'COMMERCIAL', 'COSTING', 'QUALITY', 'DIVISION_HEAD'];

    /** @var array<string, mixed> */
    private static array $cache = [];

    /** @return array<string, string> */
    public static function buyers(): array
    {
        return self::$cache['buyers:'.self::company()] ??= DB::table('buyers')->where('company_id', self::company())->orderBy('display_order')->orderBy('name')
            ->get(['id', 'name', 'short_name'])->mapWithKeys(fn ($b) => [$b->id => $b->short_name ?: $b->name])->all();
    }

    /** A file's size in MB, one place; a file with anything in it never reads as 0.0 MB. */
    public static function sizeMb(int $bytes): float
    {
        return $bytes > 0 ? max(0.1, round($bytes / 1048576, 1)) : 0.0;
    }

    public static function forget(): void
    {
        self::$cache = [];
    }

    private static function company(): string
    {
        return app(TenantContext::class)->companyId();
    }
}
