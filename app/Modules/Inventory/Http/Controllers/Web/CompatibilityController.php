<?php

declare(strict_types=1);

namespace App\Modules\Inventory\Http\Controllers\Web;

use App\Modules\Asset\Models\AssetModel;
use App\Modules\Inventory\Models\SparePart;
use App\Modules\Inventory\Models\SparePartCompatibility;
use App\Shared\Http\Controllers\Controller;
use App\Shared\Tenancy\TenantContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * Which part fits what, and what will do instead (SRS 20).
 *
 * Two different questions, and the store needs both at two in the morning.
 * "Does this hook fit a DDL-9000C" decides whether the machine can be repaired
 * at all; "what else will do" decides whether it is repaired tonight or on
 * Sunday when the supplier opens.
 *
 * Recording a substitute is also what makes a substitution visible afterwards:
 * a machine that failed early having been fitted with the second-best part is
 * a story the failure analysis can only tell if somebody wrote down that the
 * part was a substitute.
 */
class CompatibilityController extends Controller
{
    public function __construct(private readonly TenantContext $context) {}

    public function store(Request $request, SparePart $part): RedirectResponse
    {
        $this->authorizeCompatibility($request);

        $data = $request->validate([
            'compatibility_type' => ['required', Rule::in(SparePartCompatibility::TYPES)],
            'asset_model_id' => ['nullable', 'string', 'size:26'],
            'substitute_for_part_id' => ['nullable', 'string', 'size:26'],
        ]);

        if ($data['compatibility_type'] === 'FITS') {
            $this->assertModelVisible($data['asset_model_id'] ?? null);

            $exists = SparePartCompatibility::where('spare_part_id', $part->id)
                ->where('asset_model_id', $data['asset_model_id'])
                ->exists();

            if ($exists) {
                throw ValidationException::withMessages([
                    'asset_model_id' => __('inventory.compatibility_already_listed'),
                ])->status(422);
            }

            SparePartCompatibility::create([
                'company_id' => $this->context->companyId(),
                'spare_part_id' => $part->id,
                'asset_model_id' => $data['asset_model_id'],
                'compatibility_type' => 'FITS',
            ]);

            return back()->with('status', __('inventory.compatibility_added'));
        }

        $substituteFor = SparePart::find($data['substitute_for_part_id'] ?? null);

        if ($substituteFor === null) {
            throw ValidationException::withMessages([
                'substitute_for_part_id' => __('inventory.unknown_part'),
            ]);
        }

        if ($substituteFor->id === $part->id) {
            // A part standing in for itself says nothing, and would show up in
            // the store's list of alternatives as a dead end.
            throw ValidationException::withMessages([
                'substitute_for_part_id' => __('inventory.cannot_substitute_itself'),
            ])->status(422);
        }

        SparePartCompatibility::create([
            'company_id' => $this->context->companyId(),
            'spare_part_id' => $part->id,
            'substitute_for_part_id' => $substituteFor->id,
            'compatibility_type' => 'SUBSTITUTE',
        ]);

        return back()->with('status', __('inventory.substitute_added'));
    }

    public function destroy(Request $request, SparePart $part, SparePartCompatibility $compatibility): RedirectResponse
    {
        $this->authorizeCompatibility($request);

        if ($compatibility->spare_part_id !== $part->id) {
            abort(404);
        }

        $compatibility->delete();

        return back()->with('status', __('inventory.compatibility_removed'));
    }

    private function assertModelVisible(?string $modelId): void
    {
        $visible = filled($modelId)
            && AssetModel::availableTo($this->context->companyId())->whereKey($modelId)->exists();

        if (! $visible) {
            throw ValidationException::withMessages([
                'asset_model_id' => __('inventory.unknown_asset_model'),
            ]);
        }
    }

    private function authorizeCompatibility(Request $request): void
    {
        if (! $request->user()->can('inventory.part.update')) {
            abort(403);
        }
    }
}
