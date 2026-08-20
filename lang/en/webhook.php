<?php

declare(strict_types=1);

return [
    'webhooks' => 'Webhooks',
    'webhook' => 'Webhook',
    'endpoints' => 'Endpoints',
    'new_endpoint' => 'Add an endpoint',
    'no_endpoints' => 'No webhook endpoints',
    'no_endpoints_hint' => 'Add one to send breakdowns, work orders and stock movements to another system as they happen.',
    'created' => 'Endpoint created.',
    'updated' => 'Endpoint updated.',
    'rotated' => 'A new signing secret has been issued. The previous one keeps working for 24 hours.',
    'enabled' => 'Endpoint enabled.',
    'paused' => 'Endpoint paused. Nothing will be sent until it is enabled again.',
    'redelivering' => 'Sending that delivery again.',
    'back_to_endpoints' => 'All endpoints',

    'url' => 'URL',
    'description' => 'Description',
    'status' => 'Status',
    'events' => 'Events',
    'subscribed_events' => 'Subscribed events',
    'created_by' => 'Added by',
    'failures' => 'Consecutive failures',
    'disabled_reason' => 'Why it was disabled',
    'signing' => 'Signing',
    'signing_algorithm' => 'Algorithm',
    'secret_rotated_at' => 'Secret rotated',
    'rotate_secret' => 'Rotate secret',
    'pause' => 'Pause',
    'enable' => 'Enable',
    'save' => 'Save',

    'secret_shown_once' => 'Copy this signing secret now. It is never shown again.',
    'secret_hint' => 'Sign requests with HMAC-SHA256 over the timestamp, a full stop, and the exact request body. Compare against the :header header.',
    'rotation_hint' => 'During a rotation, both signatures are sent for 24 hours so a receiver can switch over on its own schedule.',

    'deliveries' => 'Deliveries',
    'no_deliveries' => 'Nothing sent to this endpoint yet',
    'event' => 'Event',
    'sent_at' => 'Sent',
    'attempts' => 'Attempts',
    'response' => 'Response',
    'duration' => 'Took',
    'next_retry' => 'Next attempt',
    'redeliver' => 'Send again',
    'delivery_id' => 'Delivery',
    'payload_purged' => 'Payload removed after 30 days',

    'statuses' => [
        'ACTIVE' => 'Active',
        'PAUSED' => 'Paused',
        'DISABLED' => 'Disabled',
    ],

    'delivery_statuses' => [
        'PENDING' => 'Pending',
        'DELIVERED' => 'Delivered',
        'FAILED' => 'Retrying',
        'EXHAUSTED' => 'Given up',
    ],

    'events_list' => [
        'breakdown_reported' => 'A machine stopped',
        'asset_status_changed' => 'A machine changed state',
        'work_order_assigned' => 'Work assigned to a technician',
        'work_order_updated' => 'A work order moved',
        'stock_changed' => 'Stock moved',
    ],

    // --- validation and errors ---
    'url_invalid' => 'That is not a URL this system can call.',
    'url_https_required' => 'Webhook URLs must use HTTPS. A signed payload over plain HTTP can be read by anyone on the path.',
    'url_no_credentials' => 'Do not put a username or password in the URL. Use the signing secret instead.',
    'url_private' => 'That address is on a private or reserved network, so this system will not call it.',
    'url_unresolvable' => 'That hostname does not resolve.',
    'events_required' => 'Choose at least one event. An endpoint subscribed to nothing never sends anything.',
    'unknown_event' => 'There is no event called :event.',
    'endpoint_not_deliverable' => 'The endpoint is paused or disabled.',
    'disabled_after_failures' => 'Disabled automatically after :count consecutive failures.',
];
