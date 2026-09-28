<?php

declare(strict_types=1);

namespace App\Modules\CustomField\Http\Controllers\Api;

use App\Modules\CustomField\Actions\SaveCustomFieldValues;
use App\Modules\CustomField\Models\CustomFieldDefinition;
use App\Modules\CustomField\Models\CustomFieldValue;
use App\Shared\Http\Api\ApiController;
use App\Shared\Http\Api\ApiException;
use App\Shared\Http\Api\ApiResponse;
use App\Shared\Http\Api\ErrorCode;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Reading or writing a *record's own* custom field values rides on that
 * record's own permission, exactly the way `FileApiController` decides
 * which permission answers for a file by what it is attached to — nobody
 * needs a separate custom-field permission just to see an asset's own
 * data. Standalone (used by any entity not yet folded into its own
 * controller's save, the way `AssetApiController` is); an entity with a
 * closer integration reads/writes through its own endpoint instead and
 * never calls this one directly.
 */
class CustomFieldValueApiController extends ApiController
{
    /**
     * @var array<string, array{view: string, update: string}>
     */
    private const PERMISSIONS = [
        'asset' => ['view' => 'asset.asset.view', 'update' => 'asset.asset.update'],
        'work_order' => ['view' => 'work_order.work_order.view', 'update' => 'work_order.work_order.update'],
        'vendor' => ['view' => 'vendor.vendor.view', 'update' => 'vendor.vendor.update'],
        'breakdown' => ['view' => 'breakdown.breakdown.view', 'update' => 'breakdown.breakdown.repair'],
    ];

    public function show(Request $request): JsonResponse
    {
        $data = $request->validate([
            'fieldable_type' => ['required', 'string'],
            'fieldable_id' => ['required', 'string'],
        ]);

        $this->allow($this->permissionFor($data['fieldable_type'], 'view'));

        $schema = CustomFieldDefinition::where('fieldable_type', $data['fieldable_type'])
            ->where('active', true)
            ->orderBy('sort_order')
            ->orderBy('label')
            ->get();

        $values = CustomFieldValue::whereIn('definition_id', $schema->pluck('id'))
            ->where('fieldable_id', $data['fieldable_id'])
            ->get()
            ->keyBy('definition_id');

        return ApiResponse::ok([
            'schema' => $schema->map(fn (CustomFieldDefinition $d): array => $d->toApiSchema())->all(),
            'values' => $schema->mapWithKeys(fn (CustomFieldDefinition $d) => [
                $d->code => $values->get($d->id)?->value_json,
            ])->all(),
        ]);
    }

    public function store(Request $request, SaveCustomFieldValues $action): JsonResponse
    {
        $data = $request->validate([
            'fieldable_type' => ['required', 'string'],
            'fieldable_id' => ['required', 'string'],
            'values' => ['sometimes', 'array'],
        ]);

        $this->allow($this->permissionFor($data['fieldable_type'], 'update'));

        try {
            $action->handle($data['fieldable_type'], $data['fieldable_id'], $data['values'] ?? []);
        } catch (ValidationException $e) {
            throw ApiException::of(ErrorCode::VALIDATION_ERROR, implode(' ', $e->validator->errors()->all()), $e->errors());
        }

        return ApiResponse::noContent();
    }

    private function permissionFor(string $fieldableType, string $action): string
    {
        return self::PERMISSIONS[$fieldableType][$action] ?? throw new NotFoundHttpException;
    }
}
