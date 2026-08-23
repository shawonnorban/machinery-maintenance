<?php

declare(strict_types=1);

namespace App\Modules\Maintenance\Http\Controllers\Web;

use App\Modules\Asset\Models\AssetType;
use App\Modules\Maintenance\Actions\AuthorTemplate;
use App\Modules\Maintenance\Models\ChecklistItem;
use App\Modules\Maintenance\Models\MaintenanceTemplate;
use App\Modules\Maintenance\Models\MaintenanceTemplateVersion;
use App\Modules\Maintenance\Models\MaintenanceType;
use App\Shared\Http\Controllers\Controller;
use App\Shared\Tenancy\TenantContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * Checklists, and writing them (SRS 12).
 *
 * Until now a factory could only read the seeded templates, so a dye house ran
 * its soft flow machines against a checklist written for a sewing floor, or
 * against nothing at all.
 *
 * A published version is frozen. Changes go into a new draft and take effect
 * when that draft is published, because editing a published checklist would
 * silently rewrite what a technician signed to say they had checked.
 */
class TemplateController extends Controller
{
    public function __construct(private readonly TenantContext $context) {}

    public function index(): View
    {
        $this->authorize('maintenance.template.view_any');

        return view('maintenance::templates.index', [
            'templates' => MaintenanceTemplate::availableTo($this->context->companyId())
                ->with(['assetType:id,name', 'maintenanceType:id,name'])
                ->withCount('versions')
                ->orderBy('name')
                ->paginate(25),
        ]);
    }

    public function show(string $template): View
    {
        $this->authorize('maintenance.template.view_any');

        $model = MaintenanceTemplate::availableTo($this->context->companyId())
            ->with(['assetType:id,name', 'maintenanceType:id,name', 'versions'])
            ->findOrFail($template);

        // Default to the published version: that is what plans bind to and
        // what a technician will actually be handed.
        $version = $model->currentVersion() ?? $model->versions->first();

        return view('maintenance::templates.show', [
            'template' => $model,
            'version' => $version,
            'items' => $version?->items()->get() ?? collect(),
            'inputTypes' => ChecklistItem::INPUT_TYPES,
            'isOwn' => $model->company_id === $this->context->companyId(),
        ]);
    }

    public function version(string $template, string $version): View
    {
        $this->authorize('maintenance.template.view_any');

        $model = MaintenanceTemplate::availableTo($this->context->companyId())
            ->with(['assetType:id,name', 'maintenanceType:id,name', 'versions'])
            ->findOrFail($template);

        $selected = MaintenanceTemplateVersion::where('template_id', $model->id)
            ->findOrFail($version);

        return view('maintenance::templates.show', [
            'template' => $model,
            'version' => $selected,
            'items' => $selected->items()->get(),
            'inputTypes' => ChecklistItem::INPUT_TYPES,
            'isOwn' => $model->company_id === $this->context->companyId(),
        ]);
    }

    public function create(): View
    {
        $this->authorize('maintenance.template.create');

        return view('maintenance::templates.form', $this->formOptions() + ['template' => null]);
    }

    public function store(Request $request, AuthorTemplate $action): RedirectResponse
    {
        $this->authorize('maintenance.template.create');

        $template = $action->createTemplate($this->validatedTemplate($request, null), $request->user()->id);

        return redirect()
            ->route('app.maintenance.templates.show', $template->id)
            ->with('status', __('maintenance.template_created'));
    }

    public function edit(string $template): View
    {
        $this->authorize('maintenance.template.update');

        return view('maintenance::templates.form', $this->formOptions() + [
            'template' => $this->ownTemplate($template),
        ]);
    }

    public function update(Request $request, string $template, AuthorTemplate $action): RedirectResponse
    {
        $this->authorize('maintenance.template.update');

        $model = $this->ownTemplate($template);

        $action->updateTemplate($model, $this->validatedTemplate($request, $model));

        return redirect()
            ->route('app.maintenance.templates.show', $model->id)
            ->with('status', __('maintenance.template_saved'));
    }

    /**
     * Start a revision. The published version stays exactly as it is until the
     * new one is published in its place.
     */
    public function draft(Request $request, string $template, AuthorTemplate $action): RedirectResponse
    {
        $this->authorize('maintenance.template.update');

        $model = $this->ownTemplate($template);

        $draft = $action->newDraft($model);

        return redirect()
            ->route('app.maintenance.templates.version', [$model->id, $draft->id])
            ->with('status', __('maintenance.draft_started'));
    }

