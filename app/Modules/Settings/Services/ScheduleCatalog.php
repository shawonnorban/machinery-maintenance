<?php

declare(strict_types=1);

namespace App\Modules\Settings\Services;

use App\Modules\Settings\Models\ScheduledJobRun;
use Carbon\CarbonImmutable;
use Illuminate\Console\Scheduling\Event;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Contracts\Console\Kernel as ConsoleKernel;

/**
 * The scheduler's own list of jobs, read live (Settings → Scheduled Jobs,
 * AnnotechERP 03/04), with each job's last run from the run log.
 *
 * routes/console.php is loaded by the console kernel only, so a web request
 * bootstraps it first; the list is then exactly what `schedule:run` sees.
 */
class ScheduleCatalog
{
    public function __construct(private readonly ConsoleKernel $console) {}

    /**
     * @return list<array<string, mixed>>
     */
    public function jobs(string $timezone): array
    {
        $this->console->bootstrap();

        $last = ScheduledJobRun::query()
            ->whereIn('id', ScheduledJobRun::query()->selectRaw('max(id)')->groupBy('command'))
            ->get()
            ->keyBy('command');

        return collect(app(Schedule::class)->events())
            ->map(function (Event $event) use ($last, $timezone): array {
                $name = self::nameOf($event);
                $run = $last->get($name);

                return [
                    'id' => $name,
                    'command' => $name,
                    'description' => self::describe($name),
                    'expression' => $event->expression,
                    'next_run_at' => CarbonImmutable::instance($event->nextRunDate())->setTimezone($timezone)->toIso8601String(),
                    'without_overlapping' => $event->withoutOverlapping,
                    'last_run_at' => $run?->started_at?->toIso8601String(),
                    'last_status' => $run?->status,
                    'last_duration_ms' => $run?->duration_ms,
                ];
            })
            ->sortBy('command')
            ->values()
            ->all();
    }

    /**
     * The artisan command a scheduled event runs, without the binary and its
     * quoting: "'/usr/local/bin/php' 'artisan' webhooks:retry" → "webhooks:retry".
     */
    public static function nameOf(Event $event): string
    {
        $command = (string) $event->command;
        $at = strpos($command, 'artisan');
        if ($at !== false) {
            return trim(str_replace(["'", '"'], '', substr($command, $at + strlen('artisan'))));
        }

        return $event->description ?: $command;
    }

    /** What the job is for, in the reader's language; the name itself when none is written. */
    private static function describe(string $name): string
    {
        $key = 'settings.system.jobs.'.str_replace([':', '-'], '_', explode(' ', $name)[0]);
        $text = __($key);

        return $text === $key ? $name : $text;
    }
}
