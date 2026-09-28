<?php

declare(strict_types=1);

namespace App\Modules\Approval\Models;

use App\Modules\Identity\Models\Role;
use App\Modules\Identity\Models\Team;
use App\Modules\Identity\Models\User;
use App\Shared\Concerns\BelongsToTenant;
use App\Shared\Models\BaseModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One step in a chain, and the conditions it applies under (SRS 14).
 *
 * The condition is data rather than code, so a factory can say "anything over
 * fifty thousand also needs the factory manager" without a deployment. Steps
 * run in sequence: a finance sign-off that can happen before the engineer has
 * looked at the job is not a workflow.
 */
class ApprovalRule extends BaseModel
{
    use BelongsToTenant;

    protected $table = 'approval_rules';

    protected $fillable = [
        'company_id', 'workflow_id', 'condition_json', 'sequence',
        'role_id', 'user_id', 'team_id', 'name',
    ];

    protected function casts(): array
    {
        return ['condition_json' => 'array', 'sequence' => 'integer'];
    }

    public function workflow(): BelongsTo
    {
        return $this->belongsTo(ApprovalWorkflow::class, 'workflow_id');
    }

    public function role(): BelongsTo
    {
        return $this->belongsTo(Role::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }

    /**
     * Whether this step applies to the values the request was raised with.
     *
     * An empty condition always applies: that is the base step every request
     * goes through, and making it explicit avoids a workflow that silently
     * approves everything because no rule matched.
     *
     * @param  array<string, mixed>  $context
     */
    public function appliesTo(array $context): bool
    {
        $conditions = $this->condition_json ?? [];

        if ($conditions === []) {
            return true;
        }

        foreach ($conditions as $field => $expected) {
            if (! $this->matches($field, $expected, $context)) {
                return false;
            }
        }

        return true;
    }

    /**
     * @param  array<string, mixed>  $context
     */
    private function matches(string $field, mixed $expected, array $context): bool
    {
        // min_cost / max_cost are inclusive bounds on the frozen context value.
        if ($field === 'min_cost') {
            return bccomp($this->money($context['cost'] ?? '0'), $this->money($expected), 4) >= 0;
        }

        if ($field === 'max_cost') {
            return bccomp($this->money($context['cost'] ?? '0'), $this->money($expected), 4) <= 0;
        }

        $actual = $context[$field] ?? null;

        if ($actual === null) {
            // A rule that names a field the context does not carry does not
            // apply. Treating a missing value as a match would attach steps to
            // requests they were never written for.
            return false;
        }

        if (is_array($expected)) {
            return in_array($actual, $expected, true);
        }

        return (string) $actual === (string) $expected;
    }

    private function money(mixed $value): string
    {
        return number_format((float) ($value ?? 0), 4, '.', '');
    }
}
