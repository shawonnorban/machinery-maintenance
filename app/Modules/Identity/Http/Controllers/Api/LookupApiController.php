<?php

declare(strict_types=1);

namespace App\Modules\Identity\Http\Controllers\Api;

use App\Modules\Identity\Models\LookupTable;
use App\Modules\Identity\Models\LookupValue;
use App\Shared\Http\Api\ApiController;
use App\Shared\Http\Api\ApiException;
use App\Shared\Http\Api\ApiResponse;
use App\Shared\Http\Api\ErrorCode;
use App\Shared\Tenancy\TenantContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * Access Control → Lookup Table Master (AnnotechERP 02/01): tables of
 * dropdown values. Shared (system) rows belong to every company, so they are
 * never changed in place: the first edit or delete of a shared value gives the
 * company its own copy of that table (same code, every value copied) and the
 * change lands there. Every reader of a list takes the company's table before
 * the shared one, so the copy is what the company sees from then on; deleting
 * the copy brings the shared list back.
 */
class LookupApiController extends ApiController
{
    public function __construct(private readonly TenantContext $context) {}

    public function index(): JsonResponse
    {
        $this->allow('masterdata.manage');

        $tables = LookupTable::query()->visible()
            ->withCount(['values' => fn ($q) => $q->visible()])
            ->orderBy('name')
            ->get();
        // A shared table the company has its own copy of is shown as that copy only.
        $own = $tables->whereNotNull('company_id')->pluck('code')->all();
        $shared = LookupTable::query()->whereNull('company_id')->pluck('code')->all();
        $tables = $tables->reject(fn (LookupTable $t) => $t->company_id === null && in_array($t->code, $own, true))->values();

        return ApiResponse::ok($tables->map(fn (LookupTable $table): array => $this->tableSummary($table) + ['customised' => $table->company_id !== null && in_array($table->code, $shared, true)])->all());
    }

    public function store(Request $request): JsonResponse
    {
        $this->allow('masterdata.manage');

        $data = $this->validatedTable($request, null);
        $table = LookupTable::create($data + ['company_id' => $this->context->companyId(), 'is_system' => false]);

        return ApiResponse::created($this->tableSummary($table->loadCount('values')));
    }

    public function update(Request $request, LookupTable $table): JsonResponse
    {
        $this->allow('masterdata.manage');
        $this->assertOwnTable($table);

        $table->update($this->validatedTable($request, $table));

        return ApiResponse::ok($this->tableSummary($table->loadCount('values')));
    }

    public function destroy(LookupTable $table): JsonResponse
    {
        $this->allow('masterdata.manage');
        $this->assertOwnTable($table);

        $table->delete();

        return ApiResponse::noContent();
    }

    public function values(LookupTable $table): JsonResponse
    {
        $this->allow('masterdata.manage');
        $this->assertVisibleTable($table);
        $table = $this->companyCopy($table) ?? $table;

        return ApiResponse::ok([
            'table' => $this->tableSummary($table->loadCount(['values' => fn ($q) => $q->visible()])),
            'values' => $table->values()->visible()->orderBy('sort_order')->orderBy('name')->get()
                ->map(fn (LookupValue $value): array => $this->valueSummary($value))->all(),
        ]);
    }

    public function storeValue(Request $request, LookupTable $table): JsonResponse
    {
        $this->allow('masterdata.manage');
        $this->assertVisibleTable($table);
        $table = $this->companyCopy($table) ?? $table;

        $value = $table->values()->create($this->validatedValue($request, $table, null) + ['company_id' => $this->context->companyId()]);

        return ApiResponse::created($this->valueSummary($value));
    }

    public function updateValue(Request $request, LookupTable $table, LookupValue $value): JsonResponse
    {
        $this->allow('masterdata.manage');
        [$table, $value] = $this->editable($table, $value);

        $value->update($this->validatedValue($request, $table, $value));

        return ApiResponse::ok($this->valueSummary($value));
    }

    public function destroyValue(LookupTable $table, LookupValue $value): JsonResponse
    {
        $this->allow('masterdata.manage');
        [, $value] = $this->editable($table, $value);

        $value->delete();

        return ApiResponse::noContent();
    }

