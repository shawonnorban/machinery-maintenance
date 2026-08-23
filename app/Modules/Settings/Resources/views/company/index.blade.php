@extends('layouts.app')
@section('title', __('settings.company_settings'))

@section('breadcrumb')
    <li class="breadcrumb-item"><a href="{{ route('app.dashboard') }}">{{ __('nav.dashboard') }}</a></li>
    <li class="breadcrumb-item active" aria-current="page">{{ __('settings.company_settings') }}</li>
@endsection

@section('content')
    <x-page-header :title="__('settings.company_settings')" :subtitle="$company->name" />

    {{-- Several of these change what a number means rather than how a screen
         looks, which is why every change is audited. --}}
    <div class="alert alert-secondary">{{ __('settings.intro') }}</div>

    @if ($factories->isNotEmpty())
        <div class="card mb-4">
            <div class="card-body">
                <form method="GET" class="row g-2 align-items-end">
                    <div class="col-sm-6">
                        <label for="factory_id" class="form-label mb-1">{{ __('settings.answering_for') }}</label>
                        <select id="factory_id" name="factory_id" class="form-select" onchange="this.form.submit()">
                            <option value="">{{ __('settings.whole_company') }}</option>
                            @foreach ($factories as $option)
                                <option value="{{ $option->id }}" @selected($factory?->id === $option->id)>
                                    {{ $option->name }}
                                </option>
                            @endforeach
                        </select>
                        <div class="form-text">{{ __('settings.scope_hint') }}</div>
                    </div>
                </form>
            </div>
        </div>
    @endif

    @foreach ($groups as $group => $definitions)
        <div class="card mb-4">
            <div class="card-header">{{ __('settings.groups.'.$group) }}</div>

            <div class="table-responsive">
                <table class="table align-middle mb-0">
                    <tbody>
                        @foreach ($definitions as $definition)
                            @php($row = $rows[$definition->key])

                            <tr>
                                <td style="width: 45%">
                                    <div class="fw-semibold">{{ $definition->name }}</div>
                                    <div class="small text-body-secondary">{{ $definition->description }}</div>
                                    <code class="small">{{ $definition->key }}</code>
                                </td>

                                <td style="width: 15%">
                                    {{-- Where this answer comes from. A factory that
                                         has not answered inherits, which is different
                                         from one that answered the same way: the first
                                         follows the company when the company changes
                                         its mind. --}}
                                    @if ($row['source'] === 'FACTORY')
                                        <span class="badge bg-info text-white">{{ __('settings.source_factory') }}</span>
                                    @elseif ($row['source'] === 'COMPANY')
                                        <span class="badge bg-secondary">{{ __('settings.source_company') }}</span>
                                    @else
                                        <span class="badge bg-light text-dark">{{ __('settings.source_platform') }}</span>
                                    @endif
                                </td>

                                <td>
                                    @if (! $row['editable_here'])
                                        <span class="text-body-secondary small">
                                            {{ __('settings.not_at_this_level', [
                                                'levels' => implode(', ', $definition->scope_levels),
                                            ]) }}
                                        </span>
                                    @else
                                        <form method="POST" action="{{ route('app.settings.company.update') }}"
                                              class="d-flex gap-2 align-items-center flex-wrap">
                                            @csrf
                                            <input type="hidden" name="key" value="{{ $definition->key }}">
                                            <input type="hidden" name="factory_id" value="{{ $factory?->id }}">

                                            @if ($definition->value_type === 'BOOL')
                                                <div class="form-check form-switch">
                                                    <input class="form-check-input" type="checkbox" name="value"
                                                           value="1" id="s-{{ $loop->parent->index }}-{{ $loop->index }}"
                                                           @checked((bool) $row['effective'])>
                                                    <label class="form-check-label small"
                                                           for="s-{{ $loop->parent->index }}-{{ $loop->index }}">
                                                        {{ (bool) $row['effective'] ? __('common.yes') : __('common.no') }}
                                                    </label>
                                                </div>
                                            @elseif ($definition->value_type === 'ENUM')
                                                <select name="value" class="form-select form-select-sm w-auto">
                                                    @foreach ($definition->allowed_values ?? [] as $allowed)
                                                        <option value="{{ $allowed }}"
                                                                @selected((string) $row['effective'] === (string) $allowed)>
                                                            {{ $allowed }}
                                                        </option>
                                                    @endforeach
                                                </select>
                                            @elseif ($definition->value_type === 'LIST')
                                                <input name="value" type="text" class="form-control form-control-sm"
                                                       value="{{ implode(', ', (array) $row['effective']) }}">
                                            @else
                                                <input name="value" type="{{ $definition->value_type === 'INT' ? 'number' : 'text' }}"
                                                       class="form-control form-control-sm w-auto"
                                                       value="{{ is_array($row['effective']) ? implode(', ', $row['effective']) : $row['effective'] }}">
                                            @endif

                                            <button class="btn btn-sm btn-info text-white">{{ __('common.save') }}</button>
                                        </form>

                                        @if ($row['has_own_answer'])
                                            <form method="POST" action="{{ route('app.settings.company.reset') }}"
                                                  class="mt-1">
                                                @csrf
                                                @method('DELETE')
                                                <input type="hidden" name="key" value="{{ $definition->key }}">
                                                <input type="hidden" name="factory_id" value="{{ $factory?->id }}">
                                                <button class="btn btn-sm btn-link p-0">
                                                    {{ __('settings.follow_company_again') }}
                                                </button>
                                            </form>
                                        @endif
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    @endforeach
@endsection
