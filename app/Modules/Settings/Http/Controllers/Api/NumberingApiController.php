<?php

declare(strict_types=1);

namespace App\Modules\Settings\Http\Controllers\Api;

use App\Modules\Settings\Actions\SaveNumberFormat;
use App\Modules\Settings\Models\NumberSequence;
use App\Modules\Settings\Models\NumberSequenceFormat;
use App\Modules\Settings\Services\NumberSequenceGenerator;
use App\Modules\Tenancy\Models\Factory;
use App\Shared\Http\Api\ApiController;
use App\Shared\Http\Api\ApiResponse;
use App\Shared\Tenancy\TenantContext;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

/**
 * How this company numbers its documents, over the wire (SRS 52; mirrors the
 * web `NumberingController`, which this delegates every write to unchanged
 * per ADR-003).
 *
 * Same reasoning as the web screen for what's in each row: the format alone
 * doesn't tell an admin whether changing it is safe, so each row also carries
 * a live sample of the next number and how many documents this type has
 * already issued.
 */
class NumberingApiController extends ApiController
{
    public function __construct(
        private readonly TenantContext $context,
        private readonly NumberSequenceGenerator $numbers,
    ) {}

    public function index(): JsonResponse
    {
        $this->allow('settings.numbering.manage');

        $factory = Factory::whereIn('id', $this->context->accessibleFactoryIds())
            ->orderBy('name')
            ->first();

        $counters = NumberSequence::query()
            ->orderByDesc('period_key')
            ->get()
            ->groupBy('document_type');

        $rows = [];

        foreach (array_keys(NumberSequenceGenerator::FORMATS) as $documentType) {
            $config = $this->numbers->configFor($documentType);
            $default = NumberSequenceGenerator::FORMATS[$documentType];

            $rows[] = [
                'document_type' => $documentType,
                'format' => $config['format'],
                'padding' => $config['padding'],
                'reset' => $config['reset'],
                'is_default' => $config['format'] === $default['format']
                    && $config['padding'] === $default['padding'],
                'default_format' => $default['format'],
                'sample' => $this->numbers->sample(
                    $config['format'],
                    $config['padding'],
                    $factory,
                    $this->nextValue($counters[$documentType] ?? collect(), $documentType, $factory),
                ),
                'issued' => ($counters[$documentType] ?? collect())->sum('current_value'),
            ];
        }

        return ApiResponse::ok($rows, [
            'overrides' => NumberSequenceFormat::pluck('document_type')->all(),
        ]);
    }

    public function update(Request $request, string $documentType, SaveNumberFormat $action): JsonResponse
    {
        $this->allow('settings.numbering.manage');

        $data = $request->validate([
            'format' => ['required', 'string', 'max:128'],
            'padding' => ['required', 'integer', 'min:1', 'max:10'],
        ]);

        $action->handle($documentType, $data['format'], $data['padding'], $this->caller()->auditUserId());

        return ApiResponse::ok(['document_type' => $documentType]);
    }

    public function reset(string $documentType, SaveNumberFormat $action): JsonResponse
    {
        $this->allow('settings.numbering.manage');

        $action->reset($documentType);

        return ApiResponse::noContent();
    }

    /** @param  Collection<int, NumberSequence>  $counters */
    private function nextValue(Collection $counters, string $documentType, ?Factory $factory): int
    {
        $config = NumberSequenceGenerator::FORMATS[$documentType];
        $now = CarbonImmutable::now($factory?->timezone ?? 'UTC');

        $period = match ($config['reset']) {
            'MONTHLY' => $now->format('Y-m'),
            'YEARLY' => $now->format('Y'),
            default => 'ALL',
        };

        $factoryId = str_contains($config['format'], '{FACTORY}') ? $factory?->id : null;

        $current = $counters
            ->first(fn (NumberSequence $row): bool => $row->period_key === $period
                && $row->factory_id === $factoryId);

        return ($current->current_value ?? 0) + 1;
    }
}
