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
    'after_minutes' => 'And nobody answers for (minutes)',
    'escalation_level_hint' => '1–5; two rules can\'t cover the same level for one event.',
    'stop_on_acknowledge_checkbox' => 'Stop once somebody acknowledges it',
    'status_label' => 'Status',
    'rule_active' => 'Active',
    'rule_paused' => 'Paused',
    'minutes_short' => ':count min',
    'delete_rule_title' => 'Delete the :event rule?',
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
    'locked_by_company' => 'Set by the company',
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
    'event_breakdown_awaiting_verification' => 'Breakdown awaiting your verification',
    'event_breakdown_awaiting_approval' => 'Breakdown awaiting your approval',
    'event_work_order_assigned' => 'Work order assigned to you',
    'event_work_order_completed' => 'Work order completed',
    'event_approval_requested' => 'Approval needed',
    'event_approval_decided' => 'Approval decided',
    'event_low_stock' => 'Spare part low on stock',
    'event_warranty_expiry' => 'Warranty about to expire',
    'event_webhook_disabled' => 'Webhook endpoint disabled',
    'event_amc_expiry' => 'Service contract about to expire',
    'event_support_access' => 'Support accessed your data',
    'event_ticket_replied' => 'Reply on your ticket',
    'event_ticket_resolved' => 'Ticket marked resolved',
    'event_floor_requisition_submitted' => 'Floor requisition submitted',
    'event_floor_requisition_issued' => 'Floor requisition issued',
    'event_floor_requisition_shortage' => 'Floor requisition short',
    'event_earmark_stock_arrived' => 'Earmarked stock arrived',
    'event_returnable_overdue' => 'Returnable item overdue',
    'event_earmark_waiting' => 'Shortage still waiting',
    'event_fabric_inspection_failed' => 'Fabric inspection failed',
    'event_lot_expiring' => 'Lot near expiry',
    'event_stock_count_stale' => 'Stock count left open',
    'event_shipment_arriving' => 'Shipment arriving',
    'event_shipment_delayed' => 'Shipment delayed',
    'event_lc_expiring' => 'Letter of credit about to expire',
    'event_global_broadcast' => 'Company announcement',
    'event_approval_overdue' => 'Approval request overdue',

    // --- the messages themselves, rendered at creation in the recipient's
    //     locale and then stored (SRS 48) ---
    'event_os_orders_changed' => 'Order Summary: orders changed',
    'event_os_pending_work' => 'Order Summary: daily pending work',
    'event_ds_samples_changed' => 'Dev Sample: samples changed',
    'event_ds_deadline_due' => 'Dev Sample: submission deadlines',
    'event_pb_requisitions_changed' => 'Pre-Budget: requisitions changed',
    'event_pb_budget_changed' => 'Pre-Budget: budget changed',
    'event_pb_stock_needed' => 'Pre-Budget: Stock Yarn Use needed',
    'event_pb_pending_work' => 'Pre-Budget: daily pending work',
    'event_es_statements_changed' => 'Export Statement: statements changed',
    'event_es_pending_work' => 'Export Statement: daily pending work',
    'sheet' => [
        'kind' => [
            'CREATE' => 'added',
            'EDIT' => 'updated',
            'DELETE' => 'deleted',
            'COMPLETE' => 'completed',
            'ADD_STYLE' => 'added styles to',
            'REMOVE_STYLE' => 'removed styles from',
            'COSTING' => 'saved the costing of',
            'COSTING_DONE' => 'finished the Knit/Dying Costing of',
            'STOCK' => 'saved the stock yarn use of',
            'STOCK_DONE' => 'finished the Stock Yarn Use of',
            'BOM_CREATE' => 'made an accessories BOM for',
            'BOM_UPDATE' => 'updated an accessories BOM of',
            'BOM_DELETE' => 'deleted an accessories BOM of',
            'WITHDRAW' => 'withdrew',
            'REVISE' => 'revised',
            'CANCEL' => 'cancelled (its order changed)',
        ],
    ],
    'event' => [
        // Order Summary, Dev Sample Status and Pre-Budget (user, 2026-11-21).
        'OS_ORDERS_CHANGED' => [
            'title' => 'Order Summary: :by :action :n order(s)',
            'body' => ':buyers — :orders · :cells cell(s)',
        ],
        'OS_PENDING_WORK' => [
            'title' => 'Order Summary: orders need attention',
            'body' => ':overdue past RFI/SD, :week due within 7 days, :booking with booking pending (LDD/ETD within 14 days) — :buyers',
        ],
        'DS_SAMPLES_CHANGED' => [
            'title' => 'Dev Sample: :by :action :n sample(s)',
            'body' => ':samples · :cells cell(s)',
        ],
        'DS_DEADLINE_DUE' => [
            'title' => 'Dev Sample: submission deadlines due',
            'body' => ':overdue past the deadline, :soon due within 2 days — :samples',
        ],
        'PB_REQUISITIONS_CHANGED' => [
            'title' => 'Pre-Budget: :by :action :n requisition(s)',
            'body' => ':buyers — :styles · :cells cell(s)',
        ],
        'PB_BUDGET_CHANGED' => [
            'title' => 'Pre-Budget: :by :action :budget',
            'body' => ':budget · :buyer',
        ],
        'PB_STOCK_NEEDED' => [
            'title' => 'Stock Yarn Use needed: :budget',
            'body' => ':by :action :budget (:buyer). Fill its Stock Yarn Use.',
        ],
        'PB_PENDING_WORK' => [
            'title' => 'Pre-Budget: budget work pending',
            'body' => ':costing costing, :stock stock yarn use, :accessories accessories budget to do — :budgets',
        ],
        'ES_STATEMENTS_CHANGED' => [
            'title' => 'Export Statement: :by :action :n invoice(s)',
            'body' => ':invoices · net :value · :cells cell(s)',
        ],
        'ES_PENDING_WORK' => [
            'title' => 'Export Statement: documents and claims due',
            'body' => ':docs_overdue past the 10-day docs limit, :docs_soon due within 2 days, :claim_soon cash incentive claim(s) closing within 30 days — :invoices',
        ],
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
        'BREAKDOWN_AWAITING_VERIFICATION' => [
            'title' => 'Ready to verify: :asset',
            'body' => ':number has been repaired and is waiting on your verification.',
        ],
        'BREAKDOWN_AWAITING_APPROVAL' => [
            'title' => 'Ready for approval: :asset',
            'body' => ':number has been verified and is waiting on your final approval.',
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
        'APPROVAL_OVERDUE' => [
            'title' => 'Overdue approval: :number',
            'body' => ':title is past its due date, still waiting on :waiting_on.',
        ],
        'LOW_STOCK' => [
            'title' => 'Low stock: :part',
            'body' => ':on_hand left, reorder level is :reorder_level.',
        ],
        'FLOOR_REQUISITION_SUBMITTED' => [
            'title' => 'Floor requisition :number',
            'body' => ':lines item(s) requested from :department.',
        ],
        'FLOOR_REQUISITION_ISSUED' => [
            'title' => 'Issued against :number',
            'body' => ':issued line(s) issued, :short still short.',
        ],
        'FLOOR_REQUISITION_SHORTAGE' => [
            'title' => 'Shortage on :number',
            'body' => ':lines line(s) could not be issued. Purchase requisition :pr_no is waiting in draft.',
        ],
        'EARMARK_STOCK_ARRIVED' => [
            'title' => 'Stock arrived: :item',
            'body' => ':quantity received against a waiting requisition. Issue the pending quantity.',
        ],
        'RETURNABLE_OVERDUE' => [
            'title' => 'Not returned: :item',
            'body' => ':quantity issued on :number is :days days out and has not come back.',
        ],
        'EARMARK_WAITING' => [
            'title' => 'Still waiting: :item',
            'body' => ':quantity has been claimed for :number for :days days and no stock has arrived.',
        ],
        'FABRIC_INSPECTION_FAILED' => [
            'title' => 'Inspection failed: :number',
            'body' => 'The lot scored :percent points against a limit of :threshold. '
                .'Open a wider inspection, or take it up with the supplier.',
        ],
        'LOT_EXPIRING' => [
            'title' => 'Expiring: :item, lot :lot_no',
            'body' => ':quantity still in stock; the lot expires :expiry_date (:days days).',
        ],
        'STOCK_COUNT_STALE' => [
            'title' => 'Count :count_no open :days days',
            'body' => ':store — :uncounted shelf/shelves still not counted. The system figures on this sheet were frozen when it was opened.',
        ],
        'SHIPMENT_ARRIVING' => [
            'title' => 'Landing in :days days: :shipment_no',
            'body' => ':po_no from :vendor is due :eta. Documents: :document_status.',
        ],
        'SHIPMENT_DELAYED' => [
            'title' => ':days days past ETA: :shipment_no',
            'body' => ':po_no from :vendor was due :eta and has not arrived.',
        ],
        'LC_EXPIRING' => [
            'title' => 'LC :lc_no expires in :days days',
            'body' => ':currency :amount against :po_no (:vendor) expires :expiry_date with nothing shipped. Amending costs a bank charge; letting it lapse costs the opening.',
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
        'GLOBAL_BROADCAST' => [
            'title' => ':title',
            'body' => ':message',
        ],
        'MP_QUOTATION_NEGOTIATION' => [
            'title' => 'Price Revision Requested',
            'body' => ':qt for :item (:pr): :message',
        ],
        'MP_QUOTATION_REJECTED' => [
            'title' => 'Quotation Rejected',
            'body' => ':qt for :item (:pr) was rejected: :reason',
        ],
        'MP_QUOTATION_APPROVED' => [
            'title' => 'Quotation Approved',
            'body' => ':qt for :item (:pr) is approved at :price.',
        ],
        'MP_QUOTATION_RECEIVED' => [
            'title' => 'New Supplier Quotation Received',
            'body' => ':supplier quoted :item on :pr (:qt).',
        ],
        'MP_QUOTATION_REVISED' => [
            'title' => 'Supplier Submitted a Revised Quotation',
            'body' => ':supplier sent revision :revision for :item on :pr (:qt).',
        ],
        'HR_OT_REQUESTED' => [
            'title' => 'OT Request Awaiting Your Approval',
            'body' => ':no — :line on :date, :window for :n employee(s).',
        ],
        'HR_OT_DECIDED' => [
            'title' => 'OT Request :status',
            'body' => ':no — :line on :date was :status by :by.',
        ],
        'MP_MATERIAL_RECEIVED' => [
            'title' => 'Material Received',
            'body' => ':item on :pr: :received of :required :uom received.',
        ],
        'MP_MATERIAL_ASSIGNED' => [
            'title' => 'Approved Material Assigned to You',
            'body' => ':item from :supplier (:pr, :qt) is yours to follow up.',
        ],
        'MP_REQUIREMENT_AVAILABLE' => [
            'title' => 'New Material Requirement Available',
            'body' => ':pr: :lines material line(s) are open for quotation.',
        ],
        'SAMPLE_ASSIGNED' => [
            'title' => 'Sample :sample assigned to you',
            'body' => 'Requisition :requisition is approved and its sample :sample is yours to develop.',
        ],
        'SAMPLE_FEEDBACK_ACTION' => [
            'title' => 'Buyer feedback :feedback needs your action',
            'body' => 'Feedback on sample :sample is assigned to you, due :due.',
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
    'all_marked_read_toast' => 'All marked read',
    'preferences_short' => 'Preferences',
    'page_summary' => 'Page :current of :last · :total total',
    'previous' => 'Previous',
    'next' => 'Next',
    'preferences_page_description' => 'Which channel each kind of event reaches you on.',
];
