<?php

declare(strict_types=1);

return [
    'work_orders' => 'কাজের আদেশ',
    'work_order' => 'কাজের আদেশ',
    'scheduled_maintenance' => 'নির্ধারিত রক্ষণাবেক্ষণ',

    'invalid_transition' => 'কাজের আদেশ :from থেকে :to-এ যেতে পারে না।',
    'assign_needs_technician' => 'আগে অন্তত একজন টেকনিশিয়ান নির্ধারণ করুন।',
    'hold_reason_unknown' => 'স্থগিতের কারণ অজানা।',
    'checklist_incomplete' => 'চেকলিস্টের :count টি আবশ্যক আইটেমের উত্তর এখনো বাকি।',
    'verification_not_required' => 'এই কাজের আদেশে যাচাইয়ের প্রয়োজন নেই।',
    'cannot_verify_own_work' => 'যাচাইয়ের জন্য দ্বিতীয় একজনের চোখ দরকার: নিজের করা কাজ নিজে যাচাই করা যাবে না।',
    'verification_required' => 'বন্ধ করার আগে এই কাজের আদেশ যাচাই করতে হবে।',
    'approval_pending' => 'এই কাজের আদেশ এখনো অনুমোদনের অপেক্ষায়।',
    'cancel_needs_reason' => 'কাজের আদেশ বাতিল করতে কারণ দরকার।',
    'reopen_needs_reason' => 'সম্পন্ন কাজের আদেশ পুনরায় খুলতে কারণ দরকার।',

    'asset_not_found' => 'নির্বাচিত মেশিনটি নেই।',
    'asset_terminal' => ':status মেশিনে নতুন কাজ দেওয়া যায় না।',
    'maintenance_type_required' => 'রক্ষণাবেক্ষণের ধরন বাছুন।',
    'schedule_not_open' => 'এই নির্ধারিত কাজটি আর চালু নেই।',

    'labor_category_unknown' => 'শ্রমের ধরন অজানা।',
    'labor_after_close' => 'বন্ধ কাজের আদেশে আর সময় যোগ করা যায় না।',
    'labor_end_before_start' => 'শেষের সময় শুরুর পরে হতে হবে।',
    'labor_in_future' => 'যে সময় এখনো আসেনি, তার জন্য শ্রম রেকর্ড করা যায় না।',
    'labor_too_long' => 'একটি এন্ট্রি ২৪ ঘণ্টার বেশি হতে পারে না। শিফট অনুযায়ী ভাগ করুন।',
    'labor_needs_technician' => 'কে কাজটি করেছেন সেই টেকনিশিয়ান বাছুন।',
    'labor_overlap' => 'এই টেকনিশিয়ানের :from থেকে :to পর্যন্ত সময় ইতিমধ্যে রেকর্ড করা আছে। একজন একসাথে দুই জায়গায় থাকতে পারেন না।',
    'technician_needs_grade' => 'এই টেকনিশিয়ানের কার্যকর শ্রম গ্রেড নেই, তাই সময়ের খরচ হিসাব করা যাচ্ছে না।',
    'external_needs_rate' => 'বাইরের কন্ট্রাক্টরের শ্রমে ভেন্ডরের চার্জ করা রেট দরকার।',
];
