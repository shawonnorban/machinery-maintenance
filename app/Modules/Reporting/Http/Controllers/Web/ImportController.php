<?php

declare(strict_types=1);

namespace App\Modules\Reporting\Http\Controllers\Web;

use App\Modules\Reporting\Actions\UploadImport;
use App\Modules\Reporting\Imports\ImporterRegistry;
use App\Modules\Reporting\Jobs\RunImportJob;
use App\Modules\Reporting\Models\ExportJob;
use App\Modules\Reporting\Models\ImportJob;
use App\Modules\Reporting\Services\DataExporter;
use App\Modules\Reporting\Services\ImportProcessor;
use App\Modules\Reporting\Services\ImportTemplate;
use App\Shared\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Upload, validate, preview, confirm (SRS 33).
 *
 * Four screens rather than one button, because the point of the flow is that a
 * person sees what an import would do before it does it. A single "upload and
 * import" control is faster right up until the afternoon somebody loads three
 * thousand machines against the wrong factory.
 */
class ImportController extends Controller
{
    /** Above this the passes run on the queue rather than in the request. */
    private const SYNCHRONOUS_ROW_LIMIT = 500;

    public function __construct(
        private readonly ImporterRegistry $registry,
        private readonly ImportProcessor $processor,
    ) {}

    public function index(Request $request): View
    {
        return view('reporting::imports.index', [
            'importers' => $this->registry->availableTo($request->user()),
            'jobs' => ImportJob::query()
                ->where('user_id', $request->user()->id)
                ->latest()
                ->limit(10)
                ->get(),
            'registry' => $this->registry,
        ]);
    }

    public function show(Request $request, string $type): View
    {
        $importer = $this->resolve($request, $type);

        return view('reporting::imports.show', [
            'importer' => $importer,
            'columns' => $importer->columns(),
            'example' => $importer->exampleRow(),
        ]);
    }

    public function template(Request $request, string $type, ImportTemplate $template): StreamedResponse
    {
        return $template->download($this->resolve($request, $type));
    }

    /**
     * Upload and validate in one step.
     *
     * Validation writes nothing, so there is no reason to make somebody press
     * a second button before finding out whether their file is usable.
     */
    public function store(Request $request, string $type, UploadImport $upload): RedirectResponse
    {
        $importer = $this->resolve($request, $type);

        $request->validate(['file' => ['required', 'file']]);

        $job = $upload->handle($importer, $request->file('file'), $request->user());

        try {
            $this->processor->validate($job);
        } catch (ValidationException $e) {
            // A file-level problem — unreadable, or too many rows. The job row
            // carries it; the flash message is what the person sees now.
            return redirect()
                ->route('app.imports.show', $type)
                ->with('error', implode(' ', $e->validator->errors()->all()));
        }

        return redirect()->route('app.imports.review', $job);
    }

    /**
     * Current data in the importer's own format, so a person can fix it in a
     * spreadsheet and upload it again (SRS 33).
     */
    public function export(Request $request, string $type, DataExporter $exporter): RedirectResponse
    {
        $importer = $this->resolve($request, $type);

        if (! $importer->supportsExport()) {
            return back()->with('error', __('import.export_unavailable'));
        }

        $format = strtoupper((string) $request->input('format', 'CSV'));

        if (! in_array($format, ['CSV', 'XLSX'], true)) {
            return back()->with('error', __('report.unknown_format'));
        }

        $job = $exporter->handle($importer, $format, $request->user());

        return redirect()->route('app.imports.download', $job);
    }

    public function download(Request $request, ExportJob $job): StreamedResponse
    {
        if ($job->requested_by !== $request->user()->id || ! $job->isDownloadable()) {
            throw new NotFoundHttpException;
        }

        $file = $job->file;

        if ($file === null || ! Storage::disk($file->disk)->exists($file->path)) {
            throw new NotFoundHttpException;
        }

        return Storage::disk($file->disk)->download(
            $file->path,
            $file->original_name,
            ['Content-Type' => $file->mime_type],
        );
    }

    public function review(Request $request, ImportJob $job): View
    {
        $this->authorizeJob($request, $job);

        $preview = $this->processor->preview($job);

        return view('reporting::imports.review', [
            'job' => $job,
            'importer' => $this->registry->find($job->type),
            'rows' => $preview['rows'],
            'columns' => $preview['columns'],
            'importErrors' => $job->errors()->orderBy('row_number')->limit(200)->get(),
            'errorCount' => $job->errors()->count(),
        ]);
    }

    /**
     * Write the rows that passed validation.
     */
    public function confirm(Request $request, ImportJob $job): RedirectResponse
    {
        $this->authorizeJob($request, $job);

        if (! $job->isConfirmable()) {
            // Covers a file with nothing valid in it, and a second press of a
            // button that has already run. An import is not idempotent from the
            // user's point of view, so the state guard is the protection.
            return back()->with('error', __('import.not_confirmable'));
        }

        if ($job->valid_rows > self::SYNCHRONOUS_ROW_LIMIT) {
            $job->update(['status' => 'IMPORTING']);

            RunImportJob::dispatch($job->id, $job->company_id, $job->user->locale ?? 'en', 'IMPORT');

            return redirect()->route('app.imports.review', $job)->with('status', __('import.queued'));
        }

        $this->processor->import($job);

        return redirect()->route('app.imports.review', $job)->with('status', __('import.completed'));
    }

    public function cancel(Request $request, ImportJob $job): RedirectResponse
    {
        $this->authorizeJob($request, $job);

        if (! in_array($job->status, ['UPLOADED', 'VALIDATED'], true)) {
            return back()->with('error', __('import.not_cancellable'));
        }

        $job->update(['status' => 'CANCELLED', 'completed_at' => now()]);

        return redirect()->route('app.imports.index')->with('status', __('import.cancelled'));
    }

    /**
     * The error report, as a file a person can work through offline (SRS 33).
     */
    public function errors(Request $request, ImportJob $job): StreamedResponse
    {
        $this->authorizeJob($request, $job);

        $rows = $job->errors()->orderBy('row_number')->get();

        return response()->streamDownload(function () use ($rows): void {
            $handle = fopen('php://output', 'wb');

            fwrite($handle, "\xEF\xBB\xBF");

            fputcsv($handle, [
                __('import.error_columns.row'),
                __('import.error_columns.column'),
                __('import.error_columns.value'),
                __('import.error_columns.error'),
            ]);

            foreach ($rows as $error) {
                fputcsv($handle, [
                    $error->row_number,
                    $error->field,
                    $error->value,
                    $error->error,
                ]);
            }

            fclose($handle);
        }, "import-errors-{$job->id}.csv", ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    private function resolve(Request $request, string $type)
    {
        if (! $this->registry->has($type)) {
            throw new NotFoundHttpException;
        }

        $importer = $this->registry->find($type);

        if (! $importer->allows($request->user())) {
            throw new NotFoundHttpException;
        }

        return $importer;
    }

    private function authorizeJob(Request $request, ImportJob $job): void
    {
        // The uploader's own jobs. An import carries whatever the person who
        // ran it was allowed to write, and the error report repeats their file
        // back to whoever opens it.
        if ($job->user_id !== $request->user()->id) {
            throw new NotFoundHttpException;
        }

        if (! $this->registry->has($job->type) || ! $this->registry->find($job->type)->allows($request->user())) {
            throw new NotFoundHttpException;
        }
    }
}
