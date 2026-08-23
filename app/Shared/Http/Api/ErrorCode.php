<?php

declare(strict_types=1);

namespace App\Shared\Http\Api;

/**
 * The machine-readable half of every failure (API 36).
 *
 * Clients branch on the code, never on the message: the message is localised
 * per Accept-Language and will read differently in Bengali, while the code is
 * the same string for ever. That promise is only worth something if the code
 * also always carries the same HTTP status, so the mapping lives here rather
 * than being decided again at each throw site. Changing a mapping is a
 * breaking change and belongs in /api/v2 (API 38).
 */
enum ErrorCode: string
{
    // -- Authentication and tenancy ----------------------------------------
    case UNAUTHENTICATED = 'UNAUTHENTICATED';
    case FORBIDDEN = 'FORBIDDEN';
    case TENANT_ACCESS_DENIED = 'TENANT_ACCESS_DENIED';
    case TENANT_CONTEXT_REQUIRED = 'TENANT_CONTEXT_REQUIRED';
    case MFA_REQUIRED = 'MFA_REQUIRED';
    case ACCOUNT_LOCKED = 'ACCOUNT_LOCKED';
    case PASSWORD_POLICY_VIOLATION = 'PASSWORD_POLICY_VIOLATION';

    // -- Request shape ------------------------------------------------------
    case VALIDATION_ERROR = 'VALIDATION_ERROR';
    case RESOURCE_NOT_FOUND = 'RESOURCE_NOT_FOUND';
    case PAYLOAD_TOO_LARGE = 'PAYLOAD_TOO_LARGE';
    case RATE_LIMITED = 'RATE_LIMITED';

    // -- Conflicts ----------------------------------------------------------
    case CONFLICT = 'CONFLICT';
    case IDEMPOTENCY_CONFLICT = 'IDEMPOTENCY_CONFLICT';
    case INVALID_STATUS_TRANSITION = 'INVALID_STATUS_TRANSITION';
    case IMMUTABLE_RECORD = 'IMMUTABLE_RECORD';
    case DEPENDENT_RECORDS_EXIST = 'DEPENDENT_RECORDS_EXIST';

    // -- Business rules -----------------------------------------------------
    case INSUFFICIENT_STOCK = 'INSUFFICIENT_STOCK';
    case NEGATIVE_STOCK_NOT_ALLOWED = 'NEGATIVE_STOCK_NOT_ALLOWED';
    case RESERVATION_EXPIRED = 'RESERVATION_EXPIRED';
    case METER_VALUE_DECREASED = 'METER_VALUE_DECREASED';
    case CHECKLIST_INCOMPLETE = 'CHECKLIST_INCOMPLETE';
    case PARTS_NOT_RECONCILED = 'PARTS_NOT_RECONCILED';
    case VERIFICATION_REQUIRED = 'VERIFICATION_REQUIRED';
    case APPROVAL_REQUIRED = 'APPROVAL_REQUIRED';
    case SELF_APPROVAL_NOT_ALLOWED = 'SELF_APPROVAL_NOT_ALLOWED';
    case CALENDAR_NOT_CONFIGURED = 'CALENDAR_NOT_CONFIGURED';
    case SETTING_KEY_UNKNOWN = 'SETTING_KEY_UNKNOWN';
    case SEQUENCE_EXHAUSTED = 'SEQUENCE_EXHAUSTED';

    // -- Files --------------------------------------------------------------
    case FILE_TOO_LARGE = 'FILE_TOO_LARGE';
    case UNSUPPORTED_FILE_TYPE = 'UNSUPPORTED_FILE_TYPE';
    case FILE_SCAN_PENDING = 'FILE_SCAN_PENDING';

    // -- Subscription and platform -----------------------------------------
    case SUBSCRIPTION_READ_ONLY = 'SUBSCRIPTION_READ_ONLY';
    case SUBSCRIPTION_EXPIRED = 'SUBSCRIPTION_EXPIRED';
    case PLAN_LIMIT_EXCEEDED = 'PLAN_LIMIT_EXCEEDED';
    case DEPENDENCY_UNAVAILABLE = 'DEPENDENCY_UNAVAILABLE';
    case SERVER_ERROR = 'SERVER_ERROR';

    /**
     * The one HTTP status this code is ever returned with.
     */
    public function status(): int
    {
        return match ($this) {
            self::UNAUTHENTICATED => 401,

            // 403 throughout, including the billing state. A caller that has
            // named its tenant explicitly is told so plainly; there is nothing
            // left to conceal from it (API 2).
            self::FORBIDDEN,
            self::TENANT_ACCESS_DENIED,
            self::TENANT_CONTEXT_REQUIRED,
            self::SUBSCRIPTION_READ_ONLY,
            self::SUBSCRIPTION_EXPIRED,
            self::PLAN_LIMIT_EXCEEDED,
            self::ACCOUNT_LOCKED,
            self::MFA_REQUIRED,
            self::SELF_APPROVAL_NOT_ALLOWED => 403,

            // 404 for a record in another tenant as well as one that never
            // existed. Cross-tenant probing must not be able to tell them
            // apart (API 2).
            self::RESOURCE_NOT_FOUND => 404,

            // 409 covers every "the world is not in the state you assumed"
            // case; the code is what distinguishes them.
            self::CONFLICT,
            self::IDEMPOTENCY_CONFLICT,
            self::INVALID_STATUS_TRANSITION,
            self::IMMUTABLE_RECORD,
            self::DEPENDENT_RECORDS_EXIST,
            self::INSUFFICIENT_STOCK,
            self::NEGATIVE_STOCK_NOT_ALLOWED,
            self::RESERVATION_EXPIRED,
            self::METER_VALUE_DECREASED,
            self::CHECKLIST_INCOMPLETE,
            self::PARTS_NOT_RECONCILED,
            self::VERIFICATION_REQUIRED,
            self::APPROVAL_REQUIRED,
            self::CALENDAR_NOT_CONFIGURED,
            self::SEQUENCE_EXHAUSTED,
            self::FILE_SCAN_PENDING => 409,

            self::PAYLOAD_TOO_LARGE,
            self::FILE_TOO_LARGE => 413,

            self::UNSUPPORTED_FILE_TYPE => 415,

            self::VALIDATION_ERROR,
            self::PASSWORD_POLICY_VIOLATION,
            self::SETTING_KEY_UNKNOWN => 422,

            self::RATE_LIMITED => 429,

            self::SERVER_ERROR => 500,

            // Retryable: the caller did nothing wrong and should come back.
            self::DEPENDENCY_UNAVAILABLE => 503,
        };
    }

    /**
     * The default human-readable message, localised.
     *
     * Throw sites are free to pass something better — "Requested 12, available
     * 4" beats any generic sentence — but every code has an answer, so no
     * failure can reach a client with an empty message.
     */
    public function message(): string
    {
        return __('api.errors.'.$this->value);
    }
}
