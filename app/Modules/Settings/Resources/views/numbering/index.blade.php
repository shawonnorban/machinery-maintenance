@extends('layouts.app')
@section('title', __('numbering.numbering'))

@section('breadcrumb')
    <li class="breadcrumb-item"><a href="{{ route('app.dashboard') }}">{{ __('nav.dashboard') }}</a></li>
    <li class="breadcrumb-item active" aria-current="page">{{ __('numbering.numbering') }}</li>
@endsection

@section('content')
    <x-page-header :title="__('numbering.numbering')" :subtitle="__('numbering.intro')" />

    {{-- Said before the first field, because it is the thing that surprises
         people: an edit made today renumbers nothing, and does not take effect
         until the next month or year. --}}
    <div class="alert alert-info">{{ __('numbering.next_period_notice') }}</div>

    @error('format')<div class="alert alert-danger">{{ $message }}</div>@enderror
    @error('padding')<div class="alert alert-danger">{{ $message }}</div>@enderror

    {{-- The forms live outside the table and the fields point at them by id.
         A <form> cannot legally wrap a row's cells, and a browser that hoists
         it out silently detaches every field in the row. --}}
    @foreach ($rows as $row)
        <form id="numbering-{{ $row['document_type'] }}" method="POST"
              action="{{ route('app.settings.numbering.update', $row['document_type']) }}" hidden>
            @csrf
            @method('PATCH')
        </form>

        @if (in_array($row['document_type'], $overrides, true))
            <form id="numbering-reset-{{ $row['document_type'] }}" method="POST"
                  action="{{ route('app.settings.numbering.reset', $row['document_type']) }}" hidden
                  onsubmit="return confirm('{{ __('numbering.reset_confirm', ['format' => $row['default_format']]) }}')">
                @csrf
                @method('DELETE')
            </form>
        @endif
    @endforeach

    <div class="card">
        <div class="card-header">
            <i class="cil-list-numbered" aria-hidden="true"></i>
            <span>{{ __('numbering.document_types') }}</span>
        </div>

        <div class="table-responsive">
            <table class="table align-middle mb-0">
                <thead>
                    <tr>
                        <th>{{ __('numbering.document_type') }}</th>
                        <th>{{ __('numbering.format') }}</th>
                        <th class="text-center">{{ __('numbering.padding') }}</th>
                        <th>{{ __('numbering.next_number') }}</th>
                        <th class="text-end">{{ __('common.actions') }}</th>
                    </tr>
                </thead>

                <tbody>
                    @foreach ($rows as $row)
                        @php($form = 'numbering-'.$row['document_type'])
                        <tr>
                            <td>
                                <div class="fw-semibold">{{ __('numbering.types.'.$row['document_type']) }}</div>
                                <div class="small text-body-secondary">
                                    {{ __('numbering.resets_'.strtolower($row['reset'])) }}
                                    @unless ($row['is_default'])
                                        &middot; {{ __('numbering.customised') }}
                                    @endunless
                                </div>
                                @if ($row['issued'] > 0)
                                    {{-- How many documents already carry the old shape.
                                         A format is impossible to un-change for numbers
                                         already issued. --}}
                                    <div class="small text-body-secondary">
                                        {{ trans_choice('numbering.already_issued', $row['issued'], ['count' => $row['issued']]) }}
                                    </div>
                                @endif
                            </td>

                            <td style="min-width: 16rem">
                                <label class="visually-hidden" for="format-{{ $row['document_type'] }}">
                                    {{ __('numbering.format') }}
                                </label>
                                <input id="format-{{ $row['document_type'] }}" form="{{ $form }}"
                                       name="format" type="text"
                                       class="form-control form-control-sm font-monospace"
                                       value="{{ $row['format'] }}" required maxlength="128">
                                <div class="form-text small">{{ __('numbering.placeholders') }}</div>
                            </td>

                            <td class="text-center" style="width: 6rem">
                                <label class="visually-hidden" for="padding-{{ $row['document_type'] }}">
                                    {{ __('numbering.padding') }}
                                </label>
                                <input id="padding-{{ $row['document_type'] }}" form="{{ $form }}"
                                       name="padding" type="number" min="1" max="10"
                                       class="form-control form-control-sm text-center"
                                       value="{{ $row['padding'] }}" required>
                            </td>

                            <td><code>{{ $row['sample'] }}</code></td>

                            <td class="text-end text-nowrap">
                                <button form="{{ $form }}" class="btn btn-sm btn-primary">
                                    {{ __('common.save') }}
                                </button>

                                @if (in_array($row['document_type'], $overrides, true))
                                    <button form="numbering-reset-{{ $row['document_type'] }}"
                                            class="btn btn-sm btn-outline-secondary btn-icon"
                                            title="{{ __('numbering.reset') }}"
                                            aria-label="{{ __('numbering.reset') }}">
                                        <i class="cil-action-undo" aria-hidden="true"></i>
                                    </button>
                                @endif
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        <div class="card-footer small text-body-secondary">{{ __('numbering.gaps_note') }}</div>
    </div>
@endsection
