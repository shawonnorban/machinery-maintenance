<?php

declare(strict_types=1);

namespace App\Shared\Pdf;

use App\Shared\Support\TenantTimezone;
use Mpdf\Config\FontVariables;
use Mpdf\HTMLParserMode;
use Mpdf\Mpdf;
use Mpdf\Output\Destination;

/**
 * A report as a PDF: a title, the company and when it was made, the filters
 * it was run with, a table of rows and, where asked for, a total row — on
 * landscape A4 (A3 past sixteen columns), the header row repeated on every page, who printed it and
 * "Page n of N" at the foot. It wears the same face as the browser prints
 * (lib/print-document.js): an indigo rule under the heading, the filters in a
 * box, a light header row and a ruled grid, dates written 24-Oct-2026.
 *
 * Through mPDF because it shapes complex scripts: a Bangla material name or
 * column label comes out with its conjuncts joined (FreeSerif, picked by
 * script), where the simpler HTML-to-PDF libraries break them apart. The
 * newer Bangla fonts (Noto Sans Bengali) use positioning mPDF cannot read.
 *
 * Rows are written in batches rather than as one document, so a long report
 * does not build its whole HTML in memory first.
 */
final class TablePdf
{
    private const BATCH = 400;

    /** Beyond this the PDF stops, saying so; a spreadsheet is the format for more. */
    public const MAX_ROWS = 20000;

    /**
     * @param  list<array{label: string, numeric?: bool, nowrap?: bool}>  $columns  `nowrap` keeps a column's text on one line (a reason of several words beside many narrow figures)
     * @param  iterable<int, list<string|int|float|null>>  $rows  each row's values, in column order
     * @param  array<string, string>  $meta  filter label => value
     * @param  list<string|int|float|null>|null  $total  a last row in bold, in column order
     */
    public function __construct(
        private readonly string $title,
        private readonly array $columns,
        private readonly iterable $rows,
        private readonly array $meta = [],
        private readonly ?array $total = null,
        private readonly string $subtitle = '',
    ) {}

    /** Write the PDF to a path; returns how many rows went in. */
    public function save(string $path): int
    {
        $pdf = $this->engine();
        $count = $this->render($pdf);
        $pdf->Output($path, Destination::FILE);

        return $count;
    }

    /** The PDF as a string, for a download. */
    public function output(): string
    {
        $pdf = $this->engine();
        $this->render($pdf);

        return $pdf->Output('', Destination::STRING_RETURN);
    }

    private function engine(): Mpdf
    {
        // One folder per process user: a run from the console (root) and the web (www-data) must not
        // leave each other a font cache neither can write to.
        $tmp = storage_path('app/mpdf-'.(function_exists('posix_geteuid') ? posix_geteuid() : 'web'));
        if (! is_dir($tmp)) {
            @mkdir($tmp, 0775, true);
        }
        $pdf = new Mpdf([
            // A very wide report (a profit sheet's two dozen columns) takes an A3 sheet rather than shrinking past reading.
            'mode' => 'utf-8', 'format' => count($this->columns) > 16 ? 'A3-L' : 'A4-L', 'tempDir' => $tmp,
            'margin_left' => 10, 'margin_right' => 10, 'margin_top' => 12, 'margin_bottom' => 14, 'margin_footer' => 6,
            'default_font' => 'dejavusanscondensed', 'default_font_size' => 8,
            // Bangla (and any other script) is found by script and given a font that carries it.
            'autoScriptToLang' => true, 'autoLangToFont' => true, 'useSubstitutions' => true,
            // FreeSerif, which mPDF gives Bangla, carries Bengali only in its regular face; a bold heading or
            // total row in Bangla is set in that face too, so it never comes out as empty boxes.
            'fontdata' => array_replace((new FontVariables)->getDefaults()['fontdata'], [
                'freeserif' => ['R' => 'FreeSerif.ttf', 'B' => 'FreeSerif.ttf', 'I' => 'FreeSerif.ttf', 'BI' => 'FreeSerif.ttf', 'useOTL' => 0xFF, 'useKashida' => 75],
            ]),
        ]);
        $pdf->SetTitle($this->title);
        // When it was made, in the company's own time, not the server's.
        $zone = rescue(fn (): string => app(TenantTimezone::class)->current(), config('app.display_timezone', 'Asia/Dhaka'), false);
        $when = now()->setTimezone($zone)->format('d M Y, h:i A');
        // The API signs in on the request's own guard; a queued export has nobody, and says only when.
        $who = (request()->user() ?? auth()->user())?->name;
        $printed = $who ? __('report.pdf_printed_by', ['user' => $who, 'when' => $when]) : __('report.pdf_printed', ['when' => $when]);
        $pdf->SetHTMLFooter('<table class="foot"><tr><td>'.$this->e($printed).'</td><td class="n">'.$this->e(__('report.pdf_page')).' {PAGENO} / {nbpg}</td></tr></table>');

        return $pdf;
    }

