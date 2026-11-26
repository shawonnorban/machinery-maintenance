<?php

declare(strict_types=1);

namespace App\Modules\OrderSummary\Support;

use App\Modules\OrderSummary\Models\OsColumn;
use App\Modules\OrderSummary\Models\OsOrder;
use App\Shared\Formula\Formula;
use App\Shared\Files\Models\FileAttachment;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Shared\Date as ExcelDate;
use PhpOffice\PhpSpreadsheet\Worksheet\Drawing;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

/**
 * Order Summary → Excel, laid out exactly like the company's own book (user,
 * 2026-11-20: "obikol amar excel file er moto"): the "Total Order" sheet of
 * `Data/order_summary_template.xlsx` — the user's Order Summary.xlsx cut to
 * its title row, header row, filter row and one styled row — filled in. Each
 * header of the book takes the sheet's column of that name (the importer's
 * own matching, so what was read in comes back out in the same place); every
 * order is a row styled like the book's first, its Excel formulas (Excel SL#,
 * Quantity (PCS), RFI/SD, Days Left …) carried down so the book stays live;
 * the title names the buyers, the update date is today, the SUBTOTAL totals
 * cover every row, and the Artwork column holds each order's picture. Columns
 * the book does not have (added in Column Setup) follow its last column.
 */
final class ExcelExport
{
    public const TEMPLATE = __DIR__.'/../Data/order_summary_template.xlsx';

    private const HEADER_ROW = 2;

    private const FIRST_ROW = 4;

    /** @var array<string, int> style index of each (own style, colour) pair already made */
    private array $toned = [];

    /** @var array<string, array{0: string, 1: int, 2: int}|false> artwork path and size by file id */
    private array $images = [];

    /** @var array{0: int, 1: int}|null the artwork cell's inner size, px */
    private ?array $box = null;

    /** The temp folder the artwork is copied to for this export (removed when written). */
    private ?string $tmpDir = null;

    public function __construct(private readonly Sheet $sheet) {}

