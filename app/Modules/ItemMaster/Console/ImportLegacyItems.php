<?php

declare(strict_types=1);

namespace App\Modules\ItemMaster\Console;

use App\Modules\Inventory\Models\UnitOfMeasure;
use App\Modules\ItemMaster\Actions\SaveItem;
use App\Modules\ItemMaster\Models\Item;
use App\Modules\ItemMaster\Models\ItemCategory;
use App\Modules\ItemMaster\Models\ItemDepartment;
use App\Modules\Tenancy\Models\Company;
use App\Shared\Scopes\TenantScope;
use App\Shared\Tenancy\TenantContext;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Throwable;

/**
 * One-off migration of a factory's legacy item library into the item master
 * (Master Data spec §2.8).
 *
 * Reads the CSV exported from the legacy "Item List" sheet (columns:
 * Company, Department, Category, Sub Category, Detail Category, Item Id,
 * Item Code, Item Name, Item Description, Unit, Model, Brand, Origin) and,
 * for one company:
 *
 *  - seeds every item department with its default item type,
 *  - builds the three-level category tree, generating stable codes,
 *  - normalises units onto the UOM master,
 *  - writes each row through `SaveItem`, so the same rules the screen
 *    enforces apply here (ADR-066) — yarn count/fibre are parsed from the
 *    name to satisfy the yarn spec rule, dyed-yarn lots are split on "::".
 *
 * Re-runnable: an item is matched by legacy id first, then part number, and
 * updated rather than duplicated. `--dry-run` reports without writing.
 */
class ImportLegacyItems extends Command
{
    protected $signature = 'itemmaster:import-legacy-items
        {file : CSV path (convert the .xls with scripts/xls-to-csv.py)}
        {--company= : Company code}
        {--dry-run : Report what would happen without writing}';

    protected $description = 'Import a legacy item library CSV into the item master';

    /** Department name → default item type. Anything else is GENERAL. */
    private const DEPARTMENT_ITEM_TYPES = [
        'Yarn Store' => 'YARN',
        'Yarn Dyeing' => 'YARN',
        'Dyes Chemical Store' => 'CHEMICAL',
        'Printing' => 'CHEMICAL',
        'Maintenance Chemical Store' => 'CHEMICAL',
        'General Chemical Store' => 'CHEMICAL',
        'Environment Management and Sustainability' => 'CHEMICAL',
        'Garments Store' => 'TRIMS',
        'Maintenance' => 'SPARE',
    ];

    /** Legacy unit spellings → UOM master codes. */
    private const UNIT_ALIASES = [
        'YARD' => 'YDS', 'YD' => 'YDS', 'ROLL' => 'ROLL', 'REAM' => 'REAM', 'RIM' => 'REAM',
        'PC' => 'PCS', 'PIECE' => 'PCS', 'PIECES' => 'PCS', 'NOS' => 'PCS', 'KGS' => 'KG', 'LITRE' => 'LTR', 'LITER' => 'LTR', 'L' => 'LTR',
        'METER' => 'MTR', 'METRE' => 'MTR', 'M' => 'MTR', 'FT' => 'FEET', 'PKT' => 'PAK', 'PACKET' => 'PAK', 'PACK' => 'PAK',
    ];

    private const UNCATEGORISED = 'Uncategorised';

    /** @var array<string, string> category cache: "parentId|name" → id */
    private array $categories = [];

    /** @var array<string, string> lowercased department name → id */
    private array $departments = [];

    /** @var array<string, string> unit code -> units_of_measure id */
    private array $knownUnits = [];

    /** @var array<string, int> */
    private array $stats = ['rows' => 0, 'created' => 0, 'updated' => 0, 'skipped' => 0, 'yarn_unparsed' => 0, 'code_fallback' => 0];

    /** @var list<string> */
    private array $problems = [];

