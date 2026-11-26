<?php

declare(strict_types=1);

namespace App\Modules\OrderSummary\Support;

use App\Modules\Merchandise\Models\Buyer;
use App\Modules\OrderSummary\Models\OsChange;
use App\Modules\OrderSummary\Models\OsColumn;
use App\Modules\OrderSummary\Models\OsOrder;
use App\Shared\Files\Actions\StoreFileAttachment;
use App\Shared\Formula\Formula;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\RichText\RichText;
use PhpOffice\PhpSpreadsheet\Shared\Date as ExcelDate;
use ZipArchive;

/**
 * Order Summary (AnnotechERP 32): the company's own Excel order summary
 * (the "Total Order" sheet: headers on row 2, rows from row 4) read into the
 * sheet. Columns are matched by header to the column names in Column Setup
 * (plus the Excel spellings below). Only typed columns are read — formula
 * columns work themselves out. Values a list does not hold yet are added to
 * that list when it is a lookup list or Supplier Master (ListFiller); a value
 * of another list (buyers, colours…) is kept as written. Text in a date or number column cannot be
 * kept and is counted in the report. Each row's first picture in the Artwork
 * column becomes its artwork.
 */
final class ExcelImport
{
    /** Excel header (letters and digits only, lower case; "#2" = its second use) → column key. */
    public const ALIASES = [
        'dept' => 'department', 'class' => 'class', 'buyersfeedbackondelay' => 'buyer_feedback_on_delay',
        'emballishmentsif' => 'embellishments', 'zipperif' => 'zipper', 'buttonif' => 'button', 'woventrimif' => 'woven_trim',
        'importitemif' => 'import_item', 'aopif' => 'aop', 'grossconsumpbodyribotherkgdoz' => 'gross_consump_kg_doz',
        'woveninhousactualdate' => 'woven_inhouse_actual_date', 'basehangerapprovdate' => 'base_hanger_approv_date',
        '1stsamplecomntsdate' => 'first_sample_comnts_date', 'printembrsoffcometsdate' => 'print_embr_soff_comets_date',
        'blackssfitapprovdate' => 'black_ss_fit_approv_date', 'prodfileh0date' => 'prod_file_handover_date',
        'shipoutvalueus' => 'shipout_value_usd', 'shipoutcommercialcontractnumber' => 'shipout_commercial_contract_number',
        'marketingmerchandisernorban' => 'marketing_merchandiser', 'productionmerchandisernorban' => 'production_merchandiser',
        'discountpenalty#2' => 'final_discount_penalty_percent', 'daysleftinhand#2' => 'reprint_days_left',
        'shipoutquantitypcs' => 'shipout_quantity_pcs',
    ];

    /** Excel columns with no place in the sheet (row counters). */
    public const SKIP = ['excelsl', 'totalsl'];

    private const HEADER_ROW = 2;

    private const FIRST_ROW = 4;

    private ListFiller $filler;

    /** @var array<string, array{n: int, examples: list<string>}> */
    private array $dropped = [];

    public function __construct(private readonly Sheet $sheet, private readonly ?string $userId)
    {
        $this->filler = new ListFiller($sheet, $userId);
    }

