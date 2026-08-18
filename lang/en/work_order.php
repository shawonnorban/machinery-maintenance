<?php

declare(strict_types=1);

return [
    'work_orders' => 'Work orders',
    'work_order' => 'Work order',
    'scheduled_maintenance' => 'Scheduled maintenance',

    'invalid_transition' => 'A work order cannot move from :from to :to.',
    'assign_needs_technician' => 'Assign at least one technician first.',
    'hold_reason_unknown' => 'Unknown hold reason.',
    'checklist_incomplete' => ':count required checklist items are still unanswered.',
    'verification_not_required' => 'This work order does not require verification.',
    'cannot_verify_own_work' => 'Verification needs a second pair of eyes: you cannot verify work you completed yourself.',
    'verification_required' => 'This work order must be verified before it can be closed.',
    'approval_pending' => 'This work order is still awaiting approval.',
    'cancel_needs_reason' => 'Cancelling a work order needs a reason.',
    'reopen_needs_reason' => 'Reopening a completed work order needs a reason.',

    'asset_not_found' => 'The selected machine does not exist.',
    'asset_terminal' => 'A :status machine cannot have new work raised against it.',
    'maintenance_type_required' => 'Choose a maintenance type.',
    'schedule_not_open' => 'This maintenance occurrence is no longer open.',

    'labor_category_unknown' => 'Unknown labour category.',
    'labor_after_close' => 'A closed work order no longer accepts labour entries.',
    'labor_end_before_start' => 'The end time must be after the start time.',
    'labor_in_future' => 'Labour cannot be recorded for a time that has not happened yet.',
    'labor_too_long' => 'A single labour entry cannot exceed 24 hours. Split it across shifts.',
    'labor_needs_technician' => 'Choose the technician who did the work.',
    'labor_overlap' => 'This technician already has time recorded from :from to :to. One person cannot be in two places at once.',
    'technician_needs_grade' => 'This technician has no labour grade in force, so their time cannot be costed.',
    'external_needs_rate' => 'External contractor labour needs the rate the vendor charged.',
];
