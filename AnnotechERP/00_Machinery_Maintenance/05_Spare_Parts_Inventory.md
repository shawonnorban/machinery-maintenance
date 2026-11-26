# Spare Parts & Inventory

> **স্ট্যাটাস:** ✅ সম্পন্ন
> **মডিউল:** [Machinery Maintenance](00_Index.md) · **ফেজ 0:** বিদ্যমান সিস্টেম, অপরিবর্তিত থাকবে · **কী:** `00/05`

**⬅ ফিরে যান:** [Machinery Maintenance](00_Index.md) | [AnnotechERP Index](../00_Index.md)

---

## ১. বিবরণ

বর্তমান machinery-maintenance সিস্টেমের অংশ। নতুন ERP-তে এটা অপরিবর্তিত থাকবে, শুধু লাইট থিম আর নতুন নেভিগেশনে বসবে।

## ২. Implementation Files

<!-- AUTO:impl:start -->
বিদ্যমান কোড (জেনারেটর ডিস্ক থেকে মিলিয়ে লেখে):

- `app/Modules/Inventory/` (৫৭টা ফাইল)
- `frontend/src/app/(app)/inventory/` (১৭টা ফাইল)
- `frontend/src/components/inventory/` (১৬টা ফাইল)
- `tests/Feature/Inventory/` (৬টা ফাইল)
<!-- AUTO:impl:end -->

## ৩. নতুন ERP-র সাথে সংযোগ

আলাদা থাকে (ব্যবহারকারীর সিদ্ধান্ত, ২০২৬-১০-১০): General Store-এর আইটেম/স্টোর নিজের টেবিলে, Machinery-র spare-part স্টোর (`spare_parts`, `stores`/`bins`, `inventory_transactions`) এখানেই।

## ১১. As-built নোট

- **২০২৬-১১-১২ ডিজাইন ও বাগ-ফিক্স:** সব পেজে ERP হেডার (`ScreenHeader`: Home › Modules › Machinery Maintenance › স্ক্রিন, আইকন, "NN of 23"), গ্রিডে নীল হেডার ও নম্বর-পেজার, ফিল্টারে "All …" লেবেল, ডিটেইল পেজের URL-এ ULID নয়, পড়া যায় এমন নম্বর (`ResolvesByReference` + `refOf`)।
- Spare parts তালিকায় **On hand** কলাম (সব bin-এর যোগফল; reorder level-এ বা নিচে লাল), reorder level চার দশমিক ছাড়া, Flags কলামের হেডার।
- Issue & Return স্ক্রিনের নিচে **History** (`GET /inventory-transactions/issue-return`, `type=ISSUE|RETURN`), পার্ট, bin, পরিমাণ, পরের ব্যালান্স, work order, কে, নোট।
- Part ডিটেইল `/inventory/parts/<part_number>`।
