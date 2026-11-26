<?php

declare(strict_types=1);

namespace App\Modules\Approval\Http\Controllers\Api;

use App\Modules\Approval\Actions\ManageApprovalTicket;
use App\Modules\Approval\Models\ApprovalRequestType;
use App\Modules\Approval\Models\ApprovalTicket;
use App\Modules\Identity\Models\User;
use App\Shared\Http\Api\ApiController;
use App\Shared\Http\Api\ApiException;
use App\Shared\Http\Api\ApiResponse;
use App\Shared\Http\Api\ErrorCode;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Approval Center → New Request (AnnotechERP 04/01): save as draft, submit,
 * edit a returned request and send it again, withdraw, or delete a draft.
 * A ticket is its author's alone until it is submitted.
 */
class ApprovalTicketApiController extends ApiController
{
    public function __construct(private readonly ManageApprovalTicket $tickets) {}

    public function show(string $ticket): JsonResponse
    {
        $this->allow('approval.ticket.create');

        return ApiResponse::ok($this->present($this->own($ticket)));
    }

    public function store(Request $request): JsonResponse
    {
        $this->allow('approval.ticket.create');

        $data = $request->validate($this->rules(false));
        $ticket = $this->tickets->save($this->fields($data), $this->person(), null, (bool) ($data['submit'] ?? false));

        return ApiResponse::created($this->present($ticket));
    }

    public function update(Request $request, string $ticket): JsonResponse
    {
        $this->allow('approval.ticket.create');

        $data = $request->validate($this->rules(true));
        $row = $this->tickets->save($this->fields($data), $this->person(), $this->own($ticket), (bool) ($data['submit'] ?? false));

        return ApiResponse::ok($this->present($row));
    }

    public function submit(string $ticket): JsonResponse
    {
        $this->allow('approval.ticket.create');

        return ApiResponse::ok($this->present($this->tickets->submit($this->own($ticket), $this->person())));
    }

    public function cancel(Request $request, string $ticket): JsonResponse
    {
        $this->allow('approval.ticket.create');

        $data = $request->validate(['comment' => ['nullable', 'string', 'max:2000']]);

        return ApiResponse::ok($this->present($this->tickets->cancel($this->own($ticket), $this->person(), $data['comment'] ?? null)));
    }

    public function destroy(string $ticket): JsonResponse
    {
        $this->allow('approval.ticket.create');

        $this->tickets->delete($this->own($ticket), $this->person());

        return ApiResponse::ok(['deleted' => true]);
    }

    /**
     * @return array<string, mixed>
     */
    private function rules(bool $editing): array
    {
        return [
            'request_type_id' => [$editing ? 'sometimes' : 'required', 'string'],
            'priority' => ['nullable', Rule::in(ApprovalRequestType::PRIORITIES)],
            'title' => ['nullable', 'string', 'max:255'],
            'reference_type' => ['nullable', 'string', 'max:255'],
            'reference_no' => ['nullable', 'string', 'max:255'],
            'buyer_name' => ['nullable', 'string', 'max:255'],
            'order_no' => ['nullable', 'string', 'max:255'],
            'style_no' => ['nullable', 'string', 'max:255'],
            'po_no' => ['nullable', 'string', 'max:255'],
            'quantity' => ['nullable', 'numeric', 'min:0', 'max:999999999999'],
            'unit' => ['nullable', Rule::in(ApprovalTicket::UNITS)],
            'amount' => ['nullable', 'numeric', 'min:0', 'max:9999999999999999'],
            'currency' => ['nullable', Rule::in(ApprovalTicket::CURRENCIES)],
            'request_to_user_id' => ['nullable', 'string'],
            'due_date' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:today'],
            'remarks' => ['nullable', 'string', 'max:4000'],
            'submit' => ['sometimes', 'boolean'],
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function fields(array $data): array
    {
        unset($data['submit']);

        return $data;
    }

    /** A ticket is shown and changed by its author only; anyone else gets nothing. */
    private function own(string $id): ApprovalTicket
    {
        $ticket = ApprovalTicket::query()->whereKey($id)->firstOrFail();
        abort_unless($ticket->requested_by === $this->person()->id, 404);

        return $ticket;
    }

    /**
     * @return array<string, mixed>
     */
    private function present(ApprovalTicket $t): array
    {
        $t->loadMissing(['type', 'requestTo']);

        return [
            'id' => $t->id, 'request_no' => $t->request_no, 'request_type_id' => $t->request_type_id, 'type' => $t->type?->name,
            'priority' => $t->priority, 'title' => $t->title, 'display_title' => $t->displayTitle(),
            'reference_type' => $t->reference_type, 'reference_no' => $t->reference_no,
            'buyer_name' => $t->buyer_name, 'order_no' => $t->order_no, 'style_no' => $t->style_no, 'po_no' => $t->po_no,
            'quantity' => $t->quantity !== null ? (float) $t->quantity : null, 'unit' => $t->unit,
            'amount' => $t->amount !== null ? (float) $t->amount : null, 'currency' => $t->currency,
            'request_to_user_id' => $t->request_to_user_id, 'request_to' => $t->requestTo?->name,
            'due_date' => $t->due_date?->format('Y-m-d'), 'remarks' => $t->remarks,
            'status' => $t->status, 'approval_request_id' => $t->approval_request_id, 'submitted_at' => $t->submitted_at?->toIso8601String(),
        ];
    }

    private function person(): User
    {
        $user = $this->caller()->user;
        if ($user === null) {
            throw ApiException::of(ErrorCode::FORBIDDEN, __('api.step_needs_a_person'));
        }

        return $user;
    }
}
