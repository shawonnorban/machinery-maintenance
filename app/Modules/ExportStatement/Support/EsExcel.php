<?php

declare(strict_types=1);

namespace App\Modules\ExportStatement\Support;

use App\Modules\ExportStatement\Models\EsColumn;
use App\Shared\Formula\Formula;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Shared\Date;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;

/**
 * Export Statement ⇄ Excel, as Norban's "EXPORT STATEMENT FOR MD SIR" book:
 * row 1 the column headings (bright green, dark-blue bold Calibri, 49.5 pt),
 * one 30 pt row per invoice, every cell centred, wrapped and boxed, each
 * column at the book's width, amounts in $ and dates d-mmm-yy. Import reads
 * the first sheet's row 1 as headings — matched to the columns by name
 * (spaces and line breaks ignored, so Norban's own file reads as it is).
 */
final class EsExcel
{
    /** Norban's book: column widths (characters). */
    private const WIDTHS = [
        'serial_no' => 6.14, 'company_name' => 22, 'job_no' => 10, 'buyer_name' => 12, 'ex_factory_date' => 13.14, 'invoice_no' => 15, 'invoice_date' => 11.57,
        'exp_no' => 16.29, 'exp_date' => 13, 'po_no' => 11.29, 'style_no' => 13, 'export_qty' => 12.71, 'invoice_value_gross' => 13.86, 'commission' => 20.29,
        'invoice_value_net' => 13.57, 'freight_charge' => 11.86, 'total_ctns' => 10.43, 'gross_weight' => 11.14, 'net_weight' => 9.71, 'total_cbm' => 10.14,
        'forwarder' => 16.43, 'bl_no' => 14.43, 'bl_date' => 15.29, 'eta' => 10.29, 'shipping_bill_no' => 16.43, 'shipping_bill_date' => 16.43,
        'max_docs_submission_date' => 16.43, 'docs_submission_date' => 20.43, 'docs_submission_delay_days' => 12.43, 'bank_ref_no' => 20.86, 'bank_ref_date' => 11.57,
        'courier' => 11.57, 'payment_acceptance_date' => 15.71, 'tt_received_date' => 15, 'realized_value' => 15, 'realized_date' => 13.71, 'shortage_over_payment' => 13.43,
        'due_payment' => 11.29, 'bl_surrender_date' => 13.29, 'shippers_bank_name' => 16, 'sc_lc_no' => 18.43, 'sc_lc_date' => 16.43, 'ud_no' => 11.43, 'ud_date' => 12.29,
        'terms_of_payment' => 11.57, 'incoterms' => 11.57, 'port_of_loading' => 11.57, 'ship_mode' => 10.71, 'port_of_discharge' => 10.71, 'country_of_destination' => 12.29,
        'rex_no' => 15.43, 'rex_submission_date' => 13.43, 'exp_duplicate_status' => 12.71, 'prc_status' => 14.29, 'cash_incentive_claim_amount' => 15,
        'cash_incentive_claim_date' => 13.71, 'audit_firm' => 20, 'claim_days_remaining' => 12, 'remarks' => 24, 'status' => 14,
    ];

    private const MONEY = '[$$-3C09]#,##0.00;[Red]-[$$-3C09]#,##0.00';

    private const DATE = '[$-409]d\-mmm\-yy;@';

