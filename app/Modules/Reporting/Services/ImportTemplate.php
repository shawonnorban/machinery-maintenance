<?php

declare(strict_types=1);

namespace App\Modules\Reporting\Services;

use App\Modules\Reporting\Imports\Importer;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * The blank file a person starts from (SRS 33).
 *
 * A template with one filled example row, because a header line alone leaves
 * every question unanswered: is the date 15/03/2024 or 2024-03-15, is the type
 * the name or the code, is "yes" a boolean. One example answers all three
 * without anybody reading documentation.
 *
 * Headers are the English column names the importer matches on. They stay in
 * English in both languages on purpose — a file that stops importing because
 * somebody downloaded the template while the interface was in Bengali would be
 * a trap.
 */
class ImportTemplate
{
    private const BOM = "\xEF\xBB\xBF";

    public function download(Importer $importer): StreamedResponse
    {
        $columns = $importer->columns();
        $name = $importer->type().'-template.csv';

        return response()->streamDownload(function () use ($columns, $importer): void {
            $handle = fopen('php://output', 'wb');

            fwrite($handle, self::BOM);

            fputcsv($handle, array_keys($columns));
            fputcsv($handle, array_values($importer->exampleRow()));

            fclose($handle);
        }, $name, [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }
}
