<?php

declare(strict_types=1);

namespace App\Modules\Asset\Http\Controllers\Web;

use App\Modules\Asset\Models\Asset;
use App\Shared\Files\Actions\StoreFileAttachment;
use App\Shared\Files\Models\FileAttachment;
use App\Shared\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

/**
 * A machine's papers (SRS 8).
 *
 * The manual, the wiring diagram, the calibration certificate, the commercial
 * invoice from the import. A technician standing at a dyeing machine at two in
 * the morning needs the manual on the machine's own screen; a copy in
 * somebody's inbox is a copy that is not there.
 *
 * Uploading is a separate permission from viewing the asset, because a document
 * attached to a machine is read by everybody who works on it and changed by
 * very few.
 */
class AssetDocumentController extends Controller
{
    public function store(Request $request, Asset $asset, StoreFileAttachment $files): RedirectResponse
    {
        $this->authorize('view', $asset);
        $this->authorizeDocuments($request);

        $request->validate([
            'file' => ['required', 'file'],
        ]);

        $files->handle($request->file('file'), 'asset', $asset->id, $request->user()->id);

        return back()->with('status', __('asset.document_uploaded'));
    }

    public function destroy(Request $request, Asset $asset, FileAttachment $attachment): RedirectResponse
    {
        $this->authorize('view', $asset);
        $this->authorizeDocuments($request);

        if ($attachment->attachable_type !== 'asset' || $attachment->attachable_id !== $asset->id) {
            abort(404);
        }

        // The row and the file go together: a record pointing at a file that is
        // not there is worse than neither.
        Storage::disk($attachment->disk)->delete($attachment->path);

        $attachment->delete();

        return back()->with('status', __('asset.document_removed'));
    }

    private function authorizeDocuments(Request $request): void
    {
        if (! $request->user()->can('asset.document.manage')) {
            abort(403);
        }
    }
}
