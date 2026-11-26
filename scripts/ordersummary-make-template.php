<?php
// Rebuilds app/Modules/OrderSummary/Data/order_summary_template.xlsx (the Order Summary Excel export's layout) from the
// company's own book "Order Summary.xlsx" at the project root: its title, header, filter and first styled rows, without
// its conditional formats or pictures. Slow (the book is large): docker exec <backend> php -d memory_limit=4096M scripts/ordersummary-make-template.php
ini_set('memory_limit', '3072M');
set_time_limit(0);
require __DIR__.'/../vendor/autoload.php';
function base_path($p) { return __DIR__.'/../'.$p; }
use PhpOffice\PhpSpreadsheet\IOFactory;
$t = microtime(true);
$book = IOFactory::load(base_path('Order Summary.xlsx'));
$ws = $book->getSheetByName('Total Order');
echo "loaded ".round(microtime(true)-$t)."s rows=".$ws->getHighestRow()." cols=".$ws->getHighestColumn()." cf=".count($ws->getConditionalStylesCollection())."\n";
foreach ($book->getSheetNames() as $name) if ($name !== 'Total Order') $book->removeSheetByIndex($book->getIndex($book->getSheetByName($name)));
// The book's 4,901 conditional formats (copy-paste fragments, some with broken ranges) go: the export colours cells by the sheet's own rules.
foreach (array_keys($ws->getConditionalStylesCollection()) as $range) { $ws->removeConditionalStyles($range); }
// Its real width: the last header on row 2 (the sheet runs to XFD from stray styles).
$last = 1; foreach (range(1, 400) as $c) { if (trim((string) $ws->getCell([$c, 2])->getValue()) !== '') $last = $c; }
echo "last header col $last
";
$max = $ws->getHighestRow();
if ($max > 4) $ws->removeRow(5, $max - 4);
for ($c = 1; $c <= $last; $c++) { $cell = $ws->getCell([$c, 4]); if (! $cell->isFormula()) $cell->setValue(null); }
foreach ($ws->getDrawingCollection() as $i => $d) {}
$letter = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($last);
$ws->setAutoFilter('A3:'.$letter.'4');
$w = IOFactory::createWriter($book, 'Xlsx');
$w->setPreCalculateFormulas(false);
$w->save(base_path('app/Modules/OrderSummary/Data/order_summary_template.xlsx'));
echo "saved ".round(microtime(true)-$t)."s cf=".count($ws->getConditionalStylesCollection())." rows=".$ws->getHighestRow()."\n";
// The leftover pictures of the book's other rows go too.
$book = IOFactory::load(base_path('app/Modules/OrderSummary/Data/order_summary_template.xlsx'));
$c = $book->getSheetByName('Total Order')->getDrawingCollection(); foreach (array_keys($c->getArrayCopy()) as $k) { $c->offsetUnset($k); }
$w = IOFactory::createWriter($book, 'Xlsx'); $w->setPreCalculateFormulas(false); $w->save(base_path('app/Modules/OrderSummary/Data/order_summary_template.xlsx'));
echo "pictures removed\n";
// Names pointing at other workbooks ('[1]PICKING SHEET'!#REF!, their links not carried) made Excel report unreadable
// content; the book's saved filter ("Shipment Pending") is not the export's.
$book = IOFactory::load(base_path('app/Modules/OrderSummary/Data/order_summary_template.xlsx'));
$ws = $book->getSheetByName('Total Order');
foreach ($book->getDefinedNames() as $n) { $book->removeDefinedName($n->getName(), $n->getScope()); }
foreach (array_keys($ws->getAutoFilter()->getColumns()) as $col) { $ws->getAutoFilter()->clearColumn($col); }
$w = IOFactory::createWriter($book, 'Xlsx'); $w->setPreCalculateFormulas(false); $w->save(base_path('app/Modules/OrderSummary/Data/order_summary_template.xlsx'));
echo "names and saved filter removed\n";
