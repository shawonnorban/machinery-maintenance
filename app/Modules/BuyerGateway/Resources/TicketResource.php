<?php

declare(strict_types=1);

namespace App\Modules\BuyerGateway\Resources;

use App\Modules\BuyerGateway\Models\Ticket;
use App\Modules\BuyerGateway\Support\PortalBook;
use Carbon\CarbonImmutable;
use Closure;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

/**
 * Support Ticket Desk (AnnotechERP 25/04): portal problems a buyer reports
 * (order problems go to requests) — subject, buyer, priority, owner (or
 * unassigned) and state. The reference is unique; left blank it takes the
 * year's next number (TKT-2026-001). Hours open count from the day it was
 * raised until it is resolved or closed.
 */
class TicketResource extends BuyerGatewayResource
{
    protected function area(): string
    {
        return 'engagement';
    }

    public function key(): string
    {
        return 'bg-tickets';
    }

    public function model(): string
    {
        return Ticket::class;
    }

    public function columns(): array
    {
        return ['reference', 'subject', 'buyer_id', 'priority', 'owner_id', 'raised_on', 'state', 'resolved_at'];
    }

    public function defaults(): array
    {
        return ['priority' => 'NORMAL', 'state' => 'NEW'];
    }

    public function rules(?Model $row): array
    {
        $required = $row ? 'sometimes' : 'required';

        return [
            'reference' => ['nullable', 'string', 'max:40', function (string $attribute, mixed $v, Closure $fail) use ($row): void {
                if ($v && Ticket::query()->whereRaw('upper(reference) = ?', [Str::upper(trim((string) $v))])->when($row, fn ($q) => $q->whereKeyNot($row->getKey()))->exists()) {
                    $fail(__('buyergateway.v.ticket_taken'));
                }
            }],
            'subject' => [$required, 'string', 'max:200'],
            'buyer_id' => ['nullable', 'string', Rule::exists('buyers', 'id')->where('company_id', $this->company())],
            'priority' => ['sometimes', Rule::in(PortalBook::TICKET_PRIORITIES)],
            'owner_id' => ['nullable', 'string', Rule::exists('company_users', 'user_id')->where('company_id', $this->company())],
            'raised_on' => ['nullable', 'date_format:Y-m-d'],
            'state' => ['sometimes', Rule::in(PortalBook::TICKET_STATES)],
        ];
    }

    public function messages(): array
    {
        return ['subject.required' => __('buyergateway.v.subject')];
    }

    public function beforeSave(array $data, ?Model $row): array
    {
        if (array_key_exists('reference', $data) || ! $row) {
            $ref = Str::upper(trim((string) ($data['reference'] ?? '')));
            $data['reference'] = $ref !== '' ? $ref : ($row?->reference ?? $this->nextNumber('TKT', 'reference'));
        }
        if (isset($data['subject'])) {
            $data['subject'] = trim($data['subject']);
        }
        if (! $row) {
            $data['raised_on'] ??= CarbonImmutable::now('Asia/Dhaka')->toDateString();
        }
        if (isset($data['state'])) {
            $done = in_array($data['state'], ['RESOLVED', 'CLOSED'], true);
            $data['resolved_at'] = $done ? ($row?->resolved_at ?? now()) : null;
        }

        return $data;
    }

    /** Hours open: from the start of the day it was raised until resolved, or now. */
    public function present(Model $row): array
    {
        $out = parent::present($row);
        $out['resolved_at'] = $row->getAttribute('resolved_at')?->toIso8601String();
        $out['owner'] = PortalBook::staff()[$row->getAttribute('owner_id')] ?? null;
        if ($out['raised_on']) {
            $start = CarbonImmutable::parse($out['raised_on'], 'Asia/Dhaka')->startOfDay();
            $end = $row->getAttribute('resolved_at') ? CarbonImmutable::parse($row->getAttribute('resolved_at')) : CarbonImmutable::now();
            $out['hours'] = max(0, (int) floor($start->diffInMinutes($end, false) / 60));
        } else {
            $out['hours'] = null;
        }

        return $out;
    }
}
