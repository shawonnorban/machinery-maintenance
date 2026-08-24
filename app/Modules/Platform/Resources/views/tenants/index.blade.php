@extends('platform::layout')
@section('title', __('platform.tenants'))

@section('content')
    <div class="d-flex flex-wrap align-items-center gap-3 mb-4">
        <div>
            <h1 class="h4 mb-1">{{ __('platform.tenants') }}</h1>
            <div class="text-body-secondary small">{{ __('platform.tenants_intro') }}</div>
        </div>

        <a href="{{ route('platform.tenants.create') }}" class="btn btn-primary ms-auto">
            {{ __('platform.new_tenant') }}
        </a>
    </div>

    <div class="card">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead>
                    <tr>
                        <th>{{ __('platform.company') }}</th>
                        <th>{{ __('platform.contract') }}</th>
                        <th class="text-end">{{ __('platform.factories') }}</th>
                        <th class="text-end">{{ __('platform.assets') }}</th>
                        <th class="text-end">{{ __('platform.users') }}</th>
                        <th>{{ __('platform.status') }}</th>
                    </tr>
                </thead>

                <tbody>
                    @forelse ($tenants as $row)
                        @php($company = $row['company'])
                        <tr>
                            <td>
                                <a href="{{ route('platform.tenants.show', $company) }}" class="fw-semibold">
                                    {{ $company->name }}
                                </a>
                                <div class="small text-body-secondary">{{ $company->code }}</div>

                                @if (($openGrants[$company->id] ?? collect())->isNotEmpty())
                                    {{-- Shown on the list, not only inside the
                                         tenant: "who is inside a customer right
                                         now" is a question that should be
                                         answerable at a glance. --}}
                                    <div class="small text-danger">
                                        {{ __('platform.support_open_by', [
                                            'name' => $openGrants[$company->id]->first()->holder?->name,
                                        ]) }}
                                    </div>
                                @endif
                            </td>

                            <td>
                                @if ($row['contract'])
                                    <div>{{ $row['contract']->contract_number }}</div>
                                    <div class="small text-body-secondary">
                                        {{ $row['contract']->amount }} {{ $row['contract']->currency }}
                                        · {{ __('platform.cycle_'.strtolower($row['contract']->billing_cycle)) }}
                                    </div>
                                @else
                                    {{-- A customer with no contract is a customer
                                         nobody will invoice. Worth seeing from
                                         the list. --}}
                                    <span class="badge bg-warning text-dark">{{ __('platform.no_contract') }}</span>
                                @endif
                            </td>

                            <td class="text-end">{{ $row['factories'] }}</td>
                            <td class="text-end">{{ $row['assets'] }}</td>
                            <td class="text-end">{{ $row['users'] }}</td>

                            <td>
                                <x-status-pill :status="$company->status"
                                               :tone="$company->status === 'ACTIVE' ? 'success' : 'danger'">
                                    {{ __('platform.company_'.strtolower($company->status)) }}
                                </x-status-pill>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="p-0">
                                <x-empty-state :title="__('platform.no_tenants')"
                                               :description="__('platform.no_tenants_hint')" />
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <p class="text-body-secondary small mt-3 mb-0">{{ __('platform.no_data_access_note') }}</p>
@endsection
