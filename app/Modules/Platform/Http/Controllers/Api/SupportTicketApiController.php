<?php

declare(strict_types=1);

namespace App\Modules\Platform\Http\Controllers\Api;

use App\Modules\Identity\Models\User;
use App\Modules\Platform\Actions\DeleteSupportTicket;
use App\Modules\Platform\Actions\ManageSupportTicket;
use App\Modules\Platform\Models\SupportTicket;
use App\Modules\Tenancy\Models\Company;
use App\Shared\Http\Api\ApiController;
use App\Shared\Http\Api\ApiException;
use App\Shared\Http\Api\ApiResponse;
use App\Shared\Http\Api\ErrorCode;
use App\Shared\Support\Sql;
use App\Shared\Tenancy\TenantContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * A customer's own side of a support conversation (regular tenant API §26.1;
 * mirrors the web `SupportTicketController`, which this delegates every
 * write to unchanged per ADR-003). The platform's side of the same
 * conversation is `PlatformTicketApiController`, under `/platform/tickets`
 * behind `platform.auth` — this controller carries no admin gate, the same
 * way the web route sits under `/app` rather than `/platform`.
 *
 * `SupportTicket` is tenant-scoped (`BelongsToTenant`), so route-model
 * binding already refuses a ticket belonging to another company before a
 * method here is reached.
 */
