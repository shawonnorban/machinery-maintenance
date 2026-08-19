@extends('layouts.app')
@section('title', $importer->title())

@section('breadcrumb')
    <li class="breadcrumb-item"><a href="{{ route('app.dashboard') }}">{{ __('nav.dashboard') }}</a></li>
    <li class="breadcrumb-item"><a href="{{ route('app.imports.index') }}">{{ __('import.imports') }}</a></li>
    <li class="breadcrumb-item active" aria-current="page">{{ $importer->title() }}</li>
@endsection

@section('content')
    <x-page-header :title="$importer->title()" :subtitle="$importer->description()">
        <x-slot:actions>
            <a href="{{ route('app.imports.index') }}" class="btn btn-sm btn-outline-secondary">
                {{ __('import.back_to_imports') }}
            </a>
        </x-slot:actions>
    </x-page-header>

    <div class="row">
        <div class="col-lg-5">
            <div class="card mb-4">
                <div class="card-header">
                    <i class="cil-cloud-upload" aria-hidden="true"></i>
                    <span>{{ __('import.upload') }}</span>
                </div>

                <div class="card-body">
                    <a href="{{ route('app.imports.template', $importer->type()) }}"
                       class="btn btn-sm btn-outline-primary mb-2">
                        {{ __('import.download_template') }}
                    </a>

                    {{-- A header line alone leaves every question unanswered:
                         which date format, the name or the code, is "yes" a
                         boolean. One filled row answers all three. --}}
                    <p class="small text-body-secondary">{{ __('import.template_hint') }}</p>

                    <form method="POST" action="{{ route('app.imports.store', $importer->type()) }}"
                          enctype="multipart/form-data">
                        @csrf

                        <div class="mb-3">
                            <label for="file" class="form-label">{{ __('import.choose_file') }}</label>
                            <input type="file" class="form-control @error('file') is-invalid @enderror"
                                   id="file" name="file" accept=".csv,.xlsx" required>

                            @error('file')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        <button class="btn btn-info text-white">{{ __('import.start') }}</button>
                    </form>

                    @if ($importer->supportsExport())
                        <hr>

                        {{-- The round trip: pull current data out in the same
                             columns this screen accepts, fix it in a
                             spreadsheet, upload it again. --}}
                        <form method="POST" action="{{ route('app.imports.export', $importer->type()) }}"
                              class="d-flex gap-2 flex-wrap">
                            @csrf

                            @foreach (['CSV', 'XLSX'] as $format)
                                <button class="btn btn-sm btn-outline-secondary" name="format" value="{{ $format }}">
                                    {{ __('import.export_current') }} · {{ $format }}
                                </button>
                            @endforeach
                        </form>
                    @endif
                </div>
            </div>
        </div>

        <div class="col-lg-7">
            <div class="card mb-4">
                <div class="card-header">
                    <i class="cil-list" aria-hidden="true"></i>
                    <span>{{ __('import.columns_expected') }}</span>
                </div>

                <div class="table-responsive">
                    <table class="table table-sm align-middle mb-0">
                        <thead>
                            <tr>
                                <th>{{ __('import.column') }}</th>
                                <th>{{ __('import.required') }}</th>
                                <th>{{ __('import.example') }}</th>
                            </tr>
                        </thead>

                        <tbody>
                            @foreach ($columns as $name => $column)
                                <tr>
                                    <td>
                                        {{-- The header a file must contain is
                                             the English name, shown as code,
                                             with the meaning translated beside
                                             it. Translating the header itself
                                             would break files on a language
                                             switch. --}}
                                        <code>{{ $name }}</code>
                                        <div class="small text-body-secondary">{{ __($column->label) }}</div>
                                    </td>
                                    <td>
                                        @if ($column->required)
                                            <span class="badge text-bg-warning">{{ __('import.required') }}</span>
                                        @else
                                            <span class="small text-body-secondary">{{ __('import.optional') }}</span>
                                        @endif
                                    </td>
                                    <td>
                                        <span class="small">{{ $column->example }}</span>

                                        @if ($column->hint)
                                            <div class="small text-body-secondary">{{ $column->hint }}</div>
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
@endsection
