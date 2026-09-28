<?php

declare(strict_types=1);

namespace App\Modules\Asset\Http\Controllers\Web;

use App\Modules\Asset\Actions\ChangeAssetStatus;
use App\Modules\Asset\Http\Requests\ChangeAssetStatusRequest;
use App\Modules\Asset\Models\Asset;
use App\Shared\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Validation\ValidationException;

class AssetStatusController extends Controller
{
    public function store(
        ChangeAssetStatusRequest $request,
        Asset $asset,
        ChangeAssetStatus $action,
    ): RedirectResponse {
        if ((int) $request->integer('version') !== $asset->version) {
            throw ValidationException::withMessages([
                'version' => __('asset.version_conflict', [
                    'current' => $asset->version,
                    'submitted' => $request->integer('version'),
                ]),
            ])->status(409);
        }

        $action->handle(
            asset: $asset,
            toStatus: $request->string('status')->toString(),
            userId: $request->user()->id,
            reason: $request->string('reason')->toString() ?: null,
            source: 'MANUAL',
            // Recommissioning a retired asset is gated separately from the
            // ordinary status permission (Data Dictionary 3.3).
            isElevated: $request->user()->can('asset.qr.regenerate'),
        );

        return back()->with('status', __('asset.status_changed', [
            'status' => __('asset.status_'.strtolower($request->string('status')->toString())),
        ]));
    }
}
