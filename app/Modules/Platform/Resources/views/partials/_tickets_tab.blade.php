{{-- This customer's support tickets.

     Opened from their own side, under /app — platform staff read and answer
     here but do not raise a ticket on a customer's behalf. A ticket platform
     staff wrote on a customer's behalf would be a conversation with itself. --}}
<section class="panel panel-wide">
    <header class="panel-head">
        <i class="cil-envelope-letter" aria-hidden="true"></i>
        <span>{{ __('platform.support_ticket') }}</span>
        @if ($openTicketCount > 0)
            <span class="badge bg-danger ms-auto">{{ $openTicketCount }}</span>
        @endif
    </header>

    <div class="panel-list">
        @forelse ($tickets as $ticket)
            <a href="{{ route('platform.tickets.show', $ticket) }}"
               class="panel-list-item panel-list-item-link">
                <div class="min-w-0">
                    <div class="fw-semibold">{{ $ticket->subject }}</div>
                    <div class="tenant-code">
                        {{ __('platform.ticket_opened_by', ['name' => $ticket->opener?->name]) }}
                        · {{ $ticket->last_message_at?->diffForHumans() }}
                        @if ($ticket->assignee)
                            · {{ __('platform.ticket_assigned_to', ['name' => $ticket->assignee->name]) }}
                        @endif
                    </div>
                </div>

                <span class="badge ms-auto {{ match ($ticket->status) {
                    'OPEN' => 'bg-danger',
                    'IN_PROGRESS' => 'bg-warning text-dark',
                    'RESOLVED' => 'bg-success',
                    default => 'bg-secondary',
                } }}">
                    {{ __('platform.ticket_status_'.strtolower($ticket->status)) }}
                </span>
            </a>
        @empty
            <x-empty-state :title="__('platform.no_tickets')" :description="__('platform.no_tickets_hint')" />
        @endforelse
    </div>
</section>
