@extends('layouts.app')
@section('title', __('maintenance.templates'))

@section('content')
    <x-page-header :title="__('maintenance.templates')" />

    <div class="card">
        <div class="card-body p-0">
            @if ($templates->isEmpty())
                <x-empty-state :title="__('maintenance.no_templates')"
                               :description="__('maintenance.no_templates_hint')" />
            @else
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead>
                            <tr>
                                <th>{{ __('maintenance.code') }}</th>
                                <th>{{ __('maintenance.name') }}</th>
                                <th>{{ __('maintenance.asset_type') }}</th>
                                <th>{{ __('maintenance.maintenance_type') }}</th>
                                <th>{{ __('maintenance.versions') }}</th>
                                <th></th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($templates as $template)
                                <tr>
                                    <td>
                                        <a href="{{ route('app.maintenance.templates.show', $template->id) }}"
                                           class="fw-semibold text-decoration-none">
                                            <code>{{ $template->code }}</code>
                                        </a>
                                    </td>
                                    <td>{{ $template->name }}</td>
                                    <td class="small">{{ $template->assetType?->name ?? '—' }}</td>
                                    <td class="small">{{ $template->maintenanceType?->name ?? '—' }}</td>
                                    <td class="small">{{ $template->versions_count }}</td>
                                    <td class="text-end">
                                        @unless ($template->isEditable())
                                            {{-- Platform templates are cloned, never edited, so the
                                                 seeded set stays identical for every tenant. --}}
                                            <x-status-pill status="SEEDED" tone="secondary">
                                                {{ __('maintenance.seeded') }}
                                            </x-status-pill>
                                        @endunless
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </div>

        @if ($templates->hasPages())
            <div class="card-footer">{{ $templates->links() }}</div>
        @endif
    </div>
@endsection
