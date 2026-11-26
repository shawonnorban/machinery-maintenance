# Machinery Maintenance

> **ফেজ ০:** Machinery (বিদ্যমান, অপরিবর্তিত) · **ফোল্ডার:** `00_Machinery_Maintenance`

**⬅ ফিরে যান:** [AnnotechERP Index](../00_Index.md)

<!-- AUTO:status:start -->
| কী | গ্রুপ | স্ক্রিন | স্ট্যাটাস |
|---|---|---|---|
| `00/01` | [Assets & Machine Register](01_Assets_Machine_Register.md) | — | ✅ সম্পন্ন |
| `00/02` | [Preventive Maintenance](02_Preventive_Maintenance.md) | — | ✅ সম্পন্ন |
| `00/03` | [Breakdowns](03_Breakdowns.md) | — | ✅ সম্পন্ন |
| `00/04` | [Work Orders](04_Work_Orders.md) | — | ✅ সম্পন্ন |
| `00/05` | [Spare Parts & Inventory](05_Spare_Parts_Inventory.md) | — | ✅ সম্পন্ন |
| `00/06` | [Metering](06_Metering.md) | — | ✅ সম্পন্ন |
| `00/07` | [Technicians & Teams](07_Technicians_Teams.md) | — | ✅ সম্পন্ন |
| `00/08` | [Vendors, Warranty & Service Contracts](08_Vendors_Warranty_Service_Contracts.md) | — | ✅ সম্পন্ন |
| `00/09` | [Maintenance Cost & Analytics](09_Maintenance_Cost_Analytics.md) | — | ✅ সম্পন্ন |
<!-- AUTO:status:end -->

## নোট

_এই মডিউলের ক্রস-কাটিং সিদ্ধান্ত এখানে লেখা হবে।_

## ডিজাইন আপডেট (২০২৬-১১-১২)

Commercial & Export-এর মতো করা হয়েছে:
- **তালিকা:** কার্ডে গ্রিড, "Search N records…" + Export (CSV), নীল হেডারে সব কলামে সর্ট, সারিতে আইকনসহ বাটন (View/Edit/Delete/Complete…), নম্বর-পেজার + Refresh। (`components/ui/data-table.jsx`)
- **ফিল্টার:** "Searching Data" কার্ড, প্রতি সারিতে ৪টা ঘর, Search/Reset/Add New/Refresh। Search চাপলে তবেই প্রয়োগ হয়। হেডারের "New …" বাটন এখন প্যানেলের Add New। (`components/erp/searching-data.jsx`)
- **ফর্ম:** "Master Info"-র মতো শিরোনামসহ বাক্স, বাঁদিকে লেবেল (আগে লাল `*`), দুই কলাম; নিচে আলাদা বারে ← Cancel আর 💾 Save। Vendor ফর্মে Registration/Bank/Purchasing/Notes আলাদা বাক্স; Spare part-এ Details/Stock; Transfer-এ Route/Items। (`components/ui/form-section.jsx`, `form-field.jsx`)

## ফলো-আপ (২০২৬-১১-১৪)

- **হেডার-বাটনে আইকন:** Work order (Hold/Complete/Cancel/Start/Verify/Close/Reopen…), Breakdown, Plan, Template, দুই ধরনের Transfer, Asset (Change status/Request transfer), Spare part (Adjust/Receive) — এক সেট আইকন (`components/ui/action-icon.jsx`)।
- **পেজ-প্রতি সারি:** পেজারে "Rows" (10/25/50/100); প্রতিটা তালিকার জন্য আলাদা কুকিতে মনে থাকে (`lib/per-page-shared.js`, সার্ভার পেজে `applyPerPage`/`perPageQuery` from `lib/per-page.js`); DataTable-এ `perPageSelect` দিলে চালু হয়। Machinery-র ১৭টা তালিকায় চালু।
- **পড়া যায় এমন URL:** Plan `PM-00001`, Warranty `WTY-00001`, Meter `MTR-00001` — নতুন কলাম `plan_number`/`warranty_number`/`meter_number`, সিরিজ NumberSequenceGenerator-এ (রিসেট হয় না), তৈরির সময় `AssignsDocumentNumber` trait নিজে নম্বর দেয়; পুরনো রেকর্ড মাইগ্রেশনে তৈরির ক্রমে নম্বর পেয়েছে। Template-এর URL এর `code` দিয়ে (কোম্পানির নিজের template platform-এরটার আগে)। তালিকায় নম্বরের কলাম, ডিটেইল হেডারে নম্বর।
- **Technician ↔ HR:** কর্মী-তালিকা থেকে বাছাই — বিস্তারিত [07](07_Technicians_Teams.md)।

