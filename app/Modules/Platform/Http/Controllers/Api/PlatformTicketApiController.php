<?php

declare(strict_types=1);

namespace App\Modules\Platform\Http\Controllers\Api;

use App\Modules\Identity\Models\User;
use App\Modules\Platform\Actions\ManageSupportTicket;
use App\Modules\Platform\Models\SupportTicket;
use App\Shared\Http\Api\ApiController;
use App\Shared\Http\Api\ApiException;
use App\Shared\Http\Api\ApiResponse;
use App\Shared\Http\Api\ErrorCode;
use App\Shared\Scopes\TenantScope;
use App\Shared\Support\Sql;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

/**
 * Every customer's tickets, from the platform side (Platform API §7;
 * mirrors `PlatformDeskController::tickets` and `PlatformTicketController`,
 * which this delegates every write to unchanged per ADR-003).
 *
 * `SupportTicket` is tenant-scoped, so every query here reads
 * `withoutGlobalScope(TenantScope::class)` — platform staff belong to no
 * company, and route-model binding would otherwise find nothing.
 */
class PlatformTicketApiController extends ApiController
{
    public function __construct(private readonly ManageSupportTicket $support) {}

    public function index(Request $request): JsonResponse
    {
        $query = SupportTicket::withoutGlobalScope(TenantScope::class)
            ->with(['company:id,name,code', 'opener:id,name', 'assignee:id,name']);

        if ($request->filled('company_id')) {
            $query->where('company_id', $request->string('company_id'));
        }

        if ($request->filled('assigned_to')) {
            $query->where('assigned_to', $request->string('assigned_to'));
        }

        if ($request->filled('status')) {
            $query->where('status', strtoupper($request->string('status')->toString()));
        }

        $tickets = $query
            ->orderByRaw(Sql::sortMatchLast('status', 'CLOSED'))
            ->orderByDesc('last_message_at')
            ->paginate($this->perPage($request))
            ->withQueryString();

        return ApiResponse::paginated($tickets, fn (SupportTicket $t): array => $this->summary($t));
    }

    public function show(string $ticket): JsonResponse
    {
        $target = $this->ticket($ticket)->load(['messages.author:id,name', 'company:id,name,code']);

        return ApiResponse::ok($this->detail($target));
    }

    public function reply(Request $request, string $ticket): JsonResponse
    {
        $data = $request->validate(['body' => ['required', 'string', 'max:5000']]);

        try {
            $this->support->reply($this->ticket($ticket), $request->user(), $data['body'], isPlatform: true);
        } catch (ValidationException $e) {
            throw ApiException::of(ErrorCode::CONFLICT, implode(' ', $e->validator->errors()->all()));
        }

        return ApiResponse::ok($this->detail($this->ticket($ticket)->load(['messages.author:id,name', 'company:id,name,code'])));
    }

    public function setStatus(Request $request, string $ticket): JsonResponse
    {
        $data = $request->validate(['status' => ['required', 'in:'.implode(',', SupportTicket::STATUSES)]]);

        try {
            $this->support->setStatus($this->ticket($ticket), $request->user(), $data['status']);
        } catch (ValidationException $e) {
            throw ApiException::of(ErrorCode::VALIDATION_ERROR, implode(' ', $e->validator->errors()->all()));
        }

        return ApiResponse::ok($this->summary($this->ticket($ticket)));
    }

    public function assign(Request $request, string $ticket): JsonResponse
    {
        $data = $request->validate(['assigned_to' => ['nullable', 'string']]);

        $assignee = isset($data['assigned_to']) ? User::find($data['assigned_to']) : null;

        $this->support->assign($this->ticket($ticket), $assignee);

        return ApiResponse::ok($this->summary($this->ticket($ticket)));
    }

    private function ticket(string $id): SupportTicket
    {
        return SupportTicket::withoutGlobalScope(TenantScope::class)->findOrFail($id);
    }

    /**
     * @return array<string, mixed>
     */
    private function summary(SupportTicket $ticket): array
    {
        return [
            'id' => $ticket->id,
            'company' => $ticket->relationLoaded('company') && $ticket->company !== null
                ? ['id' => $ticket->company->id, 'name' => $ticket->company->name, 'code' => $ticket->company->code]
                : ['id' => $ticket->company_id],
            'opener' => $ticket->relationLoaded('opener') && $ticket->opener !== null
                ? ['id' => $ticket->opener->id, 'name' => $ticket->opener->name]
                : ['id' => $ticket->opened_by],
            'assignee' => match (true) {
                $ticket->assigned_to === null => null,
                $ticket->relationLoaded('assignee') && $ticket->assignee !== null => [
                    'id' => $ticket->assignee->id, 'name' => $ticket->assignee->name,
                ],
                default => ['id' => $ticket->assigned_to],
            },
            'subject' => $ticket->subject,
            'status' => $ticket->status,
            'is_open' => $ticket->isOpen(),
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
