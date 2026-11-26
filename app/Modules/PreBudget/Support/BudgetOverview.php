<?php

declare(strict_types=1);

namespace App\Modules\PreBudget\Support;

use App\Modules\PreBudget\Models\PbBudget;
use App\Modules\PreBudget\Models\PbRequisition;
use App\Shared\Files\Models\FileAttachment;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Shared\Date as ExcelDate;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\Drawing;
use PhpOffice\PhpSpreadsheet\Worksheet\PageSetup;

/**
 * A pre-budget's Order Overview (user, 2026-11-22): one line per style —
 * a style's requisitions (its colours, sizes, POs) come together: every
 * colour and every size it has, the quantities and FOB values added up, the
 * FOB price theirs when it is one price (else the value over the quantity),
 * the other fields each distinct value. Under "Order Overview of Buyer -
 * Brand - Season - Factory"; on screen, on paper and as an Excel book.
 */
final class BudgetOverview
{
    public const COLUMNS = ['sl', 'buyer', 'brand', 'dept', 'style', 'job_no', 'item', 'artwork', 'fabrication', 'order_color', 'size_range', 'order_qty', 'fob_price', 'fob_value', 'rfi_sd_date'];

    public const LABELS = [
        'sl' => 'SL', 'buyer' => 'Buyer', 'brand' => 'Brand', 'dept' => 'Dept', 'style' => 'Style', 'job_no' => 'Job No', 'item' => 'Item', 'artwork' => 'Artwork',
        'fabrication' => 'Fabrication', 'order_color' => 'Order Color', 'size_range' => 'Size Range', 'order_qty' => 'Order Qty', 'fob_price' => 'FOB Price',
        'fob_value' => 'FOB Value', 'rfi_sd_date' => 'PO-wise RFI/SD Date',
    ];

    /** @return array<string, mixed> */
    public static function data(PbBudget $b): array
    {
        $sheet = new RequisitionSheet($b->company_id);
        $sizeOrder = array_flip(array_map(fn ($o) => $o['value'], $sheet->options('sizes')));
        // Each order's Order Qty and FOB Value once, however many parts (rows) it has.
        $reqs = OrderLink::countOnce($b->requisitions()->orderBy('serial_no')->get());
        $factories = DB::table('factories')->where('company_id', $b->company_id)->pluck('name', 'code');
        $num = fn ($v) => is_numeric($v) ? (float) $v : null;
        $text = fn ($v) => trim((string) (is_array($v) ? implode(', ', $v) : ($v ?? '')));
        $join = fn (Collection $list, string $sep = ' / ') => $list->map($text)->filter(fn ($v) => $v !== '')->unique()->implode($sep) ?: null;

        $groups = $reqs->groupBy(fn (PbRequisition $r) => ($s = mb_strtolower($text($r->values['style'] ?? ''))) !== '' ? "s:{$s}" : "r:{$r->id}");
        $rows = [];
        foreach ($groups->values() as $i => $g) {
            $vals = $g->map(fn (PbRequisition $r) => $r->values ?? []);
            $colours = [];
            $sizes = [];
            foreach ($vals as $v) {
                foreach (RequisitionSheet::colorsOf($v['order_color'] ?? null) as $c) {
                    $colours[mb_strtolower($c)] ??= $c;
                }
                foreach (is_array($v['order_color'] ?? null) ? $v['order_color'] : [] as $line) {
                    foreach ((array) ($line['sizes'] ?? []) as $s) {
                        $sizes[$s] = $sizeOrder[$s] ?? PHP_INT_MAX;
                    }
                }
            }
            asort($sizes);
            $qty = $vals->sum(fn ($v) => $num($v['order_qty'] ?? null) ?? 0);
            $value = $vals->sum(fn ($v) => $num($v['fob_value'] ?? null) ?? 0);
            $prices = $vals->map(fn ($v) => $num($v['fob_price'] ?? null))->filter(fn ($p) => $p !== null)->unique()->values();
            $artwork = $vals->map(fn ($v) => $v['artwork'] ?? null)->filter()->first();
            $dates = $vals->map(fn ($v) => $v['po_wise_rfi_sd_date'] ?? null)->filter(fn ($d) => is_string($d) && $d !== '')->map(fn ($d) => substr($d, 0, 10))->unique()->sort()->values()->all();

            $rows[] = [
                'sl' => $i + 1, 'requisitions' => $g->count(), 'serial_nos' => $g->pluck('serial_no')->all(),
                'buyer' => $join($vals->pluck('buyer')), 'brand' => $join($vals->pluck('brand')), 'dept' => $join($vals->pluck('dept')),
                'style' => $join($vals->pluck('style')), 'job_no' => $join($vals->pluck('job_no'), ', '), 'item' => $join($vals->pluck('item')),
                'artwork' => $artwork, 'artwork_url' => $artwork ? "/api/files/{$artwork}/download" : null, 'fabrication' => $join($vals->pluck('fabrication')),
                'order_color' => $colours === [] ? null : implode(', ', array_values($colours)),
                'size_range' => $sizes === [] ? null : implode(', ', array_keys($sizes)),
                'order_qty' => round($qty, 2),
                'fob_price' => $prices->count() === 1 ? $prices[0] : ($qty > 0 && $prices->count() > 1 ? round($value / $qty, 4) : null),
                'fob_value' => round($value, 2), 'rfi_sd_dates' => $dates,
            ];
        }
        $distinct = fn (string $key) => $reqs->map(fn ($r) => trim((string) ($r->values[$key] ?? '')))->filter()->unique()->values();
        $parts = [
            'buyer' => $b->buyer_name ?: $distinct('buyer')->implode(' / '),
            'brand' => $distinct('brand')->implode(' / '),
            'season' => $distinct('season')->implode(' / '),
            'factory' => $distinct('factory')->map(fn ($code) => $factories[$code] ?? $code)->implode(' / '),
        ];

        return [
            'budget_no' => $b->label(), 'status' => $b->status, 'contract_no' => $b->contract_no, 'header' => $parts,
            'title' => 'Order Overview of '.implode(' - ', array_map(fn ($p) => $p !== '' ? $p : '—', array_values($parts))),
            'rows' => $rows,
            'totals' => ['order_qty' => round(array_sum(array_column($rows, 'order_qty')), 2), 'fob_value' => round(array_sum(array_column($rows, 'fob_value')), 2)],
        ];
    }

