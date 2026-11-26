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
    'after_minutes' => 'আর কেউ সাড়া দেয় না (মিনিট)',
    'escalation_level_hint' => '১–৫; একই ইভেন্টের একই লেভেল দুটি নিয়ম একসঙ্গে কভার করতে পারে না।',
    'stop_on_acknowledge_checkbox' => 'কেউ স্বীকার করলে থেমে যাবে',
    'status_label' => 'অবস্থা',
    'rule_active' => 'সক্রিয়',
    'rule_paused' => 'বিরতিতে',
    'minutes_short' => ':count মিনিট',
    'delete_rule_title' => ':event নিয়মটি মুছে ফেলবেন?',
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
    'locked_by_company' => 'কোম্পানি ঠিক করে দিয়েছে',
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
    'event_breakdown_awaiting_verification' => 'ব্রেকডাউন আপনার ভেরিফিকেশনের অপেক্ষায়',
    'event_breakdown_awaiting_approval' => 'ব্রেকডাউন আপনার অনুমোদনের অপেক্ষায়',
    'event_work_order_assigned' => 'ওয়ার্ক অর্ডার আপনাকে দেওয়া হয়েছে',
    'event_work_order_completed' => 'ওয়ার্ক অর্ডার সম্পন্ন',
    'event_approval_requested' => 'অনুমোদন প্রয়োজন',
    'event_approval_decided' => 'অনুমোদনের সিদ্ধান্ত',
    'event_low_stock' => 'স্পেয়ার পার্টের স্টক কম',
    'event_warranty_expiry' => 'ওয়ারেন্টির মেয়াদ শেষ হতে চলেছে',
    'event_webhook_disabled' => 'ওয়েবহুক এন্ডপয়েন্ট নিষ্ক্রিয় হয়েছে',
    'event_amc_expiry' => 'সার্ভিস চুক্তির মেয়াদ শেষ হতে চলেছে',
    'event_support_access' => 'সাপোর্ট আপনার তথ্য দেখেছে',
    'event_ticket_replied' => 'আপনার টিকেটে উত্তর এসেছে',
    'event_ticket_resolved' => 'টিকেট সমাধান হিসেবে চিহ্নিত হয়েছে',
    'event_floor_requisition_submitted' => 'ফ্লোর রিকুইজিশন জমা হয়েছে',
    'event_floor_requisition_issued' => 'ফ্লোর রিকুইজিশন ইস্যু হয়েছে',
    'event_floor_requisition_shortage' => 'ফ্লোর রিকুইজিশনে ঘাটতি',
    'event_earmark_stock_arrived' => 'নির্ধারিত স্টক এসেছে',
    'event_returnable_overdue' => 'ফেরতযোগ্য জিনিস বকেয়া',
    'event_earmark_waiting' => 'ঘাটতি এখনো অপূর্ণ',
    'event_fabric_inspection_failed' => 'ফ্যাব্রিক ইন্সপেকশন ব্যর্থ',
    'event_lot_expiring' => 'লটের মেয়াদ ফুরিয়ে আসছে',
    'event_stock_count_stale' => 'স্টক গণনা খোলা পড়ে আছে',
    'event_shipment_arriving' => 'শিপমেন্ট আসছে',
    'event_shipment_delayed' => 'শিপমেন্টে দেরি',
    'event_lc_expiring' => 'LC-র মেয়াদ ফুরিয়ে আসছে',
    'event_global_broadcast' => 'কোম্পানির ঘোষণা',
    'event_approval_overdue' => 'অনুমোদনের রিকোয়েস্টের সময় পেরিয়েছে',

    // --- Notification Messages ---
    // SRS 48: Notification message is generated and stored
    // in the recipient's selected language.
    'event_os_orders_changed' => 'Order Summary: অর্ডার বদল',
    'event_os_pending_work' => 'Order Summary: প্রতিদিনের বাকি কাজ',
    'event_ds_samples_changed' => 'Dev Sample: স্যাম্পল বদল',
    'event_ds_deadline_due' => 'Dev Sample: সাবমিশন ডেডলাইন',
    'event_pb_requisitions_changed' => 'Pre-Budget: রিকুইজিশন বদল',
    'event_pb_budget_changed' => 'Pre-Budget: বাজেট বদল',
    'event_pb_stock_needed' => 'Pre-Budget: স্টক ইয়ার্ন ব্যবহার দরকার',
    'event_pb_pending_work' => 'Pre-Budget: প্রতিদিনের বাকি কাজ',
    'event_es_statements_changed' => 'Export Statement: স্টেটমেন্ট বদল',
    'event_es_pending_work' => 'Export Statement: প্রতিদিনের বাকি কাজ',
    'sheet' => [
        'kind' => [
            'CREATE' => 'যোগ করেছেন',
            'EDIT' => 'বদলেছেন',
            'DELETE' => 'মুছেছেন',
            'COMPLETE' => 'সম্পন্ন করেছেন',
            'ADD_STYLE' => 'স্টাইল যোগ করেছেন',
            'REMOVE_STYLE' => 'স্টাইল বাদ দিয়েছেন',
            'COSTING' => 'কস্টিং সেভ করেছেন',
            'COSTING_DONE' => 'নিট/ডাইং কস্টিং শেষ করেছেন',
            'STOCK' => 'স্টক ইয়ার্ন সেভ করেছেন',
            'STOCK_DONE' => 'স্টক ইয়ার্ন ব্যবহার শেষ করেছেন',
            'BOM_CREATE' => 'এক্সেসরিজ BOM বানিয়েছেন',
            'BOM_UPDATE' => 'এক্সেসরিজ BOM বদলেছেন',
            'BOM_DELETE' => 'এক্সেসরিজ BOM মুছেছেন',
            'WITHDRAW' => 'ফেরত নিয়েছেন',
            'REVISE' => 'রিভাইজ করেছেন',
            'CANCEL' => 'বাতিল করেছেন (অর্ডার বদলেছে)',
        ],
    ],
    'event' => [
        // Order Summary, Dev Sample Status and Pre-Budget (user, 2026-11-21).
        'OS_ORDERS_CHANGED' => [
            'title' => 'Order Summary: :by :n টি অর্ডার :action',
            'body' => ':buyers — :orders · :cells ঘর',
        ],
        'OS_PENDING_WORK' => [
            'title' => 'Order Summary: অর্ডারে নজর দরকার',
            'body' => ':overdue টির RFI/SD পেরিয়েছে, :week টি ৭ দিনের মধ্যে, :booking টির বুকিং বাকি (LDD/ETD ১৪ দিনের মধ্যে) — :buyers',
        ],
        'DS_SAMPLES_CHANGED' => [
            'title' => 'Dev Sample: :by :n টি স্যাম্পল :action',
            'body' => ':samples · :cells ঘর',
        ],
        'DS_DEADLINE_DUE' => [
            'title' => 'Dev Sample: সাবমিশন ডেডলাইন',
            'body' => ':overdue টির ডেডলাইন পেরিয়েছে, :soon টি ২ দিনের মধ্যে — :samples',
        ],
        'PB_REQUISITIONS_CHANGED' => [
            'title' => 'Pre-Budget: :by :n টি রিকুইজিশন :action',
            'body' => ':buyers — :styles · :cells ঘর',
        ],
        'PB_BUDGET_CHANGED' => [
            'title' => 'Pre-Budget: :by :budget — :action',
            'body' => ':budget · :buyer',
        ],
        'PB_STOCK_NEEDED' => [
            'title' => 'স্টক ইয়ার্ন ব্যবহার দরকার: :budget',
            'body' => ':by :budget (:buyer) — :action। এর Stock Yarn Use পূরণ করুন।',
        ],
        'PB_PENDING_WORK' => [
            'title' => 'Pre-Budget: বাজেটের কাজ বাকি',
            'body' => ':costing টি কস্টিং, :stock টি স্টক ইয়ার্ন, :accessories টি এক্সেসরিজ বাজেট বাকি — :budgets',
        ],
        'ES_STATEMENTS_CHANGED' => [
            'title' => 'Export Statement: :by :n টি ইনভয়েস :action',
            'body' => ':invoices · নিট :value · :cells ঘর',
        ],
        'ES_PENDING_WORK' => [
            'title' => 'Export Statement: ডকুমেন্ট ও ক্লেইমের সময়',
            'body' => ':docs_overdue টির ১০ দিনের ডকুমেন্ট জমার সময় পেরিয়েছে, :docs_soon টি ২ দিনের মধ্যে, :claim_soon টির ক্যাশ ইনসেনটিভ ক্লেইমের সময় ৩০ দিনের মধ্যে শেষ — :invoices',
        ],
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

        'BREAKDOWN_AWAITING_VERIFICATION' => [
            'title' => 'ভেরিফিকেশনের জন্য প্রস্তুত: :asset',
            'body' => ':number মেরামত হয়েছে এবং আপনার ভেরিফিকেশনের অপেক্ষায় আছে।',
        ],
        'BREAKDOWN_AWAITING_APPROVAL' => [
            'title' => 'অনুমোদনের জন্য প্রস্তুত: :asset',
            'body' => ':number ভেরিফাই হয়েছে এবং আপনার চূড়ান্ত অনুমোদনের অপেক্ষায় আছে।',
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
        'APPROVAL_OVERDUE' => [
            'title' => 'সময় পেরোনো অনুমোদন: :number',
            'body' => ':title-এর শেষ তারিখ পেরিয়েছে, এখনো :waiting_on-এর অপেক্ষায়।',
        ],

        'LOW_STOCK' => [
            'title' => 'স্টক কম: :part',
            'body' => ':on_hand টি স্টকে আছে; রিঅর্ডার লেভেল :reorder_level।',
        ],
        'FLOOR_REQUISITION_SUBMITTED' => [
            'title' => 'ফ্লোর রিকুইজিশন :number',
            'body' => ':department থেকে :lines টি আইটেম চাওয়া হয়েছে।',
        ],
        'FLOOR_REQUISITION_ISSUED' => [
            'title' => ':number-এর বিপরীতে ইস্যু',
            'body' => ':issued টি লাইন ইস্যু হয়েছে, :short টি এখনো ঘাটতিতে।',
        ],
        'FLOOR_REQUISITION_SHORTAGE' => [
            'title' => ':number-এ ঘাটতি',
            'body' => ':lines টি লাইন ইস্যু করা যায়নি। পারচেজ রিকুইজিশন :pr_no ড্রাফটে অপেক্ষা করছে।',
        ],
        'EARMARK_STOCK_ARRIVED' => [
            'title' => 'স্টক এসেছে: :item',
            'body' => 'অপেক্ষমাণ রিকুইজিশনের বিপরীতে :quantity গ্রহণ করা হয়েছে। বাকি পরিমাণ ইস্যু করুন।',
        ],
        'RETURNABLE_OVERDUE' => [
            'title' => 'ফেরত আসেনি: :item',
            'body' => ':number-এ ইস্যু করা :quantity গত :days দিন ধরে ফেরত আসেনি।',
        ],
        'EARMARK_WAITING' => [
            'title' => 'এখনো অপেক্ষায়: :item',
            'body' => ':number-এর জন্য :quantity গত :days দিন ধরে দাবি করা আছে, কোনো স্টক আসেনি।',
        ],
        'FABRIC_INSPECTION_FAILED' => [
            'title' => 'ইন্সপেকশন ফেল: :number',
            'body' => 'লটে :percent পয়েন্ট উঠেছে, সীমা :threshold। '
                .'বড় ইন্সপেকশন খুলুন, নয়তো সাপ্লায়ারের সাথে কথা বলুন।',
        ],
        'LOT_EXPIRING' => [
            'title' => 'মেয়াদ শেষ হচ্ছে: :item, লট :lot_no',
            'body' => 'স্টকে আছে :quantity; লটের মেয়াদ :expiry_date (:days দিন)।',
        ],
        'STOCK_COUNT_STALE' => [
            'title' => 'কাউন্ট :count_no :days দিন ধরে খোলা',
            'body' => ':store — :uncounted টি তাক এখনো গোনা হয়নি। এই শিটের সিস্টেম সংখ্যাগুলো শুরুর দিনেই জমে গিয়েছিল।',
        ],
        'SHIPMENT_ARRIVING' => [
            'title' => ':days দিনে পৌঁছাবে: :shipment_no',
            'body' => ':vendor-এর :po_no আসার কথা :eta তারিখে। কাগজপত্র: :document_status।',
        ],
        'SHIPMENT_DELAYED' => [
            'title' => 'ইটিএ-র :days দিন পার: :shipment_no',
            'body' => ':vendor-এর :po_no :eta তারিখে আসার কথা ছিল, এখনো আসেনি।',
        ],
        'LC_EXPIRING' => [
            'title' => 'এলসি :lc_no-এর মেয়াদ :days দিনে শেষ',
            'body' => ':po_no (:vendor)-এর বিপরীতে :currency :amount-এর মেয়াদ :expiry_date-এ শেষ, অথচ কোনো শিপমেন্ট হয়নি। অ্যামেন্ডমেন্টে ব্যাংক চার্জ লাগে, কিন্তু মেয়াদ ফুরালে পুরো ওপেনিংই যায়।',
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
        'GLOBAL_BROADCAST' => [
            'title' => ':title',
            'body' => ':message',
        ],
        'MP_QUOTATION_NEGOTIATION' => [
            'title' => 'দাম রিভাইজের অনুরোধ',
            'body' => ':pr-এর :item-এর জন্য :qt: :message',
        ],
        'MP_QUOTATION_REJECTED' => [
            'title' => 'কোটেশন প্রত্যাখ্যাত',
            'body' => ':pr-এর :item-এর জন্য :qt প্রত্যাখ্যাত: :reason',
        ],
        'MP_QUOTATION_APPROVED' => [
            'title' => 'কোটেশন অনুমোদিত',
            'body' => ':pr-এর :item-এর জন্য :qt :price দামে অনুমোদিত।',
        ],
        'HR_OT_REQUESTED' => [
            'title' => 'আপনার অনুমোদনের অপেক্ষায় OT অনুরোধ',
            'body' => ':no — :line, :date, :window, :n জন কর্মী।',
        ],
        'HR_OT_DECIDED' => [
            'title' => 'OT অনুরোধ :status',
            'body' => ':no — :line, :date — :by :status করেছেন।',
        ],
        'MP_QUOTATION_RECEIVED' => [
            'title' => 'সরবরাহকারীর নতুন কোটেশন',
            'body' => ':supplier :pr-এর :item-এর জন্য কোট দিয়েছে (:qt)।',
        ],
        'MP_QUOTATION_REVISED' => [
            'title' => 'সরবরাহকারী কোটেশন রিভাইজ করেছে',
            'body' => ':supplier :pr-এর :item-এর জন্য রিভিশন :revision পাঠিয়েছে (:qt)।',
        ],
        'MP_MATERIAL_RECEIVED' => [
            'title' => 'ম্যাটেরিয়াল এসেছে',
            'body' => ':pr-এর :item: :required :uom-এর মধ্যে :received এসেছে।',
        ],
        'MP_MATERIAL_ASSIGNED' => [
            'title' => 'অনুমোদিত ম্যাটেরিয়াল আপনার দায়িত্বে',
            'body' => ':supplier-এর :item (:pr, :qt) ফলো-আপ করার দায়িত্ব আপনার।',
        ],
        'MP_REQUIREMENT_AVAILABLE' => [
            'title' => 'নতুন ম্যাটেরিয়াল চাহিদা এসেছে',
            'body' => ':pr: :linesটা ম্যাটেরিয়াল লাইন কোটেশনের জন্য খোলা।',
        ],
        'SAMPLE_ASSIGNED' => [
            'title' => 'স্যাম্পল :sample আপনাকে দেওয়া হয়েছে',
            'body' => 'রিকুইজিশন :requisition অনুমোদিত হয়েছে; এর স্যাম্পল :sample তৈরির দায়িত্ব আপনার।',
        ],
        'SAMPLE_FEEDBACK_ACTION' => [
            'title' => 'বায়ার ফিডব্যাক :feedback-এ আপনার পদক্ষেপ দরকার',
            'body' => 'স্যাম্পল :sample-এর ফিডব্যাক আপনাকে দেওয়া হয়েছে, শেষ তারিখ :due।',
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
    'all_marked_read_toast' => 'সব পঠিত চিহ্নিত হয়েছে',
    'preferences_short' => 'পছন্দসমূহ',
    'page_summary' => 'পৃষ্ঠা :current এর :last · মোট :total',
    'previous' => 'পূর্ববর্তী',
    'next' => 'পরবর্তী',
    'preferences_page_description' => 'প্রতিটি ধরনের ঘটনা আপনার কাছে কোন চ্যানেলে পৌঁছায়।',
];
