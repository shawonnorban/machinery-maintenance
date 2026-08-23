<?php

declare(strict_types=1);

/*
 * Document numbering (SRS 52).
 *
 * A document number is printed on a work order, quoted in an email and typed
 * into somebody else's ERP. Most of the strings here are refusals, and each
 * one prevents a collision that cannot be undone afterwards.
 */

return [
    'numbering' => 'Document numbering',
    'intro' => 'What this company\'s work orders, breakdowns and transfers are called.',
    'document_types' => 'Document types',
    'document_type' => 'Document',
    'format' => 'Format',
    'padding' => 'Digits',
    'next_number' => 'Next number',
    'placeholders' => 'Use {FACTORY}, {YYYY}, {MM} and {SEQ}.',
    'customised' => 'Customised',
    'reset' => 'Back to default',
    'reset_confirm' => 'Go back to the platform default (:format)? It takes effect from the next period.',
    'resets_monthly' => 'Counter restarts each month',
    'resets_yearly' => 'Counter restarts each year',
    'resets_never' => 'Counter never restarts',
    'already_issued' => '{1} :count number already issued|[2,*] :count numbers already issued',

    'next_period_notice' => 'A change here renumbers nothing. Numbers already issued keep the shape they were given, and the new format takes effect when the counter next restarts — at the start of the next month or year.',
    'gaps_note' => 'Gaps in a run of numbers are normal. A number is allocated before the record is saved and is never reused, so a cancelled document leaves its number behind rather than passing it on.',

    'saved' => ':type numbering saved. It takes effect from the next period.',
    'reset_done' => ':type numbering is back to the platform default.',

    'unknown_document_type' => 'No such document type.',
    'format_length' => 'A format must be between 1 and 128 characters.',
    'format_characters' => 'A format may contain letters, digits, hyphens, slashes and full stops besides the placeholders.',
    'unknown_placeholder' => 'The only placeholders are :placeholders.',
    'sequence_required' => 'The format must contain {SEQ}. Without the counter, every document in the period would be given the same number.',
    'factory_required' => 'This counter runs separately in each factory, so the format must contain {FACTORY}. Without it two factories would issue the same number on the same day.',
    'month_required' => 'This counter restarts each month, so the format must contain {MM} and {YYYY}. Without the month, January\'s first number and February\'s would be the same string.',
    'year_required' => 'The format must contain {YYYY}.',
    'month_not_allowed' => 'This counter restarts once a year, so {MM} would put a month in the number that the counter does not follow.',
    'padding_range' => 'Use between 1 and 10 digits.',

    'types' => [
        'WORK_ORDER' => 'Work order',
        'BREAKDOWN' => 'Breakdown',
        'ASSET_TRANSFER' => 'Machine transfer',
        'INVENTORY_TRANSFER' => 'Stock transfer',
        'INVOICE' => 'Invoice',
        'WARRANTY_CLAIM' => 'Warranty claim',
        'SERVICE_CONTRACT' => 'Service contract',
        'GOODS_RECEIPT' => 'Goods receipt',
    ],
];
