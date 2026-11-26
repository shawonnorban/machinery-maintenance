<?php

declare(strict_types=1);

namespace App\Modules\Erp\Http\Controllers\Api;

use App\Modules\Erp\Support\ResourceDefinition;
use App\Modules\Erp\Support\ResourceRegistry;
use App\Shared\Http\Api\ApiController;
use App\Shared\Http\Api\ApiResponse;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\QueryException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * The screen kit's API (AnnotechERP): list, show, create, update and delete
 * for every `ResourceDefinition`. Rows are tenant-scoped by their model, so
 * another company's id is simply not found.
 */
class ResourceApiController extends ApiController
{
    public function __construct(private readonly ResourceRegistry $registry) {}

    /** Records; `?filter[column]=value` narrows to equal values (a grouped tab). */
    public function index(Request $request, string $resource): JsonResponse
    {
        $definition = $this->definition($resource, manage: false);

        $query = $definition->query();
        foreach ((array) $request->query('filter', []) as $column => $value) {
            if (in_array($column, $definition->columns(), true) && is_scalar($value)) {
                $query->where($column, $value);
            }
        }

        return ApiResponse::ok($query->limit(2000)->get()->map(fn (Model $row): array => $definition->present($row))->all());
    }

    public function show(string $resource, string $id): JsonResponse
    {
        $definition = $this->definition($resource, manage: false);

        return ApiResponse::ok($definition->present($this->row($definition, $id)));
    }

    public function store(Request $request, string $resource): JsonResponse
    {
        $definition = $this->definition($resource, manage: true);
        $data = $this->validated($request, $definition, null);

        $row = DB::transaction(function () use ($definition, $data): Model {
            $model = $definition->model();
            /** @var Model $row */
            $row = new $model;
            $row->fill($definition->beforeSave($data + $definition->defaults(), null));
            $row->save();
            $definition->afterSave($row, true);

            return $row;
        });

        return ApiResponse::created($definition->present($row->fresh()));
    }

    public function update(Request $request, string $resource, string $id): JsonResponse
    {
        $definition = $this->definition($resource, manage: true);
        $row = $this->row($definition, $id);
        $data = $this->validated($request, $definition, $row);

        DB::transaction(function () use ($definition, $row, $data): void {
            $row->fill($definition->beforeSave($data, $row));
            $row->save();
            $definition->afterSave($row, false);
        });

        return ApiResponse::ok($definition->present($row->fresh()));
    }

    public function destroy(string $resource, string $id): JsonResponse
    {
        $definition = $this->definition($resource, manage: true);
        if (! $definition->deletable()) {
            abort(405);
        }
        $row = $this->row($definition, $id);

        $definition->beforeDelete($row);
        try {
            // In its own (save)point, so a refused delete leaves the request's transaction usable.
            DB::transaction(fn () => $row->delete());
        } catch (QueryException $e) {
            // A row other records still point at stays; Brickland asks to deactivate it instead.
            if (($e->errorInfo[0] ?? null) === '23503') {
                throw ValidationException::withMessages(['id' => __('erp.in_use')]);
            }
            throw $e;
        }

        return ApiResponse::noContent();
    }

    private function definition(string $key, bool $manage): ResourceDefinition
    {
        $definition = $this->registry->find($key);
        if ($definition === null) {
            abort(404);
        }
        $this->allow($manage ? $definition->managePermission() : $definition->viewPermission());

        return $definition;
    }

    private function row(ResourceDefinition $definition, string $id): Model
    {
        return $definition->rowQuery()->whereKey($id)->firstOrFail();
    }

    /**
     * @return array<string, mixed>
     */
    private function validated(Request $request, ResourceDefinition $definition, ?Model $row): array
    {
        // "1"/"0" from a form, true/false from JSON: both are a boolean.
        foreach ($definition->booleans() as $flag) {
            if ($request->has($flag)) {
                $request->merge([$flag => filter_var($request->input($flag), FILTER_VALIDATE_BOOLEAN)]);
            }
        }

        return $request->validate($definition->rules($row), $definition->messages());
    }
}