    /**
     * @return array<string, mixed>
     */
    private function validatedTable(Request $request, ?LookupTable $table): array
    {
        $data = $request->validate([
            'code' => [$table === null ? 'required' : 'sometimes', 'string', 'max:48', 'regex:/^[A-Za-z0-9_\-]+$/'],
            'name' => [$table === null ? 'required' : 'sometimes', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:2000'],
            'active' => ['sometimes', 'boolean'],
        ]);

        if (isset($data['code'])) {
            $data['code'] = strtoupper($data['code']);
            $taken = LookupTable::query()->visible()->where('code', $data['code'])->when($table, fn ($q) => $q->whereKeyNot($table->id))->exists();
            if ($taken) {
                throw ValidationException::withMessages(['code' => __('access.lookup_code_taken')]);
            }
        }

        return $data;
    }

    /**
     * @return array<string, mixed>
     */
    private function validatedValue(Request $request, LookupTable $table, ?LookupValue $value): array
    {
        $data = $request->validate([
            'code' => [$value === null ? 'required' : 'sometimes', 'string', 'max:48',
                Rule::unique('lookup_values', 'code')->where('lookup_table_id', $table->id)->ignore($value?->id)],
            'name' => [$value === null ? 'required' : 'sometimes', 'string', 'max:255'],
            'name_bn' => ['nullable', 'string', 'max:255'],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:100000'],
            'active' => ['sometimes', 'boolean'],
        ]);

        if (isset($data['code'])) {
            $data['code'] = strtoupper($data['code']);
        }
        $data['sort_order'] ??= 0;

        return $data;
    }

    /** A table this company may not see is a 404, not a 403. */
    private function assertVisibleTable(LookupTable $table): void
    {
        if ($table->company_id !== null && $table->company_id !== $this->context->companyId()) {
            abort(404);
        }
    }

    /** Shared tables are read-only (403); another company's are a 404. */
    private function assertOwnTable(LookupTable $table): void
    {
        $this->assertVisibleTable($table);

        if ($table->company_id === null) {
            throw ApiException::of(ErrorCode::FORBIDDEN, __('access.lookup_system_read_only'));
        }
    }

    /**
     * The value to change, as the company's own: a shared value (or any value of a
     * shared table the company has copied) is found by its code in the company's copy.
     *
     * @return array{0: LookupTable, 1: LookupValue}
     */
    private function editable(LookupTable $table, LookupValue $value): array
    {
        $this->assertVisibleTable($table);
        if ($value->lookup_table_id !== $table->id || ($value->company_id !== null && $value->company_id !== $this->context->companyId())) {
            abort(404);
        }
        if ($table->company_id !== null || ($value->company_id !== null && $this->companyCopy($table) === null)) {
            return [$table, $value];
        }
        $copy = $this->localize($table);

        return [$copy, $copy->values()->where('code', $value->code)->firstOrFail()];
    }

    /** The company's own copy of a shared table, if it has one. */
    private function companyCopy(LookupTable $table): ?LookupTable
    {
        if ($table->company_id !== null) {
            return null;
        }

        return LookupTable::query()->where('company_id', $this->context->companyId())->where('code', $table->code)->first();
    }

    /** The company's copy of a shared table, made now if needed: every shared value copied, its own values moved in. */
    private function localize(LookupTable $table): LookupTable
    {
        return DB::transaction(function () use ($table): LookupTable {
            $company = $this->context->companyId();
            $copy = $this->companyCopy($table);
            if ($copy) {
                return $copy;
            }
            $copy = LookupTable::create(['company_id' => $company, 'code' => $table->code, 'name' => $table->name, 'description' => $table->description, 'is_system' => false, 'active' => $table->active]);
            foreach ($table->values()->whereNull('company_id')->get() as $v) {
                $copy->values()->create(['company_id' => $company, 'code' => $v->code, 'name' => $v->name, 'name_bn' => $v->name_bn, 'sort_order' => $v->sort_order, 'active' => $v->active]);
            }
            $taken = $copy->values()->pluck('code')->all();
            foreach ($table->values()->where('company_id', $company)->get() as $v) {
                $v->forceFill(['lookup_table_id' => $copy->id, 'code' => in_array($v->code, $taken, true) ? $v->code.'_2' : $v->code])->save();
            }

            return $copy;
        });
    }

    /**
     * @return array<string, mixed>
     */
    private function tableSummary(LookupTable $table): array
    {
        return [
            'id' => $table->id,
            'code' => $table->code,
            'name' => $table->name,
            'description' => $table->description,
            'is_system' => $table->company_id === null,
            'active' => $table->active,
            'values_count' => $table->values_count ?? 0,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function valueSummary(LookupValue $value): array
    {
        return [
            'id' => $value->id,
            'code' => $value->code,
            'name' => $value->name,
            'name_bn' => $value->name_bn,
            'sort_order' => $value->sort_order,
            'active' => $value->active,
            'is_system' => $value->company_id === null,
        ];
    }
}
