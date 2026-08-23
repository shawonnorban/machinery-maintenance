<?php

declare(strict_types=1);

/*
 * Your own account (SRS 50.2, 50.3).
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

    // -- Second factor ----------------------------------------------------
    'two_factor' => 'Two-step sign-in',
    'mfa_on' => 'On',
    'mfa_off' => 'Off',
    'turn_on' => 'Turn on',
    'turn_off' => 'Turn off',
    'mfa_why' => 'Ask for a code from your phone as well as your password. A stolen password on its own is then not enough to sign in.',
    'mfa_scan' => 'Scan this with an authenticator app, then type the code it shows to finish.',
    'mfa_manual_entry' => 'Or type this into the app:',
    'mfa_confirm' => 'Finish',
    'mfa_code' => 'Code from your app',
    'mfa_or_recovery' => 'You can use a recovery code here instead.',
    'mfa_off_needs_code' => 'A code is required to turn this off — knowing the password is not enough.',
    'mfa_enabled' => 'Two-step sign-in is on.',
    'mfa_disabled' => 'Two-step sign-in is off.',
    'mfa_already_on' => 'Two-step sign-in is already on for this account.',
    'mfa_not_started' => 'Start setting up two-step sign-in first.',
    'mfa_code_wrong' => 'That code is not right. Codes change every 30 seconds — try the current one.',
    'mfa_throttled' => 'Too many attempts. Try again in :seconds seconds.',
    'mfa_challenge' => 'Two-step sign-in',
    'mfa_challenge_for' => 'Signing in as :email',
    'mfa_back_to_login' => 'Start again',
    'mfa_start_again' => 'That sign-in expired. Please start again.',

    'recovery_codes_shown_once' => 'Save these recovery codes now. They are shown once and cannot be retrieved again.',
    'recovery_codes_hint' => 'Each works once, and only if you cannot use your phone. Keep them somewhere other than the phone.',
    'recovery_codes_left' => '{0} No recovery codes left|{1} :count recovery code left|[2,*] :count recovery codes left',
    'new_recovery_codes' => 'New codes',
    'recovery_codes_regenerated' => 'New recovery codes issued. The old ones no longer work.',

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
