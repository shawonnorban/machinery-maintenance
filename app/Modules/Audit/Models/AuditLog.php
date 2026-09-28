<?php

declare(strict_types=1);

namespace App\Modules\Audit\Models;

use App\Modules\Identity\Models\User;
use App\Shared\Models\BaseModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use RuntimeException;

/**
 * One recorded event (ERD Section 18, SRS 34).
 *
 * Append-only, enforced here rather than left to discipline. A row that can be
 * edited or deleted is not evidence, and the one moment somebody would want to
 * change it is exactly the moment it matters.
 *
 * Not tenant-scoped by the global scope: a failed login belongs to no company
 * yet, and the reader screen filters by company explicitly instead. Every query
 * that serves a tenant must scope itself — {@see scopeForCompany}.
 */
class AuditLog extends BaseModel
{
    /**
     * The events SRS 34 names, plus the ones the product actually raises.
     *
     * A fixed list because a typo in an action name is invisible: the row is
     * written, and the filter that should have found it silently returns
     * nothing.
     */
    public const ACTIONS = [
        'CREATED', 'UPDATED', 'DELETED', 'RESTORED',
        'STATUS_CHANGED', 'COST_CHANGED', 'PERMISSION_CHANGED',
        'APPROVAL_ACTION', 'SUBSCRIPTION_CHANGED',
        'LOGIN', 'LOGOUT', 'LOGIN_FAILED', 'PASSWORD_CHANGED',
        'SECURITY_EVENT', 'EXPORTED', 'IMPORTED',
    ];

    public const CONTEXTS = ['UI', 'API', 'JOB', 'CONSOLE', 'IMPORT', 'WEBHOOK'];

    public $timestamps = false;

    protected $table = 'audit_logs';

    protected $fillable = [
        'company_id', 'user_id', 'action', 'entity_type', 'entity_id', 'entity_label',
        'old_values_json', 'new_values_json', 'changed_fields_json',
        'ip_address', 'user_agent', 'request_id', 'context',
        'api_client_id', 'impersonated_by', 'created_at',
    ];

    protected function casts(): array
    {
        return [
            'old_values_json' => 'array',
            'new_values_json' => 'array',
            'changed_fields_json' => 'array',
            'created_at' => 'immutable_datetime',
        ];
    }

    protected static function booted(): void
    {
        static::updating(function (): void {
            throw new RuntimeException('Audit rows are append-only and cannot be updated.');
        });

        static::deleting(function (): void {
            // Retention moves old rows to cold storage; it never drops them
            // (ERD Section 18 rule 3, SRS 49.1).
            throw new RuntimeException('Audit rows are append-only and cannot be deleted.');
        });
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function impersonator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'impersonated_by');
    }

    public function scopeForCompany($query, string $companyId)
    {
        return $query->where('company_id', $companyId);
    }

    /**
     * What actually changed, as label => [before, after].
     *
     * @return array<string, array{0: mixed, 1: mixed}>
     */
    public function diff(): array
    {
        $fields = $this->changed_fields_json ?? [];
        $old = $this->old_values_json ?? [];
        $new = $this->new_values_json ?? [];

        $diff = [];

        foreach ($fields as $field) {
            $diff[$field] = [$old[$field] ?? null, $new[$field] ?? null];
        }

        return $diff;
    }
}
