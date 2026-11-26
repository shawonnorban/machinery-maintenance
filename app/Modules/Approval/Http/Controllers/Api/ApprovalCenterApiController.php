<?php

declare(strict_types=1);

namespace App\Modules\Approval\Http\Controllers\Api;

use App\Modules\Approval\Actions\DecideApproval;
use App\Modules\Approval\Models\ApprovalAction;
use App\Modules\Approval\Models\ApprovalRequest;
use App\Modules\Approval\Models\ApprovalRequestType;
use App\Modules\Approval\Models\ApprovalTicket;
use App\Modules\Approval\Models\ApprovalWorkflow;
use App\Modules\Approval\Services\ApprovalDashboard;
use App\Modules\Approval\Services\ApprovalInbox;
use App\Modules\Approval\Support\RequestTypeDefaults;
use App\Modules\Identity\Models\CompanyUser;
use App\Modules\Identity\Models\User;
use App\Modules\Settings\Services\SettingsResolver;
use App\Shared\Http\Api\ApiController;
use App\Shared\Http\Api\ApiException;
use App\Shared\Http\Api\ApiResponse;
use App\Shared\Http\Api\ErrorCode;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Approval Center (AnnotechERP 04): every approval a person may see, the
 * decisions on it, and the unalterable history.
 *
 * Opening the Center needs approval.ticket.create; acting on a request needs
 * only that it is the person's turn — Request To is whoever the requester
 * named, and a step's named people are whoever the designer assigned.
 */
class ApprovalCenterApiController extends ApiController
{
    public function __construct(
        private readonly ApprovalInbox $inbox,
        private readonly DecideApproval $decisions,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $this->allow('approval.ticket.create');

        $data = $request->validate([
            'scope' => ['nullable', Rule::in(ApprovalInbox::SCOPES)],
            'q' => ['nullable', 'string', 'max:100'],
            'type' => ['nullable', 'string', 'max:64'],
            'status' => ['nullable', Rule::in([...ApprovalRequest::STATUSES, 'DRAFT'])],
            'priority' => ['nullable', Rule::in(ApprovalRequestType::PRIORITIES)],
            'from' => ['nullable', 'date_format:Y-m-d'],
            'to' => ['nullable', 'date_format:Y-m-d'],
        ]);
        $result = $this->inbox->list($this->person(), $this->viewAny(), $data['scope'] ?? 'all', $data);

        return ApiResponse::ok($result['rows'], ['stats' => $result['stats']]);
    }

    public function show(string $approval): JsonResponse
    {
        $this->allow('approval.ticket.create');

        $request = ApprovalRequest::query()->whereKey($approval)->firstOrFail();
        $user = $this->person();
        abort_unless($this->inbox->canSee($request, $user, $this->viewAny()), 404);

        $ticket = $request->entity_type === 'MANUAL_REQUEST' ? ApprovalTicket::query()->with(['type', 'requestTo'])->find($request->entity_id) : null;
        $history = ApprovalAction::query()->where('approval_request_id', $request->id)->orderBy('acted_at')->orderBy('id')->get();
        // Earlier rounds of a returned request belong to its story too.
        if ($ticket) {
            $rounds = ApprovalRequest::query()->where('entity_type', 'MANUAL_REQUEST')->where('entity_id', $ticket->id)->pluck('id');
            $history = ApprovalAction::query()->whereIn('approval_request_id', $rounds)->orderBy('acted_at')->orderBy('id')->get();
        }

        $row = $this->inbox->rowFor($request, $user);

        return ApiResponse::ok([
            ...$row,
            'ticket' => $ticket ? $this->ticketDetail($ticket) : null,
            'levels' => $this->inbox->levels($request),
            'history' => $history->map(fn (ApprovalAction $a): array => $this->actionRow($a))->all(),
            'can_act' => $this->decisions->canAct($request, $user),
            'can_return' => $request->entity_type === 'MANUAL_REQUEST' && $this->decisions->canAct($request, $user),
            'can_cancel' => $ticket !== null && $ticket->requested_by === $user->id && in_array($ticket->status, ['PENDING', 'RETURNED'], true),
            'can_edit' => $ticket !== null && $ticket->requested_by === $user->id && $ticket->status === 'RETURNED',
        ]);
    }

