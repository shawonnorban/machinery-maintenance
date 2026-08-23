<?php

declare(strict_types=1);

namespace App\Modules\Notification\Http\Controllers\Web;

use App\Modules\Identity\Models\Role;
use App\Modules\Notification\Actions\ManageEscalationRule;
use App\Modules\Notification\Models\EscalationRule;
use App\Modules\Notification\Models\Notification;
use App\Modules\Tenancy\Models\Factory;
use App\Shared\Http\Controllers\Controller;
use App\Shared\Tenancy\TenantContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * Who gets told when nobody answers (SRS 28).
 *
 * The escalation engine has run since it was built, against rules a factory
 * could not write. This is the missing half: how long a critical breakdown may
 * sit unacknowledged before it goes past the person who ignored it.
 */
class EscalationRuleController extends Controller
{
    public function __construct(private readonly TenantContext $context) {}

    public function index(Request $request): View
    {
        $this->authorizeRules($request);

        return view('notification::escalations.index', [
            'rules' => EscalationRule::query()
                ->with(['role:id,name', 'factory:id,name'])
                ->orderBy('event_type')
                ->orderBy('escalation_level')
                ->get(),
            'eventTypes' => Notification::EVENT_TYPES,
            'severities' => Notification::SEVERITIES,
            'roles' => Role::availableTo($this->context->companyId())->orderBy('name')->get(['id', 'name']),
            'factories' => Factory::whereIn('id', $this->context->accessibleFactoryIds())->orderBy('name')->get(),
        ]);
    }

    public function store(Request $request, ManageEscalationRule $action): RedirectResponse
    {
        $this->authorizeRules($request);

        $action->create($request->validate([
            'event_type' => ['required', Rule::in(Notification::EVENT_TYPES)],
            'severity' => ['nullable', Rule::in(Notification::SEVERITIES)],
            'factory_id' => ['nullable', 'string', 'size:26'],
            'delay_minutes' => ['required', 'integer', 'min:1', 'max:10080'],
            'escalation_level' => ['required', 'integer', 'min:1', 'max:5'],
            'escalation_role_id' => ['required', 'string', 'size:26'],
            'max_escalations' => ['nullable', 'integer', 'min:1', 'max:10'],
            'stop_on_acknowledge' => ['sometimes', 'boolean'],
        ]) + ['stop_on_acknowledge' => $request->boolean('stop_on_acknowledge', true)]);

        return back()->with('status', __('notification.rule_added'));
    }

    public function toggle(Request $request, EscalationRule $rule, ManageEscalationRule $action): RedirectResponse
    {
        $this->authorizeRules($request);

        $action->setActive($rule, ! $rule->active);

        return back()->with('status', __('notification.rule_updated'));
    }

    public function destroy(Request $request, EscalationRule $rule, ManageEscalationRule $action): RedirectResponse
    {
        $this->authorizeRules($request);

        $action->delete($rule);

        return back()->with('status', __('notification.rule_removed'));
    }

    private function authorizeRules(Request $request): void
    {
        if (! $request->user()->can('settings.company.manage')) {
            abort(403);
        }
    }
}
