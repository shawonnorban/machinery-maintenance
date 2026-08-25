<?php

declare(strict_types=1);

namespace App\Modules\Platform\Http\Controllers\Web;

use App\Modules\Platform\Actions\ManageSupportTicket;
use App\Modules\Platform\Models\SupportTicket;
use App\Modules\Tenancy\Models\Company;
use App\Shared\Http\Controllers\Controller;
use App\Shared\Tenancy\TenantContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * A customer asking the platform something, in writing (SRS 5).
 *
 * No permission gate beyond being signed in to the company. A support ticket
 * is not an administrative act the way managing users or a subscription is —
 * anybody working here who has hit a problem should be able to say so.
 */
class SupportTicketController extends Controller
{
    public function __construct(private readonly TenantContext $context) {}

    public function index(): View
    {
        $tickets = SupportTicket::with('opener:id,name')
            ->orderByRaw("status = 'CLOSED'")
            ->orderByDesc('last_message_at')
            ->get();

        return view('platform::app.tickets.index', ['tickets' => $tickets]);
    }

    public function create(): View
    {
        return view('platform::app.tickets.create');
    }

    public function store(Request $request, ManageSupportTicket $support): RedirectResponse
    {
        $data = $request->validate([
            'subject' => ['required', 'string', 'max:255'],
            'body' => ['required', 'string', 'max:5000'],
        ]);

        $company = Company::findOrFail($this->context->companyId());

        $ticket = $support->open($company, $request->user(), $data['subject'], $data['body']);

        return redirect()->route('app.support.tickets.show', $ticket)
            ->with('status', __('platform.ticket_opened'));
    }

    /**
     * Route-model binding queries through SupportTicket's own tenant scope
     * (BelongsToTenant), so a ticket belonging to another company already
     * resolves to nothing before this method is reached — nothing further to
     * check here.
     */
    public function show(SupportTicket $ticket): View
    {
        return view('platform::app.tickets.show', [
            'ticket' => $ticket->load('messages.author:id,name'),
        ]);
    }

    public function reply(Request $request, SupportTicket $ticket, ManageSupportTicket $support): RedirectResponse
    {
        $data = $request->validate(['body' => ['required', 'string', 'max:5000']]);

        $support->reply($ticket, $request->user(), $data['body'], isPlatform: false);

        return redirect()->route('app.support.tickets.show', $ticket)
            ->with('status', __('platform.ticket_reply_sent'));
    }
}
