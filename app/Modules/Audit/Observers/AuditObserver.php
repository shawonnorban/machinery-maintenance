<?php

declare(strict_types=1);

namespace App\Modules\Audit\Observers;

use App\Modules\Audit\Services\AuditRecorder;
use Illuminate\Database\Eloquent\Model;

/**
 * Turns model writes into audit rows (SRS 34).
 *
 * Attached from the audit module's provider to a named list of models rather
 * than by editing twenty model classes. The direction matters: audit knows
 * about the domain, and the domain does not know it is being audited. A trait
 * on every model would mean every module carrying a dependency on this one, and
 * a model added later silently missing the trait.
 */
class AuditObserver
{
    public function __construct(private readonly AuditRecorder $recorder) {}

    public function created(Model $model): void
    {
        $this->recorder->record('CREATED', $model, null, $model->getAttributes());
    }

    public function updated(Model $model): void
    {
        // getOriginal for the before, getChanges for the after: recording the
        // full row twice would make every diff a wall of unchanged values.
        $changes = $model->getChanges();

        $before = [];

        foreach (array_keys($changes) as $field) {
            $before[$field] = $model->getOriginal($field);
        }

        $this->recorder->record($this->actionFor($model, $changes), $model, $before, $changes);
    }

    public function deleted(Model $model): void
    {
        $this->recorder->record('DELETED', $model, $model->getOriginal(), null);
    }

    public function restored(Model $model): void
    {
        $this->recorder->record('RESTORED', $model, null, $model->getAttributes());
    }

    /**
     * Name the change for what it is.
     *
     * A status change and a cost change are called out by SRS 34 as their own
     * events, and somebody investigating "who put this machine back into
     * service" should not have to read the diff of every UPDATED row to find
     * them.
     *
     * @param  array<string, mixed>  $changes
     */
    private function actionFor(Model $model, array $changes): string
    {
        $fields = array_keys($changes);

        if (in_array('status', $fields, true)) {
            return 'STATUS_CHANGED';
        }

        foreach (['amount', 'base_amount', 'actual_cost', 'value', 'settled_amount', 'unit_cost'] as $money) {
            if (in_array($money, $fields, true)) {
                return 'COST_CHANGED';
            }
        }

        if (in_array('role_id', $fields, true) || in_array('permissions', $fields, true)) {
            return 'PERMISSION_CHANGED';
        }

        return 'UPDATED';
    }
}
