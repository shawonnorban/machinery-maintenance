{{-- A machine's papers (SRS 8).

     The manual, the wiring diagram, the calibration certificate. A technician
     standing at a dyeing machine at two in the morning needs the manual on the
     machine's own screen; a copy in somebody's inbox is a copy that is not
     there. --}}
<div class="card mb-4">
    <div class="card-header">{{ __('asset.documents') }}</div>

    <div class="card-body p-0">
        @if ($documents->isEmpty())
            <div class="p-3 text-body-secondary small">{{ __('asset.no_documents') }}</div>
        @else
            <table class="table table-hover align-middle mb-0">
                <tbody>
                    @foreach ($documents as $document)
                        <tr>
                            <td>
                                <a href="{{ route('app.attachments.show', $document) }}">
                                    {{ $document->original_name }}
                                </a>
                                <div class="small text-body-secondary">
                                    {{ $document->humanSize() }}
                                    @dt($document->created_at)
                                </div>
                            </td>
                            <td class="text-end">
                                @can('asset.document.manage')
                                    <form method="POST"
                                          action="{{ route('app.assets.documents.destroy', [$asset, $document]) }}"
                                          onsubmit="return confirm(@js(__('asset.document_delete_confirm')))">
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

    @can('asset.document.manage')
        <div class="card-body border-top">
            <form method="POST" action="{{ route('app.assets.documents.store', $asset) }}"
                  enctype="multipart/form-data" class="row g-2 align-items-end">
                @csrf

                <div class="col-md-8">
                    <label for="document" class="form-label mb-1">{{ __('asset.add_document') }}</label>
                    <input id="document" name="file" type="file" class="form-control form-control-sm" required>
                    @error('file')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                </div>

                <div class="col-md-4">
                    <button class="btn btn-sm btn-outline-info w-100">{{ __('asset.upload') }}</button>
                </div>
            </form>
        </div>
    @endcan
</div>
