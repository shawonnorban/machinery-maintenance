<?php

declare(strict_types=1);

/*
 * Bonded warehouse, UD/UP and duty (Shared Systems spec §3).
 *
 * The words here have to carry a distinction the screens depend on: a
 * bond is a promise that imported goods leave again as exports, so
 * issuing bonded fabric to the cutting floor is the bond working, while
 * anything else leaving is a breach that costs money.
 */

return [
    'compliance' => 'Compliance',
    'bond_licences' => 'Bond licences',
    'bond_licence' => 'Bond licence',
    'new_licence' => 'New bond licence',
    'licence_no' => 'Licence no.',
    'issuing_office' => 'Issuing customs office',
    'issued_date' => 'Issued',
    'valid_from' => 'Valid from',
    'valid_to' => 'Valid to',
    'licence_hint' => 'What lets the factory import fabric and trims duty-free, against the promise that they leave again as garments. Everything else on this screen hangs off it.',
    'licence_statuses' => [
        'ACTIVE' => 'Active',
        'SUSPENDED' => 'Suspended',
        'CANCELLED' => 'Cancelled',
    ],
    'licence_live' => 'In force',
    'licence_expired' => 'Period ended',
    'days_to_expiry' => 'Days left',
    'suspend_licence' => 'Suspend',
    'restore_licence' => 'Restore',
    'licence_created' => 'Licence :number recorded.',
    'licence_updated' => ':number updated.',
    'licence_status_changed' => ':number is now :status.',
    'no_licences' => 'No bond licence has been recorded.',
    'licence_search_placeholder' => 'Search by licence number or office…',

    'entitlements' => 'Entitlements',
    'entitlement_hint' => 'How much of each item the licence allows in duty-free. What has actually come in is counted from goods receipts rather than kept as a running total, so it cannot drift.',
    'entitled_quantity' => 'Entitled',
    'imported_quantity' => 'Imported',
    'remaining_quantity' => 'Remaining',
    'used_percent' => 'Used',
    'entitlement_exceeded' => 'Over entitlement',
    'bases' => [
        'ANNUAL' => 'Annual ceiling',
        'PER_ORDER' => 'Per export order',
    ],
    'basis' => 'Basis',
    'period' => 'Period',

    'declarations' => 'UD / UP',
    'declaration' => 'Utilisation declaration',
    'new_declaration' => 'New declaration',
    'ud_no' => 'UD / UP no.',
    'ud_type' => 'Type',
    'ud_types' => ['UD' => 'Utilisation declaration', 'UP' => 'Utilisation permission'],
    'declaration_hint' => 'What a particular export order tells customs it will consume, accepted in advance. Afterwards the reconciliation compares it against what the store actually issued.',
    'buyer_reference' => "Buyer's reference",
    'declaration_statuses' => [
        'DRAFT' => 'Draft',
        'ISSUED' => 'Issued',
        'CLOSED' => 'Closed',
        'CANCELLED' => 'Cancelled',
    ],
    'issue_declaration' => 'Mark issued',
    'close_declaration' => 'Close and file',
    'declaration_created' => 'Declaration :number recorded.',
    'declaration_issued' => ':number issued.',
    'declaration_closed' => ':number closed.',
    'declaration_cancelled' => ':number cancelled.',
    'no_declarations' => 'No declaration has been raised against this licence.',

    'declared_quantity' => 'Declared',
    'wastage_percent' => 'Wastage allowed',
    'allowed_quantity' => 'Declared + wastage',
    'consumed_quantity' => 'Actually consumed',
    'variance_quantity' => 'Variance',
    'exceeds_allowance' => 'Over allowance',
    'reconciliation' => 'Declared vs actual',
    'reconciliation_hint' => 'Consuming far less than declared leaves bonded goods with no export to justify them; consuming more is a shortfall to account for. The variance is signed, because those are different problems.',

    'duty_assessments' => 'Duty assessments',
    'duty_assessment' => 'Duty assessment',
    'new_assessment' => 'Assess duty',
    'assessment_no' => 'Assessment no.',
    'assessment_date' => 'Assessed',
    'duty_hint' => 'Bonded stock is duty-free while it is headed for export. Issuing it to the cutting floor is the bond working as intended and costs nothing; anything else leaving it makes the duty payable, and this is where that is recorded.',
    'duty_reasons' => [
        'LOCAL_SALE' => 'Sold locally',
        'TRANSFER_OUT' => 'Moved out of bond',
        'SHORTAGE' => 'Short at stock count',
        'WRITE_OFF' => 'Written off',
        'OTHER' => 'Other',
    ],
    'reason' => 'Reason',
    'assessable_value' => 'Assessable value',
    'unit_value' => 'Unit value',
    'duty_rate' => 'Duty rate',
    'vat_rate' => 'VAT rate',
    'duty_amount' => 'Duty',
    'vat_amount' => 'VAT',
    'total_payable' => 'Total payable',
    'hs_code' => 'HS code',
    'unrated' => 'No tariff heading',
    'unrated_hint' => 'This item carries no HS code, so the assessment was written at zero. A released consignment nobody can rate is a question for customs rather than a row to drop.',
    'assessment_created' => 'Assessment :number raised for :amount.',
    'no_assessments' => 'No duty has been assessed.',
    'assessment_search_placeholder' => 'Search by assessment number or item…',
    'bonded_store' => 'Bonded store',
    'bond_applicable' => 'Against a bond',
    'duty_exemption_reference' => 'Duty exemption reference',

    'licence_period_backwards' => 'The licence cannot end before it starts.',
    'licence_not_live' => 'Licence :number is :status, so a declaration issued against it would be worthless at the port.',
    'ud_needs_lines' => 'A declaration needs at least one item.',
    'duty_store_not_bonded' => ':store is not a bonded store, so nothing leaving it owes duty.',
    'unknown_licence' => 'That bond licence is not available to your company.',

    // Shared words the bond screens need.
    'factory' => 'Factory',
    'item' => 'Item',
    'notes' => 'Notes',
    'history' => 'History',
    'created' => 'Recorded',
    'status' => 'Status',
    'quantity' => 'Quantity',
    'purchase_order' => 'Purchase order',
    'store' => 'Store',
];
