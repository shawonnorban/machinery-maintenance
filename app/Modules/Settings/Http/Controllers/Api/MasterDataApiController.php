<?php

declare(strict_types=1);

namespace App\Modules\Settings\Http\Controllers\Api;

use App\Modules\Settings\Actions\DeleteMasterDataRow;
use App\Modules\Settings\Actions\SaveMasterDataRow;
use App\Modules\Settings\MasterData\Field;
use App\Modules\Settings\MasterData\MasterDataRegistry;
use App\Modules\Settings\MasterData\MasterDataType;
use App\Modules\Tenancy\Models\Factory;
use App\Shared\Http\Api\ApiController;
use App\Shared\Http\Api\ApiResponse;
use App\Shared\Tenancy\TenantContext;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * The reference data every screen's dropdowns are built from (API 5.2, SRS
 * 6, Gap Analysis 3.4 #34): asset types, categories, manufacturers, models,
 * maintenance types, failure codes, root causes, warehouses, stores, bins,
 * and the rest of the two dozen lists in MasterDataRegistry.
 *
 * One controller for all of them, exactly as MasterDataController is one
 * controller for their web screens and for the same reason: they differ
 * only in their columns, and twenty near-identical controllers is twenty
 * places for the tenant check to be forgotten in one of them. Every rule —
 * a platform row is read-only, a code is unique across everything the
 * company can see, a row in use cannot be deleted — lives in
 * SaveMasterDataRow/DeleteMasterDataRow (ADR-003) and applies here exactly
 * as it does on the settings screen.
 */
class MasterDataApiController extends ApiController
{
    public function __construct(
        private readonly MasterDataRegistry $registry,
        private readonly TenantContext $context,
    ) {}

    public function index(): JsonResponse
    {
        $this->allow(MasterDataRegistry::PERMISSION);

        $types = collect($this->registry->all())
            ->map(fn (MasterDataType $type, string $key): array => [
                'key' => $key,
                'group' => $type->group(),
                'title' => $type->title(),
                'count' => $type->query()->count(),
            ])
            ->values()
            ->all();

        return ApiResponse::ok($types);
    }

