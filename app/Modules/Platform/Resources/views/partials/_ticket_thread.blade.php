{{-- The messages in one ticket, oldest first — read top to bottom the way the
     conversation happened, rather than newest-first the way a feed does,
     because a reply only makes sense after the message it answers. --}}
<div class="ticket-thread">
    @foreach ($ticket->messages as $message)
        <div class="ticket-message {{ $message->author_is_platform ? 'is-platform' : '' }}">
            <div class="ticket-message-head">
                <span class="fw-semibold">{{ $message->author?->name ?? '—' }}</span>
                @if ($message->author_is_platform)
                    <span class="badge bg-secondary">{{ __('platform.staff') }}</span>
                @endif
                <span class="text-body-secondary ms-auto">{{ $message->created_at->diffForHumans() }}</span>
            </div>
            <div class="ticket-message-body">{{ $message->body }}</div>
        </div>
    @endforeach
</div>
