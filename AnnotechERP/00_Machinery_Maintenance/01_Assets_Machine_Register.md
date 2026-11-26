# Assets & Machine Register

> **স্ট্যাটাস:** ✅ সম্পন্ন
> **মডিউল:** [Machinery Maintenance](00_Index.md) · **ফেজ 0:** বিদ্যমান সিস্টেম, অপরিবর্তিত থাকবে · **কী:** `00/01`

**⬅ ফিরে যান:** [Machinery Maintenance](00_Index.md) | [AnnotechERP Index](../00_Index.md)

---

## ১. বিবরণ

বর্তমান machinery-maintenance সিস্টেমের অংশ। নতুন ERP-তে এটা অপরিবর্তিত থাকবে, শুধু লাইট থিম আর নতুন নেভিগেশনে বসবে।

## ২. Implementation Files

<!-- AUTO:impl:start -->
বিদ্যমান কোড (জেনারেটর ডিস্ক থেকে মিলিয়ে লেখে):

- `app/Modules/Asset/` (৬১টা ফাইল)
- `frontend/src/app/(app)/assets/` (১১টা ফাইল)
- `frontend/src/components/assets/` (১৪টা ফাইল)
- `tests/Feature/Asset/` (৫টা ফাইল)
<!-- AUTO:impl:end -->

## ৩. নতুন ERP-র সাথে সংযোগ

Company Management-এর Machine Register (`components/company/machine-register.jsx`) এখানকার Asset-ই দেখায় ও এখানকার ডিটেইল পেজে লিংক করে। আলাদা মেশিন টেবিল নেই।

## ১১. As-built নোট

- **২০২৬-১১-১২ ডিজাইন ও বাগ-ফিক্স:** সব পেজে ERP হেডার (`ScreenHeader`: Home › Modules › Machinery Maintenance › স্ক্রিন, আইকন, "NN of 23"), গ্রিডে নীল হেডার ও নম্বর-পেজার, ফিল্টারে "All …" লেবেল, ডিটেইল পেজের URL-এ ULID নয়, পড়া যায় এমন নম্বর (`ResolvesByReference` + `refOf`)।
- Asset ডিটেইল এখন `/assets/<asset_code>` (যেমন `/assets/CUT-DHK-00401`)।
- **Print labels:** কিছু না বেছে "Generate sheet" চাপলে আগে কিছুই হতো না; এখন নাগালের সব মেশিন (সর্বোচ্চ ২০০)। ফিল্টারে "All"-এ ফেরার অপশন, ফিল্টার কার্ডে। API এখন শুধু caller-এর নাগালের ফ্যাক্টরির মেশিন দেয় (আগে factory না দিলে কোম্পানির সব)। প্রিন্ট PDF দেখা হয়েছে: A4, সারিতে ৩টা, লেবেল পাতায় ভাঙে না।
