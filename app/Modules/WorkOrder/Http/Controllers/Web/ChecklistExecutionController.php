<?php

declare(strict_types=1);

namespace App\Modules\WorkOrder\Http\Controllers\Web;

use App\Modules\WorkOrder\Actions\RecordChecklistResult;
use App\Modules\WorkOrder\Models\WorkOrder;
use App\Modules\WorkOrder\Models\WorkOrderChecklistResult;
use App\Shared\Files\Actions\StoreFileAttachment;
use App\Shared\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ChecklistExecutionController extends Controller
{
    public function __construct(
        private readonly RecordChecklistResult $record,
        private readonly StoreFileAttachment $files,
    ) {}

    public function store(Request $request, WorkOrder $workOrder): RedirectResponse
    {
        // Whoever does the work answers the checklist, so this rides on the
        // same permission as starting it.
        $this->authorize('work_order.work_order.start');

        $validated = $request->validate([
            'checklist_item_id' => ['required', 'string', 'size:26'],
            'result' => ['required', Rule::in(WorkOrderChecklistResult::RESULTS)],
            'numeric_value' => ['nullable', 'numeric'],
            'text_value' => ['nullable', 'string', 'max:2000'],
            'observation' => ['nullable', 'string', 'max:2000'],
            'photo' => ['nullable', 'file', 'max:10240'],
        ]);

        // Uploaded first so the action can enforce photo-on-failure against a
        // stored file rather than a promise of one.
        $fileId = null;

        if ($request->hasFile('photo')) {
            $fileId = $this->files->handle(
                $request->file('photo'),
                'work_order',
                $workOrder->id,
                $request->user()->id,
            )->id;
        }

        $this->record->handle($workOrder, [
            'checklist_item_id' => $validated['checklist_item_id'],
            'result' => $validated['result'],
            'numeric_value' => $validated['numeric_value'] ?? null,
            'text_value' => $validated['text_value'] ?? null,
            'observation' => $validated['observation'] ?? null,
            'file_id' => $fileId,
        ], $request->user()->id);

        return back()->with('status', __('work_order.checklist_recorded'));
    }
}