    /**
     * @param  Collection<int, OsOrder>  $orders  in the order they are listed
     * @param  list<string>  $buyers  the buyers' names, for the title
     * @param  list<string>|null  $only  the column keys to show (View All's Column View); null = every column
     * @return string the path of the written .xlsx (a temporary file)
     */
    public function build(Collection $orders, array $buyers, ?array $only = null): string
    {
        $book = IOFactory::load(self::TEMPLATE);
        $ws = $book->getSheetByName('Total Order') ?? $book->getActiveSheet();
        // The book's own width: its last header (stray styles run the sheet out to XFD).
        $lastTemplateCol = 1;
        for ($c = 1; $c <= 400; $c++) {
            if (trim((string) $ws->getCell([$c, self::HEADER_ROW])->getValue()) !== '') {
                $lastTemplateCol = $c;
            }
        }

        [$map, $extra] = $this->columns($ws, $lastTemplateCol);
        if ($only !== null) {
            // Only the columns ticked in Column View: the book's others are hidden (kept, so its formulas, merges
            // and lists stay where they are), the row counters (Excel SL#, Total SL#) stay; extra columns only if ticked.
            for ($c = 1; $c <= $lastTemplateCol; $c++) {
                $header = ExcelImport::norm((string) $ws->getCell([$c, self::HEADER_ROW])->getValue());
                $keep = isset($map[$c]) ? in_array($map[$c]->key, $only, true) : in_array($header, ExcelImport::SKIP, true);
                if (! $keep) {
                    $ws->getColumnDimensionByColumn($c)->setVisible(false);
                }
            }
            $extra = array_values(array_filter($extra, fn (OsColumn $x) => in_array($x->key, $only, true)));
        }
        // Columns the book lacks go after its last one, headed and styled like it.
        $col = $lastTemplateCol;
        foreach ($extra as $c) {
            $col++;
            $map[$col] = $c;
            $ws->getCell([$col, self::HEADER_ROW])->setValue($c->label);
            $ws->duplicateStyle($ws->getStyle([$lastTemplateCol, self::HEADER_ROW]), Coordinate::stringFromColumnIndex($col).self::HEADER_ROW);
            $ws->duplicateStyle($ws->getStyle([$lastTemplateCol, self::FIRST_ROW]), Coordinate::stringFromColumnIndex($col).self::FIRST_ROW);
            $ws->getColumnDimensionByColumn($col)->setWidth($ws->getColumnDimensionByColumn($lastTemplateCol)->getWidth() ?: 14);
        }
        $lastCol = $col;
        $n = max($orders->count(), 1);
        $lastRow = self::FIRST_ROW + $n - 1;

        // The first styled row: its formulas (to carry down) and its look (to copy to every row).
        $formulas = [];
        for ($c = 1; $c <= $lastCol; $c++) {
            $cell = $ws->getCell([$c, self::FIRST_ROW]);
            // Carried down: the row counters (Excel SL#, Total SL# — no sheet column) and the day-dependent ones
            // (=W4-TODAY(), so the book stays live). Others hold this row's own constants (=T4*3 is that row's PAC):
            // the sheet's worked-out value goes there instead.
            $f = $cell->isFormula() ? (string) $cell->getValue() : '';
            if ($f !== '' && (! isset($map[$c]) || str_contains(strtoupper($f), 'TODAY()'))) {
                $formulas[$c] = (string) $cell->getValue();
            }
            $cell->setValue(null);
        }
        if ($n > 1) {
            for ($c = 1; $c <= $lastCol; $c++) {
                $letter = Coordinate::stringFromColumnIndex($c);
                $ws->duplicateStyle($ws->getStyle($letter.self::FIRST_ROW), $letter.(self::FIRST_ROW + 1).':'.$letter.$lastRow);
            }
        }
        $height = $ws->getRowDimension(self::FIRST_ROW)->getRowHeight();

        $this->titleRow($ws, $lastCol, $lastRow, $buyers);

        $artCol = array_search('artwork', array_map(fn (OsColumn $c) => $c->key, $map), true);
        $files = $this->artworkFiles($orders);
        $r = self::FIRST_ROW;
        foreach ($orders as $order) {
            $values = $this->sheet->read($order);
            if ($height > 0) {
                $ws->getRowDimension($r)->setRowHeight($height);
            }
            for ($c = 1; $c <= $lastCol; $c++) {
                $column = $map[$c] ?? null;
                if (isset($formulas[$c])) {
                    $ws->getCell([$c, $r])->setValue(self::shift($formulas[$c], $r));
                    // Coloured by the sheet's own worked-out value (Days Left in red when overdue, as on screen).
                    $column && $this->tone($ws, $column, $values[$column->key] ?? null, $c, $r);

                    continue;
                }
                if (! $column || $column->type === 'image') {
                    continue;
                }
                $raw = $values[$column->key] ?? null;
                $v = $this->excelValue($column, $raw);
                if (is_string($v)) {
                    // Text stays text: a remark starting with "=" must not become a (broken) formula.
                    $ws->getCell([$c, $r])->setValueExplicit($v, \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
                } elseif ($v !== null) {
                    $ws->getCell([$c, $r])->setValue($v);
                }
                $this->tone($ws, $column, $raw, $c, $r);
            }
            if ($artCol !== false && ($file = $files[(string) ($values['artwork'] ?? '')] ?? null)) {
                $this->picture($ws, $file, (int) $artCol, $r);
            }
            $r++;
        }

        // Filter buttons on the blank row under the headers, as the book has them; panes frozen at Commercial Contract.
        $ws->setAutoFilter('A3:'.Coordinate::stringFromColumnIndex($lastCol).$lastRow);
        $ws->freezePane($this->freezeCell($ws, $lastTemplateCol));
        // The book's print area covered its own rows; here every exported one, headers repeated on each page.
        $ws->getPageSetup()->setPrintArea('A1:'.Coordinate::stringFromColumnIndex($lastCol).$lastRow);
        // No names pointing elsewhere (the book's links to other workbooks made Excel report unreadable content).
        foreach ($book->getDefinedNames() as $name) {
            if (str_contains($name->getValue(), '#REF!') || str_contains($name->getValue(), '[')) {
                $book->removeDefinedName($name->getName(), $name->getScope());
            }
        }
        $ws->setSelectedCell('A'.self::FIRST_ROW);

        $path = tempnam(sys_get_temp_dir(), 'osx').'.xlsx';
        $writer = IOFactory::createWriter($book, 'Xlsx');
        $writer->setPreCalculateFormulas(false);
        $writer->save($path);
        $book->disconnectWorksheets();
        if ($this->tmpDir) {
            array_map('unlink', glob($this->tmpDir.'/*') ?: []);
            @rmdir($this->tmpDir);
        }

        return $path;
    }

    /**
     * The book's headers matched to the sheet's columns (the importer's rule), and the sheet's columns the book lacks.
     *
     * @return array{0: array<int, OsColumn>, 1: list<OsColumn>}
     */
    private function columns(Worksheet $ws, int $lastCol): array
    {
        $visible = $this->sheet->columns->filter(fn (OsColumn $c) => $this->sheet->access($c) !== 'none')->values();
        $byLabel = [];
        foreach ($visible as $c) {
            $byLabel[ExcelImport::norm($c->label)] ??= $c;
        }
        $map = [];
        $seen = [];
        for ($col = 1; $col <= $lastCol; $col++) {
            $header = trim((string) $ws->getCell([$col, self::HEADER_ROW])->getValue());
            if ($header === '') {
                continue;
            }
            $n = ExcelImport::norm($header);
            $seen[$n] = ($seen[$n] ?? 0) + 1;
            if (in_array($n, ExcelImport::SKIP, true)) {
                continue;
            }
            $key = ExcelImport::ALIASES[$n.'#'.$seen[$n]] ?? ($seen[$n] === 1 ? (ExcelImport::ALIASES[$n] ?? null) : null);
            if ($key === null && str_starts_with($n, 'artwork')) {
                $key = 'artwork';
            }
            $column = $key ? $visible->firstWhere('key', $key) : ($seen[$n] === 1 ? ($byLabel[$n] ?? null) : null);
            if ($column && ! in_array($column->key, array_map(fn ($m) => $m->key, $map), true)) {
                $map[$col] = $column;
            }
        }
        $used = array_map(fn ($m) => $m->key, $map);
        $extra = $visible->filter(fn (OsColumn $c) => ! in_array($c->key, $used, true) && ! in_array($c->key, ['serial_no'], true))->values()->all();

        return [$map, $extra];
    }

    /** The title names the buyers; the update date is today; the totals cover every row. */
    private function titleRow(Worksheet $ws, int $lastCol, int $lastRow, array $buyers): void
    {
        $who = $buyers === [] ? '' : mb_strtoupper(count($buyers) > 3 ? implode(', ', array_slice($buyers, 0, 3)).' …' : implode(', ', $buyers)).' ';
        for ($c = 1; $c <= $lastCol; $c++) {
            $cell = $ws->getCell([$c, 1]);
            $v = $cell->getValue();
            if (! is_string($v) || $v === '') {
                continue;
            }
            if (str_contains($v, 'RUNNING ORDER SUMMARY')) {
                $cell->setValue($who.'BUYER RUNNING ORDER SUMMARY');
            } elseif (str_starts_with(trim($v), 'UPDATE DATE')) {
                $ws->getCell([$c + 1, 1])->setValue(ExcelDate::PHPToExcel(CarbonImmutable::now(Sheet::TIMEZONE)->startOfDay()));
            } elseif (str_starts_with($v, '=')) {
                // =SUBTOTAL(9,T4:T2603) → every exported row.
                $cell->setValue(preg_replace('/([A-Z]{1,3})4:([A-Z]{1,3})\d+/', '${1}4:${2}'.$lastRow, $v));
            }
        }
    }

    /** A formula of the first styled row moved down to row `$r` (relative row 4 references only). */
    private static function shift(string $formula, int $r): string
    {
        return (string) preg_replace('/(?<![A-Z$\d])(\$?[A-Z]{1,3})4(?!\d)/', '${1}'.$r, $formula);
    }

    /**
     * A pairs cell one line each, as the sheet shows it: "Navy: 600 Pcs" (a
     * line with no number keeps its name alone) — and reads back on import.
     *
     * @param  array<int, mixed>  $lines
     */
    public static function pairsText(OsColumn $c, array $lines): string
    {
        $out = [];
        foreach ($lines as $l) {
            $label = trim((string) (is_array($l) ? ($l['label'] ?? '') : $l));
            $n = is_array($l) ? ($l['value'] ?? null) : null;
            $num = $n === null || $n === '' ? '' : ($c->prefix ?? '').number_format((float) $n, (int) ($c->decimals ?? 2), '.', '').($c->suffix ? ' '.$c->suffix : '');
            $out[] = $label !== '' && $num !== '' ? "{$label}: {$num}" : ($label !== '' ? $label : $num);
        }

        return implode("\n", array_filter($out, fn ($s) => $s !== ''));
    }

    /** A value as Excel keeps it: dates as dates, numbers as numbers, lists one per line. */
    private function excelValue(OsColumn $c, mixed $v): mixed
    {
        if (Formula::blank($v)) {
            return null;
        }
        if ($c->type === 'pairs' && is_array($v)) {
            return self::pairsText($c, $v);
        }
        if (is_array($v)) {
            return implode("\n", array_map(fn ($x) => is_array($x) ? Formula::text($x) : (string) $x, $v));
        }
        if (in_array($c->type, ['date', 'system'], true) && is_string($v) && preg_match('/^\d{4}-\d{2}-\d{2}/', $v)) {
            return ExcelDate::PHPToExcel(CarbonImmutable::parse(substr($v, 0, 10), Sheet::TIMEZONE)->startOfDay());
        }
        if (in_array($c->type, ['number', 'money', 'percent', 'days'], true) && is_numeric($v)) {
            return $c->type === 'percent' ? (float) $v / 100 : (float) $v;
        }

        return is_scalar($v) ? (string) $v : null;
    }

    /**
     * The sheet's colour rules (Column Setup — the book's own conditional formats) and fixed text colours, put on
     * the cell as it looks on screen: Excel's Green / Yellow / Light Red fills and the rest (common.js TONE).
     */
    private function tone(Worksheet $ws, OsColumn $c, mixed $value, int $col, int $row): void
    {
        $tone = null;
        foreach ((array) ($c->color_rules ?? []) as $rule) {
            if (is_array($rule) && self::matches($rule, $value)) {
                $tone = $rule['tone'] ?? null;
                break;
            }
        }
        $text = $c->text_tone ? (self::TEXT_TONE[$c->text_tone] ?? null) : null;
        // A Column Setup "Bold" column (user, 2026-11-21) is bold in the book too.
        if (! $tone && ! $text && ! $c->bold) {
            return;
        }
        // One style per (the cell's own style, colour) pair, made once and handed out by index: styling cells one by
        // one makes PhpSpreadsheet compare every new style with all the others (200 rows took 22 s).
        $cell = $ws->getCell([$col, $row]);
        $base = $cell->getXfIndex();
        $key = $base.'|'.($tone ?? '').'|'.($c->text_tone ?? '').'|'.($c->bold ? 'b' : '');
        if (! isset($this->toned[$key])) {
            $book = $ws->getParent();
            $style = clone $book->getCellXfByIndex($base);
            if ($tone && isset(self::TONE[$tone])) {
                [$fill, $font, $bold] = self::TONE[$tone];
                $style->getFill()->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)->getStartColor()->setRGB($fill);
                $style->getFont()->getColor()->setRGB($font);
                $bold && $style->getFont()->setBold(true);
            } elseif ($text) {
                [$font, $bold] = $text;
                $style->getFont()->getColor()->setRGB($font);
                $bold && $style->getFont()->setBold(true);
            }
            $c->bold && $style->getFont()->setBold(true);
            $book->addCellXf($style);
            $this->toned[$key] = $style->getIndex();
        }
        $cell->setXfIndex($this->toned[$key]);
    }

