<?php

declare(strict_types=1);

return [
    'rule_event' => 'যখন এটি ঘটে',
    'escalations' => 'এস্কেলেশন নিয়ম',
    'escalations_intro' => 'কোনো কিছু কতক্ষণ উত্তরহীন পড়ে থাকলে সেটি উপেক্ষাকারীকে ছাড়িয়ে উপরে যাবে। লেভেল ১ প্রথম মনে করিয়ে দেওয়া; এর পরের প্রতিটি লেভেল আরও উপরে পৌঁছায়।',
    'new_rule' => 'নতুন নিয়ম',
    'add_rule' => 'নিয়ম যোগ করুন',
    'any_severity' => 'যেকোনো গুরুত্ব',
    'after' => 'আর কেউ সাড়া দেয় না',
    'level' => 'লেভেল',
    'tell' => 'জানান',
    'factory' => 'ফ্যাক্টরি',
    'every_factory' => 'সব ফ্যাক্টরি',
    'stop_on_acknowledge' => 'কেউ কাজটি ধরলে থেমে যাবে',
    'minutes' => '{1} :count মিনিট|[2,*] :count মিনিট',
    'pause' => 'বিরতি',
    'resume' => 'চালু',
    'no_rules' => 'কোনো এস্কেলেশন নিয়ম নেই।',
    'no_rules_hint' => 'নিয়ম না থাকলে যে সতর্কবার্তা কেউ পড়ে না, সেটির কথা আর কখনো কেউ জানতে পারে না।',
    'rule_added' => 'নিয়ম যোগ হয়েছে।',
    'rule_updated' => 'নিয়ম সংরক্ষিত হয়েছে।',
    'rule_removed' => 'নিয়ম সরানো হয়েছে।',
    'remove_rule_confirm' => 'এই নিয়মটি সরাবেন? আগে পাঠানো সতর্কবার্তায় কোনো প্রভাব পড়বে না।',
    'unknown_role' => 'এই রোলটি আপনার কোম্পানির জন্য উপলব্ধ নয়।',
    'factory_unavailable' => 'এই ফ্যাক্টরিটি আপনার জন্য উপলব্ধ নয়।',
    'level_already_covered' => 'এই ইভেন্টের জন্য লেভেল :level ইতিমধ্যেই আছে। এক লেভেলে দুটি নিয়ম থাকলে একই নীরবতার জন্য একই ব্যক্তিকে দুবার জানানো হতো।',
    'role_not_person_hint' => 'নিয়মে ব্যক্তির নয়, রোলের নাম থাকে — একজনের নাম লেখা নিয়ম তাঁর অনুপস্থিতির সপ্তাহেই অচল হয়, অথচ ঠিক তখনই সেটির দরকার।',
    'notifications' => 'বিজ্ঞপ্তি',
    'notification' => 'বিজ্ঞপ্তি',
    'unread' => 'অপঠিত',
    'all' => 'সব',
    'mark_read' => 'পঠিত হিসেবে চিহ্নিত করুন',
    'mark_all_read' => 'সবগুলো পঠিত হিসেবে চিহ্নিত করুন',
    'marked_read' => 'পঠিত হিসেবে চিহ্নিত করা হয়েছে।',

    'acknowledge' => 'দায়িত্ব গ্রহণ করুন',
    'acknowledged' => 'দায়িত্ব গ্রহণ করা হয়েছে।',
    'acknowledge_hint' => 'দায়িত্ব গ্রহণ করলে এসকেলেশন বন্ধ হবে। শুধু বিজ্ঞপ্তি খুললে এসকেলেশন বন্ধ হবে না। বিজ্ঞপ্তি পড়া এবং দায়িত্ব গ্রহণ করা এক বিষয় নয়।',

    'no_notifications' => 'কোনো নতুন বিজ্ঞপ্তি নেই',
    'no_notifications_hint' => 'মেশিন ব্রেকডাউন, বকেয়া মেইনটেনেন্স এবং অনুমোদনের অনুরোধ এখানে দেখানো হবে।',

    'severity' => 'গুরুত্ব',
    'severity_info' => 'তথ্য',
    'severity_warning' => 'সতর্কতা',
    'severity_critical' => 'জরুরি',

    'received' => 'প্রাপ্ত',
    'event_label' => 'ঘটনা',
    'escalated' => 'এসকেলেট করা হয়েছে',
    'escalation_level' => 'এসকেলেশনের স্তর',
    'escalated_from' => 'কেউ দায়িত্ব গ্রহণ না করায় এসকেলেট করা হয়েছে',

    'open' => 'বিস্তারিত দেখুন',
    'unknown_event' => 'অজানা বিজ্ঞপ্তির ঘটনা: :event।',
    'unknown_severity' => 'বিজ্ঞপ্তির গুরুত্ব নির্ধারণ করা যায়নি।',

    // --- Notification Preferences ---
    'preferences' => 'বিজ্ঞপ্তির সেটিংস',
    'preferences_saved' => 'বিজ্ঞপ্তির সেটিংস সংরক্ষণ করা হয়েছে।',

    'channel_in_app' => 'অ্যাপে',
    'channel_email' => 'ইমেইল',
    'channel_sms' => 'এসএমএস',
    'channel_whatsapp' => 'হোয়াটসঅ্যাপ',

    'in_app_always_on' => 'সবসময় চালু থাকবে। মেশিন ও মেইনটেনেন্স সংক্রান্ত প্রতিটি ঘটনার রেকর্ড অডিট ট্রেইলের অংশ।',

    'channels_hint' => 'আপনি কোন মাধ্যমে বিজ্ঞপ্তি পেতে চান তা নির্বাচন করুন। সব বিজ্ঞপ্তির রেকর্ড সিস্টেমে সংরক্ষিত থাকবে।',

    'not_yet_delivered' => 'এখনও চালু হয়নি',
    'not_yet_delivered_hint' => 'ইমেইল, এসএমএস এবং হোয়াটসঅ্যাপের মাধ্যমে বিজ্ঞপ্তি পাঠানোর সুবিধা পরবর্তী মেসেজিং ধাপে চালু হবে। আপনার পছন্দ এখনই সংরক্ষণ করা হচ্ছে।',

    // --- Event Names ---
    'event_maintenance_due' => 'মেইনটেনেন্স নির্ধারিত',
    'event_maintenance_overdue' => 'মেইনটেনেন্স বকেয়া',
    'event_breakdown_reported' => 'ব্রেকডাউন রিপোর্ট করা হয়েছে',
    'event_breakdown_critical' => 'জরুরি ব্রেকডাউন',
    'event_work_order_assigned' => 'ওয়ার্ক অর্ডার আপনাকে দেওয়া হয়েছে',
    'event_work_order_completed' => 'ওয়ার্ক অর্ডার সম্পন্ন',
    'event_approval_requested' => 'অনুমোদন প্রয়োজন',
    'event_approval_decided' => 'অনুমোদনের সিদ্ধান্ত',
    'event_low_stock' => 'স্পেয়ার পার্টের স্টক কম',
    'event_warranty_expiry' => 'ওয়ারেন্টির মেয়াদ শেষ হতে চলেছে',
    'event_webhook_disabled' => 'ওয়েবহুক এন্ডপয়েন্ট নিষ্ক্রিয় হয়েছে',
    'event_amc_expiry' => 'সার্ভিস চুক্তির মেয়াদ শেষ হতে চলেছে',

    // --- Notification Messages ---
    // SRS 48: Notification message is generated and stored
    // in the recipient's selected language.
    'event' => [
        'MAINTENANCE_DUE' => [
            'title' => 'মেইনটেনেন্স নির্ধারিত: :asset',
            'body' => ':plan-এর নির্ধারিত সময় :due_at।',
        ],

        'MAINTENANCE_OVERDUE' => [
            'title' => 'মেইনটেনেন্স বকেয়া: :asset',
            'body' => ':plan-এর নির্ধারিত সময় ছিল :due_at। অতিরিক্ত সময়সহ নির্ধারিত সময়সীমা পেরিয়ে গেছে।',
        ],

        'BREAKDOWN_REPORTED' => [
            'title' => 'ব্রেকডাউন: :asset',
            'body' => 'ব্রেকডাউন নম্বর :number রিপোর্ট করা হয়েছে। :problem',
        ],

        'BREAKDOWN_CRITICAL' => [
            'title' => 'জরুরি ব্রেকডাউন: :asset',
            'body' => 'জরুরি ব্রেকডাউন নম্বর :number রিপোর্ট করা হয়েছে। :problem',
        ],

        'WORK_ORDER_ASSIGNED' => [
            'title' => 'ওয়ার্ক অর্ডার আপনাকে দেওয়া হয়েছে: :number',
            'body' => ':asset-এ :title।',
        ],

        'WORK_ORDER_COMPLETED' => [
            'title' => 'ওয়ার্ক অর্ডার সম্পন্ন: :number',
            'body' => ':asset-এ :title সম্পন্ন হয়েছে।',
        ],

        'APPROVAL_REQUESTED' => [
            'title' => 'অনুমোদন প্রয়োজন: :number',
            'body' => ':title, প্রাক্কলিত খরচ :cost।',
        ],

        'APPROVAL_DECIDED' => [
            'title' => 'অনুমোদনের সিদ্ধান্ত :decision: :number',
            'body' => ':title-এর সিদ্ধান্ত :decision হয়েছে।',
        ],

        'LOW_STOCK' => [
            'title' => 'স্পেয়ার পার্টের স্টক কম: :part',
            'body' => ':on_hand টি স্টকে আছে; রিঅর্ডার লেভেল :reorder_level।',
        ],
        'WARRANTY_EXPIRY' => [
            'title' => 'ওয়ারেন্টি শেষ হচ্ছে: :asset',
            'body' => ':vendor-এর কভার :end_date তারিখে শেষ হচ্ছে, আর :days দিন বাকি।',
        ],
        'WEBHOOK_DISABLED' => [
            'title' => 'ওয়েবহুক নিষ্ক্রিয়: :url',
            'body' => 'পরপর :count বার ব্যর্থ হওয়ায় এটি বন্ধ করা হয়েছে। রিসিভার ঠিক করে আবার চালু করুন।',
        ],
        // নাম, সময় ও কারণ সহ — কারণ অস্পষ্ট "সাপোর্ট আপনার ডেটা দেখেছে"
        // মানুষকে উদ্বিগ্ন করে অথচ কাজে লাগে এমন কিছুই বলে না (SRS ৫.৪)।
        'SUPPORT_ACCESS' => [
            'title' => 'আপনার ডেটায় সাপোর্টের অ্যাক্সেস',
            'body' => 'সাপোর্টের :name কে :until পর্যন্ত :company এর অ্যাক্সেস দেওয়া হয়েছে। উল্লিখিত কারণ: :reason',
        ],
        'TICKET_REPLIED' => [
            'title' => 'আপনার টিকিটে উত্তর: :subject',
            'body' => 'সাপোর্টের :name উত্তর দিয়েছেন।',
        ],
        'TICKET_RESOLVED' => [
            'title' => 'টিকিট সমাধান হয়েছে বলে চিহ্নিত: :subject',
            'body' => 'এতে সমস্যা না মিটলে উত্তর দিন, টিকিটটি নিজে থেকেই আবার খুলে যাবে।',
        ],
        'AMC_EXPIRY' => [
            'title' => 'চুক্তি শেষ হচ্ছে: :number',
            'body' => ':vendor-এর চুক্তি :end_date তারিখে শেষ হচ্ছে, আর :days দিন বাকি।',
        ],
    ],
    'view_all' => 'সব বিজ্ঞপ্তি দেখুন',
];
