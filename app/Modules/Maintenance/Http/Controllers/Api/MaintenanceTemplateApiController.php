<?php

declare(strict_types=1);

namespace App\Modules\Maintenance\Http\Controllers\Api;

use App\Modules\Asset\Models\AssetType;
use App\Modules\Maintenance\Actions\AuthorTemplate;
use App\Modules\Maintenance\Models\ChecklistItem;
use App\Modules\Maintenance\Models\MaintenanceTemplate;
use App\Modules\Maintenance\Models\MaintenanceTemplateVersion;
use App\Modules\Maintenance\Models\MaintenanceType;
use App\Shared\Http\Api\ApiController;
use App\Shared\Http\Api\ApiException;
use App\Shared\Http\Api\ApiResponse;
use App\Shared\Http\Api\ErrorCode;
use App\Shared\Tenancy\TenantContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * Checklists, and writing them, over the wire (API 9; mirrors the web
 * `TemplateController`, which this delegates every write to unchanged per
 * ADR-003 — `AuthorTemplate` carries every draft/publish/versioning rule).
 *
 * A published version is frozen; changes go into a new draft. A platform
 * template (`company_id` null) is readable by every tenant and editable by
 * none — `ownTemplate()` is the one gate every write here passes through.
 */
class MaintenanceTemplateApiController extends ApiController
{
    public function __construct(private readonly TenantContext $context) {}

    public function index(Request $request): JsonResponse
    {
        $this->allow('maintenance.template.view_any');

        $templates = MaintenanceTemplate::availableTo($this->context->companyId())
            ->with(['assetType:id,name', 'maintenanceType:id,name'])
            ->withCount('versions')
            ->orderBy('name');

        return ApiResponse::paginated(
            $templates->paginate($this->perPage($request))->withQueryString(),
            fn (MaintenanceTemplate $t): array => $this->summary($t),
        );
    }

    /**
     * Everything the template form's dropdowns need — mirrors
     * `TemplateController::formOptions()` exactly.
     */
    public function formOptions(): JsonResponse
    {
        $this->allow('maintenance.template.view_any');

        $companyId = $this->context->companyId();

        return ApiResponse::ok([
            'asset_types' => AssetType::availableTo($companyId)->where('active', true)->orderBy('name')
                ->get(['id', 'name'])->all(),
            'maintenance_types' => MaintenanceType::availableTo($companyId)->where('active', true)->orderBy('name')
                ->get(['id', 'name'])->all(),
            'input_types' => ChecklistItem::INPUT_TYPES,
        ]);
    }

    /**
     * The template plus one version's items — the published version by
     * default (what a plan binds to and what a technician is actually
     * handed), or an explicit `?version=` for any other one (a draft being
     * edited, or an archived version being reviewed).
     */
    public function show(Request $request, string $template): JsonResponse
    {
        $this->allow('maintenance.template.view_any');

        $model = $this->findTemplate($template);

        $version = filled($versionId = $request->query('version'))
            ? MaintenanceTemplateVersion::where('template_id', $model->id)->findOrFail($versionId)
            : ($model->currentVersion() ?? $model->versions->first());

        return ApiResponse::ok($this->detail($model, $version));
    }

    public function store(Request $request, AuthorTemplate $action): JsonResponse
    {
        $this->allow('maintenance.template.create');

        $data = $this->validatedTemplate($request, null);

        $template = $action->createTemplate($data, $this->caller()->auditUserId());

        return ApiResponse::created($this->detail($template, $template->currentVersion() ?? $template->draftVersion()));
    }

    public function update(Request $request, string $template, AuthorTemplate $action): JsonResponse
    {
        $this->allow('maintenance.template.update');

        $model = $this->ownTemplate($template);

        try {
            $updated = $action->updateTemplate($model, $this->validatedTemplate($request, $model));
        } catch (ValidationException $e) {
            throw $this->translate($e);
        }

        return ApiResponse::ok($this->detail($updated, $updated->currentVersion() ?? $updated->draftVersion()));
    }

    /**
     * Start a revision. The published version stays exactly as it is until
     * the new one is published in its place.
     */
    public function draft(string $template, AuthorTemplate $action): JsonResponse
    {
        $this->allow('maintenance.template.update');

        $model = $this->ownTemplate($template);

        try {
            $draft = $action->newDraft($model);
        } catch (ValidationException $e) {
            throw $this->translate($e);
        }

        return ApiResponse::created($this->versionSummary($draft->load('items')));
    }

    public function storeItem(Request $request, string $template, string $version, AuthorTemplate $action): JsonResponse
    {
        $this->allow('maintenance.template.update');

        $model = $this->ownTemplate($template);
        $draft = $this->versionOf($model, $version);

        try {
            $item = $action->saveItem($draft, $this->validatedItem($request));
        } catch (ValidationException $e) {
            throw $this->translate($e);
        }

        return ApiResponse::created($this->itemSummary($item));
    }

