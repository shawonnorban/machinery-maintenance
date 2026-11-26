<?php

declare(strict_types=1);

namespace App\Modules\Approval\Services;

use App\Modules\Approval\Actions\DecideApproval;
use App\Modules\Approval\Contracts\Approvable;
use App\Modules\Approval\Models\ApprovalAction;
use App\Modules\Approval\Models\ApprovalRequest;
use App\Modules\Approval\Models\ApprovalRule;
use App\Modules\Approval\Models\ApprovalRuleUser;
use App\Modules\Approval\Models\ApprovalTicket;
use App\Modules\Approval\Support\ApprovalEntities;
use App\Modules\Identity\Models\Role;
use App\Modules\Identity\Models\Team;
use App\Modules\Identity\Models\User;
use App\Modules\WorkOrder\Models\WorkOrder;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

/**
 * Every approval a person may see, from every module, as the Approval Center's
 * rows (AnnotechERP 04/01–04/02).
 *
 * Who sees what: holders of approval.request.see_all see every request; anyone
 * else sees the ones they raised, the ones addressed to them (Request To), the
 * ones waiting on them, and the ones they have acted on. A hand-written request appears once, as its current round:
 * a returned request that was sent again is shown by its new round.
 */
class ApprovalInbox
{
    public const SCOPES = ['all', 'pending', 'mine', 'assigned', 'approved', 'rejected', 'returned'];

    private const LIMIT = 500;

    /** @var array<string, string> */
    private array $names = [];

    public function __construct(private readonly DecideApproval $decisions) {}

    /**
     * @param  array<string, mixed>  $filters  q, type, status, priority, from, to
     * @return array{rows: list<array<string, mixed>>, stats: array<string, int>}
     */
    public function list(User $user, bool $viewAny, string $scope, array $filters = []): array
    {
        $requests = $this->visible($user, $viewAny);
        $tickets = ApprovalTicket::query()->with('type')->whereIn('id', $requests->where('entity_type', 'MANUAL_REQUEST')->pluck('entity_id'))->get()->keyBy('id');

        $rows = $requests
            // A ticket is shown by its current round only.
            ->filter(fn (ApprovalRequest $r): bool => $r->entity_type !== 'MANUAL_REQUEST' || $tickets->get($r->entity_id)?->approval_request_id === $r->id)
            ->map(fn (ApprovalRequest $r): array => $this->row($r, $tickets->get($r->entity_id), $user))
            ->values();

        // Drafts have no request yet; they belong to their author alone.
        if (in_array($scope, ['all', 'mine'], true)) {
            $drafts = ApprovalTicket::query()->with('type')->where('status', 'DRAFT')->where('requested_by', $user->id)->get();
            $rows = $rows->concat($drafts->map(fn (ApprovalTicket $t): array => $this->draftRow($t)));
        }

        $rows = $rows->filter(fn (array $row): bool => $this->inScope($row, $scope, $user))
            ->filter(fn (array $row): bool => $this->matches($row, $filters))
            ->sortByDesc('date')
            ->take(self::LIMIT)
            ->values();

        return [
            'rows' => $rows->all(),
            'stats' => [
                'in_list' => $rows->count(),
                'pending' => $rows->where('status', 'PENDING')->count(),
                'overdue' => $rows->where('overdue', true)->count(),
                'urgent_open' => $rows->where('status', 'PENDING')->where('priority', 'URGENT')->count(),
            ],
        ];
    }

    /**
     * One request as a row, for its detail panel.
     *
     * @return array<string, mixed>
     */
    public function rowFor(ApprovalRequest $request, User $user): array
    {
        $ticket = $request->entity_type === 'MANUAL_REQUEST' ? ApprovalTicket::query()->with('type')->find($request->entity_id) : null;

        return $this->row($request, $ticket, $user);
    }

    /** Whether this person may see this request at all. */
    public function canSee(ApprovalRequest $request, User $user, bool $viewAny): bool
    {
        return $viewAny
            || $request->requested_by === $user->id
            || $request->request_to_user_id === $user->id
            || ApprovalAction::query()->where('approval_request_id', $request->id)->where('approver_id', $user->id)->exists()
            || $this->decisions->canAct($request, $user);
    }