    /** The overview as an Excel book: the title centred over the table, the artwork in its cells, totals at the foot. */
    public static function book(array $data): Spreadsheet
    {
        $book = new Spreadsheet;
        $ws = $book->getActiveSheet();
        $ws->setTitle('Order Overview');
        $last = count(self::COLUMNS);
        $lastL = Coordinate::stringFromColumnIndex($last);
        $widths = ['sl' => 5, 'buyer' => 10, 'brand' => 12, 'dept' => 9, 'style' => 18, 'job_no' => 13, 'item' => 11, 'artwork' => 12, 'fabrication' => 26, 'order_color' => 16, 'size_range' => 16, 'order_qty' => 11, 'fob_price' => 10, 'fob_value' => 13, 'rfi_sd_date' => 14];

        $ws->setCellValue('A1', $data['title']);
        $ws->mergeCells("A1:{$lastL}1");
        $ws->getStyle('A1')->getFont()->setBold(true)->setSize(14);
        $ws->getStyle('A1')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
        $ws->setCellValue('A2', trim($data['budget_no'].($data['contract_no'] ? ' · Contract No: '.$data['contract_no'] : '')));
        $ws->mergeCells("A2:{$lastL}2");
        $ws->getStyle('A2')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
        $ws->getStyle('A2')->getFont()->getColor()->setRGB('64748B');

        $head = 4;
        foreach (self::COLUMNS as $i => $key) {
            $ws->setCellValue([$i + 1, $head], self::LABELS[$key]);
            $ws->getColumnDimension(Coordinate::stringFromColumnIndex($i + 1))->setWidth($widths[$key]);
        }
        $ws->getStyle("A{$head}:{$lastL}{$head}")->applyFromArray([
            'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']], 'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '1E3A5F']],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER, 'wrapText' => true],
        ]);
        $ws->getRowDimension($head)->setRowHeight(30);

        $files = self::artworkFiles($data['rows']);
        $tmp = [];
        $r = $head;
        foreach ($data['rows'] as $row) {
            $r++;
            $ws->getRowDimension($r)->setRowHeight(48);
            foreach (self::COLUMNS as $i => $key) {
                $value = match ($key) {
                    'artwork' => null,
                    'rfi_sd_date' => count($row['rfi_sd_dates']) === 1 ? ExcelDate::PHPToExcel(new \DateTimeImmutable($row['rfi_sd_dates'][0])) : implode(', ', array_map(fn ($d) => date('d-M-Y', strtotime($d)), $row['rfi_sd_dates'])),
                    default => $row[$key] ?? null,
                };
                if ($value !== null && $value !== '') {
                    $ws->setCellValue([$i + 1, $r], $value);
                }
            }
            if ($row['artwork'] && ($file = $files[$row['artwork']] ?? null)) {
                self::picture($ws, $file, array_search('artwork', self::COLUMNS, true) + 1, $r, $tmp);
            }
        }
        $first = $head + 1;
        $col = fn (string $k) => Coordinate::stringFromColumnIndex(array_search($k, self::COLUMNS, true) + 1);
        if ($r >= $first) {
            $ws->getStyle("{$col('order_qty')}{$first}:{$col('order_qty')}{$r}")->getNumberFormat()->setFormatCode('#,##0');
            $ws->getStyle("{$col('fob_price')}{$first}:{$col('fob_price')}{$r}")->getNumberFormat()->setFormatCode('"$"#,##0.00');
            $ws->getStyle("{$col('fob_value')}{$first}:{$col('fob_value')}{$r}")->getNumberFormat()->setFormatCode('"$"#,##0.00');
            $ws->getStyle("{$col('rfi_sd_date')}{$first}:{$col('rfi_sd_date')}{$r}")->getNumberFormat()->setFormatCode('dd-mmm-yyyy');
            $ws->getStyle("A{$first}:{$lastL}{$r}")->getAlignment()->setVertical(Alignment::VERTICAL_CENTER)->setWrapText(true);
            $ws->getStyle("A{$first}:A{$r}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
            $ws->getStyle("{$col('style')}{$first}:{$col('style')}{$r}")->getFont()->setBold(true);
        }
        $t = $r + 1;
        $ws->setCellValue("A{$t}", 'Total');
        $ws->mergeCells("A{$t}:".Coordinate::stringFromColumnIndex(array_search('order_qty', self::COLUMNS, true)).$t);
        $ws->getStyle("A{$t}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
        $ws->setCellValue("{$col('order_qty')}{$t}", $data['totals']['order_qty']);
        $ws->setCellValue("{$col('fob_value')}{$t}", $data['totals']['fob_value']);
        $ws->getStyle("{$col('order_qty')}{$t}")->getNumberFormat()->setFormatCode('#,##0');
        $ws->getStyle("{$col('fob_value')}{$t}")->getNumberFormat()->setFormatCode('"$"#,##0.00');
        $ws->getStyle("A{$t}:{$lastL}{$t}")->applyFromArray(['font' => ['bold' => true], 'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'EEF2FF']]]);
        $ws->getStyle("A{$head}:{$lastL}{$t}")->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN)->getColor()->setRGB('CBD5E1');

        $ws->freezePane('A'.($head + 1));
        $ws->getPageSetup()->setOrientation(PageSetup::ORIENTATION_LANDSCAPE)->setPaperSize(PageSetup::PAPERSIZE_A4)->setFitToWidth(1)->setFitToHeight(0);
        $ws->getPageSetup()->setRowsToRepeatAtTopByStartAndEnd($head, $head);

        return $book;
    }

    /** @return array<string, FileAttachment> */
    private static function artworkFiles(array $rows): array
    {
        $ids = array_values(array_unique(array_filter(array_column($rows, 'artwork'))));

        return $ids === [] ? [] : FileAttachment::query()->whereIn('id', $ids)->where('mime_type', 'like', 'image/%')->get()->keyBy('id')->all();
    }

    /** The artwork fitted into its cell, centred. @param  array<string, array{0: string, 1: int, 2: int}|false>  $cache */
    private static function picture(\PhpOffice\PhpSpreadsheet\Worksheet\Worksheet $ws, FileAttachment $file, int $col, int $row, array &$cache): void
    {
        $img = $cache[$file->id] ??= (function () use ($file) {
            $source = Storage::disk($file->disk ?: 'local')->path($file->path);
            $bytes = is_file($source) ? @file_get_contents($source) : false;
            $size = $bytes !== false ? @getimagesizefromstring($bytes) : false;
            if ($bytes === false || ! $size) {
                return false;
            }
            $path = tempnam(sys_get_temp_dir(), 'pbov').'.'.(pathinfo((string) $file->path, PATHINFO_EXTENSION) ?: 'png');
            file_put_contents($path, $bytes);

            return [$path, (int) $size[0], (int) $size[1]];
        })();
        if ($img === false) {
            return;
        }
        [$path, $iw, $ih] = $img;
        $letter = Coordinate::stringFromColumnIndex($col);
        $boxW = (int) round(($ws->getColumnDimension($letter)->getWidth() ?: 12) * 7 + 5) - 8;
        $boxH = (int) round(($ws->getRowDimension($row)->getRowHeight() ?: 48) * 96 / 72) - 8;
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
}
