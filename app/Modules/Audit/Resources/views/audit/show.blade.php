@extends('layouts.app')
@section('title', __('audit.entry'))

@section('breadcrumb')
    <li class="breadcrumb-item"><a href="{{ route('app.dashboard') }}">{{ __('nav.dashboard') }}</a></li>
    <li class="breadcrumb-item"><a href="{{ route('app.audit-logs') }}">{{ __('audit.audit_log') }}</a></li>
    <li class="breadcrumb-item active" aria-current="page">{{ __('audit.actions.'.$log->action) }}</li>
@endsection

@section('content')
    <x-page-header :title="__('audit.actions.'.$log->action)" :subtitle="$log->entity_label">
        <x-slot:actions>
            <a href="{{ route('app.audit-logs') }}" class="btn btn-sm btn-outline-secondary">
                {{ __('audit.back_to_log') }}
            </a>
        </x-slot:actions>
    </x-page-header>

    <div class="row">
        <div class="col-lg-5">
            <div class="card mb-4">
                <div class="card-body">
                    <dl class="row mb-0">
                        <dt class="col-5">{{ __('audit.when') }}</dt>
                        <dd class="col-7">@dt($log->created_at)</dd>

                        <dt class="col-5">{{ __('audit.who') }}</dt>
                        <dd class="col-7">
                            {{ $log->user?->name ?? __('audit.system') }}
                            @if ($log->user?->email)
                                <div class="small text-body-secondary">{{ $log->user->email }}</div>
                            @endif
                        </dd>

                        @if ($log->impersonator)
                            {{-- Support acting as a tenant user. First thing an
                                 investigation asks, so it is not buried in the
                                 details block (SRS 5.4). --}}
                            <dt class="col-5 text-danger">{{ __('audit.impersonated_by') }}</dt>
                            <dd class="col-7 text-danger">{{ $log->impersonator->name }}</dd>
                        @endif

                        <dt class="col-5">{{ __('audit.entity_type') }}</dt>
                        <dd class="col-7">{{ $log->entity_type ?? '—' }}</dd>

                        <dt class="col-5">{{ __('audit.entity_id') }}</dt>
                        <dd class="col-7"><code class="small">{{ $log->entity_id ?? '—' }}</code></dd>

                        <dt class="col-5">{{ __('audit.context') }}</dt>
                        <dd class="col-7">{{ __('audit.contexts.'.$log->context) }}</dd>

                        <dt class="col-5">{{ __('audit.ip_address') }}</dt>
                        <dd class="col-7">{{ $log->ip_address ?? '—' }}</dd>

                        <dt class="col-5">{{ __('audit.request_id') }}</dt>
                        <dd class="col-7"><code class="small">{{ $log->request_id ?? '—' }}</code></dd>

                        <dt class="col-5">{{ __('audit.user_agent') }}</dt>
                        <dd class="col-7 small text-body-secondary">{{ $log->user_agent ?? '—' }}</dd>
                    </dl>
                </div>
            </div>
        </div>

        <div class="col-lg-7">
            <div class="card mb-4">
                <div class="card-header">
                    <i class="cil-transfer" aria-hidden="true"></i>
                    <span>{{ __('audit.changes') }}</span>
                </div>

                @php $diff = $log->diff(); @endphp

                @if ($diff !== [])
                    <div class="table-responsive">
                        <table class="table table-sm align-middle mb-0">
                            <thead>
                                <tr>
                                    <th>{{ __('audit.field') }}</th>
                                    <th>{{ __('audit.before') }}</th>
                                    <th>{{ __('audit.after') }}</th>
                                </tr>
                            </thead>

                            <tbody>
                                @foreach ($diff as $field => [$before, $after])
                                    <tr>
                                        <td><code>{{ $field }}</code></td>
                                        <td class="text-body-secondary">
                                            {{ is_array($before) ? json_encode($before) : ($before ?? '—') }}
                                        </td>
                                        <td>{{ is_array($after) ? json_encode($after) : ($after ?? '—') }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @elseif ($log->new_values_json)
                    <div class="table-responsive">
                        <table class="table table-sm align-middle mb-0">
                            <tbody>
                                @foreach ($log->new_values_json as $field => $value)
                                    <tr>
                                        <td><code>{{ $field }}</code></td>
                                        <td>{{ is_array($value) ? json_encode($value) : ($value ?? '—') }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @else
                    <div class="card-body text-body-secondary">{{ __('audit.no_changes') }}</div>
                @endif
            </div>

            @if ($related->isNotEmpty())
                <div class="card mb-4">
                    <div class="card-header">
                        <i class="cil-link" aria-hidden="true"></i>
                        <span>{{ __('audit.related') }}</span>
                    </div>

                    <div class="table-responsive">
                        <table class="table table-sm table-hover align-middle mb-0">
                            <tbody>
                                @foreach ($related as $sibling)
                                    <tr>
                                        <td>
                                            <a href="{{ route('app.audit-logs.show', $sibling) }}">
                                                {{ __('audit.actions.'.$sibling->action) }}
                                            </a>
                                        </td>
                                        <td>{{ $sibling->entity_label ?? $sibling->entity_type }}</td>
                                        <td class="small text-body-secondary">@dt($sibling->created_at)</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>

                    <div class="card-footer small text-body-secondary">{{ __('audit.related_hint') }}</div>
                </div>
            @endif
        </div>
    </div>
@endsection
