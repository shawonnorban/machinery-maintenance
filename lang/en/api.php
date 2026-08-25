<?php

declare(strict_types=1);

/*
 * Messages a machine caller reads.
 *
 * These are the human half of a failure. The `code` beside them never
 * changes and never translates; the sentence here is what a developer sees
 * in a log and what an integration puts in front of a person. So each one
 * says what happened and, where there is one, what to do about it — a
 * message that only restates its own code has wasted the field.
 */

return [
    'errors' => [
        'UNAUTHENTICATED' => 'This request needs a valid access token.',
        'FORBIDDEN' => 'Your account is not permitted to do this.',
        'TENANT_ACCESS_DENIED' => 'You are not a member of the company named in this request.',
        'TENANT_CONTEXT_REQUIRED' => 'No active company could be determined for this request.',
        'ACCOUNT_LOCKED' => 'This account is locked. An administrator can unlock it.',
        'PASSWORD_POLICY_VIOLATION' => 'The password does not meet the policy for this company.',

        'VALIDATION_ERROR' => 'The request could not be accepted as sent.',
        'RESOURCE_NOT_FOUND' => 'No such record.',
        'PAYLOAD_TOO_LARGE' => 'The request body is larger than this endpoint accepts.',
        'RATE_LIMITED' => 'Too many requests. Retry after the period given in Retry-After.',

        'CONFLICT' => 'The record is not in the state this request assumed.',
        'IDEMPOTENCY_CONFLICT' => 'This idempotency key has already been used for a different request.',
        'INVALID_STATUS_TRANSITION' => 'That status change is not allowed from where this record is now.',
        'IMMUTABLE_RECORD' => 'This record is posted and cannot be changed. Record a correction instead.',
        'DEPENDENT_RECORDS_EXIST' => 'Other records point at this one, so it cannot be removed.',

        'INSUFFICIENT_STOCK' => 'There is not enough of this part on hand.',
        'NEGATIVE_STOCK_NOT_ALLOWED' => 'This would take the balance below zero, which this company does not allow.',
        'RESERVATION_EXPIRED' => 'The reservation has expired and the stock has been released.',
        'METER_VALUE_DECREASED' => 'A meter reading cannot be lower than the last one. Record a reset instead.',
        'CHECKLIST_INCOMPLETE' => 'Required checklist items are still unanswered.',
        'PARTS_NOT_RECONCILED' => 'Issued parts must be consumed or returned before this can be closed.',
        'VERIFICATION_REQUIRED' => 'This work still needs to be verified.',
        'APPROVAL_REQUIRED' => 'This needs approval before it can go ahead.',
        'SELF_APPROVAL_NOT_ALLOWED' => 'The person who raised this cannot approve it.',
        'CALENDAR_NOT_CONFIGURED' => 'This factory has no working calendar, so the date cannot be worked out.',
        'SETTING_KEY_UNKNOWN' => 'No such setting.',
        'SEQUENCE_EXHAUSTED' => 'The numbering sequence has no numbers left for this period.',

        'FILE_TOO_LARGE' => 'The file is larger than the limit.',
        'UNSUPPORTED_FILE_TYPE' => 'That file type is not accepted here.',
        'FILE_SCAN_PENDING' => 'The file is still being scanned. Try again shortly.',

        'SUBSCRIPTION_READ_ONLY' => 'The subscription is in arrears, so this company is read-only. Reading and exporting still work.',
        'SUBSCRIPTION_EXPIRED' => 'The subscription has ended.',
        'PLAN_LIMIT_EXCEEDED' => 'This would go past what the current plan allows.',
        'DEPENDENCY_UNAVAILABLE' => 'A service this request depends on is unavailable. It is safe to retry.',
        'SERVER_ERROR' => 'Something went wrong at our end. Quote the request id when reporting it.',
    ],

    'idempotency_key_required' => 'This endpoint requires an Idempotency-Key header.',
    'idempotency_key_too_long' => 'An Idempotency-Key may be at most 128 characters.',
    'idempotency_body_changed' => 'This Idempotency-Key was already used with a different request body.',
    'idempotency_in_flight' => 'The first request with this Idempotency-Key is still running.',

    'default_token_name' => 'API token',
    'token_company_mismatch' => 'This token was issued for a different company. Request a token for the company you are naming.',
    'client_cannot_switch_company' => 'A machine client belongs to one company and cannot switch.',
    'step_needs_a_person' => 'This step is recorded against a person, so it cannot be taken by a machine client.',

    'token_name_required' => 'Give the token a name so it can be recognised later.',
    'client_created' => 'API client :name created. The secret is shown once and cannot be retrieved again.',
    'client_updated' => 'API client :name saved.',
    'client_revoked' => 'API client :name revoked.',
    'secret_rotated' => 'A new secret was issued for :name. The old one stops working immediately.',
    'scope_unknown' => 'A scope must be one of this company\'s own permission codes.',
    'client_expired' => 'This client\'s credentials have expired.',
    'client_revoked_error' => 'This client has been revoked.',
    'client_scope_denied' => 'This client is not scoped for that operation.',

    // -- The screen an administrator uses ---------------------------------
    'api_clients' => 'API clients',
    'api_clients_intro' => 'Credentials for other software — an ERP posting costs, a dye house controller posting meter readings. Never issued to a person.',
    'clients' => 'Clients',
    'new_client' => 'New client',
    'create_client' => 'Create client',
    'name' => 'Name',
    'name_example' => 'Dye house controller',
    'client_id' => 'Client id',
    'client_secret' => 'Client secret',
    'scopes' => 'Scopes',
    'scopes_required' => 'Choose at least one scope. A client with none can do nothing.',
    'scopes_hint' => 'A credential is narrower than the person who created it. Tick only what this integration does; anything else is refused even if your own account could do it.',
    'secret_shown_once' => 'Copy the secret now. It is shown once and cannot be retrieved again.',
    'secret_exchange_hint' => 'Exchange these at POST /api/v1/auth/token for a bearer token.',
    'expires_at' => 'Expires',
    'expires_hint' => 'Optional. Leave blank for no expiry.',
    'last_used' => 'Last used',
    'never_used' => 'Never used',
    'status' => 'Status',
    'active' => 'Active',
    'inactive' => 'Not usable',
    'rotate_secret' => 'Issue a new secret',
    'revoke' => 'Revoke',
    'revoke_confirm' => 'Revoke this client? Anything using it stops working immediately.',
    'created_by' => 'Created by :name',
    'live_tokens' => '{1} :count live token|[2,*] :count live tokens',
    'token_revoked' => 'Token revoked.',
    'no_clients' => 'No API clients',
    'no_clients_hint' => 'Create one when another system needs to read or write here without a person signing in.',
];
