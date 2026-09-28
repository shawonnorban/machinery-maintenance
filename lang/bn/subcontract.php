<?php

declare(strict_types=1);

/*
 * ডাইং, প্রিন্টিং বা এমব্রয়ডারির জন্য বাইরে পাঠানো ফেব্রিক (Inventory spec §8)।
 *
 * কাপড় বাইরে যায় কিন্তু মালিকানা আমাদেরই থাকে — তাই এটি ইস্যুও নয়, বিক্রিও নয়।
 */

return [
    'subcontract' => 'সাবকন্ট্রাক্ট',
    'challans' => 'সাবকন্ট্রাক্ট চালান',
    'challan' => 'চালান',
    'new_challan' => 'নতুন চালান',
    'challan_no' => 'চালান নং',
    'challan_date' => 'চালানের তারিখ',
    'hint' => 'ডায়ার, প্রিন্টার বা এমব্রয়ডারির কাছে পাঠানো কাপড়। পুরো সময়টাই এটি আপনারই থাকে — ইস্যুও হয় না, বিক্রিও হয় না; রোলগুলো শুধু উপলব্ধ তাক থেকে সরে যায়, হিসাব থেকে নয়।',
    'vendor' => 'সাবকন্ট্রাক্টর',
    'purpose' => 'উদ্দেশ্য',
    'purposes' => [
        'DYEING' => 'ডাইং',
        'PRINTING' => 'প্রিন্টিং',
        'EMBROIDERY' => 'এমব্রয়ডারি',
        'WASHING' => 'ওয়াশিং',
        'OTHER' => 'অন্যান্য',
    ],
    'expected_return_date' => 'ফেরত আসার কথা',
    'overdue' => 'সময় পার',
    'status' => 'অবস্থা',
    'all_statuses' => 'সব অবস্থা',
    'statuses' => [
        'DRAFT' => 'ড্রাফট',
        'SENT' => 'পাঠানো হয়েছে',
        'PARTIALLY_RETURNED' => 'আংশিক ফেরত',
        'CLOSED' => 'বন্ধ',
        'CANCELLED' => 'বাতিল',
    ],
    'line_statuses' => [
        'OUT' => 'বাইরে',
        'PARTIALLY_RETURNED' => 'আংশিক ফেরত',
        'RETURNED' => 'ফেরত এসেছে',
        'WRITTEN_OFF' => 'অবলোপন',
    ],

    'rolls' => 'রোল',
    'roll_no' => 'রোল',
    'sent_qty' => 'পাঠানো',
    'returned_qty' => 'ফেরত',
    'wastage_qty' => 'অপচয়',
    'outstanding_qty' => 'হিসাব বাকি',
    'quality_status' => 'গুণগত মান',
    'balance_hint' => 'পাঠানো = ফেরত + অপচয় হতে হবে। যে ছয় মিটার কোনোটিতেই নেই, সেটি এমন প্রসেস লস যা কেউ দেখতে পায় না।',

    'send' => 'বাইরে পাঠান',
    'receive_return' => 'ফেরত গ্রহণ',
    'close_challan' => 'বন্ধ করুন',
    'cancel_challan' => 'বাতিল',
    'close_hint' => 'বন্ধ করলে যা ফেরত আসেনি তা অবলোপন হয়ে যায়, ফলে যে স্টক নেই তা আর স্টক হিসেবে দেখাবে না।',
    'challan_created' => 'চালান :number তৈরি হয়েছে।',
    'challan_sent' => ':number :vendor-এর কাছে পাঠানো হয়েছে।',
    'return_recorded' => ':number-এর বিপরীতে ফেরত রেকর্ড হয়েছে।',
    'challan_closed' => ':number বন্ধ করা হয়েছে।',
    'challan_cancelled' => ':number বাতিল হয়েছে।',
    'no_challans' => 'সাবকন্ট্রাক্টরের কাছে কিছু নেই।',
    'outstanding_only' => 'শুধু যা এখনো বাইরে',
    'search_placeholder' => 'চালান নম্বর দিয়ে খুঁজুন…',
    'sent_at' => 'পাঠানো',
    'closed_at' => 'বন্ধ',

    'roll_not_sendable' => 'রোল :roll এখন :status অবস্থায়, বাইরে পাঠানো যাবে না।',
    'challan_not_out' => ':status অবস্থার চালানে ফেরত আসার মতো কিছু নেই।',
    'unknown_line' => 'এই রোলটি এই চালানে নেই।',
    'return_exceeds_sent' => 'রোল :roll-এর আর মাত্র :outstanding হিসাব বাকি।',
];
