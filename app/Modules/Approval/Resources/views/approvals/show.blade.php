@extends('layouts.app')
@section('title', __('approval.approval'))

@section('breadcrumb')
    <li class="breadcrumb-item"><a href="{{ route('app.dashboard') }}">{{ __('nav.dashboard') }}</a></li>
    <li class="breadcrumb-item"><a href="{{ route('app.approvals') }}">{{ __('approval.approvals') }}</a></li>
    <li class="breadcrumb-item active" aria-current="page">
        {{ $workOrder?->work_order_number ?? __('approval.approval') }}
    </li>
@endsection

@section('content')
    @php
        $tone = match ($request->status) {
            'PENDING' => 'warning',
            'APPROVED' => 'success',
            'REJECTED', 'EXPIRED' => 'danger',
            default => 'secondary',
        };
    @endphp

    <x-page-header :title="$workOrder?->work_order_number ?? __('approval.approval')"
                   :subtitle="$workOrder?->title">
        <x-slot:actions>
            <x-status-pill :status="$request->status" :tone="$tone">
                {{ __('approval.status_'.strtolower($request->status)) }}
            </x-status-pill>
        </x-slot:actions>
    </x-page-header>

    <div class="row">
        <div class="col-lg-7">
            <div class="card mb-3">
                <div class="card-header">
                    <i class="cil-lock-locked" aria-hidden="true"></i>
                    <span>{{ __('approval.context') }}</span>
                </div>
                <div class="card-body">
                    <dl class="row mb-0">
                        <dt class="col-sm-4">{{ __('approval.cost') }}</dt>
                        <dd class="col-sm-8">
                            <strong>{{ number_format((float) ($request->context_json['cost'] ?? 0), 2) }}</strong>
                            {{ $request->context_json['currency'] ?? '' }}
                        </dd>

                        <dt class="col-sm-4">{{ __('approval.criticality') }}</dt>
                        <dd class="col-sm-8">{{ $request->context_json['criticality'] ?? '—' }}</dd>

                        <dt class="col-sm-4">{{ __('approval.priority') }}</dt>
                        <dd class="col-sm-8">{{ $request->context_json['priority'] ?? '—' }}</dd>

                        <dt class="col-sm-4">{{ __('approval.requested_at') }}</dt>
                        <dd class="col-sm-8">@dt($request->requested_at)</dd>
                    </dl>

                    {{-- Frozen when the request was raised. Without this, an
                         estimate edited after approval makes "what did they
                         actually agree to" unanswerable (ERD Section 20). --}}
                    <div class="form-text mt-3">{{ __('approval.context_hint') }}</div>
                </div>
            </div>

            @if ($canAct)
                <div class="card mb-3">
                    <div class="card-header">
                        <i class="cil-check" aria-hidden="true"></i>
                        <span>{{ __('approval.step') }} {{ $request->current_step }}
                            {{ __('approval.of_steps', ['total' => $request->total_steps]) }}</span>
                    </div>
                    <div class="card-body">
                        <form method="POST" action="{{ route('app.approvals.approve', $request) }}" class="mb-3">
                            @csrf
                            <label for="approve_comment" class="form-label">{{ __('approval.comment') }}</label>
                            <textarea id="approve_comment" name="comment" class="form-control mb-2"
                                      rows="2" maxlength="2000"></textarea>
                            <button class="btn btn-success">{{ __('approval.approve') }}</button>
                        </form>

                        <form method="POST" action="{{ route('app.approvals.reject', $request) }}" class="border-top pt-3">
                            @csrf
                            <label for="reject_comment" class="form-label">{{ __('approval.comment') }}</label>
                            <textarea id="reject_comment" name="comment" class="form-control mb-2"
                                      rows="2" maxlength="2000" required></textarea>
                            {{-- Required: a refusal with no reason gives the
                                 requester nothing to act on, and the job is
                                 simply resubmitted unchanged. --}}
                            <div class="form-text mb-2">{{ __('approval.rejection_needs_reason') }}</div>
                            <button class="btn btn-outline-danger">{{ __('approval.reject') }}</button>
                        </form>
                    </div>
                </div>
            @elseif ($request->isPending())
                <div class="alert alert-secondary">
                    @if ($request->requested_by === auth()->id())
                        {{ __('approval.cannot_approve_own_request') }}
                    @else
                        {{ __('approval.not_your_step') }}
                    @endif
                </div>
            @endif
        </div>

        <div class="col-lg-5">
            <div class="card mb-3">
                <div class="card-header">
                    <i class="cil-list-numbered" aria-hidden="true"></i>
                    <span>{{ __('approval.workflow') }}</span>
                </div>
                <div class="list-group list-group-flush">
                    @foreach ($rules as $index => $rule)
                        @php $step = $index + 1; @endphp

                        <div class="list-group-item d-flex align-items-center gap-2">
                            <span class="{{ $step < $request->current_step ? 'text-success' : '' }}">
                                {{ $step }}. {{ $rule->name ?? $rule->role?->name ?? '—' }}
                            </span>

                            @if ($step < $request->current_step)
                                <span class="ms-auto small text-success">{{ __('approval.action_approved') }}</span>
                            @elseif ($step === $request->current_step && $request->isPending())
                                <span class="ms-auto small text-warning">{{ __('approval.awaiting_signature') }}</span>
                            @endif
                        </div>
                    @endforeach
                </div>
            </div>

            <div class="card mb-3">
                <div class="card-header">
                    <i class="cil-history" aria-hidden="true"></i>
                    <span>{{ __('approval.history') }}</span>
                </div>
                <div class="list-group list-group-flush">
                    @forelse ($request->actions as $action)
                        <div class="list-group-item">
                            <div class="d-flex align-items-center gap-2">
                                <span>{{ __('approval.action_'.strtolower($action->action)) }}</span>
                                <span class="text-body-secondary small">
                                    {{ __('approval.step') }} {{ $action->step }}
                                </span>
                                <span class="ms-auto text-body-secondary small">@dt($action->acted_at)</span>
                            </div>
                            @if ($action->comment)
                                <div class="small text-body-secondary mt-1">{{ $action->comment }}</div>
                            @endif
                        </div>
                    @empty
                        <div class="list-group-item text-body-secondary">{{ __('approval.no_requests') }}</div>
                    @endforelse
                </div>
            </div>
        </div>
    </div>
@endsection