    public function updateItem(Request $request, string $template, string $version, string $item, AuthorTemplate $action): JsonResponse
    {
        $this->allow('maintenance.template.update');

        $model = $this->ownTemplate($template);
        $draft = $this->versionOf($model, $version);

        try {
            $updated = $action->saveItem($draft, $this->validatedItem($request), $this->itemOf($draft, $item));
        } catch (ValidationException $e) {
            throw $this->translate($e);
        }

        return ApiResponse::ok($this->itemSummary($updated));
    }

    public function destroyItem(string $template, string $version, string $item, AuthorTemplate $action): JsonResponse
    {
        $this->allow('maintenance.template.update');

        $model = $this->ownTemplate($template);
        $draft = $this->versionOf($model, $version);

        try {
            $action->removeItem($draft, $this->itemOf($draft, $item));
        } catch (ValidationException $e) {
            throw $this->translate($e);
        }

        return ApiResponse::noContent();
    }

    public function publish(string $template, string $version, AuthorTemplate $action): JsonResponse
    {
        $this->allow('maintenance.template.publish');

        $model = $this->ownTemplate($template);
        $draft = $this->versionOf($model, $version);

        try {
            $published = $action->publish($draft, $this->caller()->auditUserId());
        } catch (ValidationException $e) {
            throw $this->translate($e);
        }

        return ApiResponse::ok($this->detail($model->fresh(), $published));
    }

    private function translate(ValidationException $e): ApiException
    {
        $status = $e->status ?? 422;
        $code = match ($status) {
            409 => ErrorCode::CONFLICT,
            403 => ErrorCode::FORBIDDEN,
            default => ErrorCode::VALIDATION_ERROR,
        };

        return ApiException::of($code, implode(' ', $e->validator->errors()->all()), $e->errors());
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

    private function findTemplate(string $id): MaintenanceTemplate
    {
        return MaintenanceTemplate::availableTo($this->context->companyId())
            ->with(['assetType:id,name', 'maintenanceType:id,name', 'versions'])
            ->findOrFail($id);
    }

    /**
     * A platform template is shared with every tenant, so it is readable
     * here and editable nowhere.
     */
    private function ownTemplate(string $id): MaintenanceTemplate
    {
        $template = $this->findTemplate($id);

        if ($template->company_id !== $this->context->companyId()) {
            throw ApiException::of(ErrorCode::FORBIDDEN, __('maintenance.platform_template_read_only'));
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

    /**
     * @return array<string, mixed>
     */
    private function summary(MaintenanceTemplate $template): array
    {
        return [
            'id' => $template->id,
            'name' => $template->name,
            'code' => $template->code,
            'status' => $template->status,
            'is_editable' => $template->isEditable(),
            'asset_type' => $template->assetType?->name,
            'maintenance_type' => $template->maintenanceType?->name,
            'versions_count' => $template->versions_count ?? null,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function detail(MaintenanceTemplate $template, ?MaintenanceTemplateVersion $version): array
    {
        $template->loadMissing(['assetType:id,name', 'maintenanceType:id,name', 'versions']);

        return $this->summary($template) + [
            'asset_type_id' => $template->asset_type_id,
            'maintenance_type_id' => $template->maintenance_type_id,
            'description' => $template->description,
            'draft_version_id' => $template->draftVersion()?->id,
            'current_version_id' => $template->currentVersion()?->id,
            'versions' => $template->versions->map(fn (MaintenanceTemplateVersion $v): array => [
                'id' => $v->id,
                'version_number' => $v->version_number,
                'status' => $v->status,
                'effective_from' => $v->effective_from?->toDateString(),
                'effective_to' => $v->effective_to?->toDateString(),
            ])->all(),
            'version' => $version === null ? null : $this->versionSummary($version),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function versionSummary(MaintenanceTemplateVersion $version): array
    {
        $version->loadMissing('items');

        return [
            'id' => $version->id,
            'version_number' => $version->version_number,
            'status' => $version->status,
            'is_editable' => $version->isEditable(),
            'has_been_used' => $version->hasBeenUsed(),
            'estimated_duration_minutes' => $version->estimated_duration_minutes,
            'instructions' => $version->instructions,
            'effective_from' => $version->effective_from?->toDateString(),
            'effective_to' => $version->effective_to?->toDateString(),
            'published_at' => $version->published_at?->toIso8601String(),
            'items' => $version->items->map(fn (ChecklistItem $item): array => $this->itemSummary($item))->all(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function itemSummary(ChecklistItem $item): array
    {
        return [
            'id' => $item->id,
            'sequence' => $item->sequence,
            'label' => $item->label,
            'input_type' => $item->input_type,
            'unit' => $item->unit,
            'tolerance_min' => $item->tolerance_min,
            'tolerance_max' => $item->tolerance_max,
            'required' => (bool) $item->required,
            'is_safety_item' => (bool) $item->is_safety_item,
            'requires_attachment_on_fail' => (bool) $item->requires_attachment_on_fail,
            'requires_note_on_fail' => (bool) $item->requires_note_on_fail,
            'fail_creates_followup_work_order' => (bool) $item->fail_creates_followup_work_order,
            'help_text' => $item->help_text,
        ];
    }
}
