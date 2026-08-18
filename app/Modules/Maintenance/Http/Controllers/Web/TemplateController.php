<?php

declare(strict_types=1);

namespace App\Modules\Maintenance\Http\Controllers\Web;

use App\Modules\Maintenance\Models\MaintenanceTemplate;
use App\Modules\Maintenance\Models\MaintenanceTemplateVersion;
use App\Shared\Http\Controllers\Controller;
use App\Shared\Tenancy\TenantContext;
use Illuminate\View\View;

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
        ]);
    }
}