    /**
     * The request's levels in order, each with who it lands on and what became of it.
     *
     * @return list<array<string, mixed>>
     */
    public function levels(ApprovalRequest $request): array
    {
        $actions = $request->actions()->whereIn('action', ['APPROVED', 'REJECTED', 'RETURNED'])->get()->keyBy('step');

        return $request->applicableRules()->values()->map(function (ApprovalRule $rule, int $i) use ($request, $actions): array {
            $step = $i + 1;
            $done = $actions->get($step);

            return [
                'level' => $step,
                'name' => $rule->name ?: ($rule->exists ? null : 'Request To'),
                'approver' => $this->approverLabel($rule),
                'state' => $done?->action ?? ($request->isPending() && $request->current_step === $step ? 'CURRENT' : ($step < $request->current_step ? 'APPROVED' : 'WAITING')),
                'by' => $done ? $this->nameOf($done->approver_id) : null,
                'at' => $done?->acted_at?->toIso8601String(),
                'comment' => $done?->comment,
            ];
        })->all();
    }

    /** Who a step lands on, in words: its named people, the user, the role or the team. */
    public function approverLabel(?ApprovalRule $rule): ?string
    {
        if ($rule === null) {
            return null;
        }
        if ($rule->exists) {
            $assigned = ApprovalRuleUser::query()->where('rule_id', $rule->id)->pluck('user_id');
            if ($assigned->isNotEmpty()) {
                return $assigned->map(fn (string $id): string => $this->nameOf($id) ?? '—')->implode(', ');
            }
        }
        if ($rule->user_id !== null) {
            return $this->nameOf($rule->user_id);
        }

        if ($rule->role_id !== null) {
            $role = Role::query()->whereKey($rule->role_id)->first(['name', 'description']);

            return $role?->description ?: $role?->name;
        }

        return $rule->team_id !== null ? Team::query()->whereKey($rule->team_id)->value('name') : null;
    }

    /**
     * @return Collection<int, ApprovalRequest>
     */
    private function visible(User $user, bool $viewAny): Collection
    {
        $query = ApprovalRequest::query()->with('workflow')->latest('requested_at')->limit(2000);
        if ($viewAny) {
            return $query->get();
        }

        $acted = ApprovalAction::query()->where('approver_id', $user->id)->pluck('approval_request_id');
        // Raised by them, addressed to them, or acted on by them.
        $mine = (clone $query)->where(fn ($q) => $q->where('requested_by', $user->id)->orWhere('request_to_user_id', $user->id)->orWhereIn('id', $acted))->get();
        $waiting = ApprovalRequest::query()->where('status', 'PENDING')->get()->filter(fn (ApprovalRequest $r): bool => $this->decisions->canAct($r, $user));

        return $mine->concat($waiting)->unique('id')->values();
    }

