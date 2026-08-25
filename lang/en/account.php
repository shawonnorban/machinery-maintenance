<?php

declare(strict_types=1);

/*
 * Your own account (SRS 50.2).
 *
 * The one screen a person owns rather than administers. Every sentence here is
 * read by somebody deciding whether their account is still only theirs, so
 * each says what will happen rather than naming a mechanism.
 */

return [
    'your_account' => 'Your account',

    // -- Password ---------------------------------------------------------
    'password' => 'Password',
    'current_password' => 'Current password',
    'new_password' => 'New password',
    'confirm_password' => 'Confirm new password',
    'change_password' => 'Change password',
    'password_policy' => 'At least 10 characters, and not one that has appeared in a known breach.',
    'password_change_signs_out' => 'Changing your password signs out every other device and stops every API token you hold. This device stays signed in.',
    'password_changed' => 'Your password has been changed. Everywhere else has been signed out.',
    'current_password_wrong' => 'That is not your current password.',

    // -- Devices ----------------------------------------------------------
    'signed_in_devices' => 'Signed-in devices',
    'device' => 'Device',
    'ip_address' => 'Address',
    'last_active' => 'Last active',
    'this_device' => 'This device',
    'sign_out_device' => 'Sign out',
    'session_revoked' => 'That device has been signed out.',
    'session_gone' => 'That device was already signed out.',
    'no_sessions' => 'No other devices',
    'no_sessions_hint' => 'This is the only browser currently signed in to your account.',
    'unknown_browser' => 'Unknown browser',
    'unknown_platform' => 'unknown device',

    // -- Tokens -----------------------------------------------------------
    'api_tokens' => 'API tokens',
    'token_name' => 'Token',
    'last_used' => 'Last used',
    'expires' => 'Expires',
    'revoke' => 'Revoke',
    'token_revoked' => 'That token has been revoked.',
];
