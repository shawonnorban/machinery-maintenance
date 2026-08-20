<?php

declare(strict_types=1);

return [
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
        'AMC_EXPIRY' => [
            'title' => 'Contract ending: :number',
            'body' => ':vendor\'s contract ends on :end_date, in :days days.',
        ],
    ],
    'view_all' => 'View all notifications',
];