    /**
     * @return array<string, mixed>
     */
    private function row(ApprovalRequest $request, ?ApprovalTicket $ticket, User $user): array
    {
        $context = $request->context_json ?? [];
        $current = $request->isPending() ? $request->currentRule() : null;
        $last = $request->isPending() ? null : $request->actions()->whereIn('action', ['APPROVED', 'REJECTED', 'RETURNED', 'CANCELLED', 'EXPIRED'])->reorder()->orderByDesc('acted_at')->orderByDesc('id')->first();
        [$number, $title] = $this->describe($request, $ticket);
        $due = $request->due_at ? CarbonImmutable::instance($request->due_at) : null;

        return [
            'id' => $request->id,
            'kind' => $ticket ? 'TICKET' : 'DOCUMENT',
            'ticket_id' => $ticket?->id,
            'request_no' => $number,
            'title' => $title,
            'entity_type' => $request->entity_type,
            'type' => $ticket?->type?->name,
            'type_key' => $ticket ? $ticket->request_type_id : $request->entity_type,
            'module' => $ticket?->type?->module,
            'department' => $this->departmentOf($request->requested_by),
            'requested_by' => $this->nameOf($request->requested_by),
            'requested_by_id' => $request->requested_by,
            'buyer' => $ticket?->buyer_name,
            'order_no' => $ticket?->order_no,
            'style_no' => $ticket?->style_no,
            'po_no' => $ticket?->po_no,
            'reference_no' => $ticket?->reference_no,
            'quantity' => $ticket?->quantity !== null ? (float) $ticket->quantity : null,
            'unit' => $ticket?->unit,
            'amount' => $ticket ? ($ticket->amount !== null ? (float) $ticket->amount : null) : (isset($context['cost']) ? (float) $context['cost'] : null),
            'currency' => $ticket?->currency ?? ($context['currency'] ?? null),
            'request_to' => $current ? $this->approverLabel($current) : null,
            'level' => $request->current_step,
            'levels' => $request->total_steps,
            'date' => $request->requested_at?->toIso8601String(),
            'due_at' => $due?->toIso8601String(),
            'overdue' => $request->isPending() && $due !== null && $due->isPast(),
            'priority' => $request->priority ?? $ticket?->priority,
            'status' => $request->status,
            'decided_by' => $last ? $this->nameOf($last->approver_id) : null,
            'decided_at' => $last?->acted_at?->toIso8601String(),
            'reason' => $last?->comment,
            'can_act' => $request->isPending() && $this->decisions->canAct($request, $user),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function draftRow(ApprovalTicket $ticket): array
    {
        return [
            'id' => 'ticket-'.$ticket->id, 'kind' => 'TICKET', 'ticket_id' => $ticket->id, 'request_no' => null, 'title' => $ticket->displayTitle(),
            'entity_type' => 'MANUAL_REQUEST', 'type' => $ticket->type?->name, 'type_key' => $ticket->request_type_id,
            'module' => $ticket->type?->module, 'department' => $this->departmentOf($ticket->requested_by),
            'requested_by' => $this->nameOf($ticket->requested_by), 'requested_by_id' => $ticket->requested_by,
            'buyer' => $ticket->buyer_name, 'order_no' => $ticket->order_no, 'style_no' => $ticket->style_no, 'po_no' => $ticket->po_no, 'reference_no' => $ticket->reference_no,
            'quantity' => $ticket->quantity !== null ? (float) $ticket->quantity : null, 'unit' => $ticket->unit,
            'amount' => $ticket->amount !== null ? (float) $ticket->amount : null, 'currency' => $ticket->currency,
            'request_to' => $this->nameOf($ticket->request_to_user_id), 'level' => null, 'levels' => null,
            'date' => $ticket->created_at?->toIso8601String(), 'due_at' => $ticket->due_date?->toIso8601String(), 'overdue' => false,
            'priority' => $ticket->priority, 'status' => 'DRAFT', 'decided_by' => null, 'decided_at' => null, 'reason' => null, 'can_act' => false,
        ];
    }

    /**
     * @return array{0: ?string, 1: ?string}
     */
    private function describe(ApprovalRequest $request, ?ApprovalTicket $ticket): array
    {
        if ($ticket) {
            return [$ticket->request_no, $ticket->displayTitle()];
        }
        $entity = ApprovalEntities::find($request->entity_type, $request->entity_id);
        if ($entity instanceof Approvable) {
            $summary = $entity->approvalSummary();

            return [$summary['number'], $summary['title']];
        }
        if ($entity instanceof WorkOrder) {
            return [$entity->work_order_number, $entity->title];
        }

        return [null, null];
    }

    /**
     * @param  array<string, mixed>  $row
     */
    private function inScope(array $row, string $scope, User $user): bool
    {
        return match ($scope) {
            'pending' => $row['status'] === 'PENDING',
            'mine' => $row['requested_by_id'] === $user->id,
            'assigned' => $row['status'] === 'PENDING' && $row['can_act'],
            'approved' => $row['status'] === 'APPROVED',
            'rejected' => $row['status'] === 'REJECTED',
            'returned' => $row['status'] === 'RETURNED',
            default => true,
        };
    }

    /**
     * @param  array<string, mixed>  $row
     * @param  array<string, mixed>  $filters
     */
    private function matches(array $row, array $filters): bool
    {
        if (! empty($filters['type']) && (string) $row['type_key'] !== (string) $filters['type']) {
            return false;
        }
        if (! empty($filters['status']) && $row['status'] !== $filters['status']) {
            return false;
        }
        if (! empty($filters['priority']) && $row['priority'] !== $filters['priority']) {
            return false;
        }
        $day = $row['date'] ? substr((string) $row['date'], 0, 10) : null;
        if (! empty($filters['from']) && ($day === null || $day < $filters['from'])) {
            return false;
        }
        if (! empty($filters['to']) && ($day === null || $day > $filters['to'])) {
            return false;
        }
        if (! empty($filters['q'])) {
            $needle = mb_strtolower((string) $filters['q']);
            $haystack = mb_strtolower(implode(' ', array_filter([$row['request_no'], $row['title'], $row['buyer'], $row['order_no'], $row['style_no'], $row['po_no'], $row['reference_no'], $row['requested_by']])));

            return str_contains($haystack, $needle);
        }

        return true;
    }

    /** @var array<string, ?string> */
    private array $departments = [];

    private function departmentOf(?string $userId): ?string
    {
        if ($userId === null) {
            return null;
        }
        if (! array_key_exists($userId, $this->departments)) {
            $this->departments[$userId] = User::query()->with('department')->find($userId)?->department?->name;
        }

        return $this->departments[$userId];
    }

    private function nameOf(?string $userId): ?string
    {
        if ($userId === null) {
            return null;
        }

        return $this->names[$userId] ??= (string) User::query()->whereKey($userId)->value('name');
    }
}
