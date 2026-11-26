<?php

declare(strict_types=1);

namespace App\Modules\Erp\Support;

use App\Modules\Api\Support\ApiCaller;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * One Brickland "Records + New X" screen's backend, as a definition
 * (AnnotechERP screen kit): the model, who may see and change it, the rules a
 * row must meet, and how a row is shown. `ResourceApiController` turns it into
 * list / show / create / update / delete under `/api/v1/erp/{key}`.
 *
 * Most of Brickland's screens are exactly this shape, so a new one is a class
 * here plus a screen config on the frontend — not a new controller, routes,
 * and the tenant checks each would have to remember.
 */
abstract class ResourceDefinition
{
    /** The url key, e.g. `report-templates`. */
    abstract public function key(): string;

    /** @return class-string<Model> A tenant-owned (BelongsToTenant) model. */
    abstract public function model(): string;

    /** The permission that opens the screen. */
    abstract public function viewPermission(): string;

    /** The permission that changes it; the view permission by default. */
    public function managePermission(): string
    {
        return $this->viewPermission();
    }

    /**
     * Validation for a new row (`$row` null) or an edit.
     *
     * @return array<string, mixed>
     */
    abstract public function rules(?Model $row): array;

    /**
     * Custom messages, e.g. Brickland's "Template Code is required."
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [];
    }

    /**
     * The columns a row is shown with; `id`, `created_at` and `updated_at`
     * come along anyway.
     *
     * @return list<string>
     */
    abstract public function columns(): array;

    /** Values a new row starts with when the request leaves them out. @return array<string, mixed> */
    public function defaults(): array
    {
        return [];
    }

    /** Booleans the request may send as "1"/"0" or true/false. @return list<string> */
    public function booleans(): array
    {
        return [];
    }

    public function query(): Builder
    {
        $model = $this->model();

        return $model::query()->latest('created_at');
    }

    /**
     * Where a single row is looked up for show, edit and delete. The model's
     * own query by default, which its tenant scope narrows; a model shared
     * with the platform (units of measure) narrows it to the company's rows.
     */
    public function rowQuery(): Builder
    {
        $model = $this->model();

        return $model::query();
    }

    /**
     * The row as the screen shows it.
     *
     * @return array<string, mixed>
     */
    public function present(Model $row): array
    {
        $out = ['id' => $row->getKey()];
        foreach ($this->columns() as $column) {
            $value = $row->getAttribute($column);
            $out[$column] = $value instanceof \DateTimeInterface ? $value->format('Y-m-d') : $value;
        }
        $out['created_at'] = $row->created_at?->toIso8601String();
        $out['updated_at'] = $row->updated_at?->toIso8601String();

        return $out;
    }

    /**
     * The last say over what is stored (normalise a code, stamp who did it).
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public function beforeSave(array $data, ?Model $row): array
    {
        return $data;
    }

    /** After a create or an edit (send a broadcast, clear another default). */
    public function afterSave(Model $row, bool $created): void {}

    /** Refuse a delete by throwing; nothing by default. */
    public function beforeDelete(Model $row): void {}

    /** The signed-in user acting on the screen, for "raised by" and the like. */
    protected function actingUserId(): ?string
    {
        return app(ApiCaller::class)->auditUserId();
    }

    /** Whether rows may be deleted at all. */
    public function deletable(): bool
    {
        return true;
    }
}