    /**
     * @return array<string, mixed> what was done
     */
    public function run(string $path, bool $replace, ?string $picturesFrom = null): array
    {
        $book = IOFactory::createReader('Xlsx')->setReadDataOnly(true)->setLoadSheetsOnly(['Total Order'])->load($path);
        $ws = $book->getSheetByName('Total Order') ?? $book->getSheet(0);
        $maxCol = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::columnIndexFromString($ws->getHighestDataColumn());
        $maxRow = $ws->getHighestDataRow();

        [$map, $unmatched] = $this->mapHeaders($ws, $maxCol);
        $artworkCol = $this->artworkColumn($ws, $maxCol);
        // The pictures may come from another copy of the book (an .xlsb read through a converted .xlsx).
        $pictures = $this->pictures($picturesFrom ?? $path, $artworkCol);

        $rows = [];
        for ($r = self::FIRST_ROW; $r <= $maxRow; $r++) {
            $values = [];
            $pcs = null;
            foreach ($map as $col => $column) {
                $raw = $this->cell($ws, $col, $r);
                if ($column->key === 'quantity_pcs') {
                    $pcs = Formula::num($raw);

                    continue;
                }
                $v = $this->value($column, $raw);
                if (! Formula::blank($v)) {
                    $values[$column->key] = $v;
                }
            }
            if (Formula::blank($values['style_number_name'] ?? null) && Formula::blank($values['job_no'] ?? null) && Formula::blank($values['po_number'] ?? null)) {
                continue;
            }
            // The sheet works pieces out as tickets × PAC; Excel holds the pieces.
            $ticket = Formula::num($values['quantity_ticket'] ?? null);
            if ($pcs !== null && $ticket) {
                $values['pac'] = round($pcs / $ticket, 4);
            }
            $rows[] = ['row' => $r, 'values' => $values];
        }

        $summary = DB::transaction(function () use ($rows, $replace, $pictures): array {
            $deleted = 0;
            if ($replace) {
                OsChange::query()->where('company_id', $this->sheet->companyId)->delete();
                $deleted = OsOrder::withTrashed()->where('company_id', $this->sheet->companyId)->count();
                OsOrder::withTrashed()->where('company_id', $this->sheet->companyId)->forceDelete();
            }
            $serial = (int) OsOrder::withTrashed()->where('company_id', $this->sheet->companyId)->max('serial_no');
            $batch = (string) Str::ulid();
            $files = [];
            $artworks = 0;
            foreach ($rows as $row) {
                $values = $row['values'];
                if (isset($pictures[$row['row']])) {
                    $files[$pictures[$row['row']]['target']] ??= $this->store($pictures[$row['row']]);
                    if ($files[$pictures[$row['row']]['target']]) {
                        $values['artwork'] = $files[$pictures[$row['row']]['target']];
                        $artworks++;
                    }
                }
                $values = array_filter($this->sheet->formulas->apply($values, Sheet::today()), fn ($v) => ! Formula::blank($v));
                $o = OsOrder::query()->create([
                    'serial_no' => ++$serial, 'buyer_id' => $this->buyerId($values['buyer'] ?? null), 'values' => $values, 'version' => 1,
                    'created_by' => $this->userId, 'updated_by' => $this->userId,
                ]);
                $this->sheet->log($o, 'CREATE', [], $batch, $this->userId);
            }

            return ['deleted' => $deleted, 'imported' => count($rows), 'artworks' => $artworks, 'files' => count(array_filter($files))];
        });

        return $summary + ['columns' => count($map), 'unmatched' => $unmatched, 'list_values_added' => $this->filler->added, 'not_kept' => $this->dropped];
    }

    /**
     * @return array{0: array<int, OsColumn>, 1: list<string>} sheet column index → column, and the headers left out
     */
    private function mapHeaders($ws, int $maxCol): array
    {
        $byLabel = [];
        foreach ($this->sheet->columns as $c) {
            $byLabel[self::norm($c->label)] = $c;
        }
        $seen = [];
        $map = [];
        $unmatched = [];
        for ($col = 1; $col <= $maxCol; $col++) {
            $header = trim((string) $this->text($ws->getCell([$col, self::HEADER_ROW])->getValue()));
            if ($header === '') {
                continue;
            }
            $n = self::norm($header);
            $seen[$n] = ($seen[$n] ?? 0) + 1;
            if (in_array($n, self::SKIP, true)) {
                continue;
            }
            $key = self::ALIASES[$n.'#'.$seen[$n]] ?? ($seen[$n] === 1 ? (self::ALIASES[$n] ?? null) : null);
            $column = $key ? $this->sheet->column($key) : ($seen[$n] === 1 ? ($byLabel[$n] ?? null) : null);
            if (! $column || in_array($column->key, array_map(fn ($c) => $c->key, $map), true)) {
                $unmatched[] = $header;

                continue;
            }
            // Worked out by the sheet, or a picture: not read as a value (Quantity (PCS) gives PAC).
            if (($column->formula && $column->key !== 'quantity_pcs') || in_array($column->type, ['system', 'image'], true)) {
                continue;
            }
            $map[$col] = $column;
        }

        return [$map, $unmatched];
    }

    private function artworkColumn($ws, int $maxCol): ?int
    {
        for ($col = 1; $col <= $maxCol; $col++) {
            if (str_starts_with(self::norm((string) $this->text($ws->getCell([$col, self::HEADER_ROW])->getValue())), 'artwork')) {
                return $col;
            }
        }

        return null;
    }