    public function storeItem(Request $request, string $template, string $version, AuthorTemplate $action): RedirectResponse
    {
        $this->authorize('maintenance.template.update');

        $model = $this->ownTemplate($template);

        $action->saveItem($this->versionOf($model, $version), $this->validatedItem($request));

        return back()->with('status', __('maintenance.item_saved'));
    }

    public function updateItem(
        Request $request,
        string $template,
        string $version,
        string $item,
        AuthorTemplate $action,
    ): RedirectResponse {
        $this->authorize('maintenance.template.update');

        $model = $this->ownTemplate($template);
        $draft = $this->versionOf($model, $version);

        $action->saveItem($draft, $this->validatedItem($request), $this->itemOf($draft, $item));

        return back()->with('status', __('maintenance.item_saved'));
    }

    public function destroyItem(
        Request $request,
        string $template,
        string $version,
        string $item,
        AuthorTemplate $action,
    ): RedirectResponse {
        $this->authorize('maintenance.template.update');

        $model = $this->ownTemplate($template);
        $draft = $this->versionOf($model, $version);

        $action->removeItem($draft, $this->itemOf($draft, $item));

        return back()->with('status', __('maintenance.item_removed'));
    }

    public function publish(Request $request, string $template, string $version, AuthorTemplate $action): RedirectResponse
    {
        $this->authorize('maintenance.template.publish');

        $model = $this->ownTemplate($template);

        $action->publish($this->versionOf($model, $version), $request->user()->id);

        return redirect()
            ->route('app.maintenance.templates.show', $model->id)
            ->with('status', __('maintenance.version_published'));
    }

    /**
     * @return array<string, mixed>
     */
    private function validatedTemplate(Request $request, ?MaintenanceTemplate $template): array
    {
        $rules = [
            'name' => ['required', 'string', 'max:255'],
            'asset_type_id' => ['nullable', 'string', 'size:26'],
            'maintenance_type_id' => ['nullable', 'string', 'size:26'],
            'description' => ['nullable', 'string', 'max:2000'],
            'estimated_duration_minutes' => ['nullable', 'integer', 'min:1', 'max:10080'],
        ];

        // The code ties every version of a checklist together, so it is set
        // once and never edited.
        if ($template === null) {
            $rules['code'] = [
                'required', 'string', 'max:64', 'regex:/^[A-Za-z0-9][A-Za-z0-9._-]*$/',
                Rule::unique('maintenance_templates', 'code')
                    ->where(fn ($q) => $q->where('company_id', $this->context->companyId())),
            ];
        }

        return $request->validate($rules);
    }

    /**
     * @return array<string, mixed>
     */
    private function validatedItem(Request $request): array
    {
        $validated = $request->validate([
            'label' => ['required', 'string', 'max:500'],
            'input_type' => ['required', Rule::in(ChecklistItem::INPUT_TYPES)],
            'unit' => ['nullable', 'string', 'max:32'],
            'tolerance_min' => ['nullable', 'numeric'],
            'tolerance_max' => ['nullable', 'numeric'],
            'help_text' => ['nullable', 'string', 'max:1000'],
        ]);

        foreach (['required', 'is_safety_item'] as $flag) {
            $validated[$flag] = $request->boolean($flag);
        }

        return $validated;
    }

    /**
     * @return array<string, mixed>
     */
    private function formOptions(): array
    {
        $companyId = $this->context->companyId();

        return [
            'assetTypes' => AssetType::availableTo($companyId)->where('active', true)->orderBy('name')->get(),
            'maintenanceTypes' => MaintenanceType::availableTo($companyId)->where('active', true)->orderBy('name')->get(),
            'inputTypes' => ChecklistItem::INPUT_TYPES,
        ];
    }

    /**
     * A platform template is shared with every tenant, so it is readable here
     * and editable nowhere.
     */
    private function ownTemplate(string $id): MaintenanceTemplate
    {
        $template = MaintenanceTemplate::availableTo($this->context->companyId())->findOrFail($id);

        if ($template->company_id !== $this->context->companyId()) {
            abort(403, __('maintenance.platform_template_read_only'));
        }

        return $template;
    }

    private function versionOf(MaintenanceTemplate $template, string $versionId): MaintenanceTemplateVersion
    {
        return MaintenanceTemplateVersion::where('template_id', $template->id)->findOrFail($versionId);
    }

    private function itemOf(MaintenanceTemplateVersion $version, string $itemId): ChecklistItem
    {
        return ChecklistItem::where('template_version_id', $version->id)->findOrFail($itemId);
    }
}
