<?php

declare(strict_types=1);

namespace App\Modules\Platform\Http\Controllers\Api;

use App\Modules\Identity\Models\User;
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
        ]);

        $company = Company::findOrFail($this->context->companyId());

        try {
            $ticket = $this->support->open($company, $this->person(), $data['subject'], $data['body']);
        } catch (ValidationException $e) {
            throw ApiException::of(ErrorCode::VALIDATION_ERROR, implode(' ', $e->validator->errors()->all()));
        }

        return ApiResponse::created($this->detail($ticket));
    }

    public function show(SupportTicket $ticket): JsonResponse
    {
        return ApiResponse::ok($this->detail($ticket->load('messages.author:id,name')));
    }

    public function reply(Request $request, SupportTicket $ticket): JsonResponse
    {
        $data = $request->validate(['body' => ['required', 'string', 'max:5000']]);

        try {
            $this->support->reply($ticket, $this->person(), $data['body'], isPlatform: false);
        } catch (ValidationException $e) {
            throw ApiException::of(ErrorCode::CONFLICT, implode(' ', $e->validator->errors()->all()));
        }

        return ApiResponse::ok($this->detail($ticket->fresh()->load('messages.author:id,name')));
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
     * @return array<string, mixed>
     */
    private function summary(SupportTicket $ticket): array
    {
        return [
            'id' => $ticket->id,
            'subject' => $ticket->subject,
            'status' => $ticket->status,
            'is_open' => $ticket->isOpen(),
            'opener' => $ticket->relationLoaded('opener') && $ticket->opener !== null
                ? ['id' => $ticket->opener->id, 'name' => $ticket->opener->name]
                : ['id' => $ticket->opened_by],
            'last_message_at' => $ticket->last_message_at?->toIso8601String(),
            'resolved_at' => $ticket->resolved_at?->toIso8601String(),
            'closed_at' => $ticket->closed_at?->toIso8601String(),
        ];
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
