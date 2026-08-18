{{-- Label sheet with its own print stylesheet (Frontend 5.3). --}}
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" data-coreui-theme="light">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ __('scan.label_sheet') }} — {{ config('app.name') }}</title>
    @vite(['resources/sass/app.scss'])

    <style>
        .label-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(58mm, 1fr));
            gap: 4mm;
        }

        .label {
            border: 1px dashed #adb5bd;
            border-radius: 2mm;
            padding: 3mm;
            display: flex;
            gap: 3mm;
            align-items: center;
            break-inside: avoid;
        }

        /* Minimum 20 mm square when printed. Below that a smeared or
           scratched label on a machine frame stops scanning reliably
           (Data Dictionary 5.5). */
        .label svg {
            width: 22mm;
            height: 22mm;
            flex: none;
        }

        .label-text {
            min-width: 0;
        }

        .label-code {
            font-weight: 700;
            font-size: 3.2mm;
            font-family: ui-monospace, SFMono-Regular, Menlo, monospace;
        }

        .label-name {
            font-size: 2.8mm;
            line-height: 1.25;
            overflow-wrap: anywhere;
        }

        @media print {
            .no-print { display: none !important; }
            body { background: #fff; }
            .label { border-color: #dee2e6; }
            @page { margin: 8mm; }
        }
    </style>
</head>
<body class="p-4">
    <div class="no-print mb-4 d-flex flex-wrap gap-3 align-items-center">
        <h1 class="h5 mb-0">{{ __('scan.label_sheet') }}</h1>

        <div class="ms-auto d-flex gap-2">
            <button type="button" class="btn btn-primary" onclick="window.print()">
                {{ __('scan.print') }}
            </button>
            <a href="{{ route('app.assets.index') }}" class="btn btn-outline-secondary">
                {{ __('asset.assets') }}
            </a>
        </div>

        <p class="w-100 mb-0 small text-body-secondary">{{ __('scan.label_hint') }}</p>

        @if ($truncated)
            <p class="w-100 mb-0 small text-warning-emphasis">{{ __('scan.label_truncated') }}</p>
        @endif
    </div>

    @if ($assets->isEmpty())
        <div class="no-print">
            <x-empty-state :title="__('scan.no_labels')" :description="__('scan.no_labels_hint')" />
        </div>
    @else
        <div class="label-grid">
            @foreach ($assets as $asset)
                {{-- QR, asset code and name. Nothing else: anything more is one
                     more thing to go stale on a sticker nobody reprints. --}}
                <div class="label">
                    {!! $labels[$asset->id] !!}
                    <div class="label-text">
                        <div class="label-code">{{ $asset->asset_code }}</div>
                        <div class="label-name">{{ $asset->name }}</div>
                    </div>
                </div>
            @endforeach
        </div>
    @endif
</body>
</html>
