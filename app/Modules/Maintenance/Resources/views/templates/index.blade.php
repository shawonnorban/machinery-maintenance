@extends('layouts.app')
@section('title', __('maintenance.templates'))

@section('breadcrumb')
    <li class="breadcrumb-item"><a href="{{ route('app.dashboard') }}">{{ __('nav.dashboard') }}</a></li>
    <li class="breadcrumb-item">{{ __('nav.maintenance') }}</li>
    <li class="breadcrumb-item active" aria-current="page">{{ __('maintenance.templates') }}</li>
@endsection

@section('content')
    <form method="GET" action="{{ route('app.maintenance.templates') }}" id="list-filter"></form>

    <x-data-table :title="__('maintenance.templates')" icon="cil-task" :paginator="$templates">
        <thead>
            <tr>
                <th class="col-index">{{ __('common.row_number') }}</th>
                <th>{{ __('maintenance.code') }}</th>
                <th>{{ __('maintenance.name') }}</th>
                <th>{{ __('maintenance.asset_type') }}</th>
                <th>{{ __('maintenance.maintenance_type') }}</th>
                <th>{{ __('maintenance.versions') }}</th>
                <th>{{ __('maintenance.status') }}</th>
                <th>{{ __('common.actions') }}</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($templates as $index => $template)
                <tr>
                    <td class="col-index">{{ $templates->firstItem() + $index }}</td>
                    <td class="fw-semibold">{{ $template->code }}</td>
                    <td>{{ $template->name }}</td>
                    <td>{{ $template->assetType?->name ?? '—' }}</td>
                    <td>{{ $template->maintenanceType?->name ?? '—' }}</td>
                    <td>{{ $template->versions_count }}</td>
                    <td>
                        @if ($template->isEditable())
                            <x-status-pill status="ACTIVE" tone="success">
                                {{ __('maintenance.active') }}
                            </x-status-pill>
                        @else
                            {{-- Platform templates are cloned, never edited, so the
                                 seeded set stays identical for every tenant. --}}
                            <x-status-pill status="SEEDED" tone="secondary">
                                {{ __('maintenance.seeded') }}
                            </x-status-pill>
                        @endif
                    </td>
                    <td>
                        <a href="{{ route('app.maintenance.templates.show', $template->id) }}"
                           class="btn btn-sm btn-info text-white btn-icon"
                           title="{{ __('common.view') }}" aria-label="{{ __('common.view') }}">
                            <i class="cil-eye" aria-hidden="true"></i>
                        </a>
                    </td>
                </tr>
            @empty
                <tr><td colspan="8" class="p-0">
                    <x-empty-state :title="__('maintenance.no_templates')"
                                   :description="__('maintenance.no_templates_hint')" />
                </td></tr>
            @endforelse
        </tbody>
    </x-data-table>
@endsection