    /** Fill, text colour, bold — common.js TONE. */
    private const TONE = [
        'green' => ['C6EFCE', '006100', false], 'yellow' => ['FFEB9C', '9C5700', false], 'lightred' => ['FFC7CE', '9C0006', false],
        'red' => ['FF0000', 'FFFFFF', true], 'brightgreen' => ['00B050', 'FFFFFF', true], 'highlight' => ['FFFF00', 'FF0000', true],
        'peach' => ['F6C6AD', '111827', false], 'blue' => ['DCEAF7', '002060', true], 'orange' => ['F97316', 'FFFFFF', false],
        'pink' => ['FCE7F3', '9D174D', false], 'purple' => ['A02B93', 'FFFFFF', true], 'gray' => ['E5E7EB', '374151', false], 'cyan' => ['00B0F0', '111827', false],
    ];

    /** Text colour, bold — common.js TEXT_TONE. */
    private const TEXT_TONE = ['orange' => ['C04F15', false], 'green' => ['275417', false], 'navy' => ['002060', true], 'red' => ['FF0000', false]];

    /** common.js matches(): does a value meet a colour rule? */
    private static function matches(array $rule, mixed $value): bool
    {
        $op = $rule['op'] ?? '';
        $blank = Formula::blank($value);
        if ($op === 'any') {
            return ! $blank;
        }
        if ($op === 'blank') {
            return $blank;
        }
        if ($blank) {
            return false;
        }
        $s = mb_strtolower(Formula::text($value));
        $want = mb_strtolower((string) ($rule['value'] ?? ''));
        $n = ! is_array($value) && is_numeric($value) ? (float) $value : null;
        $w = is_numeric($rule['value'] ?? null) ? (float) $rule['value'] : null;
        $w2 = is_numeric($rule['value2'] ?? null) ? (float) $rule['value2'] : null;

        return match ($op) {
            'eq' => $n !== null && $w !== null ? $n === $w : $s === $want,
            'neq' => $n !== null && $w !== null ? $n !== $w : $s !== $want,
            'contains' => str_contains($s, $want),
            'lt' => $n !== null && $w !== null && $n < $w,
            'lte' => $n !== null && $w !== null && $n <= $w,
            'gt' => $n !== null && $w !== null && $n > $w,
            'gte' => $n !== null && $w !== null && $n >= $w,
            'between' => $n !== null && $w !== null && $w2 !== null && $n >= $w && $n <= $w2,
            default => false,
        };
    }

