# Work Orders

> **স্ট্যাটাস:** ✅ সম্পন্ন
> **মডিউল:** [Machinery Maintenance](00_Index.md) · **ফেজ 0:** বিদ্যমান সিস্টেম, অপরিবর্তিত থাকবে · **কী:** `00/04`

**⬅ ফিরে যান:** [Machinery Maintenance](00_Index.md) | [AnnotechERP Index](../00_Index.md)

---

## ১. বিবরণ

বর্তমান machinery-maintenance সিস্টেমের অংশ। নতুন ERP-তে এটা অপরিবর্তিত থাকবে, শুধু লাইট থিম আর নতুন নেভিগেশনে বসবে।

## ২. Implementation Files

<!-- AUTO:impl:start -->
বিদ্যমান কোড (জেনারেটর ডিস্ক থেকে মিলিয়ে লেখে):

- `app/Modules/WorkOrder/` (৫২টা ফাইল)
- `frontend/src/app/(app)/work-orders/` (৬টা ফাইল)
- `frontend/src/components/work-orders/` (১১টা ফাইল)
- `tests/Feature/WorkOrder/` (৭টা ফাইল)
<!-- AUTO:impl:end -->

## ৩. নতুন ERP-র সাথে সংযোগ

Breakdown থেকে তৈরি work order আর Approval Center-এর অনুমোদন এখানে লিংক হয়।

## ১১. As-built নোট

- **২০২৬-১১-১২ ডিজাইন ও বাগ-ফিক্স:** সব পেজে ERP হেডার (`ScreenHeader`: Home › Modules › Machinery Maintenance › স্ক্রিন, আইকন, "NN of 23"), গ্রিডে নীল হেডার ও নম্বর-পেজার, ফিল্টারে "All …" লেবেল, ডিটেইল পেজের URL-এ ULID নয়, পড়া যায় এমন নম্বর (`ResolvesByReference` + `refOf`)।
- **বাগ:** "Open only" ফিল্টার COMPLETED/VERIFIED অর্ডারও দেখাত, অথচ উপরের Open কার্ড শুধু খোলা স্ট্যাটাস গুনত। এখন দুটোই `WorkOrder::OPEN_STATUSES`।
- WO ডিটেইল `/work-orders/<work_order_number>`।