    public function handle(TenantContext $context, SaveItem $save): int
    {
        $path = (string) $this->argument('file');
        $dryRun = (bool) $this->option('dry-run');

        // Artisan reuses the command instance, so a dry run's rolled-back ids
        // must not leak into the run that follows it.
        $this->categories = [];
        $this->departments = [];
        $this->seenCodes = [];
        $this->problems = [];
        $this->stats = array_fill_keys(array_keys($this->stats), 0);

        if (! is_readable($path)) {
            $this->error("Cannot read {$path}");

            return self::FAILURE;
        }

        $company = Company::withoutGlobalScope(TenantScope::class)->where('code', $this->option('company'))->first();

        if ($company === null) {
            $this->error('Unknown company code; pass --company=');

            return self::FAILURE;
        }

        $context->forget();
        $context->set($company->id);

        $rows = $this->readCsv($path);
        $this->info(sprintf('%d rows in %s (%s)', count($rows), basename($path), $dryRun ? 'dry run' : 'writing'));

        $this->knownUnits = UnitOfMeasure::availableTo($company->id)->where('active', true)->pluck('id', 'code')->all();

        $run = function () use ($rows, $company, $save, $dryRun): void {
            $this->seedDepartments($rows, $company);
            $this->seedCategories($rows, $company);

            $bar = $this->output->createProgressBar(count($rows));

            foreach ($rows as $index => $row) {
                $this->importRow($row, $index + 2, $company, $save);
                $bar->advance();
            }

            $bar->finish();
            $this->newLine();

            if ($dryRun) {
                throw new DryRunRollback;
            }
        };

        try {
            DB::transaction($run);
        } catch (DryRunRollback) {
            $this->warn('Dry run: every change above was rolled back.');
        }

        $this->report();

        return self::SUCCESS;
    }

    /**
     * @return list<array<string, string>>
     */
    private function readCsv(string $path): array
    {
        $handle = fopen($path, 'rb');
        $header = null;
        $rows = [];

        while (($record = fgetcsv($handle)) !== false) {
            if ($header === null) {
                $header = array_map(fn ($h) => trim((string) $h, " \t\n\r\0\x0B\xEF\xBB\xBF"), $record);

                continue;
            }

            if (count(array_filter($record, fn ($v) => trim((string) $v) !== '')) === 0) {
                continue;
            }

            $rows[] = array_combine($header, array_map(fn ($v) => trim((string) $v), array_pad($record, count($header), '')));
        }

        fclose($handle);

        return $rows;
    }

    /**
     * @param  list<array<string, string>>  $rows
     */
    private function seedDepartments(array $rows, Company $company): void
    {
        $names = collect($rows)->pluck('Department')->filter()->unique();

        foreach ($names as $name) {
            $code = $this->code($name, 32);
            $department = ItemDepartment::query()->firstOrCreate(
                ['company_id' => $company->id, 'code' => $code],
                ['name' => $name, 'default_item_type' => self::DEPARTMENT_ITEM_TYPES[$name] ?? 'GENERAL', 'active' => true],
            );

            if ($department->default_item_type === null) {
                $department->forceFill(['default_item_type' => self::DEPARTMENT_ITEM_TYPES[$name] ?? 'GENERAL'])->save();
            }

            $this->departments[mb_strtolower($name)] = $department->id;
        }

        $this->line(sprintf('Departments: %d', count($this->departments)));
    }

    /**
     * Category → sub-category → detail category, company-wide. A name that
     * appears under several departments becomes one row, which is the point
     * of a shared catalogue.
     *
     * @param  list<array<string, string>>  $rows
     */
    private function seedCategories(array $rows, Company $company): void
    {
        $this->category(self::UNCATEGORISED, null, $company);

        foreach ($rows as $row) {
            $level1 = $row['Category'] !== '' ? $row['Category'] : null;
            $level2 = $row['Sub Category'] !== '' ? $row['Sub Category'] : null;
            $level3 = $row['Detail Category'] !== '' ? $row['Detail Category'] : null;

            if ($level1 === null) {
                continue;
            }

            $parent = $this->category($level1, null, $company);

            if ($level2 !== null) {
                $parent = $this->category($level2, $parent, $company);

                if ($level3 !== null) {
                    $this->category($level3, $parent, $company);
                }
            }
        }

        $this->line(sprintf('Categories: %d', count($this->categories)));
    }

    private function category(string $name, ?string $parentId, Company $company): string
    {
        $name = trim(preg_replace('/\s+/', ' ', $name));
        $key = ($parentId ?? '').'|'.mb_strtolower($name);

        if (isset($this->categories[$key])) {
            return $this->categories[$key];
        }

        $existing = ItemCategory::query()
            ->where('parent_id', $parentId)
            ->whereRaw('LOWER(name) = ?', [mb_strtolower($name)])
            ->first();

        if ($existing === null) {
            $existing = ItemCategory::create([
                'company_id' => $company->id,
                'parent_id' => $parentId,
                'name' => $name,
                'code' => $this->uniqueCategoryCode($name, $parentId, $company),
                'active' => true,
            ]);
        }

        return $this->categories[$key] = $existing->id;
    }

