<?php

declare(strict_types=1);

namespace App\Modules\Merchandise\Resources;

use App\Modules\Merchandise\Models\Buyer;
use App\Modules\Merchandise\Models\TrimSpecControl;
use App\Modules\Merchandise\Models\TrimSpecItemRule;
use App\Modules\Merchandise\Models\TrimSpecTemplate;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * Trims & Accessories Spec Setup: specification templates, where each applies, and the columns its sheet carries. Merchandise Desk → Master Data (AnnotechERP 06/01).
 */
class TrimSpecTemplateResource extends MasterResource
{
    public function key(): string
    {
        return 'trim-spec-templates';
    }

    public function model(): string
    {
        return TrimSpecTemplate::class;
    }

    public function columns(): array
    {
        return ['code', 'name', 'active'];
    }

    /** @var array{rules: list<array<string, mixed>>|null, controls: list<array<string, mixed>>|null}|null */
    private ?array $lines = null;

    public function rules(?Model $row): array
    {
        $required = $row ? 'sometimes' : 'required';
        $company = $this->company();

        return [
            'code' => [$required, 'string', 'max:32', $this->uniqueCode($row)],
            'name' => [$required, 'string', 'max:255'],
            'active' => ['sometimes', 'boolean'],
            // The New tab names the template; Spec. Configuration adds its lines.
            'item_rules' => ['sometimes', 'array', 'max:50'],
            'item_rules.*.item_category' => ['nullable', 'string', 'max:255'],
            'item_rules.*.item_name' => ['nullable', 'string', 'max:255'],
            'item_rules.*.buyer_id' => ['nullable', Rule::exists('buyers', 'id')->where('company_id', $company)],
            'controls' => ['sometimes', 'array', 'max:50'],
            'controls.*.name' => ['required', 'string', 'max:255', 'distinct'],
            'controls.*.control_type' => ['required', Rule::in(TrimSpecControl::TYPES)],
            'controls.*.options' => ['nullable', 'string', 'max:255'],
            'controls.*.required' => ['sometimes', 'boolean'],
        ];
    }

    public function beforeSave(array $data, ?Model $row): array
    {
        $data = parent::beforeSave($data, $row);
        if (array_key_exists('item_rules', $data) || array_key_exists('controls', $data)) {
            foreach ($data['controls'] ?? [] as $i => $control) {
                if ($control['control_type'] === 'SELECT' && blank($control['options'] ?? null)) {
                    throw ValidationException::withMessages(["controls.{$i}.options" => __('merchandise.master.select_needs_options')]);
                }
            }
            // A list that is sent replaces what is there; one left out stays as it is.
            $this->lines = ['rules' => $data['item_rules'] ?? null, 'controls' => $data['controls'] ?? null];
            unset($data['item_rules'], $data['controls']);
        }

        return $data;
    }

    public function afterSave(Model $row, bool $created): void
    {
        if ($this->lines === null) {
            return;
        }
        $company = $row->getAttribute('company_id');
        if ($this->lines['rules'] !== null) {
            TrimSpecItemRule::query()->where('trim_spec_template_id', $row->getKey())->delete();
            foreach ($this->lines['rules'] as $rule) {
                TrimSpecItemRule::query()->create(['company_id' => $company, 'trim_spec_template_id' => $row->getKey(), 'item_category' => $rule['item_category'] ?? null, 'item_name' => $rule['item_name'] ?? null, 'buyer_id' => $rule['buyer_id'] ?? null]);
            }
        }
        if ($this->lines['controls'] !== null) {
            TrimSpecControl::query()->where('trim_spec_template_id', $row->getKey())->delete();
            foreach (array_values($this->lines['controls']) as $i => $control) {
                TrimSpecControl::query()->create(['company_id' => $company, 'trim_spec_template_id' => $row->getKey(), 'name' => $control['name'], 'control_type' => $control['control_type'], 'options' => $control['options'] ?? null, 'required' => (bool) ($control['required'] ?? false), 'sort_order' => ($i + 1) * 10]);
            }
        }
        $this->lines = null;
    }

    public function present(Model $row): array
    {
        $out = parent::present($row);
        $buyers = Buyer::query()->pluck('short_name', 'id');
        $out['item_rules'] = TrimSpecItemRule::query()->where('trim_spec_template_id', $row->getKey())->get()
            ->map(fn (TrimSpecItemRule $r): array => ['item_category' => $r->item_category, 'item_name' => $r->item_name, 'buyer_id' => $r->buyer_id, 'buyer' => $r->buyer_id ? ($buyers[$r->buyer_id] ?? null) : null])->all();
        $out['controls'] = TrimSpecControl::query()->where('trim_spec_template_id', $row->getKey())->orderBy('sort_order')->get()
            ->map(fn (TrimSpecControl $c): array => ['name' => $c->name, 'control_type' => $c->control_type, 'options' => $c->options, 'required' => $c->required])->all();
        $out['rule_count'] = count($out['item_rules']);
        $out['control_count'] = count($out['controls']);

        return $out;
    }
}
