# Vendors, Warranty & Service Contracts

> **স্ট্যাটাস:** ✅ সম্পন্ন
> **মডিউল:** [Machinery Maintenance](00_Index.md) · **ফেজ 0:** বিদ্যমান সিস্টেম, অপরিবর্তিত থাকবে · **কী:** `00/08`

**⬅ ফিরে যান:** [Machinery Maintenance](00_Index.md) | [AnnotechERP Index](../00_Index.md)

---

## ১. বিবরণ

বর্তমান machinery-maintenance সিস্টেমের অংশ। নতুন ERP-তে এটা অপরিবর্তিত থাকবে, শুধু লাইট থিম আর নতুন নেভিগেশনে বসবে।

## ২. Implementation Files

<!-- AUTO:impl:start -->
বিদ্যমান কোড (জেনারেটর ডিস্ক থেকে মিলিয়ে লেখে):

- `app/Modules/Vendor/` (৩৬টা ফাইল)
- `frontend/src/app/(app)/vendors/` (১৭টা ফাইল)
- `frontend/src/components/vendors/` (৪টা ফাইল)
- `tests/Feature/Vendor/` (৪টা ফাইল)
<!-- AUTO:impl:end -->

## ৩. নতুন ERP-র সাথে সংযোগ

আলাদা থাকে (ব্যবহারকারীর সিদ্ধান্ত, ২০২৬-১০-১০): Material Procurement-এর সাপ্লায়ার `procurement_suppliers`, আর Machinery-র `vendors` এখানেই।

## ১১. As-built নোট

- **২০২৬-১১-১২ ডিজাইন ও বাগ-ফিক্স:** সব পেজে ERP হেডার (`ScreenHeader`: Home › Modules › Machinery Maintenance › স্ক্রিন, আইকন, "NN of 23"), গ্রিডে নীল হেডার ও নম্বর-পেজার, ফিল্টারে "All …" লেবেল, ডিটেইল পেজের URL-এ ULID নয়, পড়া যায় এমন নম্বর (`ResolvesByReference` + `refOf`)।
- Vendor ডিটেইল `/vendors/<code>` (code না থাকলে id), Service contract `/vendors/service-contracts/<contract_number>`।
- Warranties ও Service contracts-এ হেডারের বিবরণ লাইন; শেষ/শুরুর তারিখ এখন বাকি ERP-র মতো লোকাল ফরম্যাটে (আগে কাঁচা `2026-10-06`)।
