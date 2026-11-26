<?php

declare(strict_types=1);

namespace App\Modules\CommandCenter\Resources;

use App\Modules\CommandCenter\Models\DrillPath;
use App\Modules\CommandCenter\Support\CommandBook;
use App\Modules\Erp\Support\DemoResource;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\Rule;

/**
 * Metric Drill-down Explorer (AnnotechERP 27/01): how a headline number opens
 * up — the metric, its domain, the three levels it may be broken down by
 * (coarse to fine), the module its rows come from, the finest grain kept and
 * how many rows lie behind it (in thousands). A metric has one path.
 */
class DrillPathResource extends DemoResource
{
    protected function prefix(): string
    {
        return 'commandcenter';
    }

    protected function area(): string
    {
        return 'command';
    }

    public function key(): string
    {
        return 'cc-drill-paths';
    }

    public function model(): string
    {
        return DrillPath::class;
    }

    public function columns(): array
    {
        return ['metric', 'domain', 'level_1', 'level_2', 'level_3', 'source', 'grain', 'rows_k'];
    }

    public function defaults(): array
    {
        return ['rows_k' => 0];
    }

    public function rules(?Model $row): array
    {
        return [
            'metric' => [$row ? 'sometimes' : 'required', 'string', 'max:160', $this->once($row, 'metric', 'commandcenter.v.metric_taken')],
            'domain' => ['nullable', Rule::in(CommandBook::DOMAINS)],
            'level_1' => ['nullable', Rule::in(CommandBook::LEVEL_1)],
            'level_2' => ['nullable', Rule::in(CommandBook::LEVEL_2)],
            'level_3' => ['nullable', Rule::in(CommandBook::LEVEL_3)],
            'source' => ['nullable', Rule::in(CommandBook::SOURCES)],
            'grain' => ['nullable', Rule::in(CommandBook::GRAINS)],
            'rows_k' => $this->count(),
        ];
    }

    public function messages(): array
    {
        return ['metric.required' => __('commandcenter.v.metric')];
    }

    public function beforeSave(array $data, ?Model $row): array
    {
        if (isset($data['metric'])) {
            $data['metric'] = trim($data['metric']);
        }

        return $data;
    }
}
