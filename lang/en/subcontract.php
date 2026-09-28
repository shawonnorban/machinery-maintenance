<?php

declare(strict_types=1);

/*
 * Fabric sent out to be dyed, printed or embroidered (Inventory spec §8).
 *
 * The cloth leaves the building and stays ours, which is why none of this
 * reads like an issue or a sale.
 */

return [
    'subcontract' => 'Subcontract',
    'challans' => 'Subcontract challans',
    'challan' => 'Challan',
    'new_challan' => 'New challan',
    'challan_no' => 'Challan no.',
    'challan_date' => 'Challan date',
    'hint' => 'Cloth sent out to a dyer, printer or embroiderer. It stays yours the whole time — nothing is issued and nothing is sold, so the rolls come off the available shelf without leaving your books.',
    'vendor' => 'Subcontractor',
    'purpose' => 'Purpose',
    'purposes' => [
        'DYEING' => 'Dyeing',
        'PRINTING' => 'Printing',
        'EMBROIDERY' => 'Embroidery',
        'WASHING' => 'Washing',
        'OTHER' => 'Other',
    ],
    'expected_return_date' => 'Expected back',
    'overdue' => 'Overdue',
    'status' => 'Status',
    'all_statuses' => 'All statuses',
    'statuses' => [
        'DRAFT' => 'Draft',
        'SENT' => 'Sent out',
        'PARTIALLY_RETURNED' => 'Partly back',
        'CLOSED' => 'Closed',
        'CANCELLED' => 'Cancelled',
    ],
    'line_statuses' => [
        'OUT' => 'Out',
        'PARTIALLY_RETURNED' => 'Partly back',
        'RETURNED' => 'Back',
        'WRITTEN_OFF' => 'Written off',
    ],

    'rolls' => 'Rolls',
    'roll_no' => 'Roll',
    'sent_qty' => 'Sent',
    'returned_qty' => 'Returned',
    'wastage_qty' => 'Wastage',
    'outstanding_qty' => 'Unaccounted',
    'quality_status' => 'Quality',
    'balance_hint' => 'Sent must equal returned plus wastage. Six metres that are neither is a process loss nobody can see.',

    'send' => 'Send out',
    'receive_return' => 'Receive back',
    'close_challan' => 'Close',
    'cancel_challan' => 'Cancel',
    'close_hint' => 'Closing writes off whatever never came back, so it stops showing as stock you do not have.',
    'challan_created' => 'Challan :number created.',
    'challan_sent' => ':number sent to :vendor.',
    'return_recorded' => 'Return recorded against :number.',
    'challan_closed' => ':number closed.',
    'challan_cancelled' => ':number cancelled.',
    'no_challans' => 'Nothing is out at a subcontractor.',
    'outstanding_only' => 'Still out only',
    'search_placeholder' => 'Search by challan number…',
    'sent_at' => 'Sent',
    'closed_at' => 'Closed',

    'roll_not_sendable' => 'Roll :roll cannot be sent out while it is :status.',
    'challan_not_out' => 'A :status challan has nothing out to come back.',
    'unknown_line' => 'That roll is not on this challan.',
    'return_exceeds_sent' => 'Only :outstanding of roll :roll is still unaccounted for.',
];
