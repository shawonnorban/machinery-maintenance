<?php

declare(strict_types=1);

namespace App\Modules\DyeHouse\Resources;

use App\Modules\DyeHouse\Models\DyeLabDipRecipe;
use App\Modules\Erp\Support\ResourceDefinition;
use App\Shared\Tenancy\TenantContext;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * Lab Dip Recipe Entry: one row per option tried at a buyer's shade, under the
 * recipe number of its submission — a second attempt is another option under
 * the same number, never a new number. The style must be the buyer's; the
 * colour type and colour group are read off the shade. Lab Dip Recipe Lookup
 * reads the same rows. Dye House → Lab-Dip (AnnotechERP 17/02).
 */
class DyeLabDipRecipeResource extends ResourceDefinition
{
    /** @var array<string, array<string, mixed>> lookups for present(), once per request */
    private array $names = [];

    public function key(): string
    {
        return 'dye-lab-dip-recipes';
    }

    public function model(): string
    {
        return DyeLabDipRecipe::class;
    }

    public function viewPermission(): string
    {
        return 'dyehouse.labdip.view';
    }

    public function managePermission(): string
    {
        return 'dyehouse.labdip.manage';
    }

    public function columns(): array
    {
        return ['recipe_no', 'option_no', 'buyer_id', 'style_id', 'colour_id', 'fabric_type_id', 'dye_method_id', 'fiber_composition'];
    }

    public function defaults(): array
    {
        return ['option_no' => 1];
    }

    public function query(): Builder
    {
        return DyeLabDipRecipe::query()->orderByDesc('created_at')->orderBy('recipe_no')->orderBy('option_no');
    }

    public function messages(): array
    {
        return [
            'recipe_no.required' => __('dyehouse.labdip.recipe_no_required'),
            'option_no.required' => __('dyehouse.labdip.option_required'),
            'option_no.unique' => __('dyehouse.labdip.option_taken'),
            'buyer_id.required' => __('dyehouse.labdip.buyer_required'),
            'colour_id.required' => __('dyehouse.labdip.colour_required'),
        ];
    }

    public function rules(?Model $row): array
    {
        $company = $this->company();
        $required = $row ? 'sometimes' : 'required';
        $recipeNo = request()->input('recipe_no', $row?->getAttribute('recipe_no'));

        return [
            'recipe_no' => [$required, 'string', 'max:40'],
            'option_no' => [$required, 'integer', 'min:1', 'max:999',
                Rule::unique('dye_lab_dip_recipes', 'option_no')->where('company_id', $company)->where('recipe_no', trim((string) $recipeNo))->ignore($row?->getKey()),
            ],
            'buyer_id' => [$required, Rule::exists('buyers', 'id')->where('company_id', $company)],
            'style_id' => ['nullable', Rule::exists('merch_styles', 'id')->where('company_id', $company)],
            'colour_id' => [$required, Rule::exists('colours', 'id')->where('company_id', $company)],
            'fabric_type_id' => ['nullable', Rule::exists('fabric_types', 'id')->where('company_id', $company)],
            'dye_method_id' => ['nullable', Rule::exists('dye_methods', 'id')->where('company_id', $company)],
            'fiber_composition' => ['nullable', 'string', 'max:255'],
        ];
    }

    public function beforeSave(array $data, ?Model $row): array
    {
        if (isset($data['recipe_no'])) {
            $data['recipe_no'] = trim((string) $data['recipe_no']);
        }
        // Brickland: "Style X belongs to a different buyer. Pick a style of the buyer named above."
        $buyer = $data['buyer_id'] ?? $row?->getAttribute('buyer_id');
        $style = array_key_exists('style_id', $data) ? $data['style_id'] : $row?->getAttribute('style_id');
        if ($style) {
            $owner = DB::table('merch_styles')->where('id', $style)->first(['style_ref', 'buyer_id']);
            if ($owner && $owner->buyer_id !== $buyer) {
                throw ValidationException::withMessages(['style_id' => __('dyehouse.labdip.style_other_buyer', ['style' => $owner->style_ref])]);
            }
        }
        if (! $row) {
            $data['created_by'] = $this->actingUserId();
        }

        return $data;
    }

    public function present(Model $row): array
    {
        $out = parent::present($row);
        $n = $this->names();
        $colour = $n['colours'][$row->getAttribute('colour_id')] ?? null;
        $out['ref'] = $row->getAttribute('recipe_no').'-'.$row->getAttribute('option_no');
        $out['buyer'] = $n['buyers'][$row->getAttribute('buyer_id')] ?? null;
        $out['style'] = $n['styles'][$row->getAttribute('style_id')] ?? null;
        $out['colour'] = $colour?->name;
        $out['colour_type'] = $colour?->colour_type;
        $out['colour_group'] = $colour && $colour->colour_group_id ? ($n['groups'][$colour->colour_group_id] ?? null) : null;
        $out['fabric_type'] = $n['fabrics'][$row->getAttribute('fabric_type_id')] ?? null;
        $out['dye_method'] = $n['methods'][$row->getAttribute('dye_method_id')] ?? null;
        $out['created_by'] = $n['users'][$row->getAttribute('created_by')] ?? null;

        return $out;
    }

    /** @return array<string, array<string, mixed>> */
    private function names(): array
    {
        if ($this->names) {
            return $this->names;
        }
        $company = $this->company();

        return $this->names = [
            'buyers' => DB::table('buyers')->where('company_id', $company)->pluck('short_name', 'id')->all(),
            'styles' => DB::table('merch_styles')->where('company_id', $company)->pluck('style_ref', 'id')->all(),
            'colours' => DB::table('colours')->where('company_id', $company)->get(['id', 'name', 'colour_type', 'colour_group_id'])->keyBy('id')->all(),
            'groups' => DB::table('dye_colour_groups')->where('company_id', $company)->pluck('name', 'id')->all(),
            'fabrics' => DB::table('fabric_types')->where('company_id', $company)->pluck('name', 'id')->all(),
            'methods' => DB::table('dye_methods')->where('company_id', $company)->pluck('name', 'id')->all(),
            'users' => DB::table('users')->whereIn('id', DyeLabDipRecipe::query()->whereNotNull('created_by')->distinct()->pluck('created_by'))->pluck('name', 'id')->all(),
        ];
    }

    private function company(): string
    {
        return app(TenantContext::class)->companyId();
    }
}