    /**
     * Rows, plus what the API had never told a caller before this: the
     * schema behind them (`meta.schema`) and every reference/belongs-to
     * field's options (`meta.reference_options`) — both of which the web's
     * `MasterDataController::show()` has always had, because Blade renders
     * from the same `MasterDataType` object PHP already holds. An API
     * caller has no such object, so nothing before this endpoint could
     * build a create/edit form for any of these two dozen types without
     * hard-coding each one's fields by hand. Not a permission gap like the
     * others found this session — the permission was already right
     * (`MasterDataRegistry::PERMISSION`, the same one every other method
     * here uses); the schema itself just never had anywhere to go out.
     */
    public function show(Request $request, string $type): JsonResponse
    {
        $this->allow(MasterDataRegistry::PERMISSION);

        $masterType = $this->type($type);

        $query = $this->applySort(
            $masterType->query(),
            $request,
            [$masterType->displayColumn(), 'code'],
            $masterType->displayColumn(),
            'asc',
        );

        $paginator = $query->paginate($this->perPage($request))->withQueryString();
        $rows = collect($paginator->items())->map(fn (Model $row): array => $this->summary($masterType, $row))->values();

        return ApiResponse::ok($rows->all(), [
            'current_page' => $paginator->currentPage(),
            'last_page' => $paginator->lastPage(),
            'per_page' => $paginator->perPage(),
            'total' => $paginator->total(),
            'schema' => $this->schema($masterType),
            'reference_options' => $this->referenceOptions($masterType),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function schema(MasterDataType $type): array
    {
        return [
            'key' => $type->key(),
            'title' => $type->title(),
            'display_column' => $type->displayColumn(),
            'supports_active' => $type->supportsActive(),
            'fields' => collect($type->fields())->map(fn (Field $field): array => [
                'name' => $field->name,
                'type' => $field->type,
                'label' => $field->label(),
                'required' => $field->isRequired(),
                'options' => $field->options,
                'reference' => $field->reference,
                'in_list' => $field->inList,
            ])->all(),
        ];
    }

    /**
     * The options behind every reference/belongs-to field on this type's
     * form — mirrors `MasterDataController::referenceOptions()` exactly,
     * including the Factory-specific "only what this caller can reach"
     * scoping, since a full factory list would leak the shape of the
     * estate to whoever can edit master data but not factories themselves.
     *
     * @return array<string, list<array{id: string, label: string, code: string}>>
     */
    private function referenceOptions(MasterDataType $type): array
    {
        $options = [];

        foreach ($type->fields() as $field) {
            if ($field->type === Field::REFERENCE) {
                $parent = $this->registry->find((string) $field->reference);

                if ($parent === null) {
                    continue;
                }

                $query = $parent->query()->orderBy($parent->displayColumn());

                if ($parent->supportsActive()) {
                    $query->where('active', true);
                }

                $options[$field->name] = $this->asOptions($query->get(), $parent->displayColumn());
            }

            if ($field->type === Field::BELONGS_TO) {
                $model = (string) $field->model;

                $query = $model::query()->orderBy('name');

                if ($model === Factory::class) {
                    $query->whereIn('id', $this->context->accessibleFactoryIds());
                }

                $options[$field->name] = $this->asOptions($query->get(), 'name');
            }
        }

        return $options;
    }

    /**
     * @param  Collection<int, Model>  $rows
     * @return list<array{id: string, label: string, code: string}>
     */
    private function asOptions($rows, string $labelColumn): array
    {
        return $rows->map(fn (Model $row): array => [
            'id' => (string) $row->getKey(),
            'label' => (string) $row->getAttribute($labelColumn),
            'code' => (string) $row->getAttribute('code'),
        ])->all();
    }

    public function store(Request $request, string $type, SaveMasterDataRow $action): JsonResponse
    {
        $this->allow(MasterDataRegistry::PERMISSION);

        $masterType = $this->type($type);

        $row = $action->create($masterType, $request->all());

        return ApiResponse::created($this->summary($masterType, $row));
    }

    public function update(Request $request, string $type, string $row, SaveMasterDataRow $action): JsonResponse
    {
        $this->allow(MasterDataRegistry::PERMISSION);

        $masterType = $this->type($type);
        $model = $action->update($masterType, $this->row($masterType, $row), $request->all());

        return ApiResponse::ok($this->summary($masterType, $model));
    }

    /**
     * Deactivating rather than deleting, same as the settings screen: the
     * row disappears from every picker while the breakdowns and work orders
     * already filed against it keep reading correctly.
     */
    public function setActive(Request $request, string $type, string $row, SaveMasterDataRow $action): JsonResponse
    {
        $this->allow(MasterDataRegistry::PERMISSION);

        $data = $request->validate(['active' => ['required', 'boolean']]);

        $masterType = $this->type($type);
        $model = $action->setActive($masterType, $this->row($masterType, $row), $data['active']);

        return ApiResponse::ok($this->summary($masterType, $model));
    }

    public function destroy(string $type, string $row, DeleteMasterDataRow $action): JsonResponse
    {
        $this->allow(MasterDataRegistry::PERMISSION);

        $masterType = $this->type($type);

        $action->handle($masterType, $this->row($masterType, $row));

        return ApiResponse::noContent();
    }

    private function type(string $key): MasterDataType
    {
        return $this->registry->find($key) ?? abort(404);
    }

    /**
     * Scoped to what the company can see, so a ulid from another tenant is a
     * 404 rather than a row.
     */
    private function row(MasterDataType $type, string $id): Model
    {
        return $type->query()->findOrFail($id);
    }

    /**
     * @return array<string, mixed>
     */
    private function summary(MasterDataType $type, Model $row): array
    {
        $data = ['id' => (string) $row->getKey()];

        foreach ($type->fields() as $field) {
            $data[$field->name] = $row->getAttribute($field->name);
        }

        // Null here is what makes a row the platform's rather than a
        // company's own — the same test SaveMasterDataRow itself uses to
        // decide whether it may be edited.
        $data['company_id'] = $row->getAttribute('company_id');
        $data['is_platform'] = $row->getAttribute('company_id') === null;

        return $data;
    }
}
