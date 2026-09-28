@extends('layouts.app')
@section('title', $type->title())

@php
    use App\Modules\Settings\MasterData\Field;
@endphp

@section('breadcrumb')
    <li class="breadcrumb-item"><a href="{{ route('app.dashboard') }}">{{ __('nav.dashboard') }}</a></li>
    <li class="breadcrumb-item">
        <a href="{{ route('app.settings.master-data') }}">{{ __('masterdata.master_data') }}</a>
    </li>
    <li class="breadcrumb-item active" aria-current="page">{{ $type->title() }}</li>
@endsection

@section('content')
    <x-page-header :title="$type->title()" :subtitle="$type->description()">
        <x-slot:actions>
            <a href="{{ route('app.settings.master-data') }}" class="btn btn-sm btn-outline-secondary">
                <i class="cil-arrow-left" aria-hidden="true"></i> {{ __('common.back') }}
            </a>
            <a href="#master-data-form" class="btn btn-sm btn-info text-white">{{ __('masterdata.new_row') }}</a>
        </x-slot:actions>
    </x-page-header>

    <div class="row">
        <div class="col-lg-8">
            <div class="card mb-4">
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead>
                            <tr>
                                @foreach ($type->columns() as $column)
                                    <th>{{ __('masterdata.fields.'.$column) }}</th>
                                @endforeach
                                @if ($type->sharedWithPlatform())
                                    <th>{{ __('masterdata.owner') }}</th>
                                @endif
                                <th class="text-end">{{ __('common.actions') }}</th>
                            </tr>
                        </thead>

                        <tbody>
                            @forelse ($rows as $row)
                                <tr @class(['opacity-50' => $type->supportsActive() && ! $row->active])>
                                    @foreach ($type->columns() as $column)
                                        <td>{{ $type->display($row, $column) }}</td>
                                    @endforeach

                                    @if ($type->sharedWithPlatform())
                                        <td>
                                            @if ($row->company_id === null)
                                                {{-- Shared by every tenant, so nobody may rewrite it. --}}
                                                <span class="badge bg-secondary">{{ __('masterdata.platform') }}</span>
                                            @else
                                                <span class="badge bg-info text-white">{{ __('masterdata.company') }}</span>
                                            @endif
                                        </td>
                                    @endif

                                    <td class="text-end text-nowrap">
                                        @if ($row->company_id === null)
                                            {{-- The only thing a company may do with a shared row: start
                                                 its own from it. --}}
                                            <a class="btn btn-sm btn-outline-secondary"
                                               href="{{ route('app.settings.master-data.show', [$type->key(), 'copy' => $row->id]) }}#master-data-form">
                                                {{ __('masterdata.copy') }}
                                            </a>
                                        @else
                                            <a class="btn btn-sm btn-outline-secondary"
                                               href="{{ route('app.settings.master-data.show', [$type->key(), 'edit' => $row->id]) }}#master-data-form">
                                                {{ __('common.edit') }}
                                            </a>

                                            @if ($type->supportsActive())
                                                <form method="POST" class="d-inline"
                                                      action="{{ route('app.settings.master-data.toggle', [$type->key(), $row->id]) }}">
                                                    @csrf
                                                    <button class="btn btn-sm btn-outline-secondary">
                                                        {{ $row->active ? __('masterdata.deactivate') : __('masterdata.activate') }}
                                                    </button>
                                                </form>
                                            @endif

                                            {{-- Only reaches the action for a row nothing is filed
                                                 against; anything in use is refused there and the
                                                 person is told to deactivate instead. --}}
                                            <form method="POST" class="d-inline"
                                                  action="{{ route('app.settings.master-data.destroy', [$type->key(), $row->id]) }}"
                                                  onsubmit="return confirm(@js(__('masterdata.delete_confirm')))">
                                                @csrf
                                                @method('DELETE')
                                                <button class="btn btn-sm btn-outline-danger"
                                                        title="{{ __('common.delete') }}" aria-label="{{ __('common.delete') }}">
                                                    <i class="cil-trash" aria-hidden="true"></i>
                                                </button>
                                            </form>
                                        @endif
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="{{ count($type->columns()) + ($type->sharedWithPlatform() ? 2 : 1) }}">
                                        <x-empty-state :title="__('masterdata.empty')"
                                                       :description="__('masterdata.empty_hint')" />
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <div class="col-lg-4">
            <div class="card mb-4" id="master-data-form">
                <div class="card-header">
                    {{ $editing ? __('masterdata.edit_row') : __('masterdata.new_row') }}
                </div>

                <div class="card-body">
                    @if ($editing && $editing->company_id === null)
                        <div class="alert alert-secondary mb-0">{{ __('masterdata.platform_row_read_only') }}</div>
                    @else
                        <form method="POST"
                              action="{{ $editing
                                  ? route('app.settings.master-data.update', [$type->key(), $editing->id])
                                  : route('app.settings.master-data.store', $type->key()) }}">
                            @csrf
                            @if ($editing)
                                @method('PUT')
                            @endif

                            @if ($prefill && ! $editing)
                                <div class="alert alert-info small">{{ __('masterdata.copied_hint') }}</div>
                            @endif

                            @foreach ($type->fields() as $field)
                                {{-- A copy takes everything but the code: two rows sharing a code is
                                     exactly what the uniqueness rule exists to prevent. --}}
                                @php($source = $editing ?? ($field->type === Field::CODE ? null : $prefill))
                                @php($value = old($field->name, $source?->{$field->name}))

                                <div class="mb-3">
                                    @if ($field->type === Field::BOOLEAN)
                                        <div class="form-check">
                                            <input type="hidden" name="{{ $field->name }}" value="0">
                                            <input class="form-check-input" type="checkbox" id="f-{{ $field->name }}"
                                                   name="{{ $field->name }}" value="1"
                                                   @checked($source ? (bool) $value : $field->name === 'active')>
                                            <label class="form-check-label" for="f-{{ $field->name }}">
                                                {{ $field->label() }}
                                            </label>
                                        </div>
                                    @else
                                        <label class="form-label" for="f-{{ $field->name }}">
                                            {{ $field->label() }}
                                            @if ($field->isRequired())
                                                <span class="text-danger">*</span>
                                            @endif
                                        </label>

                                        @if ($field->type === Field::ENUM)
                                            <select class="form-select @error($field->name) is-invalid @enderror"
                                                    id="f-{{ $field->name }}" name="{{ $field->name }}">
                                                @foreach ($field->options as $option)
                                                    <option value="{{ $option }}" @selected($value === $option)>
                                                        {{ __('masterdata.values.'.strtolower($option)) }}
                                                    </option>
                                                @endforeach
                                            </select>
                                        @elseif (in_array($field->type, [Field::REFERENCE, Field::BELONGS_TO], true))
                                            <select class="form-select @error($field->name) is-invalid @enderror"
                                                    id="f-{{ $field->name }}" name="{{ $field->name }}">
                                                <option value="">—</option>
                                                @foreach ($references[$field->name] ?? [] as $option)
                                                    <option value="{{ $option['id'] }}" @selected($value === $option['id'])>
                                                        {{ $option['label'] }} ({{ $option['code'] }})
                                                    </option>
                                                @endforeach
                                            </select>
                                        @else
                                            <input type="text" class="form-control @error($field->name) is-invalid @enderror"
                                                   id="f-{{ $field->name }}" name="{{ $field->name }}"
                                                   value="{{ $value }}"
                                                   @if ($field->type === Field::CODE) style="text-transform: uppercase" @endif>
                                        @endif
                                    @endif

                                    @error($field->name)
                                        <div class="invalid-feedback d-block">{{ $message }}</div>
                                    @enderror
                                </div>
                            @endforeach

                            <button class="btn btn-info text-white">{{ __('common.save') }}</button>

                            @if ($editing)
                                <a class="btn btn-link" href="{{ route('app.settings.master-data.show', $type->key()) }}">
                                    {{ __('common.cancel') }}
                                </a>
                            @endif
                        </form>
                    @endif
                </div>
            </div>

            <div class="card mb-4">
                <div class="card-body small text-body-secondary">
                    {{-- The answer to "do we import this or enter it" is both, into the same rows. --}}
                    {{ __('masterdata.import_hint') }}
                </div>
            </div>
        </div>
    </div>
@endsection
