<?php

declare(strict_types=1);

namespace App\Modules\Audit\Services;

use App\Modules\Audit\Jobs\WriteAuditEntry;
use App\Shared\Tenancy\TenantContext;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

/**
 * Writes the audit trail (SRS 34, ERD Section 18).
 *
 * Two rules shape this class.
 *
 * An audit write never blocks the business transaction, and never rolls back
 * with one either. The row is dispatched after commit: a job that ran inside
 * the transaction could read a state that never existed, and one that ran
 * before it could record work that was then undone.
 *
 * Nothing sensitive is stored. Password hashes, tokens, secrets and MFA
 * material are stripped before the payload leaves this class — an audit log
 * that leaks credentials turns the safest table in the system into the most
 * dangerous one (ERD Section 18 rule 2).
 */
class AuditRecorder
{
    /**
     * Never recorded, whatever model they appear on.
     *
     * Matched as substrings, because the same secret arrives under a dozen
     * names — password, password_hash, remember_token, api_token, secret,
     * previous_secret — and an exact list is a list somebody will forget to
     * extend.
     */
    private const REDACTED = [
        'password', 'token', 'secret', 'mfa', 'otp', 'signature', 'private_key',
    ];

    /** Not worth recording: noise on every row, meaning on none. */
    private const IGNORED = ['updated_at', 'created_at', 'version'];

    public function __construct(private readonly TenantContext $context) {}

    /**
     * Record a change to a model.
     *
     * @param  array<string, mixed>|null  $old
     * @param  array<string, mixed>|null  $new
     */
    public function record(
        string $action,
        ?Model $entity = null,
        ?array $old = null,
        ?array $new = null,
        ?string $companyId = null,
        ?string $userId = null,
        ?string $label = null,
        bool $inheritCompany = true,
    ): void {
        // Diffed before redaction, then redacted. The other order makes a
        // password change look like no change at all — both sides read
        // "[redacted]" — and the one security event most worth recording
        // disappears.
        $changed = $this->changedFields($old, $new);

        $old = $old === null ? null : $this->clean($old);
        $new = $new === null ? null : $this->clean($new);

        if ($action === 'UPDATED' && $changed === []) {
            // A save that changed nothing is not an event. Recording it fills
            // the trail with rows that say "somebody pressed save".
            return;
        }

        WriteAuditEntry::dispatch([
            // An event may belong to no company at all — a failed sign-in for
            // an address nobody recognises — and inheriting whatever tenant
            // happened to be resolved would file it under the wrong one.
            'company_id' => $companyId ?? ($inheritCompany ? $this->companyIdFor($entity) : null),
            'user_id' => $userId ?? auth()->id(),
            'action' => $action,
            'entity_type' => $entity === null ? null : $this->typeOf($entity),
            'entity_id' => $entity?->getKey(),
            'entity_label' => $label ?? $this->labelFor($entity),
            'old_values_json' => $old,
            'new_values_json' => $new,
            'changed_fields_json' => $changed === [] ? null : $changed,
            'ip_address' => request()?->ip(),
            'user_agent' => Str::limit((string) request()?->userAgent(), 500, ''),
            'request_id' => request()?->attributes?->get('request_id'),
            'context' => $this->context(),
            'impersonated_by' => session('impersonated_by'),
            'created_at' => CarbonImmutable::now(),
        ])->afterCommit();
    }

    /**
     * Record something that is not a model change: a login, a denied request,
     * an export.
     *
     * @param  array<string, mixed>  $details
     */
    public function event(
        string $action,
        array $details = [],
        ?string $companyId = null,
        ?string $userId = null,
        ?string $label = null,
        bool $inheritCompany = true,
    ): void {
        $this->record(
            action: $action,
            entity: null,
            old: null,
            new: $details === [] ? null : $details,
            companyId: $companyId,
            userId: $userId,
            label: $label,
            inheritCompany: $inheritCompany,
        );
    }

    /**
     * @param  array<string, mixed>  $values
     * @return array<string, mixed>
     */
    private function clean(array $values): array
    {
        $clean = [];

        foreach ($values as $key => $value) {
            if (in_array($key, self::IGNORED, true)) {
                continue;
            }

            $clean[$key] = $this->isSensitive($key) ? '[redacted]' : $this->scalarise($value);
        }

        return $clean;
    }

    private function isSensitive(string $key): bool
    {
        $key = strtolower($key);

        foreach (self::REDACTED as $needle) {
            if (str_contains($key, $needle)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Values are stored as they would be read, not as PHP objects. A date that
     * arrives as a Carbon and a date that arrives as a string must produce the
     * same audit row, or every diff shows a change that did not happen.
     */
    private function scalarise(mixed $value): mixed
    {
        return match (true) {
            $value instanceof \DateTimeInterface => $value->format('Y-m-d H:i:s'),
            $value instanceof \BackedEnum => $value->value,
            is_array($value) => $value,
            is_object($value) => (string) $value,
            default => $value,
        };
    }

    /**
     * @param  array<string, mixed>|null  $old
     * @param  array<string, mixed>|null  $new
     * @return list<string>
     */
    private function changedFields(?array $old, ?array $new): array
    {
        if ($old === null || $new === null) {
            return [];
        }

        $changed = [];

        foreach ($new as $key => $value) {
            if (in_array($key, self::IGNORED, true)) {
                // Timestamps and the optimistic-lock counter move on every
                // save: noise on every row, meaning on none.
                continue;
            }

            // Loose comparison on purpose: "1" and 1 arriving from a form and
            // from the database are the same value, and reporting them as a
            // change trains people to ignore the diff.
            if (! array_key_exists($key, $old) || $old[$key] != $value) {
                $changed[] = $key;
            }
        }

        return $changed;
    }

    private function companyIdFor(?Model $entity): ?string
    {
        if ($entity !== null && $entity->getAttribute('company_id') !== null) {
            return $entity->getAttribute('company_id');
        }

        return $this->context->companyIdOrNull();
    }

    /**
     * The table name, not the class name.
     *
     * A class can be moved between modules; the table it writes to is what a
     * five-year-old audit row still has to resolve against.
     */
    private function typeOf(Model $entity): string
    {
        return $entity->getTable();
    }

    private function labelFor(?Model $entity): ?string
    {
        if ($entity === null) {
            return null;
        }

        foreach (['asset_code', 'work_order_number', 'breakdown_number', 'contract_number',
            'claim_number', 'transfer_number', 'part_number', 'code', 'email', 'name'] as $field) {
            $value = $entity->getAttribute($field);

            if (is_string($value) && $value !== '') {
                return Str::limit($value, 250, '');
            }
        }

        return null;
    }

    private function context(): string
    {
        if (app()->runningInConsole()) {
            return app()->runningUnitTests() ? 'UI' : 'CONSOLE';
        }

        return request()?->is('api/*') ? 'API' : 'UI';
    }
}