    /** @return array<string, FileAttachment> the orders' artwork files that can be read, by id */
    private function artworkFiles(Collection $orders): array
    {
        $ids = $orders->map(fn (OsOrder $o) => (string) ($o->values['artwork'] ?? ''))->filter()->unique()->values()->all();

        return $ids === [] ? [] : FileAttachment::query()->whereIn('id', $ids)->where('mime_type', 'like', 'image/%')->get()->keyBy('id')->all();
    }

    /** The artwork, fitted into its cell with a little margin (the book's "low pixel & fix cell"). */
    private function picture(Worksheet $ws, FileAttachment $file, int $col, int $row): void
    {
        // Each file read once (path and size), however many orders show it.
        $img = $this->images[$file->id] ??= (function () use ($file) {
            // Copied once to the temp folder: every drawing reads its file again, which is slow on a mounted disk.
            $source = Storage::disk($file->disk ?: 'local')->path($file->path);
            $bytes = is_file($source) ? @file_get_contents($source) : false;
            if ($bytes === false) {
                return false;
            }
            $this->tmpDir ??= (function () {
                $dir = sys_get_temp_dir().'/osx-art-'.bin2hex(random_bytes(6));
                @mkdir($dir, 0700, true);

                return $dir;
            })();
            $path = $this->tmpDir.'/'.$file->id.'.'.(pathinfo((string) $file->path, PATHINFO_EXTENSION) ?: 'png');
            file_put_contents($path, $bytes);
            $size = @getimagesizefromstring($bytes);

            return $size ? [$path, (int) $size[0], (int) $size[1]] : false;
        })();
        if ($img === false) {
            return;
        }
        [$path, $iw, $ih] = $img;
        $letter = Coordinate::stringFromColumnIndex($col);
        [$boxW, $boxH] = $this->box ??= [
            (int) round(($ws->getColumnDimension($letter)->getWidth() ?: 12) * 7 + 5) - 8,
            (int) round(($ws->getRowDimension($row)->getRowHeight() ?: 60) * 96 / 72) - 8,
        ];
        $scale = min($boxW / max($iw, 1), $boxH / max($ih, 1), 1);
        $w = (int) max(1, floor($iw * $scale));
        $h = (int) max(1, floor($ih * $scale));

        $d = new Drawing;
        $d->setPath($path, false);
        $d->setName('Artwork');
        $d->setCoordinates($letter.$row);
        $d->setResizeProportional(false);
        $d->setWidth($w);
        $d->setHeight($h);
        $d->setOffsetX((int) max(0, ($boxW + 8 - $w) / 2));
        $d->setOffsetY((int) max(0, ($boxH + 8 - $h) / 2));
        $d->setWorksheet($ws);
    }

    /** Panes frozen at the book's Commercial Contract Number column (else after Job No), under the filter row. */
    private function freezeCell(Worksheet $ws, int $lastCol): string
    {
        for ($c = 1; $c <= $lastCol; $c++) {
            if (str_starts_with(ExcelImport::norm((string) $ws->getCell([$c, self::HEADER_ROW])->getValue()), 'commercialcontract')) {
                return Coordinate::stringFromColumnIndex($c).self::FIRST_ROW;
            }
        }

        return 'A'.self::FIRST_ROW;
    }
}
