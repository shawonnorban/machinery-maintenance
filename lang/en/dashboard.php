<?php

declare(strict_types=1);

return [
    'dashboard' => 'Dashboard',
    'period' => 'Period',
    'last_days' => 'Last :days days',
    'period_note' => 'Every figure below covers this period and the factory selected in the header.',
    'scope_all_factories' => 'All factories',

    // --- management ---
    'management' => 'Fleet',
    'total_assets' => 'Machines',
    'running' => 'Running',
    'idle' => 'Idle',
    'breakdown' => 'Broken down',
    'under_maintenance' => 'Under maintenance',
    'under_repair' => 'Under repair',
    'overdue_maintenance' => 'Overdue maintenance',
    'overdue_hint' => 'Past the grace period, not merely past the due date.',

    'availability' => 'Availability',
    'availability_hint' => 'Operating time as a share of scheduled operating time, against the factory shift calendar — not against 24 hours a day.',
    'mtbf' => 'MTBF',
    'mtbf_hint' => 'Mean operating time between failures. Operating time, not calendar time.',
    'mttr' => 'MTTR',
    'mttr_hint' => 'Mean repair time, hold time excluded: waiting for a part is a supply problem, not slow repair work.',
    'mtta' => 'Response time',
    'mtta_hint' => 'Report to acknowledgement. Measured from the report, because time before anyone said so is a reporting problem.',
    'time_to_arrive' => 'Time to arrive',
    'unplanned_downtime' => 'Unplanned downtime',
    'failures' => 'Failures',
    'failures_hint' => 'Unplanned breakdowns. A duplicate report against a machine already down is not a second failure.',
    'downtime_minutes' => 'Downtime',
    'scheduled_minutes' => 'Scheduled operating time',
    'operating_minutes' => 'Operating time',

    'cost' => 'Cost',
    'maintenance_cost' => 'Maintenance',
    'breakdown_cost' => 'Breakdown',
    'total_cost' => 'Total',
    'cost_hint' => 'Split because the two mean different things: one is the cost of keeping machines running, the other the cost of them stopping.',

    // --- maintenance ---
    'maintenance_dashboard' => 'Maintenance',
    'todays_tasks' => 'Due today',
    'due' => 'Due',
    'open_work_orders' => 'Open work orders',
    'active_breakdowns' => 'Active breakdowns',
    'unacknowledged' => 'Not acknowledged',
    'unacknowledged_hint' => 'A machine is down and nobody has picked it up yet.',
    'pm_compliance' => 'PM compliance',
    'pm_compliance_hint' => 'Preventive maintenance completed within its due date plus grace.',
    'schedule_attainment' => 'Schedule attainment',
    'technician_workload' => 'Technician workload',
    'technician' => 'Technician',
    'open_jobs' => 'Open jobs',
    'at_capacity' => 'At capacity',
    'no_technicians' => 'No technicians on this factory.',

    // --- store ---
    'store' => 'Store',
    'stock_value' => 'Stock value',
    'low_stock' => 'Low stock',
    'low_stock_hint' => 'At or below the reorder level. By the time stock is out, the lead time has already been lost.',
    'out_of_stock' => 'Out of stock',
    'critical_low' => 'Critical spares low',
    'critical_low_hint' => 'Parts whose absence stops a critical machine.',
    'reserved' => 'Reserved',
    'active_reservations' => 'Active reservations',
    'parts_issued' => 'Parts issued',

    // --- shared ---
    'minutes' => 'min',
    'hours' => 'h',
    'not_available_reason' => 'Nothing happened in this period to compute it from. A zero would say something different.',
    'no_panels' => 'Nothing to show',
    'no_panels_hint' => 'Your role has no dashboard panels. The lists in the sidebar are where your work is.',
];