    public function approve(Request $request, string $approval): JsonResponse
    {
        $this->allow('approval.ticket.create');
        $data = $request->validate(['comment' => ['nullable', 'string', 'max:2000']]);
        $this->decisions->approve($this->findVisible($approval), $this->person(), $data['comment'] ?? null);

        return $this->show($approval);
    }

    public function reject(Request $request, string $approval): JsonResponse
    {
        $this->allow('approval.ticket.create');
        $data = $request->validate(['comment' => ['required', 'string', 'max:2000']]);
        $this->decisions->reject($this->findVisible($approval), $this->person(), $data['comment']);

        return $this->show($approval);
    }

    public function sendBack(Request $request, string $approval): JsonResponse
    {
        $this->allow('approval.ticket.create');
        $data = $request->validate(['comment' => ['required', 'string', 'max:2000']]);
        $this->decisions->returnForRevision($this->findVisible($approval), $this->person(), $data['comment']);

        return $this->show($approval);
    }

    /** Approval History: every action on the requests this person may see, newest first. */
    public function history(Request $request): JsonResponse
    {
        $this->allow('approval.ticket.create');

        $data = $request->validate([
            'q' => ['nullable', 'string', 'max:100'],
            'action' => ['nullable', Rule::in(ApprovalAction::ACTIONS)],
            'from' => ['nullable', 'date_format:Y-m-d'],
            'to' => ['nullable', 'date_format:Y-m-d'],
        ]);
        $visible = collect($this->inbox->list($this->person(), $this->viewAny(), 'all')['rows'])->keyBy('id');
        // Earlier rounds of a hand-written request are part of its history.
        $ids = $visible->keys()->filter(fn ($id) => ! str_starts_with((string) $id, 'ticket-'));
        $ticketIds = $visible->pluck('ticket_id')->filter();
        $rounds = ApprovalRequest::query()->where('entity_type', 'MANUAL_REQUEST')->whereIn('entity_id', $ticketIds)->get(['id', 'entity_id']);
        $numberOf = $visible->mapWithKeys(fn ($row, $id) => [$id => $row['request_no']])->all();
        foreach ($rounds as $round) {
            $numberOf[$round->id] ??= $visible->firstWhere('ticket_id', $round->entity_id)['request_no'] ?? null;
        }

        $rows = ApprovalAction::query()
            ->whereIn('approval_request_id', $ids->concat($rounds->pluck('id'))->unique()->values())
            ->when($data['action'] ?? null, fn ($q, $a) => $q->where('action', $a))
            ->when($data['from'] ?? null, fn ($q, $d) => $q->whereDate('acted_at', '>=', $d))
            ->when($data['to'] ?? null, fn ($q, $d) => $q->whereDate('acted_at', '<=', $d))
            ->latest('acted_at')
            ->limit(1000)
            ->get()
            ->map(fn (ApprovalAction $a): array => $this->actionRow($a) + ['request_no' => $numberOf[$a->approval_request_id] ?? null])
            ->filter(function (array $row) use ($data): bool {
                if (empty($data['q'])) {
                    return true;
                }
                $needle = mb_strtolower($data['q']);

                return str_contains(mb_strtolower(implode(' ', array_filter([$row['request_no'], $row['by'], $row['comment']]))), $needle);
            })
            ->values();

        return ApiResponse::ok($rows->all());
    }

