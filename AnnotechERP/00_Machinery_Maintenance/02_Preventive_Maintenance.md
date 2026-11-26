# Preventive Maintenance

> **স্ট্যাটাস:** ✅ সম্পন্ন
> **মডিউল:** [Machinery Maintenance](00_Index.md) · **ফেজ 0:** বিদ্যমান সিস্টেম, অপরিবর্তিত থাকবে · **কী:** `00/02`

**⬅ ফিরে যান:** [Machinery Maintenance](00_Index.md) | [AnnotechERP Index](../00_Index.md)

---

## ১. বিবরণ

বর্তমান machinery-maintenance সিস্টেমের অংশ। নতুন ERP-তে এটা অপরিবর্তিত থাকবে, শুধু লাইট থিম আর নতুন নেভিগেশনে বসবে।

## ২. Implementation Files

<!-- AUTO:impl:start -->
বিদ্যমান কোড (জেনারেটর ডিস্ক থেকে মিলিয়ে লেখে):

- `app/Modules/Maintenance/` (৪১টা ফাইল)
- `app/Modules/Calendar/` (১টা ফাইল)
- `frontend/src/app/(app)/maintenance/` (১৬টা ফাইল)
- `frontend/src/components/maintenance/` (৯টা ফাইল)
- `tests/Feature/Maintenance/` (৫টা ফাইল)
<!-- AUTO:impl:end -->

## ৩. নতুন ERP-র সাথে সংযোগ

Maintenance schedule থেকে work order তৈরি হয় (04 Work Orders)।

## ১১. As-built নোট

- **২০২৬-১১-১২ ডিজাইন ও বাগ-ফিক্স:** সব পেজে ERP হেডার (`ScreenHeader`: Home › Modules › Machinery Maintenance › স্ক্রিন, আইকন, "NN of 23"), গ্রিডে নীল হেডার ও নম্বর-পেজার, ফিল্টারে "All …" লেবেল, ডিটেইল পেজের URL-এ ULID নয়, পড়া যায় এমন নম্বর (`ResolvesByReference` + `refOf`)।
- Plans তালিকায় সার্চ (প্ল্যানের নাম, মেশিনের কোড বা নাম; বড়-ছোট হাতের অক্ষর নির্বিশেষে)। খোঁজে কিছু না মিললে "No plan matches this search"।
