<?php

declare(strict_types=1);

namespace App\Modules\Platform\Http\Controllers\Web;

use App\Modules\Identity\Models\User;
use App\Modules\Platform\Actions\ManageSupportTicket;
use App\Modules\Platform\Models\SupportTicket;
use App\Shared\Http\Controllers\Controller;
use App\Shared\Scopes\TenantScope;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * One ticket, from the platform side.
 *
 * Reached both from a customer's own page (their Tickets tab) and from the
 * global inbox at /platform/tickets — the same screen either way, because a
 * ticket does not read differently depending on where the click came from.
 */
class PlatformTicketController extends Controller
{
    public function show(string $ticket): View
    {
        return view('platform::tenants.ticket', [
            'ticket' => $this->ticket($ticket)->load(['messages.author:id,name', 'company:id,name,code']),
            'staff' => User::where('is_platform_admin', true)->where('status', 'ACTIVE')->orderBy('name')->get(),
        ]);
    }

    public function reply(Request $request, string $ticket, ManageSupportTicket $support): RedirectResponse
    {
        $data = $request->validate(['body' => ['required', 'string', 'max:5000']]);

        $support->reply($this->ticket($ticket), $request->user(), $data['body'], isPlatform: true);

        return back()->with('status', __('platform.ticket_reply_sent'));
    }

    public function setStatus(Request $request, string $ticket, ManageSupportTicket $support): RedirectResponse
    {
        $data = $request->validate(['status' => ['required', 'in:'.implode(',', SupportTicket::STATUSES)]]);

        $support->setStatus($this->ticket($ticket), $request->user(), $data['status']);

        return back()->with('status', __('platform.ticket_status_saved'));
    }

    public function assign(Request $request, string $ticket, ManageSupportTicket $support): RedirectResponse
    {
        $data = $request->validate(['assigned_to' => ['nullable', 'string']]);

        $assignee = isset($data['assigned_to']) ? User::find($data['assigned_to']) : null;

        $support->assign($this->ticket($ticket), $assignee);

        return back()->with('status', __('platform.ticket_assigned'));
    }

    /**
     * SupportTicket is tenant-scoped, so route-model binding would find
     * nothing for platform staff, who belong to no company — resolved by hand
     * exactly as every other platform-facing model is in this module.
     */
    private function ticket(string $id): SupportTicket
    {
        return SupportTicket::withoutGlobalScope(TenantScope::class)->findOrFail($id);
    }
}