    /**
     * Codes are unique per company across every level, so a sub-category
     * carries its parent's code as a prefix; a clash beyond that gets a
     * numeric suffix rather than failing the run.
     */
    private function uniqueCategoryCode(string $name, ?string $parentId, Company $company): string
    {
        $base = $this->code($name, 40);

        if ($parentId !== null) {
            $parentCode = (string) ItemCategory::query()->whereKey($parentId)->value('code');
            $base = substr($parentCode, 0, 20).'-'.$this->code($name, 24);
        }

        $candidate = $base;
        $n = 2;

        while (ItemCategory::query()->where('code', $candidate)->exists()) {
            $candidate = substr($base, 0, 44).'-'.$n++;
        }

        return $candidate;
    }

    /**
     * @param  array<string, string>  $row
     */
    private function importRow(array $row, int $line, Company $company, SaveItem $save): void
    {
        $this->stats['rows']++;

        $name = $row['Item Name'];

        if ($name === '') {
            $this->skip($line, 'blank item name');

            return;
        }

        $departmentId = $this->departments[mb_strtolower($row['Department'])] ?? null;
        $itemType = self::DEPARTMENT_ITEM_TYPES[$row['Department']] ?? 'GENERAL';

        $uomId = $this->unit($row['Unit']);

        if ($uomId === null) {
            $this->skip($line, "unknown unit '{$row['Unit']}'");

            return;
        }

        $notes = [];

        // A dyed-yarn lot: "30/1 Combed Cotton::Black::2430::OB010625/Le::25YD1".
        // The master item is the yarn; the colour, lot and supplier are kept
        // as a note until lots exist as their own records.
        if (str_contains($name, '::')) {
            $parts = array_values(array_filter(array_map('trim', explode('::', $name)), fn ($p) => $p !== ''));
            $name = array_shift($parts) ?? $name;

            if ($parts !== []) {
                $notes[] = 'Legacy lot: '.implode(' / ', $parts);
            }
        }

        if ($row['Item Description'] !== '' && $row['Item Description'] !== $name) {
            $notes[] = $row['Item Description'];
        }

        [$itemCode, $fellBack] = $this->itemCode($row, $company);

        if ($fellBack) {
            $this->stats['code_fallback']++;
        }

        $categoryIds = $this->categoryIds($row, $company);

        $data = [
            'item_code' => $itemCode,
            'legacy_item_id' => $this->legacyId($row),
            'name' => Str::limit($name, 255, ''),
            'item_department_id' => $departmentId,
            'category_id' => $categoryIds[0],
            'sub_category_id' => $categoryIds[1],
            'detail_category_id' => $categoryIds[2],
            'item_type' => $itemType,
            'tracking_mode' => $itemType === 'YARN' ? 'LOT' : 'QUANTITY',
            'uom_id' => $uomId,
            'model' => $row['Model'] !== '' ? Str::limit($row['Model'], 100, '') : null,
            'brand' => $row['Brand'] !== '' ? Str::limit($row['Brand'], 255, '') : null,
            'origin' => $row['Origin'] !== '' ? Str::limit($row['Origin'], 100, '') : null,
            'description' => $notes === [] ? null : Str::limit(implode("\n", $notes), 2000, ''),
        ];

        if ($itemType === 'YARN') {
            $data['yarn'] = $this->yarnSpec($name);

            if ($data['yarn']['count'] === null) {
                $this->stats['yarn_unparsed']++;
                $this->problems[] = "line {$line}: yarn count not found in '{$name}' — filed as GENERAL, needs spec";
                $data['item_type'] = 'GENERAL';
                $data['tracking_mode'] = 'QUANTITY';
                unset($data['yarn']);
            }
        }

        try {
            $existing = $this->existing($data, $company);

            if ($existing !== null) {
                $save->update($existing, $data);
                $this->stats['updated']++;
            } else {
                $save->create($data);
                $this->stats['created']++;
            }
        } catch (ValidationException $e) {
            $this->skip($line, implode('; ', $e->validator->errors()->all()));
        } catch (Throwable $e) {
            $this->skip($line, $e->getMessage());
        }
    }

    /**
     * Legacy id first — it is the one thing that never collided — then the
     * item code, so a re-run after a code fix still finds the same row.
     *
     * @param  array<string, mixed>  $data
     */
    private function existing(array $data, Company $company): ?Item
    {
        if ($data['legacy_item_id'] !== null) {
            $byLegacy = Item::query()->where('legacy_item_id', $data['legacy_item_id'])->first();

            if ($byLegacy !== null) {
                return $byLegacy;
            }
        }

        return Item::query()->where('item_code', $data['item_code'])->first();
    }

