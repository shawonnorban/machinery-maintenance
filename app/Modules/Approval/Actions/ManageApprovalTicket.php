<?php

declare(strict_types=1);

namespace App\Modules\Approval\Actions;

use App\Modules\Approval\Models\ApprovalRequestType;
use App\Modules\Approval\Models\ApprovalTicket;
use App\Modules\Identity\Models\CompanyUser;
use App\Modules\Identity\Models\User;
use App\Modules\Settings\Services\NumberSequenceGenerator;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Approval Center → New Request (AnnotechERP 04/01): a draft is saved as it
 * stands; submitting checks it, numbers it and sends it to Request To, whose
 * approval opens the type's chain. A returned request is edited and submitted
 * again as a new round.
 */
class ManageApprovalTicket
{
    public function __construct(
        private readonly RequestApproval $approvals,
        private readonly DecideApproval $decisions,
        private readonly NumberSequenceGenerator $numbers,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function save(array $data, User $author, ?ApprovalTicket $ticket = null, bool $submit = false): ApprovalTicket
    {
        if ($ticket !== null && ! in_array($ticket->status, ['DRAFT', 'RETURNED'], true)) {
            throw ValidationException::withMessages(['status' => __('approval.center.not_editable')]);
        }

        $type = ApprovalRequestType::query()->whereKey($data['request_type_id'] ?? $ticket?->request_type_id)->first();
        if ($type === null || (! $type->active && $ticket?->request_type_id !== $type->id)) {
            throw ValidationException::withMessages(['request_type_id' => __('approval.center.type_required')]);
        }

        $ticket ??= new ApprovalTicket(['status' => 'DRAFT', 'requested_by' => $author->id]);
        $ticket->fill(array_merge($data, ['request_type_id' => $type->id]));
        $ticket->priority ??= $type->default_priority;
        $ticket->save();

        return $submit ? $this->submit($ticket, $author) : $ticket->refresh();
    }

    public function submit(ApprovalTicket $ticket, User $author): ApprovalTicket
    {
        if ($ticket->requested_by !== $author->id) {
            abort(403);
        }
        if (! in_array($ticket->status, ['DRAFT', 'RETURNED'], true)) {
            throw ValidationException::withMessages(['status' => __('approval.center.not_editable')]);
        }
        $this->assertComplete($ticket, $author);

        return DB::transaction(function () use ($ticket, $author): ApprovalTicket {
            $again = $ticket->status === 'RETURNED';
            $type = $ticket->type()->firstOrFail();
            $ticket->forceFill([
                'request_no' => $ticket->request_no ?: $this->numbers->next('APPROVAL_REQUEST'),
                'due_date' => $ticket->due_date ?? ($type->due_days > 0 ? CarbonImmutable::today()->addDays($type->due_days) : null),
                'status' => 'PENDING',
                'submitted_at' => CarbonImmutable::now(),
            ])->save();

            $this->approvals->forTicket($ticket, $author->id, $again);

            return $ticket->refresh();
        });
    }

    /** Withdrawn by its author while it waits or after it came back. */
    public function cancel(ApprovalTicket $ticket, User $author, ?string $comment = null): ApprovalTicket
    {
        if ($ticket->requested_by !== $author->id) {
            abort(403);
        }
        if ($ticket->status === 'PENDING' && $ticket->approval_request_id) {
            $request = $ticket->approvalRequest();
            if ($request !== null && $request->isPending()) {
                $this->decisions->cancel($request, $author->id, $comment);

                return $ticket->refresh();
            }
        }
        if (! in_array($ticket->status, ['RETURNED', 'PENDING'], true)) {
            throw ValidationException::withMessages(['status' => __('approval.center.not_cancellable')]);
        }
        $ticket->forceFill(['status' => 'CANCELLED'])->save();

        return $ticket->refresh();
    }

    /** Only a draft, and only its author's: anything submitted is history. */
    public function delete(ApprovalTicket $ticket, User $author): void
    {
        if ($ticket->requested_by !== $author->id || $ticket->status !== 'DRAFT') {
            throw ValidationException::withMessages(['status' => __('approval.center.only_drafts')]);
        }
        $ticket->delete();
    }

    private function assertComplete(ApprovalTicket $ticket, User $author): void
    {
        $errors = [];
        if (blank($ticket->reference_no)) {
            $errors['reference_no'] = __('approval.center.reference_required');
        }
        if (! in_array($ticket->priority, ApprovalRequestType::PRIORITIES, true)) {
            $errors['priority'] = __('approval.center.priority_required');
        }
        if ($ticket->request_to_user_id === null) {
            $errors['request_to_user_id'] = __('approval.center.request_to_required');
        } elseif ($ticket->request_to_user_id === $author->id) {
            $errors['request_to_user_id'] = __('approval.center.not_yourself');
        } elseif (! CompanyUser::query()->where('company_id', $ticket->company_id)->where('user_id', $ticket->request_to_user_id)->where('status', 'ACTIVE')->exists()) {
            $errors['request_to_user_id'] = __('approval.center.request_to_member');
        }
        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }
    }
}
