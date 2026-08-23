<?php

declare(strict_types=1);

namespace App\Modules\Settings\Actions;

use App\Modules\Settings\MasterData\Field;
use App\Modules\Settings\MasterData\MasterDataRegistry;
use App\Modules\Settings\MasterData\MasterDataType;
use App\Modules\Tenancy\Models\Factory;
use App\Shared\Tenancy\TenantContext;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * Creates or edits one reference row (SRS 6, Seed Catalog 1).
 *
 * The one place the rules live, so the screen, an import and a future API
 * cannot each enforce a different set (ADR-066).
 *
 * Two of those rules are not obvious:
 *
 * A platform row is not editable. It carries a null company_id and is shared
 * by every tenant on the platform, so "renaming it" would rename it for
 * strangers. A company that wants its own wording adds its own row.
 *
 * A code has to be unique across everything the company can see, not merely
 * unique in its own rows. Imports and cost posting resolve master data by
 * code and take the first match; a company code that shadows a platform code
 * would make which row they get depend on row order.
 */
class SaveMasterDataRow
{
    public function __construct(
        private readonly TenantContext $context,
        private readonly MasterDataRegistry $registry,
    ) {}

    /**
     * @param  array<string, mixed>  $input
     */
    public function create(MasterDataType $type, array $input): Model
    {
        $data = $this->validate($type, $input, null);

        $model = $type->model();

        /** @var Model $row */
        $row = new $model;

        // Always the acting company. A row created here is the company's own,
        // never another platform row.
        $row->fill($data + ['company_id' => $this->context->companyId()]);
        $row->save();

        return $row->fresh();
    }

    /**
     * @param  array<string, mixed>  $input
     */
    public function update(MasterDataType $type, Model $row, array $input): Model
    {
        $this->assertOwned($row);

        $row->fill($this->validate($type, $input, $row));
        $row->save();

        return $row->fresh();
    }

    /**
     * Deactivating rather than deleting is the normal way out of a row that
     * turned out to be wrong: it disappears from every picker while the
     * breakdowns and work orders already filed against it keep reading
     * correctly.
     */
    public function setActive(MasterDataType $type, Model $row, bool $active): Model
    {
        $this->assertOwned($row);

        $row->forceFill(['active' => $active])->save();

        return $row->fresh();
    }

    public function assertOwned(Model $row): void
    {
        if ($row->getAttribute('company_id') === null) {
            throw ValidationException::withMessages([
                'code' => __('masterdata.platform_row_read_only'),
            ])->status(403);
        }

        if ($row->getAttribute('company_id') !== $this->context->companyId()) {
            // Another company's row is not theirs to see, let alone edit.
            abort(404);
        }
    }

    /**
     * @param  array<string, mixed>  $input
     * @return array<string, mixed>
     */
    private function validate(MasterDataType $type, array $input, ?Model $existing): array
    {
        $rules = [];
        $data = [];

        foreach ($type->fields() as $field) {
            $rules[$field->name] = $this->rulesFor($type, $field, $existing);
            $data[$field->name] = $this->valueFor($field, $input, $existing);
        }

        return Validator::make($data, $rules, [], $this->attributeNames($type))->validate();
    }

    /**
     * @return list<mixed>
     */
    private function rulesFor(MasterDataType $type, Field $field, ?Model $existing): array
    {
        $rules = $field->rules;

        if ($field->type === Field::CODE) {
            $rules[] = $this->uniqueCodeRule($type, $existing);
        }

        if ($field->type === Field::ENUM) {
            $rules[] = Rule::in($field->options);
        }

        if ($field->type === Field::REFERENCE) {
            $rules[] = $this->visibleReferenceRule($field);
        }

        if ($field->type === Field::BELONGS_TO) {
            $rules[] = $this->visibleModelRule($field);
        }

        return $rules;
    }

    /**
     * Unique among the rows this company can see — its own and the platform's.
     */
    private function uniqueCodeRule(MasterDataType $type, ?Model $existing): \Closure
    {
        return function (string $attribute, mixed $value, \Closure $fail) use ($type, $existing): void {
            $query = $type->query()->where('code', (string) $value);

            if ($existing !== null) {
                $query->whereKeyNot($existing->getKey());
            }

            if ($query->exists()) {
                $fail(__('masterdata.code_taken'));
            }
        };
    }

    /**
     * A reference must point at a row of the right type that this company can
     * actually see. Without the check, a ulid copied out of another tenant's
     * screen would be accepted and silently link the two companies' data.
     */
    private function visibleReferenceRule(Field $field): \Closure
    {
        return function (string $attribute, mixed $value, \Closure $fail) use ($field): void {
            if ($value === null || $value === '') {
                return;
            }

            $parent = $this->registry->find((string) $field->reference);

            if ($parent === null || ! $parent->query()->whereKey($value)->exists()) {
                $fail(__('masterdata.unknown_reference'));
            }
        };
    }

    /**
     * A pointer at something with its own screen — a factory, say.
     *
     * Tenant-scoped models, so a row belonging to another company is simply
     * not found. A factory is narrowed further to the ones this user can
     * reach, because a factory manager configuring their own floor has no
     * business hanging a building off somebody else's plant.
     */
    private function visibleModelRule(Field $field): \Closure
    {
        return function (string $attribute, mixed $value, \Closure $fail) use ($field): void {
            if (! filled($value)) {
                return;
            }

            $model = (string) $field->model;

            $exists = $model::query()->whereKey($value)->exists();

            if ($model === Factory::class) {
                $exists = $exists && $this->context->canAccessFactory((string) $value);
            }

            if (! $exists) {
                $fail(__('masterdata.unknown_reference'));
            }
        };
    }

    /**
     * @param  array<string, mixed>  $input
     */
    private function valueFor(Field $field, array $input, ?Model $existing): mixed
    {
        if ($field->type === Field::BOOLEAN) {
            // An unchecked box sends nothing at all, which is not the same as
            // "leave it alone" on a form that shows every field.
            return array_key_exists($field->name, $input)
                ? filter_var($input[$field->name], FILTER_VALIDATE_BOOLEAN)
                : false;
        }

        $value = $input[$field->name] ?? $existing?->getAttribute($field->name);

        if ($value === '' || $value === null) {
            return null;
        }

        // Normalised before validation, not after: the rule is that a code is
        // uppercase, not that the person typing it has to be. Checking first
        // and normalising afterwards rejects "sunstar" and then stores
        // "SUNSTAR" for anyone who happened to type it that way.
        if ($field->type === Field::CODE) {
            return strtoupper(trim((string) $value));
        }

        return $value;
    }

    /**
     * @return array<string, string>
     */
    private function attributeNames(MasterDataType $type): array
    {
        $names = [];

        foreach ($type->fields() as $field) {
            $names[$field->name] = $field->label();
        }

        return $names;
    }
}