    /**
     * @param  array<string, string>  $row
     * @return array{0: string, 1: bool}
     */
    private function itemCode(array $row, Company $company): array
    {
        $code = strtoupper(trim($row['Item Code']));
        $legacyId = $this->legacyId($row);

        if ($code === '' || $code === '0') {
            return [$legacyId ?? 'LEGACY-'.Str::upper(Str::random(8)), true];
        }

        // The code belongs to a different legacy item already imported.
        $taken = Item::query()->where('item_code', $code)
            ->when($legacyId !== null, fn ($q) => $q->where(fn ($w) => $w->whereNull('legacy_item_id')->orWhere('legacy_item_id', '!=', $legacyId)))
            ->exists();

        if ($taken || isset($this->seenCodes[$code])) {
            return [$legacyId !== null ? "{$code}-{$legacyId}" : $code.'-'.Str::upper(Str::random(4)), true];
        }

        $this->seenCodes[$code] = true;

        return [$code, false];
    }

    /** @var array<string, true> */
    private array $seenCodes = [];

    /**
     * @param  array<string, string>  $row
     */
    private function legacyId(array $row): ?string
    {
        $id = trim($row['Item Id']);

        if ($id === '') {
            return null;
        }

        // The sheet exported ids as floats: "337788.0".
        return preg_replace('/\.0+$/', '', $id);
    }

    /**
     * @param  array<string, string>  $row
     * @return array{0: string, 1: ?string, 2: ?string}
     */
    private function categoryIds(array $row, Company $company): array
    {
        $level1 = $row['Category'] !== '' ? $row['Category'] : null;

        if ($level1 === null) {
            // Dyed yarn without a category is still yarn.
            $fallback = $row['Department'] === 'Yarn Dyeing' && isset($this->categories['|yarn']) ? 'Yarn' : self::UNCATEGORISED;

            return [$this->category($fallback, null, $company), null, null];
        }

        $first = $this->category($level1, null, $company);
        $second = $row['Sub Category'] !== '' ? $this->category($row['Sub Category'], $first, $company) : null;
        $third = $second !== null && $row['Detail Category'] !== '' ? $this->category($row['Detail Category'], $second, $company) : null;

        return [$first, $second, $third];
    }

    private function unit(string $raw): ?string
    {
        $code = strtoupper(trim($raw));
        $code = self::UNIT_ALIASES[$code] ?? $code;

        if ($code === '') {
            $code = 'PCS';
        }

        return $this->knownUnits[$code] ?? null;
    }

    /**
     * "22/1 Carded CVC 51/49" → count 22/1, fibre "Carded CVC 51/49";
     * "150D/48 Polyester" → count 150D/48. The whole name stands in as the
     * fibre when nothing follows the count, so the spec rule is met and a
     * person can tidy it later.
     *
     * @return array{count: ?string, fiber: ?string}
     */
    private function yarnSpec(string $name): array
    {
        if (preg_match('/^\s*(\d+(?:[A-Za-z]+)?(?:\/\d+[A-Za-z]*)?)\s*[-:]?\s*(.*)$/u', $name, $m) !== 1) {
            return ['count' => null, 'fiber' => null];
        }

        $fiber = trim($m[2]);

        return [
            'count' => Str::limit($m[1], 32, ''),
            'fiber' => Str::limit($fiber !== '' ? $fiber : $name, 255, ''),
        ];
    }

    private function code(string $name, int $max): string
    {
        $code = strtoupper(Str::slug($name, '_'));
        $code = trim($code, '_.-');

        if ($code === '' || ! preg_match('/^[A-Z0-9]/', $code)) {
            $code = 'X'.$code;
        }

        return substr($code, 0, $max);
    }

    private function skip(int $line, string $why): void
    {
        $this->stats['skipped']++;
        $this->problems[] = "line {$line}: {$why}";
    }

    private function report(): void
    {
        $this->table(array_keys($this->stats), [array_values($this->stats)]);

        if ($this->problems !== []) {
            $this->warn(sprintf('%d rows need attention:', count($this->problems)));

            foreach (array_slice($this->problems, 0, 60) as $problem) {
                $this->line('  '.$problem);
            }

            if (count($this->problems) > 60) {
                $this->line(sprintf('  … and %d more', count($this->problems) - 60));
            }
        }
    }
}
