{{-- Which machines this part fits, and what will do instead (SRS 20).

     Two different questions the store needs at two in the morning: whether the
     machine can be repaired at all, and whether it is repaired tonight or on
     Sunday when the supplier opens. --}}
<div class="card mb-4">
    <div class="card-header">{{ __('inventory.compatibility') }}</div>

    <div class="card-body p-0">
        @if ($compatibility->isEmpty())
            <div class="p-3 text-body-secondary small">{{ __('inventory.no_compatibility') }}</div>
        @else
            <table class="table table-hover align-middle mb-0">
                <tbody>
                    @foreach ($compatibility as $row)
                        <tr>
                            <td>
                                @if ($row->compatibility_type === 'FITS')
                                    <span class="badge bg-info text-white">{{ __('inventory.fits') }}</span>
                                    {{ $row->assetModel?->model }}
                                @else
                                    <span class="badge bg-secondary">{{ __('inventory.substitute') }}</span>
                                    {{-- Recorded so a failure analysis can later say the
                                         machine ran on the second-best part. --}}
                                    {{ $row->substituteFor?->part_number }}
                                    <span class="text-body-secondary">{{ $row->substituteFor?->name }}</span>
                                @endif
                            </td>
                            <td class="text-end">
                                @can('inventory.part.update')
                                    <form method="POST"
                                          action="{{ route('app.inventory.parts.compatibility.destroy', [$part, $row]) }}">
                                        @csrf
                                        @method('DELETE')
                                        <button class="btn btn-sm btn-outline-danger btn-icon"
                                                title="{{ __('common.delete') }}"
                                                aria-label="{{ __('common.delete') }}">
                                            <i class="cil-trash" aria-hidden="true"></i>
                                        </button>
                                    </form>
                                @endcan
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @endif
    </div>

    @can('inventory.part.update')
        <div class="card-body border-top">
            <form method="POST" action="{{ route('app.inventory.parts.compatibility.store', $part) }}"
                  class="row g-2 align-items-end">
                @csrf
                <input type="hidden" name="compatibility_type" value="FITS">

                <div class="col-md-8">
                    <label for="asset_model_id" class="form-label mb-1">{{ __('inventory.fits_model') }}</label>
                    <select id="asset_model_id" name="asset_model_id" class="form-select form-select-sm" required
                            data-tom-select>
                        <option value="">—</option>
                        @foreach ($assetModels as $model)
                            <option value="{{ $model->id }}">{{ $model->model }}</option>
                        @endforeach
                    </select>
                    @error('asset_model_id')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                </div>

                <div class="col-md-4">
                    <button class="btn btn-sm btn-outline-info w-100">{{ __('inventory.add_fit') }}</button>
                </div>
            </form>

            <form method="POST" action="{{ route('app.inventory.parts.compatibility.store', $part) }}"
                  class="row g-2 align-items-end mt-2">
                @csrf
                <input type="hidden" name="compatibility_type" value="SUBSTITUTE">

                <div class="col-md-8">
                    <label for="substitute_for_part_id" class="form-label mb-1">
                        {{ __('inventory.substitutes_for') }}
                    </label>
                    <select id="substitute_for_part_id" name="substitute_for_part_id"
                            class="form-select form-select-sm" required data-tom-select>
                        <option value="">—</option>
                        @foreach ($otherParts as $other)
                            <option value="{{ $other->id }}">{{ $other->part_number }} — {{ $other->name }}</option>
                        @endforeach
                    </select>
                    @error('substitute_for_part_id')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                </div>

                <div class="col-md-4">
                    <button class="btn btn-sm btn-outline-secondary w-100">{{ __('inventory.add_substitute') }}</button>
                </div>
            </form>
        </div>
    @endcan
</div>
