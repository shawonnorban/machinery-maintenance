<?php

declare(strict_types=1);

// Dev Sample Status (AnnotechERP 31, from the user's Norban ERP).
return [
    'folders' => ['samples' => 'Samples', 'reports' => 'Reports', 'setup' => 'Setup'],
    'screens' => [
        'dashboard' => 'Dashboard',
        'list' => 'View All Dev Sample',
        'new' => 'Add Dev Sample',
        'edit' => 'Edit Dev Sample',
        'report' => 'Pivot Report',
        'columns' => 'Column Setup',
        'audit' => 'Audit Log',
    ],
    'descriptions' => [
        'dashboard' => 'The samples at a glance: Norban\'s summary cards, samples to submit by days left to the deadline, samples by status, buyers and requested months, and the latest saves.',
        'list' => 'Every development sample as a sheet: filter any column, tick rows to edit them in place, copy and paste like Excel.',
        'new' => 'New development samples, typed into the sheet like Excel: the buyer\'s request, the sample\'s dates, quantities, quoted prices and status.',
        'columns' => 'The sample sheet\'s groups and columns: add or change columns, formulas, colour rules, widths, freeze panes and who sees each column.',
        'edit' => 'Change a development sample.',
        'report' => 'Running development sample summary: buyers by order confirmation, and by sample status in as much detail as you choose.',
        'audit' => 'Every save, cell by cell: who changed what and when — only the samples you see on the sheet.',
    ],

    'sections' => [
        'basic' => 'Basic Information',
        'details' => 'Sample Details',
        'dates' => 'Dates',
        'tracking' => 'Production & Sample Tracking',
        'qty_price' => 'Quantity & Price',
        'status' => 'Status',
    ],

    'f' => [
        'season' => 'Season', 'buyer' => 'Buyer', 'customer_name' => 'Customer Name', 'brand' => 'Brand', 'department' => 'Department',
        'division' => 'Division', 'styling' => 'Styling', 'image' => 'Image', 'job_no' => 'Job No.', 'items' => 'Item',
        'required_category' => 'Required Category', 'sample_type' => 'Sample Type', 'fabrication' => 'Fabrication', 'colours' => 'Color with Qty',
        'tp_rcv_date' => 'TP RCV Date', 'buyer_requested_date' => 'Buyer Requested Date', 'submission_deadline' => 'Submission Deadline', 'sub_plan_date' => 'Sample Sub. Plan Date',
        'booking_date' => 'Booking Date', 'knitting_plan_date' => 'Knitting Plan Date', 'actual_grige_rcvd_date' => 'Actual Grige Rcvd Date',
        'finish_fabric_delivery_plan_date' => 'Finish Fabric Delivery Plan Date', 'finish_fabric_rcvd_date' => 'Finish Fabric Rcvd Date',
        'sample_sewing_plan_date' => 'Sample Sewing Plan Date', 'file_handover_date' => 'File Handover Date', 'pattern_ready_date' => 'Pattern Ready Date',
        'cutting_date' => 'Cutting Date', 'print_emb_date' => 'Print/EMB Date', 'sewing_start_date' => 'Sewing Start Date', 'sewing_output_date' => 'Sewing Output Date',
        'sample_submitted_date' => 'Sample Submitted Date', 'awb_tracking' => 'AWB Tracking',
        'sample_qty' => 'Sample QTY (Buyer)', 'possible_qty' => 'Possible QTY', 'actual_order_qty' => 'Actual Order qty',
        'target_prices' => 'Buyer Target Price ($)', 'fob_prices' => 'FOB Price ($)', 'last_price_quote_date' => 'Last Price Quote Date',
        'price_status' => 'Price Status', 'sample_status' => 'Sample Status', 'order_confirmation' => 'Order Confirmation',
        'current_order_situation' => 'Current Order Situation', 'remarks' => 'Remarks', 'search' => 'Search', 'per_page' => 'Per Page',
        'colour' => 'Color', 'qty' => 'Quantity', 'label' => 'Label', 'price' => 'Price ($)',
    ],
    'ph' => [
        'styling' => 'e.g. NW7431 OPT-1', 'job_no' => 'e.g. NCL-SMN-25-00390', 'fabrication' => 'e.g. TERRY -Y/D Loop Back', 'awb_tracking' => 'AWB / tracking no.',
        'search' => 'Styling / Job / Item / Color', 'items' => 'Select items', 'label' => 'e.g. BIO', 'select' => '-- Select --',
    ],
    'help' => [
        'pcs' => 'Pcs',
        'quote_by' => 'Entered by is saved automatically as the signed-in user.',
        'quote_by_named' => 'Entered by :name',
        'price_levels' => 'The first line is the summary price; the second (else the first) is the one kept.',
        'image' => 'JPG, PNG, WEBP or GIF, up to 2 MB.',
    ],

    // The list's columns (Norban's names).
    'col' => [
        'serial' => '#', 'season' => 'Season', 'buyer' => 'Buyer', 'customer_name' => 'Customer Name', 'brand' => 'Brand', 'department' => 'Department',
        'division' => 'Division', 'styling' => 'Styling', 'image' => 'Image', 'job_no' => 'Job', 'items' => 'Item', 'required_category' => 'Req. Category',
        'sample_type' => 'Sample Type', 'fabrication' => 'Fabrication', 'colours' => 'Color', 'sample_qty' => 'Sample QTY', 'possible_qty' => 'Possible QTY',
        'actual_order_qty' => 'Actual Order qty', 'target_prices' => 'Target Price', 'fob_prices' => 'FOB Price', 'tp_rcv_date' => 'TP RCV Date',
        'buyer_requested_date' => 'Buyer Req. Date', 'submission_deadline' => 'Submission Deadline', 'sub_plan_date' => 'Sub. Plan Date',
        'booking_date' => 'Booking Date', 'knitting_plan_date' => 'Knitting Plan Date', 'actual_grige_rcvd_date' => 'Actual Grige Rcvd Date',
        'finish_fabric_delivery_plan_date' => 'Finish Fabric Delivery Plan Date', 'finish_fabric_rcvd_date' => 'Finish Fabric Rcvd Date',
        'sample_sewing_plan_date' => 'Sample Sewing Plan Date', 'file_handover_date' => 'File Handover Date', 'pattern_ready_date' => 'Pattern Ready Date',
        'cutting_date' => 'Cutting Date', 'print_emb_date' => 'Print/EMB Date', 'sewing_start_date' => 'Sewing Start Date', 'sewing_output_date' => 'Sewing Output Date',
        'sample_submitted_date' => 'Sample Submitted Date', 'awb_tracking' => 'AWB Tracking', 'last_price_quote_date' => 'Last Quote Date',
        'price_status' => 'Price Status', 'sample_status' => 'Sample Status', 'order_confirmation' => 'Order Confirmation',
        'current_order_situation' => 'Current Order Situation', 'remarks' => 'Remarks', 'last_update' => 'Last Update', 'actions' => 'Actions',
    ],

    'kpi' => [
        'total_records' => 'Total Orders', 'submission_pending' => 'Order Submission Pending', 'submitted' => 'Order Submitted',
        'order_dropped' => 'Order Dropped', 'order_confirmed' => 'Order Confirmed', 'total_sample_qty' => 'Total Sample Qty',
        'total_possible_qty' => 'Total Possible Qty', 'avg_target_price' => 'Avg Target Price', 'avg_fob_price' => 'Avg FOB Price',
        'total_fob_value' => 'Total FOB Value',
    ],

    'dash' => [
        'deadline' => 'To submit, by days left to the Submission Deadline', 'by_status' => 'By sample status', 'by_buyer' => 'By buyer',
        'by_month' => 'By buyer requested month', 'buyer' => 'Buyer', 'samples' => 'Samples', 'qty' => 'Sample Qty', 'value' => 'FOB Value',
        'pending' => 'To submit', 'no_status' => 'No status', 'recent' => 'Latest changes', 'no_recent' => 'Nothing changed yet.',
        'recent_line' => ':samples sample(s), :cells cell(s)',
        'bucket' => ['overdue' => 'Overdue', 'week' => '0–7 days', 'month' => '8–30 days', 'later' => '30+ days', 'none' => 'No date'],
        'kind' => ['CREATE' => 'Created', 'EDIT' => 'Updated', 'DELETE' => 'Deleted', 'RECALC' => 'Recalculated'],
    ],

    'btn' => [
        'add_new' => 'Add New Sample', 'report' => 'Pivot Report', 'print' => 'Print', 'raw_copy' => 'Raw Copy', 'columns' => 'Columns',
        'all' => 'All', 'none' => 'None', 'apply' => 'Apply', 'reset_default' => 'Reset', 'save' => 'Save Record', 'save_changes' => 'Save Changes',
        'cancel' => 'Cancel', 'back' => 'Back to List', 'add_colour' => 'Add Color & Qty', 'add_level' => 'Add (Label – $)', 'remove' => 'Remove',
        'edit' => 'Edit', 'delete' => 'Delete', 'copy_all' => 'Copy All to Clipboard', 'close' => 'Close', 'save_field' => 'Save', 'full_edit' => 'Full edit',
        'upload' => 'Choose image', 'remove_image' => 'Remove image', 'view_all' => 'View All',
    ],

    'msg' => [
        'saved' => 'Sample #:no saved.', 'updated' => 'Sample #:no updated.', 'deleted' => 'Sample deleted.', 'copied' => 'Copied — paste it into Excel or Google Sheets.',
        'delete_title' => 'Delete this sample?', 'delete_text' => 'Sample #:no (:styling) will be removed from the list.',
        'empty' => 'No development samples match.', 'error' => 'Something went wrong. Try again.', 'image_failed' => 'The image could not be uploaded.',
        'cell_full_edit' => 'This field is edited on the full form.', 'raw_hint' => 'Paste directly into Excel or Google Sheets (Ctrl+V).',
        'showing' => 'Showing :from to :to of :total results', 'no_image' => 'No Image', 'columns_hint' => 'Choose the columns the list and print show.',
    ],

    'report' => [
        'summary' => 'STYLE NAME/NUMBER', 'detail' => 'STYLE NAME/NUMBER (Detail by Buyer)', 'show_in_report' => 'Show in report:',
        'buyer' => 'Buyer', 'row_labels' => 'Row Labels', 'grand_total' => 'Grand Total', 'blank' => '(Blank)', 'empty' => 'No samples for these filters.',
        'levels' => ['sample_type' => 'Sample Type', 'item' => 'Item', 'styling' => 'Styling', 'job_no' => 'Job No.', 'color' => 'Color', 'fabrication' => 'Fabrication'],
        'all_seasons' => 'All Seasons', 'all_buyers' => 'All Buyers',
    ],

    'opt' => [
        'required_category' => ['SAMPLE' => 'Sample', 'SAMPLE_WITH_PRICE' => 'SAMPLE WITH PRICE'],
        'sample_type' => [
            'PROTO' => 'Proto/Initial Development', 'PP' => 'PP Sample', 'REVISED_PROTO' => 'Revised Proto', 'STRIKE_OFF' => 'Strike Off',
            'SMS' => 'SMS Samples', 'FABRIC_HANGER' => 'Fabric Hanger Only', 'PRICE_ONLY' => 'Price Only', 'SHIPMENT' => 'Shipment Sample', 'OTHERS' => 'Others',
        ],
        'price_status' => ['OFFERED' => 'OFFERED PRICE', 'PENDING_OFFER' => 'PENDING TO OFFER', 'CANCELLED' => 'CANCELLED', 'SELF_DROP' => 'SELF DROP', 'CONFIRMED' => 'CONFIRMED'],
        'sample_status' => ['SUBMISSION_PENDING' => 'SUBMISSION PENDING', 'SUBMITTED' => 'SUBMITTED', 'CLARIFICATION' => 'CLARIFICATION ON PROCESS', 'CANCELLED' => 'CANCELLED', 'SELF_DROP' => 'SELF DROP'],
        'order_confirmation' => [
            'CONFIRMED_WITH_DEV' => 'CONFIRMED WITH DEVELOPMENT', 'CONFIRMED_WITHOUT_DEV' => 'CONFIRMED WITHOUT DEVELOPMENT', 'ORDER_CONFIRMED' => 'ORDER CONFIRMED',
            'CONFIRMATION_PENDING' => 'CONFIRMATION PENDING', 'NEGOTIATION' => 'NEGOTIATION ON PROCESS', 'PENDING_OFFER' => 'PENDING TO OFFER',
            'DROP' => 'DROP', 'CANCELLED' => 'CANCELLED', 'SELF_DROP' => 'SELF DROP',
        ],
        'current_order_situation' => [
            'FABRIC_BOOKING' => 'On Fabric Booking Stage', 'KNITTING' => 'On Knitting Stage', 'DYEING_FINISHING' => 'On Dyeing & Finishing Stage',
            'CUTTING' => 'On Cutting Stage', 'PRINT_EMB' => 'On Print/Emb stage', 'SEWING' => 'On Sewing stage', 'READY_TO_SEND' => 'Samples are Ready to Send',
            'SENT_TO_BUYER' => 'Samples sent to buyer', 'CANCELLED' => 'Cancelled', 'SELF_DROP' => 'Self Drop',
        ],
    ],

    // Validation attribute names.
    'attrs' => [
        'season' => 'season', 'buyer_id' => 'buyer', 'customer_name' => 'customer name', 'brand' => 'brand', 'department' => 'department', 'division' => 'division',
        'styling' => 'styling', 'job_no' => 'job no.', 'image_file_id' => 'image', 'items' => 'item', 'colours' => 'colors', 'colours_name' => 'color',
        'colours_qty' => 'color quantity', 'target_prices_price' => 'target price', 'fob_prices_price' => 'FOB price',
        'target_prices_label' => 'target price label', 'fob_prices_label' => 'FOB price label', 'sample_qty' => 'sample qty', 'possible_qty' => 'possible qty',
        'actual_order_qty' => 'actual order qty', 'price_status' => 'price status', 'sample_status' => 'sample status', 'order_confirmation' => 'order confirmation',
        'current_order_situation' => 'current order situation', 'required_category' => 'required category', 'sample_type' => 'sample type',
        'last_price_quote_date' => 'last price quote date', 'file' => 'image',
    ],

    // The sample sheet and its Column Setup (2026-11-21).
    'v' => [
        'number' => ':column needs a number.',
        'date' => ':column needs a date.',
        'option' => ':column: pick a value from the list.',
        'image' => ':column needs an uploaded picture.',
        'read_only' => 'You cannot change :column.',
        'row_gone' => 'This sample was deleted meanwhile.',
        'missing' => 'Row :row (:serial): fill :columns.',
        'system_column' => 'A starting column cannot be deleted; hide it instead.',
        'column_in_use' => 'These formulas read this column: :columns',
    ],
    'cs' => [
        'source' => ['buyers' => 'Buyers', 'items' => 'Items (garment)', 'colours' => 'Colours', 'fabric_types' => 'Fabric types', 'compositions' => 'Compositions', 'suppliers' => 'Supplier Master', 'ds' => 'Dev Sample list: :name'],
    ],
    // The Audit Log's filter bar (the shared AuditLog / Filters reads these).
    'sheet' => ['all' => 'All', 'filter' => 'Filter', 'clear' => 'Clear'],
    'audit' => [
        'from' => 'From', 'to' => 'To', 'action' => 'Action', 'user' => 'User', 'search' => 'Style, job, buyer…', 'empty' => 'No changes found.',
        'kind' => ['CREATE' => 'Created', 'EDIT' => 'Updated', 'DELETE' => 'Deleted', 'RECALC' => 'Recalculated', 'RESTORE' => 'Restored'],
    ],
];
