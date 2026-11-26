# Breakdowns

> **স্ট্যাটাস:** ✅ সম্পন্ন
> **মডিউল:** [Machinery Maintenance](00_Index.md) · **ফেজ 0:** বিদ্যমান সিস্টেম, অপরিবর্তিত থাকবে · **কী:** `00/03`

**⬅ ফিরে যান:** [Machinery Maintenance](00_Index.md) | [AnnotechERP Index](../00_Index.md)

---

## ১. বিবরণ

বর্তমান machinery-maintenance সিস্টেমের অংশ। নতুন ERP-তে এটা অপরিবর্তিত থাকবে, শুধু লাইট থিম আর নতুন নেভিগেশনে বসবে।

## ২. Implementation Files

<!-- AUTO:impl:start -->
বিদ্যমান কোড (জেনারেটর ডিস্ক থেকে মিলিয়ে লেখে):

- `app/Modules/Breakdown/` (৩২টা ফাইল)
- `frontend/src/app/(app)/breakdowns/` (৪টা ফাইল)
- `frontend/src/components/breakdowns/` (৬টা ফাইল)
- `tests/Feature/Breakdown/` (৮টা ফাইল)
<!-- AUTO:impl:end -->

## ৩. নতুন ERP-র সাথে সংযোগ

Breakdown থেকে মেরামতের work order তৈরি হয় (04 Work Orders)।

## ১১. As-built নোট

- **২০২৬-১১-১২:** ERP হেডার ও গ্রিড; Breakdown ডিটেইল এখন `/breakdowns/<breakdown_number>` (যেমন `/breakdowns/BD-DHK-202609-00008`), ULID নয়।