    /** Request Dashboard: counts, turnaround and stalls over what this person may see. */
    public function dashboard(ApprovalDashboard $dashboard, SettingsResolver $settings): JsonResponse
    {
        $this->allow('approval.ticket.create');

        return ApiResponse::ok($dashboard->build($this->person(), $this->viewAny(), $settings->string('locale.timezone') ?: (string) config('app.timezone')));
    }

    /** What the New Request form offers. */
    public function formOptions(): JsonResponse
    {
        $this->allow('approval.ticket.create');

        RequestTypeDefaults::ensure($this->caller()->companyId);
        $members = CompanyUser::query()->where('company_id', $this->caller()->companyId)->where('status', 'ACTIVE')->pluck('user_id');

        return ApiResponse::ok([
            'types' => ApprovalRequestType::query()->where('active', true)->orderBy('sort_order')->orderBy('name')->get()
                ->map(fn (ApprovalRequestType $t): array => ['id' => $t->id, 'code' => $t->code, 'name' => $t->name, 'module' => $t->module, 'default_priority' => $t->default_priority, 'due_days' => $t->due_days])->all(),
            'users' => User::query()->whereIn('id', $members)->where('status', 'ACTIVE')->orderBy('name')->get(['id', 'name', 'email'])
                ->map(fn (User $u): array => ['id' => $u->id, 'name' => $u->name, 'email' => $u->email])->all(),
            'workflows' => ApprovalWorkflow::query()->where('active', true)->orderBy('type_code')->orderBy('name')->get()
                ->map(fn (ApprovalWorkflow $w): array => ['id' => $w->id, 'label' => trim(($w->type_code ? $w->type_code.' — ' : '').$w->name)])->all(),
            'priorities' => ApprovalRequestType::PRIORITIES,
            'units' => ApprovalTicket::UNITS,
            'currencies' => ApprovalTicket::CURRENCIES,
        ]);
    }

    private function findVisible(string $approval): ApprovalRequest
    {
        $request = ApprovalRequest::query()->whereKey($approval)->firstOrFail();
        abort_unless($this->inbox->canSee($request, $this->person(), $this->viewAny()), 404);

        return $request;
    }

    /**
     * @return array<string, mixed>
     */
    private function actionRow(ApprovalAction $a): array
    {
        return [
            'id' => $a->id,
            'approval_request_id' => $a->approval_request_id,
            'at' => $a->acted_at?->toIso8601String(),
            'action' => $a->action,
            'by' => $a->approver_id ? User::query()->whereKey($a->approver_id)->value('name') : null,
            'previous_status' => $a->previous_status,
            'new_status' => $a->new_status,
            'level' => $a->level ?? $a->step,
            'comment' => $a->comment,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function ticketDetail(ApprovalTicket $t): array
    {
        return [
            'id' => $t->id, 'request_no' => $t->request_no, 'request_type_id' => $t->request_type_id, 'type' => $t->type?->name,
            'priority' => $t->priority, 'title' => $t->title, 'reference_type' => $t->reference_type, 'reference_no' => $t->reference_no,
            'buyer_name' => $t->buyer_name, 'order_no' => $t->order_no, 'style_no' => $t->style_no, 'po_no' => $t->po_no,
            'quantity' => $t->quantity !== null ? (float) $t->quantity : null, 'unit' => $t->unit,
            'amount' => $t->amount !== null ? (float) $t->amount : null, 'currency' => $t->currency,
            'request_to_user_id' => $t->request_to_user_id, 'request_to' => $t->requestTo?->name,
            'due_date' => $t->due_date?->format('Y-m-d'), 'remarks' => $t->remarks, 'status' => $t->status,
        ];
    }

    private function viewAny(): bool
    {
        return $this->caller()->can('approval.request.see_all');
    }

    /** A decision recorded against nobody cannot be asked about later. */
    private function person(): User
    {
        $user = $this->caller()->user;
        if ($user === null) {
            throw ApiException::of(ErrorCode::FORBIDDEN, __('api.step_needs_a_person'));
        }

        return $user;
    }
}
