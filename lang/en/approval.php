<?php

declare(strict_types=1);

return [
    'approvals' => 'Approvals',
    'approval' => 'Approval',
    'workflow' => 'Workflow',
    'step' => 'Step',
    'of_steps' => 'of :total',
    'requested_by' => 'Requested by',
    'requested_at' => 'Requested',
    'approver' => 'Approver',
    'comment' => 'Comment',
    'acted_at' => 'When',
    'history' => 'History',
    'entity' => 'Record',
    'context' => 'What was approved',
    'context_hint' => 'Frozen when the request was raised. A later cost change never alters what an approver was shown.',
    'no_requests' => 'Nothing awaiting approval',
    'no_requests_hint' => 'Work orders that need a signature appear here.',
    'pending_for_me' => 'Waiting on you',
    'all_pending' => 'All pending',

    'status' => 'Status',
    'status_pending' => 'Pending',
    'status_approved' => 'Approved',
    'status_rejected' => 'Rejected',
    'status_cancelled' => 'Cancelled',
    'status_expired' => 'Expired',

    'action_approved' => 'Approved',
    'action_rejected' => 'Rejected',
    'action_delegated' => 'Delegated',
    'action_cancelled' => 'Cancelled',
    'action_expired' => 'Expired',

    'approve' => 'Approve',
    'reject' => 'Reject',
    'approved_message' => 'Approved.',
    'rejected_message' => 'Rejected.',
    'cancelled_message' => 'Approval request cancelled.',
    'advanced_to_next' => 'Approved. It now needs :name.',

    'not_pending' => 'That request is no longer pending.',
    'no_current_step' => 'That request has no step waiting on a decision.',
    'cannot_approve_own_request' => 'You cannot approve a request you raised yourself. An approval you can grant yourself is a checkbox, not a control.',
    'not_your_step' => 'This step is not waiting on you.',
    'rejection_needs_reason' => 'A rejection needs a reason, otherwise the job is simply resubmitted unchanged.',
    'expired_automatically' => 'Expired: nobody acted on it before the deadline.',

    'cost' => 'Estimated cost',
    'criticality' => 'Criticality',
    'factory' => 'Factory',
    'priority' => 'Priority',
    'rule_min_cost' => 'From :amount',
    'rule_max_cost' => 'Up to :amount',
    'awaiting_signature' => 'Awaiting approval',
];
