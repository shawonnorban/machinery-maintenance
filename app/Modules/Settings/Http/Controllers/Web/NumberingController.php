<?php

declare(strict_types=1);

namespace App\Modules\Settings\Http\Controllers\Web;

use App\Modules\Settings\Actions\SaveNumberFormat;
use App\Modules\Settings\Models\NumberSequence;
use App\Modules\Settings\Models\NumberSequenceFormat;
use App\Modules\Settings\Services\NumberSequenceGenerator;
use App\Modules\Tenancy\Models\Factory;
use App\Shared\Http\Controllers\Controller;
use App\Shared\Tenancy\TenantContext;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\View\View;

/**
 * How this company numbers its documents (SRS 52).
 *
 * The screen the `settings.numbering.manage` permission has been naming since
 * the permission list was written. Until now the formats were a constant in a
 * service class, which meant a customer whose auditor expects
 * WO/DHK/25-08/0001 had to be told no.
 *
 * What it shows alongside each format matters as much as the field: the
 * counter's current value for the period in progress. A format is easy to
 * change and impossible to un-change for numbers already issued, so somebody
 * about to edit one should be able to see how many documents already carry the
 * old shape.
 */
class NumberingController extends Controller
{
    public function __construct(
        private readonly TenantContext $context,
        private readonly NumberSequenceGenerator $numbers,
    ) {}

    public function index(Request $request): View
    {
        $this->authorizeNumbering($request);

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
                // What the next document of this kind would actually be
                // called, in the factory this person can see and in the period
                // running now. Answering with a real sample beats explaining
                // the placeholders.
                'sample' => $this->numbers->sample(
                    $config['format'],
                    $config['padding'],
                    $factory,
                    $this->nextValue($counters[$documentType] ?? collect(), $documentType, $factory),
                ),
                // Across every factory and every period: this is "how many
                // documents already carry a number", which is what somebody
                // about to change the format needs to weigh.
                'issued' => ($counters[$documentType] ?? collect())->sum('current_value'),
            ];
        }

        return view('settings::numbering.index', [
            'rows' => $rows,
            'overrides' => NumberSequenceFormat::pluck('document_type')->all(),
        ]);
    }

    /**
     * The value the next document would get, for the sample.
     *
     * Matched to the same factory and period the generator would use, because
     * a sample taken from another factory's counter is a number that will
     * never be issued.
     *
     * @param  Collection<int, NumberSequence>  $counters
     */
    private function nextValue(Collection $counters, string $documentType, ?Factory $factory): int
    {
        $config = NumberSequenceGenerator::FORMATS[$documentType];
        $now = CarbonImmutable::now($factory?->timezone ?? 'UTC');

        $period = match ($config['reset']) {
            'MONTHLY' => $now->format('Y-m'),
            'YEARLY' => $now->format('Y'),
            default => 'ALL',
        };

        // The factory is only part of the key for the types allocated per
        // factory; the rest carry a null factory whatever is on screen.
        $factoryId = str_contains($config['format'], '{FACTORY}') ? $factory?->id : null;

        $current = $counters
            ->first(fn (NumberSequence $row): bool => $row->period_key === $period
                && $row->factory_id === $factoryId);

        return ($current->current_value ?? 0) + 1;
    }

    public function update(Request $request, string $documentType, SaveNumberFormat $action): RedirectResponse
    {
        $this->authorizeNumbering($request);

        $data = $request->validate([
            'format' => ['required', 'string', 'max:128'],
            'padding' => ['required', 'integer', 'min:1', 'max:10'],
        ]);

        $action->handle($documentType, $data['format'], $data['padding'], $request->user()->id);

        return back()->with('status', __('numbering.saved', [
            'type' => __('numbering.types.'.$documentType),
        ]));
    }

    public function reset(Request $request, string $documentType, SaveNumberFormat $action): RedirectResponse
    {
        $this->authorizeNumbering($request);

        $action->reset($documentType);

        return back()->with('status', __('numbering.reset_done', [
            'type' => __('numbering.types.'.$documentType),
        ]));
    }

    private function authorizeNumbering(Request $request): void
    {
        if (! $request->user()->can('settings.numbering.manage')) {
            abort(403);
        }
    }
}
