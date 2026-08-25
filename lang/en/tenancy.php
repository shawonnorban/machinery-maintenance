<?php

declare(strict_types=1);

/*
 * What a customer is told when their company has been stopped.
 *
 * These sentences are read by somebody whose whole company has just stopped
 * working, which is a bad five minutes. So they say what happened, why, that
 * nothing has been lost, and who to speak to — in that order, because that is
 * the order the questions arrive in.
 */

return [
    'suspended_title' => 'This account has been suspended',
    'suspended_lede' => 'Your company\'s access has been stopped by the provider. Nobody in your company can sign in until it is restored.',
    'suspended_reason' => 'Reason given',
    'suspended_no_reason' => 'No reason was recorded.',
    'suspended_since' => 'Suspended on :date.',

    // The first thing anybody in a factory fears when a system stops.
    'suspended_data_safe' => 'Nothing has been deleted. Every machine, work order, breakdown and stock record is exactly where you left it, and will be there when access is restored.',
    'suspended_what_now' => 'Contact your provider to have this reviewed. If it concerns an unpaid invoice, settling it is usually enough.',

    'suspended_body' => ':company has been suspended. Reason: :reason',

    // A closed account. Said plainly, and without the "nothing has been lost"
    // reassurance above, because here it would not be true for long.
    'closed_title' => 'This account has been closed',
    'closed_body' => 'This company\'s account has been closed and can no longer be used.',
    'closed_since' => 'Closed on :date.',
    'closed_what_now' => 'If you believe this is a mistake, contact your provider. An account can be reopened for a short while after it is closed.',

    'switch_company' => 'Go to another company',
];