    /** A cell's value; a formula's last worked-out value. */
    private function cell($ws, int $col, int $row): mixed
    {
        $cell = $ws->getCell([$col, $row]);
        $v = $cell->isFormula() ? $cell->getOldCalculatedValue() : $cell->getValue();

        return $this->text($v);
    }

    private function text(mixed $v): mixed
    {
        return $v instanceof RichText ? $v->getPlainText() : $v;
    }

    /** One Excel value as the column keeps it, or null (counted when it could not be kept). */
    private function value(OsColumn $c, mixed $raw): mixed
    {
        if (is_string($raw)) {
            $raw = trim(str_replace("\r", '', $raw));
        }
        if (Formula::blank($raw) || (is_string($raw) && str_starts_with($raw, '#'))) {
            return null;
        }
        switch ($c->type) {
            case 'number':
            case 'money':
            case 'percent':
            case 'days':
                $n = is_numeric($raw) ? (float) $raw : Formula::num($raw);
                if ($n === null) {
                    return $this->drop($c, $raw);
                }

                // Excel keeps a percentage as a fraction (5 % = 0.05).
                return $c->type === 'percent' && is_numeric($raw) ? round($n * 100, 6) : $n;
            case 'date':
                if (is_numeric($raw)) {
                    if ((float) $raw < 3000) { // before 1908: a formula over an empty cell
                        return null;
                    }

                    return ExcelDate::excelToDateTimeObject((float) $raw)->format('Y-m-d');
                }
                $s = (string) $raw;

                return Formula::day($s) !== null ? substr($s, 0, 10) : $this->drop($c, $raw);
            case 'multi':
                $out = [];
                foreach (preg_split('/\s*\n\s*/', self::str($raw)) as $part) {
                    if ($part !== '') {
                        $out[] = $c->source ? $this->listValue($c, $part) : mb_substr($part, 0, 120);
                    }
                }

                return array_values(array_unique($out)) ?: null;
            case 'pairs':
                // "Navy: 600 Pcs" one per line (as Export Excel writes it), or an older file's colours alone.
                try {
                    return Sheet::pairs($c, preg_replace('/\s*\n\s*/', ';', self::str($raw)), fn () => throw new \RuntimeException('number'));
                } catch (\RuntimeException) {
                    return $this->drop($c, $raw);
                }
            case 'dropdown':
                return $this->listValue($c, self::str($raw));
            case 'longtext':
                return mb_substr(self::str($raw), 0, 5000);
            default:
                return mb_substr(self::str($raw), 0, 500);
        }
    }

    /** A list value as the list spells it; lookup lists and Supplier Master get a value they lack, other lists keep the text. */
    private function listValue(OsColumn $c, string $v): string
    {
        return $this->filler->keep($c, $v);
    }

    private function drop(OsColumn $c, mixed $raw): null
    {
        $d = &$this->dropped[$c->label];
        $d['n'] = ($d['n'] ?? 0) + 1;
        $s = mb_substr(str_replace("\n", ' / ', self::str($raw)), 0, 40);
        if (count($d['examples'] ?? []) < 3 && ! in_array($s, $d['examples'] ?? [], true)) {
            $d['examples'][] = $s;
        }

        return null;
    }

    /** A buyer by name or short name; "C&A ERVE" falls back to the buyer it starts with. */
    private function buyerId(?string $name): ?string
    {
        if (Formula::blank($name)) {
            return null;
        }
        $exact = Buyer::query()->where(fn ($q) => $q->where('short_name', $name)->orWhere('name', $name))->orderBy('created_at')->value('id');
        if ($exact) {
            return $exact;
        }
        foreach (Buyer::query()->get(['id', 'name', 'short_name']) as $b) {
            foreach ([$b->short_name, $b->name] as $n) {
                if ($n && str_starts_with(mb_strtolower($name), mb_strtolower($n).' ')) {
                    return $b->id;
                }
            }
        }

        return null;
    }

