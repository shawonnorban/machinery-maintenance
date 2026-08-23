<?php

declare(strict_types=1);

namespace App\Modules\Approval\Http\Controllers\Web;

use App\Modules\Approval\Actions\ManageApprovalWorkflow;
use App\Modules\Approval\Models\ApprovalRule;
use App\Modules\Approval\Models\ApprovalWorkflow;
use App\Modules\Asset\Models\Asset;
use App\Modules\Identity\Models\Role;
use App\Modules\Tenancy\Models\Factory;
use App\Shared\Http\Controllers\Controller;
use App\Shared\Tenancy\TenantContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * The approval chain a factory actually wants (SRS 14).
 *
 * Approvals have worked since they were built, against rules nobody could
 * write — so every company got whatever the seed said, and a factory whose
 * owner wanted to sign for anything over five lakh had no way to say so.
 *
 * Rules are ordered and the order is the chain. Each names a role rather than a
 * person, because a chain that names Karim stops working the week Karim is on
 * leave.
 */
class WorkflowController extends Controller
{
    public function __construct(private readonly TenantContext $context) {}

    public function index(Request $request): View
    {
        $this->authorizeWorkflows($request);

        $workflows = ApprovalWorkflow::query()
            ->with(['rules' => fn ($q) => $q->orderBy('sequence'), 'rules.role:id,name'])
            ->orderBy('entity_type')
            ->get();

        $action = app(ManageApprovalWorkflow::class);

        return view('approval::workflows.index', [
            'workflows' => $workflows,
            // Shown rather than enforced: a factory may change its chain, and
            // requests already raised keep the context they froze.
            'requestCounts' => $workflows->mapWithKeys(fn (ApprovalWorkflow $w) => [
                $w->id => $action->requestCount($w),
            ]),
            'entityTypes' => ApprovalWorkflow::ENTITY_TYPES,
            'roles' => Role::availableTo($this->context->companyId())->orderBy('name')->get(['id', 'name']),
            'factories' => Factory::whereIn('id', $this->context->accessibleFactoryIds())->orderBy('name')->get(),
            'criticalities' => Asset::CRITICALITIES,
        ]);
    }

    public function store(Request $request, ManageApprovalWorkflow $action): RedirectResponse
    {
        $this->authorizeWorkflows($request);

        $action->createWorkflow($request->validate([
            'name' => ['required', 'string', 'max:255'],
            'entity_type' => ['required', Rule::in(ApprovalWorkflow::ENTITY_TYPES)],
        ]));

        return back()->with('status', __('approval.workflow_created'));
    }

    public function toggle(Request $request, ApprovalWorkflow $workflow, ManageApprovalWorkflow $action): RedirectResponse
    {
        $this->authorizeWorkflows($request);

        $action->setActive($workflow, ! $workflow->active);

        return back()->with('status', __('approval.workflow_updated'));
    }

    public function storeRule(Request $request, ApprovalWorkflow $workflow, ManageApprovalWorkflow $action): RedirectResponse
    {
        $this->authorizeWorkflows($request);

        $action->addRule($workflow, $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'role_id' => ['required', 'string', 'size:26'],
            'min_cost' => ['nullable', 'numeric', 'min:0'],
            'max_cost' => ['nullable', 'numeric', 'min:0'],
            'criticality' => ['nullable', 'array'],
            'criticality.*' => [Rule::in(Asset::CRITICALITIES)],
            'factory_id' => ['nullable', 'string', 'size:26'],
        ]));

        return back()->with('status', __('approval.rule_added'));
    }

    public function destroyRule(
        Request $request,
        ApprovalWorkflow $workflow,
        ApprovalRule $rule,
        ManageApprovalWorkflow $action,
    ): RedirectResponse {
        $this->authorizeWorkflows($request);

        if ($rule->workflow_id !== $workflow->id) {
            abort(404);
        }

        $action->removeRule($rule);

        return back()->with('status', __('approval.rule_removed'));
    }

    private function authorizeWorkflows(Request $request): void
    {
        // Deciding who must sign is a company-level decision, not a factory
        // one, so it sits with whoever manages company settings.
        if (! $request->user()->can('settings.company.manage')) {
            abort(403);
        }
    }
}
