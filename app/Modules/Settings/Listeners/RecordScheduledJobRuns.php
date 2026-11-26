<?php

declare(strict_types=1);

namespace App\Modules\Settings\Listeners;

use App\Modules\Settings\Models\ScheduledJobRun;
use App\Modules\Settings\Services\ScheduleCatalog;
use Carbon\CarbonImmutable;
use Illuminate\Console\Events\ScheduledBackgroundTaskFinished;
use Illuminate\Console\Events\ScheduledTaskFailed;
use Illuminate\Console\Events\ScheduledTaskFinished;
use Illuminate\Console\Events\ScheduledTaskStarting;
use Illuminate\Console\Scheduling\Event;
use Throwable;

/**
 * Writes the scheduler's run log (Settings → Scheduled Jobs, AnnotechERP 03/04)
 * from Laravel's own scheduler events. Status, exit code and time only — never
 * the output, which could mention any company.
 *
 * A job sent to the background "finishes" the moment it is spawned; its real
 * end arrives later, from `schedule:finish` in another process, so its row
 * stays RUNNING until then.
 */
class RecordScheduledJobRuns
{
    public function onStarting(ScheduledTaskStarting $event): void
    {
        $this->quietly(fn () => ScheduledJobRun::query()->create([
            'command' => ScheduleCatalog::nameOf($event->task),
            'expression' => $event->task->expression,
            'status' => 'RUNNING',
            'started_at' => CarbonImmutable::now(),
        ]));
    }

    public function onFinished(ScheduledTaskFinished $event): void
    {
        if ($event->task->runInBackground) {
            return;
        }
        $this->finish($event->task, $event->task->exitCode);
    }

    public function onBackgroundFinished(ScheduledBackgroundTaskFinished $event): void
    {
        $this->finish($event->task, $event->task->exitCode);
    }

    public function onFailed(ScheduledTaskFailed $event): void
    {
        $this->finish($event->task, $event->task->exitCode ?? 1, failed: true);
    }

    private function finish(Event $task, ?int $exitCode, bool $failed = false): void
    {
        $this->quietly(function () use ($task, $exitCode, $failed): void {
            $run = ScheduledJobRun::query()
                ->where('command', ScheduleCatalog::nameOf($task))
                ->where('status', 'RUNNING')
                ->latest('started_at')
                ->first();
            if ($run === null) {
                return;
            }
            $now = CarbonImmutable::now();
            $run->forceFill([
                'status' => $failed || ($exitCode !== null && $exitCode !== 0) ? 'FAILED' : 'SUCCEEDED',
                'exit_code' => $exitCode,
                'finished_at' => $now,
                'duration_ms' => (int) max(0, $run->started_at->diffInMilliseconds($now)),
            ])->save();
        });
    }

    /** A broken log must never stop the job it describes. */
    private function quietly(callable $write): void
    {
        try {
            $write();
        } catch (Throwable $e) {
            report($e);
        }
    }
}