    private function render(Mpdf $pdf): int
    {
        $pdf->WriteHTML($this->css(), HTMLParserMode::HEADER_CSS);
        $head = '<div class="head"><h1>'.$this->e($this->title).'</h1>'
            .($this->subtitle !== '' ? '<p class="sub">'.$this->e($this->subtitle).'</p>' : '').'</div>';
        if ($this->meta !== []) {
            // The filters in a box, four to a row: a small label over each value.
            $cells = array_map(fn ($k, $v) => '<td><span class="k">'.$this->e((string) $k).'</span><br><b>'.$this->e($this->cell($v, false)).'</b></td>', array_keys($this->meta), $this->meta);
            $head .= '<table class="facts">'.implode('', array_map(fn (array $row) => '<tr>'.implode('', $row).str_repeat('<td></td>', 4 - count($row)).'</tr>', array_chunk($cells, 4))).'</table>';
        }
        $pdf->WriteHTML($head, HTMLParserMode::HTML_BODY);

        // The first batch is read before the header is written: its widest values set each column's share.
        $rows = (fn () => yield from $this->rows)();
        $first = [];
        while ($rows->valid() && count($first) < self::BATCH) {
            $first[] = $rows->current();
            $rows->next();
        }
        $pdf->WriteHTML('<table repeat_header="1"><thead><tr>'.$this->header($this->total !== null ? [...$first, $this->total] : $first).'</tr></thead><tbody>', HTMLParserMode::HTML_BODY, true, false);

        // One table, opened once, its header repeated on each page; rows go in a batch at a time.
        $count = 0;
        $batch = '';
        $add = function (array $row) use (&$count, &$batch, $pdf): void {
            $batch .= $this->tr($row, $count % 2 === 1 ? 'odd' : '');
            $count++;
            if ($count % self::BATCH === 0) {
                $pdf->WriteHTML($batch, HTMLParserMode::HTML_BODY, false, false);
                $batch = '';
            }
        };
        foreach ($first as $row) {
            $add($row);
        }
        while ($rows->valid() && $count < self::MAX_ROWS) {
            $add($rows->current());
            $rows->next();
        }
        if ($count === 0) {
            $batch .= '<tr><td colspan="'.count($this->columns).'" class="empty">—</td></tr>';
        }
        if ($this->total !== null && $count > 0) {
            $batch .= $this->tr($this->total, 'total');
        }
        $pdf->WriteHTML($batch.'</tbody></table>', HTMLParserMode::HTML_BODY, false, true);
        if ($count >= self::MAX_ROWS) {
            $pdf->WriteHTML('<p class="meta">'.$this->e(__('report.pdf_truncated', ['n' => number_format(self::MAX_ROWS)])).'</p>', HTMLParserMode::HTML_BODY);
        }

        return $count;
    }

    /**
     * The header row, each column a fixed share of the width by the longer of
     * its label and its widest value in the sample — or mPDF hands all the
     * spare width to one column and wraps the rest.
     *
     * @param  list<list<string|int|float|null>>  $sample
     */
    private function header(array $sample): string
    {
        $weights = [];
        foreach (array_values($this->columns) as $i => $c) {
            $numeric = $c['numeric'] ?? false;
            $word = max(array_map(fn ($w) => mb_strlen($w), preg_split('/\s+/', $c['label']) ?: ['']));
            $values = array_map(fn (array $r) => $this->cell($r[$i] ?? null, $numeric), $sample);
            $value = max([0, ...array_map(fn ($v) => mb_strlen($v), $values)]);
            // The longest unbroken run (a document no, a code) is never split, so the column is at least that wide.
            $token = max([0, ...array_map(fn ($v) => max(array_map(fn ($w) => mb_strlen($w), preg_split('/\s+/', $v) ?: [''])), $values)]);
            // A bold header word is wider than a value of the same length; a column is never narrower than its longest label word.
            $weights[$i] = max($numeric ? 7 : 8, (int) ceil($word * 1.2) + 4, min($numeric ? 16 : 34, $value + ($numeric ? 5 : 4)), min(42, (int) ceil($token * 1.15) + 4));
        }
        $sum = array_sum($weights) ?: 1;
        $th = '';
        foreach (array_values($this->columns) as $i => $c) {
            // A very wide table (an A3 sheet) is left to size its own columns around what may not wrap.
            $width = count($this->columns) > 16 ? '' : ' width="'.round($weights[$i] / $sum * 100, 2).'%"';
            $th .= '<th'.$width.(($c['numeric'] ?? false) ? ' class="n"' : '').$this->nowrap($c['label']).'>'.$this->e($c['label']).'</th>';
        }

        return $th;
    }

