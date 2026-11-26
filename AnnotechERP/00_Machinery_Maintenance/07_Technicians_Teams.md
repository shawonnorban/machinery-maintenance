# Technicians & Teams

> **স্ট্যাটাস:** ✅ সম্পন্ন
> **মডিউল:** [Machinery Maintenance](00_Index.md) · **ফেজ 0:** বিদ্যমান সিস্টেম, অপরিবর্তিত থাকবে · **কী:** `00/07`

**⬅ ফিরে যান:** [Machinery Maintenance](00_Index.md) | [AnnotechERP Index](../00_Index.md)

---

## ১. বিবরণ

বর্তমান machinery-maintenance সিস্টেমের অংশ। নতুন ERP-তে এটা অপরিবর্তিত থাকবে, শুধু লাইট থিম আর নতুন নেভিগেশনে বসবে।

## ২. Implementation Files

<!-- AUTO:impl:start -->
বিদ্যমান কোড (জেনারেটর ডিস্ক থেকে মিলিয়ে লেখে):

- `frontend/src/app/(app)/technicians/` (৬টা ফাইল)
- `frontend/src/app/(app)/teams/` (২টা ফাইল)
- `frontend/src/components/technicians/` (৩টা ফাইল)
- `frontend/src/components/teams/` (১টা ফাইল)
<!-- AUTO:impl:end -->

## ৩. নতুন ERP-র সাথে সংযোগ

Technician বাছাই হয় HR & Payroll-এর কর্মী-তালিকা থেকে (ব্যবহারকারীর সিদ্ধান্ত, ২০২৬-১১-১৪): `technicians.hr_employee_id` → `hr_employees`। কর্মী বাছাই করলে নাম, Employee ID (= HR কোড), মোবাইল, ইমেইল ও যোগদানের তারিখ HR থেকে আসে (ফর্মে read-only, প্রতিবার সেভে আবার কপি হয়); Factory/Department/Line শুরু হয় HR পোস্টিং থেকে, পরে বদলানো যায়। শুধু সক্রিয় কর্মী নতুন করে যোগ করা যায়; এক কর্মী = এক টেকনিশিয়ান। লিংকের আগের টেকনিশিয়ানরা থাকেন, এডিট পেজে "HR কর্মী বাছাই করুন" বার্তা দেখায়।

## ১০. খোলা প্রশ্ন

(এখন কোনো খোলা প্রশ্ন নেই — Technician ↔ HR কর্মী: HR & Payroll-এর তালিকা থেকে বাছাই, ২০২৬-১১-১৪-এ উত্তর ও বাস্তবায়ন।)

## ১১. As-built নোট

- **২০২৬-১১-১২ ডিজাইন ও বাগ-ফিক্স:** সব পেজে ERP হেডার (`ScreenHeader`: Home › Modules › Machinery Maintenance › স্ক্রিন, আইকন, "NN of 23"), গ্রিডে নীল হেডার ও নম্বর-পেজার, ফিল্টারে "All …" লেবেল, ডিটেইল পেজের URL-এ ULID নয়, পড়া যায় এমন নম্বর (`ResolvesByReference` + `refOf`)।
- তালিকার Specialisation কলাম আগে সবসময় "—" দেখাত (API সারাংশে ফিল্ডটা ছিল না), ঠিক করা হয়েছে।
- Technician এডিট `/technicians/<employee_id>/edit`।
- Teams: "New team" বাটন এখন হেডারে (অন্য পেজের মতো)। UI-তে "Sewing floor mechanics" টিম তৈরি করে রাখা হয়েছে।
- **২০২৬-১১-১৪:** Technician ফর্মে "Employee (HR)" বাছাই (সার্চসহ), পরিচয়-ফিল্ড HR থেকে; `form-options` এ `employees` (কে ইতিমধ্যে টেকনিশিয়ান, তা `technician_id`-তে)। তালিকায় পেজ-প্রতি সারি (Rows 10/25/50/100) বাছাই, প্রতিটি তালিকার জন্য আলাদা কুকিতে মনে থাকে।
