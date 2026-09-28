<?php

declare(strict_types=1);

namespace App\Modules\CustomField\Http\Controllers\Api;

use App\Modules\CustomField\Actions\DeleteCustomFieldDefinition;
use App\Modules\CustomField\Actions\SaveCustomFieldDefinition;
use App\Modules\CustomField\Models\CustomFieldDefinition;
use App\Shared\Http\Api\ApiController;
use App\Shared\Http\Api\ApiException;
use App\Shared\Http\Api\ApiResponse;
use App\Shared\Http\Api\ErrorCode;
use App\Shared\Tenancy\TenantContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

/**
 * The admin screen for defining custom fields — one company-wide permission
 * covers every entity type (`custom_field.definition.manage`), the same
 * shape `masterdata.manage` already has for its own generic admin screen.
 */
class CustomFieldDefinitionApiController extends ApiController
{
    public function __construct(private readonly TenantContext $context) {}

    public function index(Request $request): JsonResponse
    {
        $this->allow('custom_field.definition.manage');

        $data = $request->validate(['fieldable_type' => ['required', 'string']]);

        $definitions = CustomFieldDefinition::where('fieldable_type', $data['fieldable_type'])
            ->orderBy('sort_order')
            ->orderBy('label')
            ->get();

        return ApiResponse::ok($definitions->map(fn (CustomFieldDefinition $d): array => $d->toApiSchema())->all());
    }

    public function store(Request $request, SaveCustomFieldDefinition $action): JsonResponse
    {
        $this->allow('custom_field.definition.manage');

        $data = $this->validated($request);

        try {
            $definition = $action->create($this->context->companyId(), $data['fieldable_type'], $data);
        } catch (ValidationException $e) {
            throw ApiException::of(ErrorCode::VALIDATION_ERROR, implode(' ', $e->validator->errors()->all()), $e->errors());
        }

        return ApiResponse::created($definition->toApiSchema());
    }

    public function update(Request $request, CustomFieldDefinition $definition, SaveCustomFieldDefinition $action): JsonResponse
    {
        $this->allow('custom_field.definition.manage');

        $data = $this->validated($request, requireFieldableType: false);

        try {
            $definition = $action->update($definition, $data);
        } catch (ValidationException $e) {
            throw ApiException::of(ErrorCode::VALIDATION_ERROR, implode(' ', $e->validator->errors()->all()), $e->errors());
        }

        return ApiResponse::ok($definition->toApiSchema());
    }

    public function setActive(Request $request, CustomFieldDefinition $definition, SaveCustomFieldDefinition $action): JsonResponse
    {
        $this->allow('custom_field.definition.manage');

        $data = $request->validate(['active' => ['required', 'boolean']]);

        return ApiResponse::ok($action->setActive($definition, $data['active'])->toApiSchema());
    }

    public function destroy(CustomFieldDefinition $definition, DeleteCustomFieldDefinition $action): JsonResponse
    {
        $this->allow('custom_field.definition.manage');

        $action->handle($definition);

        return ApiResponse::noContent();
    }

    /**
     * @return array<string, mixed>
     */
    private function validated(Request $request, bool $requireFieldableType = true): array
    {
        return $request->validate([
            'fieldable_type' => [$requireFieldableType ? 'required' : 'sometimes', 'string', 'max:64'],
            'code' => [$requireFieldableType ? 'required' : 'sometimes', 'string', 'max:64', 'regex:/^[a-z0-9_]+$/'],
            'label' => ['required', 'string', 'max:255'],
            'field_type' => ['required', 'string', 'in:'.implode(',', CustomFieldDefinition::FIELD_TYPES)],
            'options' => ['sometimes', 'array'],
            'options.*' => ['string', 'max:255'],
            'required' => ['sometimes', 'boolean'],
            'sort_order' => ['sometimes', 'integer', 'min:0'],
            'active' => ['sometimes', 'boolean'],
        ]);
    }
}
