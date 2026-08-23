@extends('layouts.app')
@section('title', __('inventory.spare_parts'))

@section('breadcrumb')
    <li class="breadcrumb-item"><a href="{{ route('app.dashboard') }}">{{ __('nav.dashboard') }}</a></li>
    <li class="breadcrumb-item">{{ __('inventory.inventory') }}</li>
    <li class="breadcrumb-item active" aria-current="page">{{ __('inventory.spare_parts') }}</li>
@endsection

@section('content')
    <form method="GET" action="{{ route('app.inventory.parts') }}" id="list-filter">
        <x-data-table :title="__('inventory.spare_parts')" icon="cil-list" :paginator="$parts">
            <x-slot:actions>
                @can('inventory.stock.view')
                    <a href="{{ route('app.inventory.low-stock') }}" class="btn btn-sm btn-outline-warning">
                        {{ __('inventory.low_stock') }}
                    </a>
                @endcan

                @can('inventory.part.create')
                    <a href="{{ route('app.inventory.parts.create') }}" class="btn btn-sm btn-info text-white">
                        <i class="cil-plus" aria-hidden="true"></i> {{ __('common.add_new') }}
                    </a>
                @endcan
            </x-slot:actions>

            <x-slot:toolbar>
                <select name="category_id" class="form-select form-select-sm" onchange="this.form.requestSubmit()">
                    <option value="">{{ __('inventory.category') }}: {{ __('common.all') }}</option>
                    @foreach ($categories as $category)
                        <option value="{{ $category->id }}" @selected(request('category_id') === $category->id)>
                            {{ $category->label() }}
                        </option>
                    @endforeach
                </select>
            </x-slot:toolbar>

            <thead>
                <tr>
                    <th class="col-index">{{ __('common.row_number') }}</th>
                    <th>{{ __('inventory.part_number') }}</th>
                    <th>{{ __('inventory.name') }}</th>
                    <th>{{ __('inventory.category') }}</th>
                    <th class="text-end">{{ __('inventory.on_hand') }}</th>
                    <th class="text-end">{{ __('inventory.available') }}</th>
                    <th class="text-end">{{ __('inventory.reorder_level') }}</th>
                    <th>{{ __('common.actions') }}</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($parts as $index => $part)
                    @php
                        $onHand = number_format((float) ($part->on_hand ?? 0), 2);
                        $available = (float) ($part->on_hand ?? 0) - (float) ($part->reserved ?? 0);
                        $low = (float) ($part->on_hand ?? 0) <= (float) ($part->reorder_level ?? 0);
                    @endphp

                    <tr>
                        <td class="col-index">{{ $parts->firstItem() + $index }}</td>
                        <td>
                            <a href="{{ route('app.inventory.parts.show', $part) }}" class="fw-semibold">
                                {{ $part->part_number }}
                            </a>
                            @if ($part->is_critical_spare)
                                <div>
                                    <x-status-pill status="CRITICAL" tone="danger">
                                        {{ __('inventory.is_critical_spare') }}
                                    </x-status-pill>
                                </div>
                            @endif
                        </td>
                        <td>
                            {{ $part->name }}
                            <div class="text-body-secondary">{{ $part->brand }}</div>
                        </td>
                        <td>{{ $part->category?->label() ?? '—' }}</td>
                        <td class="text-end {{ $low ? 'text-danger fw-semibold' : '' }}">
                            {{ $onHand }} {{ $part->unit }}
                        </td>
                        <td class="text-end">{{ number_format($available, 2) }}</td>
                        <td class="text-end text-body-secondary">{{ number_format((float) $part->reorder_level, 2) }}</td>
                        <td>
                            <div class="d-flex gap-1">
                                <a href="{{ route('app.inventory.parts.show', $part) }}"
                                   class="btn btn-sm btn-info text-white btn-icon"
                                   title="{{ __('common.view') }}" aria-label="{{ __('common.view') }}">
                                    <i class="cil-magnifying-glass" aria-hidden="true"></i>
                                </a>

                                @can('inventory.part.update')
                                    <a href="{{ route('app.inventory.parts.edit', $part) }}"
                                       class="btn btn-sm btn-outline-secondary btn-icon"
                                       title="{{ __('common.edit') }}" aria-label="{{ __('common.edit') }}">
                                        <i class="cil-pencil" aria-hidden="true"></i>
                                    </a>

                                    <form method="POST" action="{{ route('app.inventory.parts.destroy', $part) }}"
                                          onsubmit="return confirm(@js(__('inventory.delete_confirm', ['number' => $part->part_number])))">
                                        @csrf
                                        @method('DELETE')
                                        <button class="btn btn-sm btn-outline-danger btn-icon"
                                                title="{{ __('common.delete') }}" aria-label="{{ __('common.delete') }}">
                                            <i class="cil-trash" aria-hidden="true"></i>
                                        </button>
                                    </form>
                                @endcan
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="8" class="p-0">
                        <x-empty-state :title="__('inventory.no_parts')" :description="__('inventory.no_parts_hint')">
                            <x-slot:action>
                                @can('inventory.part.create')
                                    <a href="{{ route('app.inventory.parts.create') }}" class="btn btn-sm btn-info text-white">
                                        {{ __('inventory.new_part') }}
                                    </a>
                                @endcan
                            </x-slot:action>
                        </x-empty-state>
                    </td></tr>
                @endforelse
            </tbody>
        </x-data-table>
    </form>
@endsection
