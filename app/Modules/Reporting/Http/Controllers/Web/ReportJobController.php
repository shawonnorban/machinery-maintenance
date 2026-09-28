<?php

declare(strict_types=1);

namespace App\Modules\Reporting\Http\Controllers\Web;

use App\Modules\Reporting\Models\ReportJob;
use App\Modules\Reporting\Reports\ReportRegistry;
use App\Shared\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Generated reports and their files (SRS 32).
 *
 * A person sees their own requests. A report carries whatever the person who
 * asked for it was allowed to see, so handing the file to a colleague with
 * narrower permissions would be an export that walked around the permission
 * check that produced it (SRS 33).
 */
class ReportJobController extends Controller
{
    public function __construct(private readonly ReportRegistry $registry) {}

    public function index(Request $request): View
    {
        $jobs = ReportJob::query()
            ->where('user_id', $request->user()->id)
            ->with('file')
            ->latest()
            ->paginate(20);

        return view('reporting::jobs.index', [
            'jobs' => $jobs,
            'registry' => $this->registry,
        ]);
    }

    public function download(Request $request, ReportJob $job): StreamedResponse
    {
        if ($job->user_id !== $request->user()->id) {
            throw new NotFoundHttpException;
        }

        if (! $job->isDownloadable()) {
            // Covers failed, still running, and expired alike. All three mean
            // there is no file to hand over, and saying which is which on a
            // download route tells nobody anything they cannot see on the list.
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
}