    /** @param list<string|int|float|null> $row */
    private function tr(array $row, string $class): string
    {
        $out = '<tr'.($class !== '' ? ' class="'.$class.'"' : '').'>';
        foreach (array_values($this->columns) as $i => $c) {
            $numeric = $c['numeric'] ?? false;
            $value = $row[$i] ?? null;
            // A total row leaves the cells it does not add up empty, rather than dashed.
            $text = $class === 'total' && $value === null ? '' : $this->cell($value, $numeric);
            $keep = ($c['nowrap'] ?? false) && $text !== '' ? ' nowrap="nowrap"' : $this->nowrap($text);
            $out .= '<td'.($numeric ? ' class="n"' : '').$keep.'>'.$this->e($text).'</td>';
        }

        return $out.'</tr>';
    }

    private function cell(mixed $value, bool $numeric): string
    {
        if ($value === null || $value === '') {
            return $numeric ? '' : '—';
        }
        if ($numeric && is_numeric($value)) {
            return rtrim(rtrim(number_format((float) $value, 3, '.', ','), '0'), '.');
        }
        // A bare date is written as the browser prints write it: 2026-10-24 → 24-Oct-2026.
        if (is_string($value) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $value)) {
            return date('d-M-Y', (int) strtotime($value));
        }

        return is_bool($value) ? ($value ? '✓' : '') : (string) $value;
    }

    /**
     * A single unbroken run — a document no, a code, a figure, a one-word
     * heading — is kept on one line; mPDF narrows the whole table to fit
     * rather than splitting it mid-word (LINE0 / 1).
     */
    private function nowrap(string $text): string
    {
        return $text !== '' && mb_strlen($text) <= 24 && ! preg_match('/\s/u', $text) ? ' nowrap="nowrap"' : '';
    }

    private function e(string $s): string
    {
        return htmlspecialchars($s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }

    private function css(): string
    {
        return '.head { border-bottom: 1.5pt solid #4f46e5; padding-bottom: 4pt; margin-bottom: 6pt; }
            h1 { font-size: 13pt; margin: 0; color: #312e81; text-transform: uppercase; letter-spacing: 0.4pt; }
            .sub { font-size: 8pt; color: #4b5563; margin: 2pt 0 0 0; }
            .meta { font-size: 8pt; color: #333; margin: 6pt 0 0 0; }
            table.facts { width: 100%; border-collapse: collapse; margin: 0 0 6pt 0; }
            table.facts td { width: 25%; border: 0.4pt solid #d1d5e0; padding: 3pt 5pt; font-size: 8pt; vertical-align: top; }
            .k { font-size: 6.5pt; color: #6b7280; text-transform: uppercase; }
            table { width: 100%; border-collapse: collapse; }
            th { background-color: #eef0fb; color: #1e1b4b; font-weight: bold; text-align: left; padding: 4pt 5pt; font-size: 7.5pt; border: 0.4pt solid #c7cbe0; vertical-align: bottom; }
            td { padding: 3.5pt 5pt; border: 0.4pt solid #d9dce6; font-size: 8pt; }
            tr.odd td { background-color: #fafafa; }
            tr.total td { font-weight: bold; background-color: #f3f4f6; border-top: 0.9pt solid #4b5563; }
            .n { text-align: right; }
            .empty { text-align: center; color: #777; padding: 10pt; }
            table.foot td { border: 0; padding: 0; font-size: 7pt; color: #6b7280; }';
    }
}
