<?php

declare(strict_types=1);

namespace App\Modules\Settings\Http\Controllers\Web;

use App\Modules\Settings\Actions\DeleteMasterDataRow;
use App\Modules\Settings\Actions\SaveMasterDataRow;
use App\Modules\Settings\MasterData\Field;
use App\Modules\Settings\MasterData\MasterDataRegistry;
use App\Modules\Settings\MasterData\MasterDataType;
use App\Modules\Tenancy\Models\Factory;
use App\Shared\Http\Controllers\Controller;
use App\Shared\Tenancy\TenantContext;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\View\View;

/**
 * The reference data screens (SRS 6).
 *
 * One controller for a dozen lists. They differ only in their columns, and
 * twelve near-identical controllers would be twelve places for the tenant
 * check to be forgotten in one of them.
 */
class MasterDataController extends Controller
{
    public function __construct(
        private readonly MasterDataRegistry $registry,
        private readonly TenantContext $context,
    ) {}

    public function index(Request $request): View
    {
        $this->authorizeMasterData($request);

        return view('settings::master-data.index', [
            'grouped' => $this->registry->grouped(),
            'counts' => $this->counts(),
        ]);
    }

    public function show(Request $request, string $type): View
    {
        $this->authorizeMasterData($request);

        $masterType = $this->type($type);

        return view('settings::master-data.show', [
            'type' => $masterType,
            'rows' => $masterType->rows(),
            'references' => $this->referenceOptions($masterType),
            'editing' => $request->query('edit')
                ? $masterType->query()->find($request->query('edit'))
                : null,
            // A platform row cannot be edited, but it can be the starting point
            // for the company's own version of it. Without this the only thing
            // to do with the seeded list is retype it.
            'prefill' => $request->query('copy')
                ? $masterType->query()->find($request->query('copy'))
                : null,
        ]);
    }

    public function store(Request $request, string $type, SaveMasterDataRow $action): RedirectResponse
    {
        $this->authorizeMasterData($request);

        $masterType = $this->type($type);

        $action->create($masterType, $request->all());

        return redirect()
            ->route('app.settings.master-data.show', $masterType->key())
            ->with('status', __('masterdata.created'));
    }

    public function update(Request $request, string $type, string $row, SaveMasterDataRow $action): RedirectResponse
    {
        $this->authorizeMasterData($request);

        $masterType = $this->type($type);

        $action->update($masterType, $this->row($masterType, $row), $request->all());

        return redirect()
            ->route('app.settings.master-data.show', $masterType->key())
            ->with('status', __('masterdata.updated'));
    }

    public function toggle(Request $request, string $type, string $row, SaveMasterDataRow $action): RedirectResponse
    {
        $this->authorizeMasterData($request);

        $masterType = $this->type($type);
        $model = $this->row($masterType, $row);

        $action->setActive($masterType, $model, ! $model->getAttribute('active'));

        return back()->with('status', __('masterdata.updated'));
    }

    public function destroy(Request $request, string $type, string $row, DeleteMasterDataRow $action): RedirectResponse
    {
        $this->authorizeMasterData($request);

        $masterType = $this->type($type);

        $action->handle($masterType, $this->row($masterType, $row));

        return redirect()
            ->route('app.settings.master-data.show', $masterType->key())
            ->with('status', __('masterdata.deleted'));
    }

    private function type(string $key): MasterDataType
    {
        return $this->registry->find($key) ?? abort(404);
    }

    /**
     * Scoped to what the company can see, so a ulid from another tenant is a
     * 404 rather than an edit form.
     */
    private function row(MasterDataType $type, string $id): Model
    {
        return $type->query()->findOrFail($id);
    }

    /**
     * The options behind every reference field on this type's form.
     *
     * Flattened to id/label/code here rather than in the view, because which
     * column carries the name differs by type and a template is the wrong
     * place to work that out.
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

                // Inactive parents are excluded: offering a category nobody is
                // supposed to use any more is how it comes back into use.
                if ($parent->supportsActive()) {
                    $query->where('active', true);
                }

                $options[$field->name] = $this->asOptions($query->get(), $parent->displayColumn());
            }

            if ($field->type === Field::BELONGS_TO) {
                $model = (string) $field->model;

                $query = $model::query()->orderBy('name');

                // Only factories this user can reach. Listing the rest would
                // leak the shape of the estate.
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
        return $rows->map(fn (Model $row) => [
            'id' => (string) $row->getKey(),
            'label' => (string) $row->getAttribute($labelColumn),
            'code' => (string) $row->getAttribute('code'),
        ])->all();
    }

    /**
     * @return array<string, int>
     */
    private function counts(): array
    {
        $counts = [];

        foreach ($this->registry->all() as $key => $type) {
            $counts[$key] = $type->query()->count();
        }

        return $counts;
    }

    private function authorizeMasterData(Request $request): void
    {
        if (! $request->user()->can(MasterDataRegistry::PERMISSION)) {
            abort(403);
        }
    }
}
