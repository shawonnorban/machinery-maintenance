<?php

declare(strict_types=1);

return [
    'rule_event' => 'When this happens',
    'escalations' => 'Escalation rules',
    'escalations_intro' => 'How long something may sit unanswered before it goes past the person who ignored it. Level 1 is the first nudge; each level after it reaches further up.',
    'new_rule' => 'Add a rule',
    'add_rule' => 'Add rule',
    'any_severity' => 'Any severity',
    'after' => 'And nobody answers for',
    'level' => 'Level',
    'tell' => 'Tell',
    'factory' => 'Factory',
    'every_factory' => 'Every factory',
    'stop_on_acknowledge' => 'Stop once somebody picks it up',
    'minutes' => '{1} :count minute|[2,*] :count minutes',
    'pause' => 'Pause',
    'resume' => 'Resume',
    'no_rules' => 'No escalation rules.',
    'no_rules_hint' => 'Without one, an alert nobody reads is an alert nobody ever hears about again.',
    'rule_added' => 'Rule added.',
    'rule_updated' => 'Rule saved.',
    'rule_removed' => 'Rule removed.',
    'remove_rule_confirm' => 'Remove this rule? Alerts already sent are unaffected.',
    'unknown_role' => 'That role is not available to your company.',
    'factory_unavailable' => 'That factory is not available to you.',
    'level_already_covered' => 'Level :level is already covered for this event. Two rules at one level would tell the same person twice about one silence.',
    'role_not_person_hint' => 'A rule names a role rather than a person, because one that names an individual stops working the week they are away — which is exactly the week somebody needs it.',
    'notifications' => 'Notifications',
    'notification' => 'Notification',
    'unread' => 'Unread',
    'all' => 'All',
    'mark_read' => 'Mark read',
    'mark_all_read' => 'Mark all read',
    'marked_read' => 'Marked as read.',
    'acknowledge' => 'Acknowledge',
    'acknowledged' => 'Acknowledged.',
    'acknowledge_hint' => 'Acknowledging stops the escalation. Opening the list does not: reading is not the same as picking something up.',
    'no_notifications' => 'Nothing new',
    'no_notifications_hint' => 'Breakdowns, overdue maintenance and approvals arrive here.',
    'severity' => 'Severity',
    'severity_info' => 'Information',
    'severity_warning' => 'Warning',
    'severity_critical' => 'Critical',
    'received' => 'Received',
    'event_label' => 'Event',
    'escalated' => 'Escalated',
    'escalation_level' => 'Escalation level',
    'escalated_from' => 'Escalated because nobody acknowledged it',
    'open' => 'Open',
    'unknown_event' => 'Unknown notification event: :event.',
    'unknown_severity' => 'Unknown notification severity.',

    // --- preferences ---
    'preferences' => 'Notification settings',
    'preferences_saved' => 'Notification settings saved.',
    'channel_in_app' => 'In app',
    'channel_email' => 'Email',
    'channel_sms' => 'SMS',
    'channel_whatsapp' => 'WhatsApp',
    'in_app_always_on' => 'Always on. The record of what happened is part of the audit trail, not a preference.',
    'channels_hint' => 'These decide how loudly you are told. Everything still appears in this list.',
    'not_yet_delivered' => 'Not yet available',
    'not_yet_delivered_hint' => 'Email, SMS and WhatsApp delivery land with the messaging workstream. The preference is recorded now so nothing has to be reconfigured later.',

    // --- event names, shown on the preferences screen ---
    'event_maintenance_due' => 'Maintenance due',
    'event_maintenance_overdue' => 'Maintenance overdue',
    'event_breakdown_reported' => 'Breakdown reported',
    'event_breakdown_critical' => 'Critical breakdown',
    'event_work_order_assigned' => 'Work order assigned to you',
    'event_work_order_completed' => 'Work order completed',
    'event_approval_requested' => 'Approval needed',
    'event_approval_decided' => 'Approval decided',
    'event_low_stock' => 'Spare part low on stock',
    'event_warranty_expiry' => 'Warranty about to expire',
    'event_webhook_disabled' => 'Webhook endpoint disabled',
    'event_amc_expiry' => 'Service contract about to expire',

    // --- the messages themselves, rendered at creation in the recipient's
    //     locale and then stored (SRS 48) ---
    'event' => [
        'MAINTENANCE_DUE' => [
            'title' => 'Maintenance due: :asset',
            'body' => ':plan is due on :due_at.',
        ],
        'MAINTENANCE_OVERDUE' => [
            'title' => 'Maintenance overdue: :asset',
            'body' => ':plan was due on :due_at and is past its grace period.',
        ],
        'BREAKDOWN_REPORTED' => [
            'title' => 'Breakdown: :asset',
            'body' => ':number reported. :problem',
        ],
        'BREAKDOWN_CRITICAL' => [
            'title' => 'Critical breakdown: :asset',
            'body' => ':number reported on a critical machine. :problem',
        ],
        'WORK_ORDER_ASSIGNED' => [
            'title' => 'Assigned to you: :number',
            'body' => ':title on :asset.',
        ],
        'WORK_ORDER_COMPLETED' => [
            'title' => 'Completed: :number',
            'body' => ':title on :asset was completed.',
        ],
        'APPROVAL_REQUESTED' => [
            'title' => 'Approval needed: :number',
            'body' => ':title, estimated :cost.',
        ],
        'APPROVAL_DECIDED' => [
            'title' => 'Approval :decision: :number',
            'body' => ':title was :decision.',
        ],
        'LOW_STOCK' => [
            'title' => 'Low stock: :part',
            'body' => ':on_hand left, reorder level is :reorder_level.',
        ],
        'WARRANTY_EXPIRY' => [
            'title' => 'Warranty ending: :asset',
            'body' => 'Cover from :vendor ends on :end_date, in :days days.',
        ],
        'WEBHOOK_DISABLED' => [
            'title' => 'Webhook disabled: :url',
            'body' => 'It failed :count times in a row and has been switched off. Fix the receiver, then enable it again.',
        ],
        // Named, timed and reasoned, because a vague "support accessed your
        // data" is the kind of notice that worries people without telling them
        // anything they can act on (SRS 5.4).
        'SUPPORT_ACCESS' => [
            'title' => 'Support access to your data',
            'body' => ':name from support was given access to :company until :until. Reason given: :reason',
        ],
        'TICKET_REPLIED' => [
            'title' => 'Reply on your ticket: :subject',
            'body' => ':name from support has replied.',
        ],
        'TICKET_RESOLVED' => [
            'title' => 'Ticket marked resolved: :subject',
            'body' => 'If this did not fix it, reply and it reopens automatically.',
        ],
        'AMC_EXPIRY' => [
            'title' => 'Contract ending: :number',
            'body' => ':vendor\'s contract ends on :end_date, in :days days.',
        ],
    ],
    'view_all' => 'View all notifications',
];
