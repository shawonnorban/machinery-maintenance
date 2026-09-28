@extends('layouts.app')
@section('title', $part->part_number)

@section('breadcrumb')
    <li class="breadcrumb-item"><a href="{{ route('app.dashboard') }}">{{ __('nav.dashboard') }}</a></li>
    <li class="breadcrumb-item"><a href="{{ route('app.inventory.parts') }}">{{ __('inventory.spare_parts') }}</a></li>
    <li class="breadcrumb-item">
        <a href="{{ route('app.inventory.parts.show', $part) }}">{{ $part->part_number }}</a>
    </li>
    <li class="breadcrumb-item active" aria-current="page">{{ __('common.edit') }}</li>
@endsection

@section('content')
    <x-page-header :title="__('inventory.edit_part')" :subtitle="$part->part_number.' — '.$part->name">
        <x-slot:actions>
            <a href="{{ route('app.inventory.parts.show', $part) }}" class="btn btn-sm btn-outline-secondary">
                <i class="cil-arrow-left" aria-hidden="true"></i> {{ __('common.back') }}
            </a>
        </x-slot:actions>
    </x-page-header>

    <form method="POST" action="{{ route('app.inventory.parts.update', $part) }}">
        @csrf
        @method('PATCH')

        @include('inventory::parts._form', ['part' => $part, 'categories' => $categories])

        <div class="d-flex gap-2">
            <button type="submit" class="btn btn-info text-white">{{ __('common.save') }}</button>
            <a href="{{ route('app.inventory.parts.show', $part) }}" class="btn btn-outline-secondary">
                {{ __('common.cancel') }}
            </a>
        </div>
    </form>

    <div class="card mt-4">
        <div class="card-body d-flex flex-wrap align-items-center justify-content-between gap-3">
            <div>
                <div class="fw-semibold">
                    {{ $part->active ? __('inventory.part_active') : __('inventory.part_inactive') }}
                </div>
                {{-- Never deleted: the ledger points at this row, and a part
                     nobody stocks any more is still the part that was fitted to
                     a machine two years ago. --}}
                <div class="small text-body-secondary">{{ __('inventory.deactivate_hint') }}</div>
            </div>

            <div class="d-flex gap-2">
                <form method="POST" action="{{ route('app.inventory.parts.toggle', $part) }}">
                    @csrf
                    <button class="btn btn-outline-{{ $part->active ? 'warning' : 'success' }}">
                        {{ $part->active ? __('inventory.deactivate') : __('inventory.activate') }}
                    </button>
                </form>

                {{-- Reaches the action only for a part nothing points at. One
                     the ledger names is refused there, with the count of what
                     is in the way. --}}
                <form method="POST" action="{{ route('app.inventory.parts.destroy', $part) }}"
                      onsubmit="return confirm(@js(__('inventory.delete_confirm', ['number' => $part->part_number])))">
                    @csrf
                    @method('DELETE')
                    <button class="btn btn-outline-danger">
                        <i class="cil-trash" aria-hidden="true"></i> {{ __('common.delete') }}
                    </button>
                </form>
            </div>
        </div>
    </div>
@endsection