    /**
     * The first picture anchored in the Artwork column of each row.
     *
     * @return array<int, array{target: string, bytes: string}> sheet row → picture
     */
    private function pictures(string $path, ?int $artworkCol): array
    {
        if ($artworkCol === null) {
            return [];
        }
        $zip = new ZipArchive;
        if ($zip->open($path) !== true) {
            return [];
        }
        $out = [];
        $drawings = $this->sheetDrawings($zip);
        for ($i = 0; $i < $zip->numFiles; $i++) {
            $name = $zip->getNameIndex($i);
            if (! preg_match('#^xl/drawings/(drawing\d+)\.xml$#', $name, $m) || ($drawings !== null && ! in_array($name, $drawings, true))) {
                continue;
            }
            $rels = [];
            $relXml = $zip->getFromName("xl/drawings/_rels/{$m[1]}.xml.rels") ?: '';
            preg_match_all('/Id="([^"]+)"[^>]*Target="([^"]+)"/', $relXml, $rm, PREG_SET_ORDER);
            foreach ($rm as $x) {
                $rels[$x[1]] = 'xl/'.ltrim(str_replace('../', '', $x[2]), '/');
            }
            preg_match_all('#<xdr:(?:twoCellAnchor|oneCellAnchor)\b.*?</xdr:(?:twoCellAnchor|oneCellAnchor)>#s', $zip->getFromName($name) ?: '', $anchors);
            foreach ($anchors[0] as $a) {
                if (! preg_match('#<xdr:from><xdr:col>(\d+)</xdr:col>.*?<xdr:row>(\d+)</xdr:row>#s', $a, $pos) || ! preg_match('/r:embed="([^"]+)"/', $a, $emb)) {
                    continue;
                }
                $row = (int) $pos[2] + 1;
                $target = $rels[$emb[1]] ?? null;
                if ((int) $pos[1] + 1 !== $artworkCol || ! $target || isset($out[$row]) || ! preg_match('/\.(png|jpe?g|gif|webp)$/i', $target)) {
                    continue;
                }
                $out[$row] = ['target' => $target, 'bytes' => (string) $zip->getFromName($target)];
            }
        }
        $zip->close();

        return $out;
    }

    /**
     * The drawing parts of the "Total Order" sheet only (other sheets carry their own pictures).
     * An .xlsx names its sheets in workbook.xml; an .xlsb keeps that binary, so there it is the first sheet.
     *
     * @return list<string>|null null when the sheet cannot be told apart (then every drawing is read)
     */
    private function sheetDrawings(ZipArchive $zip): ?array
    {
        $part = null;
        $book = $zip->getFromName('xl/workbook.xml');
        if ($book !== false) {
            if (preg_match('#<sheet[^>]*name="Total Order"[^>]*r:id="([^"]+)"#', $book, $m)
                && preg_match('#Id="'.preg_quote($m[1], '#').'"[^>]*Target="([^"]+)"|Target="([^"]+)"[^>]*Id="'.preg_quote($m[1], '#').'"#', (string) $zip->getFromName('xl/_rels/workbook.xml.rels'), $t)) {
                $part = basename($t[1] ?: $t[2]);
            }
        } elseif ($zip->locateName('xl/worksheets/sheet1.bin') !== false) {
            $part = 'sheet1.bin';
        }
        if ($part === null) {
            return null;
        }
        preg_match_all('#Target="\.\./drawings/(drawing\d+\.xml)"#', (string) $zip->getFromName("xl/worksheets/_rels/{$part}.rels"), $d);

        return array_map(fn ($x) => "xl/drawings/{$x}", $d[1]);
    }

    /** A picture stored as the company's file; its id, or null when it cannot be. */
    private function store(array $picture): ?string
    {
        $tmp = tempnam(sys_get_temp_dir(), 'osart');
        file_put_contents($tmp, $picture['bytes']);
        try {
            $file = new UploadedFile($tmp, basename($picture['target']), null, null, true);

            return app(StoreFileAttachment::class)->handle($file, 'os_artwork', $this->sheet->companyId, $this->userId)->id;
        } catch (\Throwable) {
            return null;
        } finally {
            @unlink($tmp);
        }
    }

    public static function norm(string $s): string
    {
        return preg_replace('/[^a-z0-9]/', '', mb_strtolower($s)) ?? '';
    }

    private static function str(mixed $v): string
    {
        if (is_float($v) && floor($v) === $v && abs($v) < 1e15) {
            return (string) (int) $v;
        }

        return trim((string) $v);
    }
}