    /**
     * The statements as one sheet: the given columns (in order), one row each.
     *
     * @param  Collection<int, EsColumn>  $columns
     * @param  list<array<string, mixed>>  $rows  each statement as EsSheet::read() gives it
     */
    public static function book(Collection $columns, array $rows): Spreadsheet
    {
        $book = new Spreadsheet;
        $ws = $book->getActiveSheet();
        $ws->setTitle('Export Statement');
        $last = Coordinate::stringFromColumnIndex(max(1, $columns->count()));
        foreach ($columns->values() as $i => $c) {
            $col = Coordinate::stringFromColumnIndex($i + 1);
            $ws->setCellValue("{$col}1", $c->label);
            $ws->getColumnDimension($col)->setWidth(self::WIDTHS[$c->key] ?? max(10, $c->width / 7));
            $format = match (true) {
                $c->type === 'money' => self::MONEY,
                $c->type === 'date' => self::DATE,
                $c->type === 'days' => '0 "Days";[Red]-0 "Days"',
                $c->type === 'number' => $c->decimals ? '#,##0.'.str_repeat('0', (int) $c->decimals) : '#,##0',
                default => null,
            };
            if ($format && $rows !== []) {
                $ws->getStyle("{$col}2:{$col}".(count($rows) + 1))->getNumberFormat()->setFormatCode($format);
            }
        }
        foreach ($rows as $r => $values) {
            foreach ($columns->values() as $i => $c) {
                $v = $values[$c->key] ?? null;
                if (Formula::blank($v)) {
                    continue;
                }
                $cell = Coordinate::stringFromColumnIndex($i + 1).($r + 2);
                if ($c->type === 'date' && Formula::day($v) !== null) {
                    $ws->setCellValue($cell, Date::PHPToExcel(Carbon::parse(substr((string) $v, 0, 10))));
                } elseif (in_array($c->type, ['number', 'money', 'percent', 'days'], true) && is_numeric($v)) {
                    $ws->setCellValue($cell, (float) $v);
                } else {
                    // Text as text: an invoice or job number never turns into a number or a date.
                    $ws->setCellValueExplicit($cell, is_array($v) ? implode(', ', $v) : (string) $v, DataType::TYPE_STRING);
                }
            }
        }
        $ws->getRowDimension(1)->setRowHeight(49.5);
        for ($r = 2; $r <= count($rows) + 1; $r++) {
            $ws->getRowDimension($r)->setRowHeight(30);
        }
        $ws->getStyle("A1:{$last}1")->applyFromArray([
            'font' => ['bold' => true, 'name' => 'Calibri', 'size' => 11, 'color' => ['rgb' => '003366']],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '00FF00']],
        ]);
        $ws->getStyle("A1:{$last}".(count($rows) + 1))->applyFromArray([
            'font' => ['name' => 'Calibri'],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER, 'wrapText' => true],
            'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => '000000']]],
        ]);
        if ($rows !== []) {
            $ws->getStyle("A2:{$last}".(count($rows) + 1))->getFont()->setBold(true);
        }
        $ws->freezePane('A2');

        return $book;
    }

    /**
     * A book's rows as cell values by column key. Row 1 holds the headings;
     * a heading no column has is skipped (listed in `unknown`).
     *
     * @param  Collection<int, EsColumn>  $columns
     * @return array{rows: list<array{line: int, values: array<string, mixed>}>, unknown: list<string>}
     */
    public static function read(string $path, Collection $columns): array
    {
        $ws = IOFactory::load($path)->getSheet(0);
        $byName = [];
        foreach ($columns as $c) {
            $byName[self::norm($c->label)] = $c;
            $byName[self::norm($c->key)] = $c;
        }
        foreach (EsCatalog::columns() as $c) {
            // The catalog's names (Norban's headings) still read after a rename in Column Setup.
            $byName[self::norm($c['label'])] ??= $columns->firstWhere('key', $c['key']);
        }
        $byName[self::norm('SL No.')] ??= $columns->firstWhere('key', 'serial_no');
        $highest = Coordinate::columnIndexFromString($ws->getHighestDataColumn());
        $map = [];
        $unknown = [];
        for ($i = 1; $i <= $highest; $i++) {
            $head = trim((string) $ws->getCell([$i, 1])->getValue());
            if ($head === '') {
                continue;
            }
            $c = $byName[self::norm($head)] ?? null;
            $c ? $map[$i] = $c : $unknown[] = $head;
        }
        $rows = [];
        $lastRow = $ws->getHighestDataRow();
        for ($r = 2; $r <= $lastRow; $r++) {
            $values = [];
            foreach ($map as $i => $c) {
                $raw = $ws->getCell([$i, $r])->getValue();
                if (is_string($raw)) {
                    $raw = trim($raw);
                }
                if ($raw === null || $raw === '') {
                    continue;
                }
                $values[$c->key] = $c->type === 'date' ? self::date($raw) : (is_float($raw) && floor($raw) === $raw && ! in_array($c->type, ['number', 'money', 'percent', 'days'], true) ? (string) (int) $raw : $raw);
            }
            if ($values !== []) {
                $rows[] = ['line' => $r, 'values' => $values];
            }
        }

        return ['rows' => $rows, 'unknown' => $unknown];
    }

    /** An Excel date (a serial number or text such as 05-05-2026, 5-May-26) as Y-m-d; other text as it is. */
    private static function date(mixed $raw): mixed
    {
        if (is_numeric($raw)) {
            return Carbon::instance(Date::excelToDateTimeObject((float) $raw))->toDateString();
        }
        foreach (['Y-m-d', 'd-m-Y', 'd/m/Y', 'd.m.Y', 'j-M-y', 'j-M-Y', 'd-M-y', 'd-M-Y', 'j M Y', 'M j, Y'] as $f) {
            $d = \DateTime::createFromFormat('!'.$f, (string) $raw);
            if ($d && $d->format($f) === (string) $raw) {
                return $d->format('Y-m-d');
            }
        }

        return $raw;
    }

    private static function norm(string $s): string
    {
        return mb_strtolower(preg_replace('/\s+/u', ' ', trim($s)));
    }
}