class SupportTicketApiController extends ApiController
{
    public function __construct(
        private readonly TenantContext $context,
        private readonly ManageSupportTicket $support,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $tickets = SupportTicket::with('opener:id,name')
            ->withCount('messages')
            ->orderByRaw(Sql::sortMatchLast('status', 'CLOSED'))
            ->orderByDesc('last_message_at')
            ->paginate($this->perPage($request))
            ->withQueryString();

        return ApiResponse::paginated($tickets, fn (SupportTicket $t): array => $this->summary($t));
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'subject' => ['required', 'string', 'max:255'],
            'body' => ['required', 'string', 'max:5000'],
            // A suggestion, not a control — `open()` falls back to MEDIUM on
            // anything invalid or omitted, so this stays lenient rather than
            // rejecting the whole ticket over it.
            'priority' => ['sometimes', 'nullable', Rule::in(SupportTicket::PRIORITIES)],
        ]);

        $company = Company::findOrFail($this->context->companyId());

        try {
            $ticket = $this->support->open($company, $this->person(), $data['subject'], $data['body'], $data['priority'] ?? null);
        } catch (ValidationException $e) {
            throw ApiException::of(ErrorCode::VALIDATION_ERROR, implode(' ', $e->validator->errors()->all()));
        }

        return ApiResponse::created($this->detail($ticket));
    }

    public function show(SupportTicket $ticket): JsonResponse
    {
        return ApiResponse::ok($this->detail($this->loadVisibleMessages($ticket)));
    }

    /**
     * A quick correction, not a general-purpose edit — `ManageSupportTicket::
     * update()` refuses once anyone (platform or the opener's own follow-up)
     * has replied. Ownership is checked here (a 403, an authorization
     * concern) rather than inside the Action (which throws a 409 for the
     * ticket's own state, a different kind of failure).
     */
    public function update(Request $request, SupportTicket $ticket): JsonResponse
    {
        $this->assertOwner($ticket);

        $data = $request->validate([
            'subject' => ['required', 'string', 'max:255'],
            'body' => ['required', 'string', 'max:5000'],
        ]);

        try {
            $this->support->update($ticket, $this->person(), $data['subject'], $data['body']);
        } catch (ValidationException $e) {
            throw ApiException::of(ErrorCode::CONFLICT, implode(' ', $e->validator->errors()->all()));
        }

        return ApiResponse::ok($this->detail($this->loadVisibleMessages($ticket->fresh())));
    }

    public function destroy(SupportTicket $ticket, DeleteSupportTicket $action): JsonResponse
    {
        $this->assertOwner($ticket);

        try {
            $action->handle($ticket, $this->person()->id);
        } catch (ValidationException $e) {
            throw ApiException::of(ErrorCode::DEPENDENT_RECORDS_EXIST, implode(' ', $e->validator->errors()->all()));
        }

        return ApiResponse::noContent();
    }

    public function reply(Request $request, SupportTicket $ticket): JsonResponse
    {
        $data = $request->validate(['body' => ['required', 'string', 'max:5000']]);

        try {
            // A tenant can never post an internal note — not accepted from
            // the request at all, not even defaulted false and trusted.
            $this->support->reply($ticket, $this->person(), $data['body'], isPlatform: false);
        } catch (ValidationException $e) {
            throw ApiException::of(ErrorCode::CONFLICT, implode(' ', $e->validator->errors()->all()));
        }

        return ApiResponse::ok($this->detail($this->loadVisibleMessages($ticket->fresh())));
    }

    /**
     * Internal notes are filtered at the query level, not just left out of
     * the serializer — a bug in `detail()`'s array-mapping must never be the
     * only thing standing between a platform-only note and a customer.
     */
    private function loadVisibleMessages(SupportTicket $ticket): SupportTicket
    {
        return $ticket->load(['messages' => fn ($query) => $query->where('is_internal', false)->with('author:id,name')]);
    }

    private function person(): User
    {
        $user = $this->caller()->user;

        if ($user === null) {
            throw ApiException::of(ErrorCode::FORBIDDEN, __('api.step_needs_a_person'));
        }

        return $user;
    }

    /**
     * Every signed-in company member can see every one of the company's
     * tickets (no permission gate at all — see this class's own docblock),
     * but editing or deleting one is narrower: only the person who opened
     * it, the same way nobody else's draft is theirs to rewrite.
     */
    private function assertOwner(SupportTicket $ticket): void
    {
        if ($ticket->opened_by !== $this->person()->id) {
            throw ApiException::of(ErrorCode::FORBIDDEN);
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function summary(SupportTicket $ticket): array
    {
        return [
            'id' => $ticket->id,
            'subject' => $ticket->subject,
            'status' => $ticket->status,
            'is_open' => $ticket->isOpen(),
            // Read-only here — a tenant sees the platform's own triage, not
            // a control to change it themselves.
            'priority' => $ticket->priority,
            'category' => $ticket->category,
            'opener' => $ticket->relationLoaded('opener') && $ticket->opener !== null
                ? ['id' => $ticket->opener->id, 'name' => $ticket->opener->name]
                : ['id' => $ticket->opened_by],
            'last_message_at' => $ticket->last_message_at?->toIso8601String(),
            'resolved_at' => $ticket->resolved_at?->toIso8601String(),
            'closed_at' => $ticket->closed_at?->toIso8601String(),
            'sla' => [
                'first_response_due_at' => $ticket->first_response_due_at?->toIso8601String(),
                'resolution_due_at' => $ticket->resolution_due_at?->toIso8601String(),
                'first_responded_at' => $ticket->first_responded_at?->toIso8601String(),
                'breached' => $ticket->isSlaBreached(),
            ],
            // A quick correction/undo is only offered while the ticket is
            // still exactly what its opener typed — see `ManageSupportTicket::
            // update()`'s own note on why a second message (from either
            // side) ends that window, and only the opener gets it at all.
            'can_edit' => $ticket->opened_by === $this->person()->id
                && $ticket->status === 'OPEN'
                && $this->messageCount($ticket) <= 1,
        ];
    }

    private function messageCount(SupportTicket $ticket): int
    {
        if (isset($ticket->messages_count)) {
            return (int) $ticket->messages_count;
        }

        if ($ticket->relationLoaded('messages')) {
            return $ticket->messages->count();
        }

        return $ticket->messages()->count();
    }

    /**
     * @return array<string, mixed>
     */
    private function detail(SupportTicket $ticket): array
    {
        return $this->summary($ticket) + [
            'messages' => $ticket->messages->map(fn ($m): array => [
                'id' => $m->id,
                'author' => $m->relationLoaded('author') && $m->author !== null
                    ? ['id' => $m->author->id, 'name' => $m->author->name]
                    : ['id' => $m->author_id],
                'author_is_platform' => $m->author_is_platform,
                'body' => $m->body,
                'created_at' => $m->created_at?->toIso8601String(),
            ])->all(),
        ];
    }
}
