# Inventory Module

**Module Key:** `inventory.store`
**নির্ভরতা:** [Master Data Module](01_Master_Data_Module.md) (Item Master, Department, UOM)

**⬅ ফিরে যান:** [Main Index](00_Main_Index.md)

---

## 1. Objective

গার্মেন্টস ফ্যাক্টরির সম্পূর্ণ Inventory ম্যানেজমেন্ট — Fabric (Knit + Woven), Yarn, Trims, Accessories, Chemical, General Item এবং Spare Parts। বিশেষ ফোকাস: **Roll-wise Fabric Control**, **Inspection**, **Splitting**, **Reservation**, **Issue to Production**, **BOM vs Actual Variance**।

## 2. Tracking Modes

| Mode | প্রযোজ্য আইটেম | আচরণ |
|---|---|---|
| quantity | Trims, Accessories, General | শুধু পরিমাণ |
| lot | Yarn, Chemical, কিছু Trims | Lot/Batch নম্বর সহ |
| roll | সব Fabric (Knit + Woven) | প্রতিটি রোল আলাদা + Inspection |
| serial | Machines & Equipment | Asset Register-এ যায় |

## 3. Fabric Roll Management

### 3.1 Roll Fields
roll_no, item_id (→ [Item Master](01_Master_Data_Module.md#2-item-master--base--extension-model)), grn_id (→ [Procurement Module](03_Procurement_Module.md#9-grn-goods-receipt-note--formal-definition)), lot_no, shade, shade_band, nominal_width, actual_width, gross_length, net_length, gross_weight, net_weight, store_id, location, status, parent_roll_id, reserved_order_id/style_id, unit_cost, inspection_grade, inspection_points, received_date

### 3.2 Roll Status Flow
`Received → Under Inspection → Available → Reserved → Issued → Closed`
অন্যান্য: `Damaged`, `Returned`, `Split`, `Merged`, **`Sent for Subcontract`** (নতুন — সেকশন ৮ দেখুন)

> **As built (Phase 2):** `fabric_rolls` টেবিল — এই সেকশনের সব ফিল্ড, **`App\Modules\ItemMaster`** মডিউলে (কোডবেসের `Inventory` মডিউল না — কারণ নিচে দেখুন)। ROLL-ট্র্যাকড আইটেম কখনো `item_stock_balances`-এ লেখে না ([§16](#16-relationship-with-existing-inventory-code)); `fabric_rolls`-ই তার স্টক। স্ট্যাটাস enum পূর্ণ, কিন্তু `RECEIVED→UNDER_INSPECTION→AVAILABLE/DAMAGED/RETURNED`-ই Phase 2-এ reachable — `RESERVED`/`ISSUED`/`CLOSED` Phase 3 (reservation/issue) এবং `SPLIT`/`MERGED`/`SENT_FOR_SUBCONTRACT` Phase 4-এ action পাবে; কলাম (`parent_roll_id`, `reserved_order_id`) এখনই আছে যাতে ঐ ফেজগুলো নতুন migration না লাগে।

## 4. Fabric Inspection at Receiving

### 4.1 Ownership নির্ধারণ (গুরুত্বপূর্ণ)

| বিষয় | সিদ্ধান্ত |
|---|---|
| Inspection Process Owner | **Inventory Module** (Roll creation ও Defect Catalogue এখানেই থাকে) |
| Trigger Point | [Procurement](03_Procurement_Module.md)-এর GRN Complete হওয়ার পর, GRN status = "Under Inspection" |
| Defect Catalogue Location | Inventory Module — Knit/Woven আলাদা list, single source of truth |
| Result Sync | Pass/Fail রেজাল্ট GRN Line-এ ফিরে যাবে |
| Reject হলে | [Procurement](03_Procurement_Module.md#12-purchase-return--claim)-এর Claim/Return Process অটো ট্রিগার হবে |

> **As built (Phase 2) — মডিউল ম্যাপিং:** এই টেবিলের "Inventory Module" কোডবেসে **`ItemMaster`**-এ ম্যাপ হয়েছে, Phase 0-এর সিদ্ধান্তের ধারাবাহিকতায় — `SparePart`/কোডবেসের `Inventory` মডিউল ইচ্ছাকৃতভাবে আলাদা রাখা হয়েছে (এটাই maintenance-এর পুরনো ক্যাটালগ), আর Item লাইব্রেরির নিজস্ব স্টক (quantity/lot ledger, এবং এখন roll) সবসময় `ItemMaster`-এ থেকেছে — কোন মডিউলে item X-এর স্টক আছে, ট্র্যাকিং মোড যাই হোক, এক জায়গাতেই উত্তর পাওয়া যায়। Trigger Point ও Result Sync স্পেক অনুযায়ীই বাস্তবায়িত (`ManageGoodsReceipt::complete()` → `InspectFabricRoll`)। **Reject হলে Claim/Return Process অটো ট্রিগার হয় না** — সেটা Phase 5-এর "Purchase Return + Claim" ([§12](03_Procurement_Module.md#12-purchase-return--claim)); Phase 2-তে `RETURNED` শুধু রোলের নিজের status, ডাউনস্ট্রিম workflow ছাড়া।

### 4.2 Process
1. GRN / Gate Pass ([Procurement Module](03_Procurement_Module.md) থেকে)
2. Roll Entry
3. Inspection
4. Grade & Decision
5. Accepted → Available | Rejected → Damaged/Return

### 4.3 Inspection Data
Inspector, Date, Actual Width, Shade matching, Defect points, Grade (A/B/C/Reject), Remarks + Photos

### 4.3.1 Four-Point System — যেভাবে তৈরি হয়েছে (Phase 7)

Phase 2-এর ইন্সপেকশন ছিল রোলপ্রতি একটা গ্রেড আর ইন্সপেক্টরের টাইপ করা একটা "points" সংখ্যা। মিলের ফ্লোরে কাজটা ওভাবে হয় না, তাই ব্যবহারকারীর নিজের **OCS Fabric Inspection Report** আর **Visual Fabric Inspection Report** (Form JMCL-07) ধরে পুরোটা নতুন করে বসানো হয়েছে।

**সবচেয়ে বড় পরিবর্তন: রায়ের একক এখন লট, রোল নয়।** রোলের শতাংশ বলে ওই রোলটা ব্যবহারযোগ্য কিনা; ডেলিভারিটা নেওয়া হবে কিনা সেটা বলে ইন্সপেক্টেড গজের ওপর pooled হিসাব। নতুন হেডার টেবিল `fabric_inspections`, আর রোলগুলো ওই শিটে **তোলা** হয়।

- **ফর্মুলা** — `মোট পয়েন্ট × ৩৬ × ১০০ ÷ (ইন্সপেক্টেড গজ × প্রস্থ ইঞ্চি)` (`FourPointScore`)। ব্যবহারকারীর শিটের চারটি রোলেই হুবহু মেলে: ৩৪ পয়েন্ট / ১২৪ গজ / ৬০" → **16.45**, আর ৩৮/১০৮ → 21.11, ৪২/১১৩ → 22.30, ৩৯/১২১ → 19.33। টেস্টে এই সংখ্যাগুলোই ধরে রাখা আছে।
- **লটের হিসাব pooled, গড় নয়** — ১৫৩ পয়েন্ট ও ৪৬৬ গজে **19.69** — শিটে যা লেখা হুবহু তাই (bcmath ট্রাঙ্কেট করে, রাউন্ড করলে 19.70 হতো আর কাগজের সাথে মিলত না)। রোল-শতাংশের গড় হতো 19.79। গড় নিলে ২০ গজের বাজে রোল ২০০ গজের ভালো রোলের সমান ওজন পেত — একটা ভালো ডেলিভারি একটা ছোট রোলের দোষে ফেল করত।
- **পয়েন্ট ট্যালি** — `fabric_roll_defects`-এ এখন `point_class` (১–৪) ও `occurrences`। ত্রুটির পয়েন্ট তার **দৈর্ঘ্য** দিয়ে ঠিক হয়, ধরন দিয়ে নয় (৩ ইঞ্চির কম = ১, ৬ পর্যন্ত = ২, ৯ পর্যন্ত = ৩, বেশি = ৪; ছিদ্র যত ছোটই হোক ৪)। তাই একই ডিফেক্ট এক রোলে কয়েকটা ক্লাসে থাকতে পারে — ঠিক Visual ফর্মের ট্যালি ঘরের মতো।
- **নমুনা বাড়ে, শিট বদলায় না** — `FIRST` (১০%) → ফেল → `SECOND` (১৫%) → ফেল → `FULL` (১০০%) বা সাপ্লায়ারের কাছে ফেরত। **এসকালেট মানে নতুন শিট**, যা আগেরটার দিকে `supersedes_id` দিয়ে দেখায়। ফেল করা শিট কখনো এডিট করে পাস বানানো হয় না — ওটাই প্রমাণ, আর মিলকে জবাব দিতে হয় কেন তাদের পুরো ডেলিভারি খোলা হলো। সীমা পেরোলে (১০০% হয়ে গেলে) এসকালেশন 409 — এরপর যা হয় সেটা বাণিজ্যিক সিদ্ধান্ত।
- **প্রস্থ তিন জায়গায়** — `width_start` / `width_middle` / `width_end`; স্কোরে যায় এদের গড় (`actual_width`)। রোল বরাবর মিলের প্রস্থ নড়ে, আর মার্কার বসাতে হয় সবচেয়ে সরু জায়গা ধরে।
- **Marked vs Actual** — `marked_length` (সাপ্লায়ারের টিকিট) ও `inspected_length` (টেবিলে মাপা); ঘাটতি হিসাব হয়, কিন্তু দুটোর একটা না থাকলে **null**, শূন্য নয় — নইলে না-মাপা রোল "ঠিকই আছে" দেখাত। দৈর্ঘ্য ছাড়া রোল 422: দৈর্ঘ্য না মাপলে রোলটা ইন্সপেক্টই হয়নি।
- **শেড ৩×৩ গ্রিড** — `shade_assessment` JSON: তিনটি আলোর উৎস (D-65 / TL-83 / A) × তিনটি তুলনা (অনুমোদিত ফ্যাব্রিক, end-to-end, side-to-center)। পুরনো `shade_match` boolean রাখা হয়েছে, সব ঘর পরিষ্কার হলেই true। দিনের আলোয় মেলা রোল স্টোরের টিউবলাইটে সবুজ দেখাতে পারে — একটা boolean সেটা কখনো বলতে পারত না।
- **গ্রেড আসে স্কোর থেকে** — সীমার ভিতরে A, দেড়গুণ পর্যন্ত B, তার বেশি C। আর গ্রেড বসে **অনুমোদনের সময়**, রোল তোলার সময় নয়: যে লট পাস করেছে তার খারাপ রোলও ব্যবহারযোগ্য কাপড়, আর ডেলিভারি নেওয়ার সিদ্ধান্ত যে সই করে তার, হিসাবের নয়।
- **সীমা সারিতে কপি হয়** — `acceptance_threshold` ইন্সপেকশনের দিনেরটা রোলে বসে যায় (ডিউটি রেটের মতোই)। পরে বার বদলালে আগের লট নীরবে পুনর্বিচার হয় না। সেটিং `inspection.four_point_acceptance` (ডিফল্ট ২৮), সাথে `inspection.sampling_first_percent` (১০) ও `inspection.sampling_second_percent` (১৫)।
- **সই** — `inspected_by` → `verified_by` (submit) → `approved_by` (accept)। কাগজে পাঁচটা ঘর (QC, QC/Store, Store Incharge, Store Manager, Sr. QA Manager); সিস্টেমে তিন ধাপ, কারণ এর বেশি ধাপ মানে এমন সই যেগুলো কেউ আলাদা করে দেখে না। **সচেতন সরলীকরণ।**
- **পারমিশন** — রোল তোলা `itemmaster.roll.inspect` (স্টোরকিপার); লট গ্রহণ/প্রত্যাখ্যান নতুন `itemmaster.inspection.approve` (স্টোর ম্যানেজার) — টাকা জড়িত সিদ্ধান্ত।
- **নোটিফিকেশন** — লট ফেল করলে `FABRIC_INSPECTION_FAILED` স্টোর টিমকে; পরের ধাপ QC-র সিদ্ধান্ত নয়।
- **API** — `/fabric-inspections` (index/store/form-options/show + rolls/submit/approve/reject/escalate/cancel)। পুরনো `/fabric-rolls/{roll}/inspect` রয়ে গেছে — যে ডেলিভারি কেউ ফোর-পয়েন্ট করে না তার এক-রোল শর্টকাট।
- **ছবি (Phase 7, as built)** — Phase 2-এর জানা গ্যাপ (শুধু লিখিত Remarks) এখন বন্ধ। `FileAttachment`-এর বিদ্যমান প্যাটার্ন পুনর্ব্যবহার করা হয়েছে (Breakdown/Work Order-এর মতোই) — `attachable_type` দুটো: `fabric_roll` (এক-রোল পথের নিজস্ব প্রমাণ) ও `fabric_inspection` (পুরো লটের একটাই evidence ফাইল, প্রতিটি রোলে ছড়ানো নয়)। **ছবি তোলা যায় শিট ড্রাফট না থাকলেও** — শেড বা ডিফেক্টের বিরোধ প্রায়ই গ্রেড বসার দিন কয়েক পরে ছবি দিয়েই মেটে, তাই `can_manage_photos` সচেতনভাবে `can_record`-এর মতো status-এ বাঁধা নয়, কেবল পারমিশনে। আপলোড `itemmaster.roll.inspect`-এ, দেখা `itemmaster.roll.view_any`-তে। `GET/POST /fabric-rolls/{roll}/attachments` ও `GET/POST /fabric-inspections/{inspection}/attachments`; দুটো detail payload-এই `photos[]` যোগ হয়েছে। পরীক্ষিত: ছবি আপলোড করে উভয় detail-এ ফিরে আসা, VIEWER-এর আপলোড না পারা (403), আরেক কোম্পানির ছবি 404 — `tests/Feature/ItemMaster/FabricInspectionPhotoTest`।
- টেস্ট: `tests/Feature/ItemMaster/FourPointInspectionTest`, `tests/Feature/ItemMaster/FabricInspectionPhotoTest`।

> **সিদ্ধান্ত দরকার:** গ্রহণযোগ্য সীমার ডিফল্ট ২৮ ধরা হয়েছে — ব্যবহারকারীর শিটে ২২.৩০% রোল PASS দেখানো আছে বলে সীমা অন্তত তার বেশি, কিন্তু প্রকৃত সংখ্যাটা বায়ারভেদে বদলায়। সেটিং `inspection.four_point_acceptance`-এ ঠিক করা যায়, তাই এটা আর ইমপ্লিমেন্টেশনের গ্যাপ নয়, শুধু একটা কনফিগারেশন মান — ব্যবহারকারী চাইলে বদলে নেবেন।

### 4.4 Knit Fabric Defects
Hole/Needle Line, Drop Stitch, Barré, Contamination, Oil Spot, Uneven Dyeing, Width Variation, GSM Variation, Snagging, Other

> Woven-এর জন্য আলাদা Defect List। **Phase 7-এ এটা ১০ থেকে ২০-এ বাড়ানো হয়েছে** — ব্যবহারকারীর Visual Inspection শিটে যে ঘরগুলো ছাপা আছে সেই ধরনে: Broken End, Broken Pick, Missing End, Missing Yarn, Knot, Slub, Naps, Thick Yarn, Foreign Fibre, Dirty Spot, Oil Stain, Colour Out, Hole, Wavy Fault, Crease Mark, Stop Mark, Width/GSM/Shade Variation, Other। ইন্সপেক্টর যে ত্রুটিটা দেখছেন সেটা তালিকায় না পেলে Other-এ ফেলেন, আর Other কাউকে কিছু বলে না।

## 5. Roll Splitting Logic

### কখন
বড় রোল থেকে ছোট রোল, আংশিক Issue, Damaged অংশ আলাদা করতে

### নিয়ম
1. শুধু `Available`/`Reserved` রোল Split করা যাবে
2. Parent Roll-এর net_length/net_weight থেকে ভাগ হবে; মোট Child = Parent (validation)
3. Cost অনুপাত অনুযায়ী ভাগ হবে
4. Parent Roll → `Split` (Closed); Child রোল নতুন roll_no + parent_roll_id পাবে
5. Shade, Lot, Item, Store একই থাকবে
6. Audit Log বাধ্যতামূলক

## 6. Roll Merging Logic
একই Item + Shade/Lot + Store-এর একাধিক Available Roll একত্রিত হয়ে নতুন Roll তৈরি হবে; পুরনো রোল `Merged` স্ট্যাটাস পাবে; Cost Weighted Average হবে।

### 5.1 / 6.1 যেভাবে তৈরি হয়েছে (Phase 4)

`SplitFabricRoll` ও `MergeFabricRolls` (`app/Modules/ItemMaster/Actions`), API `POST /fabric-rolls/{roll}/split` ও `POST /fabric-rolls/merge`, permission `itemmaster.roll.split`।

**স্প্লিট:**

- টুকরোগুলোর যোগফল প্যারেন্টের সমান না হলে 409। আধা মিটার হারিয়ে যাওয়া স্প্লিট আসলে ছদ্মবেশে স্টক অ্যাডজাস্টমেন্ট — সেটা অ্যাডজাস্টমেন্ট হিসেবেই পোস্ট হওয়া উচিত, যাতে কেউ দেখতে পায়। একটি টুকরোয় "স্প্লিট" মানে নাম বদল, তাই অন্তত দুটি লাগে।
- প্যারেন্ট `SPLIT` (ক্লোজড স্ট্যাটাস) — সে ছোট হয় না, রোল হিসেবে থাকাই বন্ধ করে; তার দৈর্ঘ্য সন্তানদের মধ্যে ফিরে আসে।
- **খরচ প্রতি-ইউনিট হিসেবে চলে**, রোল হিসেবে নয়। প্যারেন্টের `unit_cost` মানে এক মিটারের দাম; প্রতিটি সন্তানের এক মিটারের দামও তা-ই, ফলে মোট মূল্য দৈর্ঘ্যের অনুপাতেই ভাগ হয়ে যায় — কাউকে অনুপাত কষতে হয় না।
- শেড, লট, আইটেম ও বিন প্যারেন্টেরই থাকে (§5.5): স্প্লিট কিছু নাড়ায় না, কাপড়ের কোনো বৈশিষ্ট্যও বদলায় না। গ্রস ফিগারগুলো একই অনুপাতে ভাগ হয় — সদ্য কাটা রোল কেউ আবার ওজন করে না।
- **RESERVED রোল স্প্লিট করলে দাবিটি টুকরোগুলোতে সরে যায়**: প্যারেন্টের দাবি কারণসহ ক্লোজ হয় আর প্রতিটি সন্তানে একই রিকুইজিশনের জন্য নতুন দাবি বসে। নাহলে ধরে রাখা কাপড় চুপচাপ পরের জনের হাতে চলে যেত।

**মার্জ:**

- নতুন রোলের দাম **ওয়েটেড এভারেজ**, সংখ্যার গড় নয়: ২০০ মিটার @২৫০ আর ৫০ মিটার @৩০০ মিলে ২৬০, ২৭৫ নয়। সরল গড় নিলে র‍্যাক গোছানোর প্রতিটা দিন নীরবে মূল্য সরিয়ে দিত।
- শুধু প্রকৃত বিনিময়যোগ্য রোল: একই item + shade + lot + bin, এবং সবাই `AVAILABLE`। শেড মিলিয়ে না নিলে তৈরি রোলের শেড একটা মিথ্যা, আর সেই মিথ্যা ধরা পড়ে কাটিং টেবিলে গিয়ে।
- সোর্সগুলো `MERGED` + `merged_into_roll_id` (নতুন কলাম — `parent_roll_id` উল্টোদিক প্রকাশ করতে পারে না)। `received_date` হয় সবচেয়ে পুরনোটি, যাতে FIFO সবচেয়ে বেশি দিন পড়ে থাকা কাপড়েই পৌঁছায়।

## 7. Reservation & Allocation

- Soft Reservation (Quantity block), Hard Allocation (Specific Roll lock)
- Order/Style/Cutting Program রেফারেন্স
- Auto release after Issue বা Manual Unreserve (Approval সহ — [Shared Systems](05_Shared_Systems_and_Compliance.md) দেখুন)

### 7.1 Fabric Booking ↔ Reservation Sync
[Procurement Module](03_Procurement_Module.md#3-fabric-booking)-এর Fabric Booking Confirmed হলে এখানে Auto Soft Reservation তৈরি হবে:

```
Fabric Booking Confirmed (Procurement)
        ↓
Auto Soft Reservation (reservation_type="Booking", ref=booking_id)
        ↓
Booking → PR/PO Convert হলে ref আপডেট (booking_id → po_id)
        ↓
GRN Complete + Inspection Pass → Roll তৈরি → Reservation "Hard Allocation"-এ আপগ্রেড
        ↓
Booking Cancel হলে → Reservation Auto Release
```

### 7.2 যেভাবে তৈরি হয়েছে (Phase 3A)

`item_stock_balances.quantity_reserved` কলামটি Phase 1 থেকেই পড়া হচ্ছিল (`ItemLedger`-এর issue চেক, `Item::totalAvailable()`, item API) কিন্তু কেউ লিখত না। Phase 3A সেই লেখার জায়গাটা তৈরি করেছে — `app/Modules/ItemMaster` মডিউলে, স্পেয়ার-পার্টসের `SparePartReservation`-এর পাশে নয় বরং তার সমান্তরালে, কারণ কী আলাদা: এখানে (item, bin, lot) অথবা একটি নির্দিষ্ট রোল।

| জিনিস | কোথায় | কী করে |
| --- | --- | --- |
| `item_reservations` টেবিল + `ItemReservation` মডেল | `app/Modules/ItemMaster/{Database/Migrations, Models}` | status: `PENDING_ARRIVAL, ACTIVE, PARTIALLY_ISSUED, ISSUED, RELEASED, EXPIRED, CANCELLED` |
| `ReserveItemStock` | `app/Modules/ItemMaster/Actions` | `reserve()` (soft), `allocateRoll()` (hard), `earmark()`, `fillPendingEarmarks()`, `consume()`, `release()`, `releaseFor()`, `expireOverdue()` |
| API | `/item-reservations` (index/store/release) | permission `itemmaster.reservation.view_any` / `.manage` |
| UI | আইটেম ডিটেইল পেজের "যাদের জন্য সংরক্ষিত" কার্ড | মজুদ আর উপলব্ধের পার্থক্য কেন, তার উত্তর |

তিনটি রূপ, একই টেবিলে, কারণ lifecycle এক:

- **Soft reservation** — `bin_id` সেট, `fabric_roll_id` null। ব্যালেন্স সারি লক করে `quantity_reserved` বাড়ায়। **ledger-এ কিছু লেখে না** — কিছু শারীরিকভাবে নড়েনি, আর ledger-এ সারি থাকলে replay করে তাক-এর পরিমাণ আর মিলত না।
- **Hard allocation** — `fabric_roll_id` সেট। ROLL-ট্র্যাকড আইটেমের ব্যালেন্স সারিই নেই, তাই রোলের নিজের status (`AVAILABLE → RESERVED`) এনকাম্ব্রেন্স বহন করে।
- **Earmark** — `bin_id` null, status `PENDING_ARRIVAL`। এখনো না-আসা স্টকের উপর দাবি (§5)। কিছু ধরে রাখে না; GRN complete হলে `fillPendingEarmarks()` পুরনো দাবি আগে (oldest-first) পূরণ করে, আংশিক হলে বাকিটার জন্য নতুন earmark রেখে দেয়।

**সচেতন সরলীকরণ:** ROLL-ট্র্যাকড আইটেমের earmark স্বয়ংক্রিয়ভাবে পূরণ হয় না। ইন্সপেকশনে রোল `AVAILABLE` হলে স্টোরকে `EARMARK_STOCK_ARRIVED` পাঠানো হয়, কিন্তু কোন রোল কার জন্য — সেটা shade/width দেখে মানুষ ঠিক করে (§7.1), তাই বরাদ্দ ম্যানুয়াল। Order-wise hard allocation Phase 3B-র কাজ।

`expires_at` পার হওয়া দাবি প্রতিদিন `requisition:remind-outstanding` কমান্ড ছেড়ে দেয় — স্পেয়ার-পার্টস পাশে একই কলাম আছে কিন্তু কখনো সুইপ করা হয়নি, যেভাবে সংরক্ষণ চুপচাপ স্থায়ী হয়ে যায়।

## 8. Subcontract / Job-Work Fabric Tracking

Dyeing, Printing, Embroidery ইত্যাদির জন্য বাইরে ফেব্রিক পাঠানোর ট্র্যাকিং:

- **Subcontract Challan (Out):** roll_id, subcontractor, purpose, sent_qty, expected_return_date → Roll Status: `Sent for Subcontract`
- **Subcontract Return (In):** received_qty, wastage/shrinkage_qty, quality_status — আংশিক ফেরত সাপোর্ট
- Subcontractor-ভিত্তিক Outstanding Report

```
Available Roll → Subcontract Challan Out → Status "Sent for Subcontract"
        ↓
Subcontractor Processing
        ↓
Return In (full/partial) → Quality Check → Status back to Available
        ↓
Wastage/Shrinkage recorded → Wastage Entry report-এ লিংক
```

### 8.1 যেভাবে তৈরি হয়েছে (Phase 4)

`subcontract_challans` + `subcontract_challan_lines`, `ManageSubcontractChallan`, API `/subcontract-challans`, permission `itemmaster.subcontract.view_any|manage`।

- **স্টক লেজারে কিছুই যায় না**, ইচ্ছাকৃতভাবে। কাপড় বাইরে যায় কিন্তু আমাদেরই থাকে: এটি ইস্যু নয় (কিছু খরচ হয়নি), বিক্রি নয় (কিছু বিক্রি হয়নি), ট্রান্সফারও নয় (আমাদের কোনো স্টোরে নেই)। লেজার রিপ্লে করলে তাকের পরিমাণ মিলতেই হবে, আর এই কাপড় কোনো তাকেই নেই — তাই ভার বহন করে রোলের নিজের স্ট্যাটাস `SENT_FOR_SUBCONTRACT`।
- **ড্রাফট কিছু নাড়ায় না।** রোলের স্ট্যাটাস বদলায় শুধু `send()`-এ — পরিকল্পনা তাক থেকে মাল সরাতে পারে না।
- **`sent = returned + wastage`** না মিললে লাইন সেটেল হয় না। ডায়ার ১০০ মিটারের ৯৪ ফেরত দিলে হয় ৬ মিটার শ্রিংকেজে গেছে, নয়তো সে ঋণী; শুধু ৯৪ লিখলে পার্থক্যটা হাওয়া হয়ে যায় — আর যে প্রসেস লস কেউ দেখে না, তা কেউ ঠিকও করে না।
- ফেরত এলে রোল **প্রকৃত ফেরত দৈর্ঘ্য নিয়েই** তাকে ফেরে: শ্রিংকেজ বাস্তব, রোলটা সত্যিই ছোট হয়ে গেছে।
- `close()` যা কখনো ফেরত আসেনি তা অবলোপন করে (রোল `DAMAGED`) — নাহলে সেটা চিরকাল `SENT_FOR_SUBCONTRACT` থেকে এমন স্টক দেখাত যা ফ্যাক্টরির নেই। `cancel()` রোলগুলো তাকে ফিরিয়ে দেয়।

## 9. Issue to Production + BOM Consumption Variance

```
Cutting Program/Job তৈরি
        ↓
System BOM থেকে Required Qty দেখাবে
        ↓
User Available/Reserved Roll সিলেক্ট করে Issue করবে
        ↓
Actual Issued Qty রেকর্ড হবে
        ↓
System অটো Variance হিসাব করবে
```

```
Variance = Actual Issued Qty – BOM Required Qty
Variance % = (Variance / BOM Required) × 100
```

- Tolerance-এর মধ্যে হলে Issue Final; সীমার বাইরে গেলে Warning + [Approval](05_Shared_Systems_and_Compliance.md) লাগবে
- Variance Report Order/Style/Program-wise
- Extra Issue আলাদাভাবে ট্র্যাক হবে

> **নোট:** Tolerance %-এর প্রকৃত সংখ্যা এখনো ঠিক করা হয়নি — Production/Merchandising টিমের ইনপুট লাগবে, পরে ঠিক হবে। Phase 0/1 শুরু করার জন্য ব্লকিং না; বাস্তবায়নের সময় item/style-ভিত্তিক configurable সেটিং হিসেবে রাখা হবে।

### 9.1 যেভাবে তৈরি হয়েছে (Phase 3B)

`production_material_issues` টেবিল + `MaterialIssue` মডেল + `IssueToProduction` অ্যাকশন; API `GET|POST /cutting-programs/{program}/material-issues`, permission `production.issue.create`।

**সবচেয়ে গুরুত্বপূর্ণ সিদ্ধান্ত — টলারেন্স ছাড়ালে স্টক নড়ে না।** সারিটি `PENDING_APPROVAL` অবস্থায় লেখা হয়, `item_stock_transaction_id` ফাঁকা থাকে, এবং অনুমোদন এলে তখন `MaterialIssue::onApprovalDecided()` → `IssueToProduction::post()` ledger-এ পোস্ট করে। কাপড় আগে হাতে দিয়ে পরে সই চাইলে ওটা রিপোর্ট হতো, নিয়ন্ত্রণ নয় — আর স্পেকের "Approval লাগবে" কথাটার মানেও তা-ই।

- Approval ইঞ্জিনের নতুন entity type `PRODUCTION_ISSUE`। থ্রেশহোল্ড মাপা হয় **অতিরিক্ত অংশের মূল্যে**, পুরো ইস্যুর নয় — বড় কাটে দুই মিটার বেশি গেলে ডিরেক্টরের সই চাওয়া অর্থহীন।
- টলারেন্স: `production.bom_variance_tolerance_percent` (COMPANY + FACTORY, ডিফল্ট ৫%)। স্পেক নিজেই বলেছে সংখ্যাটা অনির্ধারিত, তাই হার্ডকোড করা হয়নি।
- প্রতিটি ইস্যু নিজের ভ্যারিয়েন্স **স্ন্যাপশট** রাখে (required/issued_to_date/variance/percent)। পরে আজকের BOM দিয়ে হিসাব করলে একটা কনজাম্পশন সংশোধনেই পুরো ইতিহাস নীরবে বদলে যেত।
- অপেক্ষমাণ ইস্যু ভ্যারিয়েন্সে গোনা হয় না — যা স্টোর থেকে বেরোয়নি তা খরচ নয়।
- ROLL আইটেম গোটা রোল হিসেবে যায় (ফ্লোর রিকুইজিশনের মতোই); অর্ধেক কাটা মানে Split, যা Phase 4।
- BOM-এ নেই এমন আইটেম ইস্যু করা যায় না (409) — মাপার মতো কোনো প্রয়োজনই নেই, তাই টলারেন্সেরও কোনো মানে থাকে না।

## 10. Landed Cost ↔ Roll Cost Timing

*(Landed Cost-এর মূল প্রক্রিয়া [Procurement Module](03_Procurement_Module.md#11-landed-cost)-এ)*

1. GRN Complete হলে Roll তৈরি হবে **Provisional Unit Cost** সহ (PO Rate ভিত্তিতে)
2. Roll তখনই Available/Reserved/Split/Issue করা যাবে — আটকানো হবে না
3. Landed Cost Post হলে সেই GRN-এর সব Roll-এর `unit_cost` recalculate হবে (Weighted Distribution)
4. ততক্ষণে Split/Merge হয়ে থাকলে cost update child/merged roll-এও cascade হবে
5. Issued/Closed Roll হলে cost update হবে কিন্তু রিপোর্টে "Cost Adjusted After Issue" flag দেখাবে
6. Landed Cost Post-এর পর Rate লক থাকবে

### 10.1 যেভাবে তৈরি হয়েছে (Phase 4)

`RecalculateRollCosts` (`app/Modules/ItemMaster/Actions`) — ধাপ ৩–৫ এর রোল-লেজারের দিকটা। ল্যান্ডেড কস্ট **পোস্টিং** নিজে Phase 5-এর, তাই এই সার্ভিসটি আলাদাভাবে ডাকা যায় এবং সেই পোস্টিং এলে তাতে যুক্ত হবে।

যে অংশটা সহজে ভুল হয়: ততক্ষণে রোলগুলো আর সেই রোল নেই। একটা কেটে চার টুকরো হয়েছে, তিনটে মিলে একটা হয়েছে। তাই সংশোধন **কাসকেড করে** —

- চাইল্ড রোল প্যারেন্টের নতুন দরেই দাম পায় (খরচ প্রতি-ইউনিট, §5.1 দেখুন)।
- মার্জড রোল তার **সব সোর্স থেকে পুনরায় ওয়েটেড** হয়, শুধু যেটা বদলেছে তা থেকে নয় — বাকিগুলোর দাম হয়তো একটুও নড়েনি।
- ইতিমধ্যে ইস্যু/ক্লোজড হয়ে যাওয়া রোলের দামও সংশোধিত হয় এবং `cost_adjusted_after_issue` ফ্ল্যাগ ওঠে (§10.5)। গত মাসে স্টোর থেকে বেরিয়ে যাওয়া মালের খরচ সত্যিই বদলেছে; যে রিপোর্ট সেটা চুপচাপ নতুন করে বলে দেয়, সেটা স্বীকার করে বলা রিপোর্টের চেয়ে খারাপ।
- দাম না বদলালে কিছুই লেখা হয় না — অপরিবর্তিত রোলে "ইস্যুর পরে সংশোধিত" ছাপ পড়া উচিত নয়।

## 11. Purchase Return ↔ Roll Status Link

*(মূল Return প্রক্রিয়া [Procurement Module](03_Procurement_Module.md#12-purchase-return--claim)-এ)*

```
Return Initiate (Procurement) → roll_id/grn_line_id সিলেক্ট → Approval
        ↓
Roll Status → "Returned" (Inventory অটো আপডেট)
        ↓
Outward Gate Pass → Stock কমবে (Ledger: Return Out) → Debit Note
```

**নিয়ম:** শুধু `Available`/`Damaged`/`Under Inspection (Rejected)` Roll Return করা যাবে। `Issued`/`Split (Closed)`/`Merged` সরাসরি Return না — Claim হিসেবে হ্যান্ডেল।

## 12. Other Features

- **Yarn Inventory:** Lot-wise + Issue to Knitting
- **Chemical:** Expiry Alert + FIFO suggestion

### 12.1 Lot, মেয়াদ ও FIFO — যেভাবে তৈরি হয়েছে (Phase 4)

লট নম্বর লেজারে থাকলেও সেটা নিছক একটা স্ট্রিং — কবে রং নষ্ট হবে বা সুতা কবে কাটা হয়েছে তা বলতে পারে না, ফলে কোন ড্রাম আগে খুলতে হবে তা-ও কেউ বলতে পারে না। `item_lots` সেই ছোট রেকর্ডটা: প্রতি (item, lot) একটি সারি, GRN-এর গেটে ড্রামের গায়ে যা লেখা আছে তা থেকে (`lot_expiry_date`, `lot_manufactured_date`, `supplier_lot_no` — নতুন GRN লাইন কলাম)।

ইচ্ছাকৃতভাবে এখানে কোনো **পরিমাণ নেই** — পরিমাণ `item_stock_balances`-এ থাকে যেখানে লেজার তার মালিক; এখানে রাখলে একই সংখ্যার দুটো বাড়ি হতো আর অমিল হওয়ার একটা পথ।

`LotSuggestion`-এর ক্রম:

1. **নিকটতম মেয়াদ আগে** — যেটা ব্যবহার না হলে ফেলে দিতে হবে।
2. মেয়াদ না থাকলে **সাধারণ FIFO** (`first_received_date`)।
3. **মেয়াদোত্তীর্ণ লট সবার শেষে, ফ্ল্যাগসহ — কিন্তু তালিকায় থাকে।** লুকিয়ে ফেললে কেউ ওটা অবলোপনও করে না। প্রি-সিলেকশন কখনো মেয়াদোত্তীর্ণ লটে বসে না।

এটি পরামর্শ, নিয়ম নয়: ইস্যু অ্যাকশন ক্রম চাপিয়ে দেয় না — শেড মেলানোর জন্য একটা ডাই লট আলাদা করে রাখা থাকতে পারে, যা স্টোরকিপার জানে আর সিস্টেম জানে না।

দৈনিক `itemmaster:expiry-alerts` কমান্ড শুধু **যে লটের স্টক এখনো আছে** তা নিয়ে কথা বলে — যে লট শেষ হয়ে গেছে তার মেয়াদ শেষ হতে পারে না, আর তা নিয়ে সতর্ক করলে মানুষ সতর্কবার্তা উপেক্ষা করতে শেখে। সেটিং `inventory.lot_expiry_notice_days` (ডিফল্ট ৩০, শূন্য দিলে বন্ধ)।
- **Location:** Store → Area → Rack
- **Physical Inventory:** Cycle Count + Full Stock Taking + Adjustment
- **Valuation:** Weighted Average (Landed Cost সহ)
- **Wastage Entry:** Cutting ও Process wastage আলাদা

### 12.2 Physical Inventory — যেভাবে তৈরি হয়েছে (Phase 6)

`stock_counts` + `stock_count_lines` (`app/Modules/ItemMaster`), অ্যাকশন `ConductStockCount`, API `/stock-counts` (open/lines/found/submit/post/cancel + `/stock-count-lines/{id}/recount`), UI `/item-master/stock-counts`। নাম্বারিং `STOCK_COUNT` (`CNT-`)। Permission: `itemmaster.count.view_any|create` (স্টোরকিপার) ও `itemmaster.count.post` (স্টোর ম্যানেজার — অ্যাডজাস্টমেন্ট স্টকের মূল্য নাড়ায়)।

তিনটি সিদ্ধান্ত পুরো ডিজাইনটা ধরে রাখে, আর তিনটিই একই কারণে — কাউন্ট যেন নিজেই গরমিল তৈরি করতে না পারে:

1. **শিটই ফ্রিজ।** শিট তৈরির মুহূর্তে সিস্টেমের পরিমাণ লাইনে কপি হয়ে যায় (`system_qty`), পোস্ট করার সময় আর পড়া হয় না। গোনার মাঝখানে পোস্ট হওয়া একটা রিসিট সত্যিকারের মুভমেন্ট — পরে আবার পড়লে সেটাই "গরমিল" হয়ে যেত। এজন্যই কোনো DRAFT ধাপ নেই: শিট তৈরি আর আইল হাঁটার মাঝে ফাঁক রাখলে ঠিক ওই ফাঁকটাই গরমিল বানায়।
2. **অ্যাডজাস্টমেন্ট পার্থক্য বসায়, উত্তর নয়।** `counted − system_at_freeze` পোস্ট হয়, ব্যালেন্স জোর করে counted-এ বসানো হয় না। জোর করে বসালে ফ্রিজ ও পোস্টের মাঝের প্রতিটি মুভমেন্ট নিঃশব্দে মুছে যেত — যেগুলো নিয়ে কাউন্টের কোনো আপত্তিই ছিল না।
3. **না-গোনা তাক শূন্য নয়।** `counted_qty` nullable; কোনো তাক না গোনা থাকলে submit 409 দেয়, কারণ নইলে যেখানে কেউ পৌঁছায়নি সেটুকু চুপচাপ অবলোপন হয়ে যেত।

আরও যা আছে: **ব্লাইন্ড কাউন্ট** (`is_blind`, ডিফল্ট চালু) — গোনা চলাকালীন `system_qty`/variance API পেলোড থেকেই বাদ থাকে, স্ক্রিনে লুকানো নয়; যে সংখ্যা ক্লায়েন্ট কখনো পায়নি তা পাশের বাক্সে টুকে দেওয়া যায় না। **"তাকে পাওয়া গেছে"** (`/found`) — যে স্টকের সারিই সিস্টেমে নেই তা লেখার পথ; এটা না থাকলে কাউন্ট কেবল সেই গরমিলই ধরতে পারত যা সিস্টেম আগে থেকেই আধাআধি জানত। এমন লাইনের দর আসে আইটেমের **অন্যত্র প্রচলিত গড় থেকে** (`prevailingCost`) — `ADJUSTMENT_IN` ওয়েটেড অ্যাভারেজ নাড়ায়, তাই শূন্য দরে ঢোকালে ওই বিনের গড়ই নেমে যেত আর তাকের পুরনো মালও ভুল মূল্যে বসত।

Approval: entity type `STOCK_COUNT`, `approvalContext()['cost']` = **পরম** variance value (এক আইটেমে ৫০,০০০ কম আর অন্যটায় ৫০,০০০ বেশি মানে দুটো সমস্যা, শূন্য নয়) — workflow না থাকলে engine সাথে সাথেই APPROVED দেয়। পোস্টিং যায় সাধারণ `ItemLedger`-এর `ADJUSTMENT_IN`/`ADJUSTMENT_OUT` দিয়ে (নতুন movement type নয় — type ধরে গ্রুপ করা প্রতিটি রিপোর্টকে নতুন শব্দ শেখাতে হতো), idempotency key `count:{line_id}`, আর লাইনে `item_stock_transaction_id` ফিরে লেখা থাকে যাতে ছয় মাস পরে কোনো মুভমেন্ট দেখে তার কারণ-শিটটা খুঁজে পাওয়া যায়। GL-এ যায় `STOCK_ADJUSTMENT` ইভেন্ট হিসেবে (Shared Systems §2 দ্রষ্টব্য)। দৈনিক `itemmaster:chase-counts` খোলা পড়ে থাকা শিট তাড়া দেয় (`STOCK_COUNT_STALE`), সেটিং `inventory.count_stale_days` (ডিফল্ট ৩)।

**স্কোপ নোট:** ROLL/SERIAL আইটেম শিটে ওঠে না। ওদের স্টক `fabric_rolls`-এ আলাদা আলাদা শনাক্ত করা পিস, ব্যালেন্স সারি নেই যা অ্যাডজাস্ট করা যায় — ওগুলো মেলানো মানে রেজিস্টার চেক ("R-1183 রোলটা কি র‍্যাকে আছে?"), পরিমাণ গোনা নয়; সেটা আলাদা কাজ। টেস্ট `tests/Feature/ItemMaster/StockCountTest`।

## 13. Floor/Store Requisition Fulfillment (Inventory-এর দিক থেকে)

*(পূর্ণ Requisition ডিজাইন [Floor/Store Requisition Module](04_Floor_Store_Requisition_Module.md)-এ)*

Store Keeper এই মডিউল থেকেই স্টক দেখে Requisition Fulfill করবে, Shortage হলে Auto Purchase Requisition ট্রিগার হবে (বিস্তারিত [Procurement Module](03_Procurement_Module.md#5-purchase-requisition-pr)-এ)।

## 14. Key Reports

Roll Ledger, Shade-wise & Shade Band Stock, Inspection Register (Knit+Woven), Split/Merge History, Reserved vs Available, BOM vs Actual Consumption Variance, Ageing Report, Slow/Non Moving, Chemical Expiry Alert, Stock Valuation

### 14.1 যেভাবে তৈরি হয়েছে (Phase 6)

নয়টি রিপোর্ট বিদ্যমান `ReportRegistry`-তে যোগ হয়েছে (`app/Modules/Reporting/Reports/Inventory`), সব `inventory` গ্রুপে — তাই `/reports` স্ক্রিন, CSV/এক্সপোর্ট, কিউয়িং ও পারমিশন-ভিত্তিক ফিল্টারিং বিনা পরিবর্তনে কাজ করে। প্রতিটি সারি lazy, কিছুই অ্যারেতে জমা হয় না।

| রিপোর্ট | কী | Permission |
|---|---|---|
| Item stock valuation | `item_stock_valuation` | `inventory.stock.view` |
| Reserved vs available | `item_reserved_vs_available` | `itemmaster.reservation.view_any` |
| Stock ageing | `item_stock_ageing` | `inventory.stock.view` |
| Slow / non-moving | `item_slow_moving` | `inventory.stock.view` |
| Lot expiry | `item_lot_expiry` | `inventory.stock.view` |
| Roll ledger | `fabric_roll_ledger` | `itemmaster.roll.view_any` |
| Shade-wise stock | `fabric_shade_stock` | `itemmaster.roll.view_any` |
| Inspection register | `fabric_inspection_register` | `itemmaster.roll.view_any` |
| Split/Merge history | `fabric_roll_lineage` | `itemmaster.roll.view_any` |

স্পেয়ার-পার্টসের `inventory_valuation` রিপোর্ট আগেই ছিল; সেটি আলাদা রেখে নতুন `item_stock_valuation` বানানো হয়েছে, কারণ **দুটো আলাদা খাতা** — একটা মেইনটেন্যান্স ক্যাটালগ, আরেকটা আইটেম লাইব্রেরি। এক স্ক্রিনে মিলিয়ে দিলে এমন স্টক যোগ হতো যা একসাথে কেউ ধরে রাখে না।

চারটি সিদ্ধান্ত যেগুলো সংখ্যার মানে ঠিক করে:

- **এজিং গোনা হয় সর্বশেষ আগমন থেকে**, প্রথমটা থেকে নয়। লেজার ওয়েটেড অ্যাভারেজ রাখে, লেয়ার নয় — কোন মিটারটা কবে ঢুকেছে তা সৎভাবে বলার উপায় নেই। সর্বশেষ আগমন থেকে গোনা রক্ষণশীল পাঠ: স্টককে যতটা পুরনো হতে পারে তার চেয়ে কম বয়সী দেখায়, অর্থাৎ যে দিকটায় ভুল করলে বানানো রাইট-অফ তৈরি হয় না।
- **স্লো-মুভিং মাপে চাহিদা, সব outbound নয়** — `ISSUE`/`CONSUME`/`TRANSFER_OUT` গোনা হয়, কিন্তু `ADJUSTMENT_OUT` ও `SCRAP` নয়। নইলে স্টক কাউন্ট যেদিন কোনো লাইন রাইট-অফ করত, ঠিক সেদিনই সেটা "সদ্য নড়েছে" হয়ে যেত — যা এই রিপোর্টের উদ্দেশ্যের ঠিক উল্টো। কখনোই ইস্যু না হওয়া স্টক বাদ পড়ে না, বরং "কখনো ইস্যু হয়নি" লেখা থাকে: ওটাই সবচেয়ে খারাপ কেস, অনুপস্থিত কেস নয়।
- **Earmark আর hold আলাদা কলামে।** যে দাবি এখনো আসেনি এমন মালের ওপর (`bin_id` null) তাকের কিছুই আটকায় না; দুটো মিলিয়ে দিলে অনুপলব্ধ স্টক বেশি দেখাত আর বিক্রয়যোগ্য স্টক কম।
- **শেড-ভিত্তিক স্টকে শুধু `AVAILABLE`/`RESERVED` রোল যোগ হয়।** ইস্যু/স্প্লিট/মার্জড রোল ইতিহাস — যোগ করলে কারখানার হাতে নেই এমন কাপড়ের প্রতিশ্রুতি তৈরি হতো। আর দুই শেড সবসময় দুই সারি, কখনো এক নয় (দুই শেডে কাটা গার্মেন্ট মানে বাতিল গার্মেন্ট)।

**স্কোপ নোট:** §১৪-র তালিকার **BOM vs Actual Consumption Variance** বানানো হয়নি — এটি প্রোডাকশন ডোমেইনের রিপোর্ট, যা ব্যবহারকারীর সিদ্ধান্তে এই প্রজেক্টের বাইরে রাখা হয়েছে (ভ্যারিয়েন্সের হিসাব `IssueToProduction::variance()`-এ আছে, রিপোর্টটি মার্জের সময় যোগ হবে)। **Chemical Expiry Alert** এখানে `item_lot_expiry` রিপোর্ট হিসেবে আছে; দৈনিক অ্যালার্ট কমান্ডটি Phase 4-এই হয়েছিল (§12.1)।

পাশাপাশি একটা পুরনো ফাঁক বন্ধ হয়েছে: `itemmaster.statuses` অনুবাদ ব্লকটি কখনো লেখা হয়নি, অথচ ইনস্পেকশন স্ক্রিনগুলো সেটাই ডাকত — ফলে ব্যবহারকারী `statuses.AVAILABLE` দেখতেন। রোল রিপোর্টগুলোর জন্য এটি যোগ করায় ওই স্ক্রিনগুলোও ঠিক হয়েছে। টেস্ট `tests/Feature/Reporting/InventoryReportsTest` (আচরণ) ও বিদ্যমান `ReportCatalogTest` (অনুবাদ/পারমিশন/কলাম আকার, নয়টিই স্বয়ংক্রিয়ভাবে ঢাকা পড়ে)।

## 15. Core Business Rules

1. Roll Tracking Item Inspection ছাড়া Available হবে না
2. Rejected Roll সরাসরি Available করা যাবে না
3. Split/Merge শুধু অনুমোদিত স্ট্যাটাসে
4. Reserved Roll অন্য Order-এ Issue করা যাবে না
5. BOM Variance tolerance অতিক্রম করলে Approval বাধ্যতামূলক *(tolerance %-এর চূড়ান্ত সংখ্যা পরে নির্ধারণ হবে — non-blocking, ডিফল্ট একটা কনফিগারযোগ্য সেটিং হিসেবে রাখা হবে)*
6. Negative Stock ব্লক *(বিদ্যমান `inventory.allow_negative_stock` সেটিং হিসেবে ইতিমধ্যে আছে — নতুন বানাতে হবে না, দেখুন Section 16)*
7. সব Critical Transaction-এ Audit Log

## 16. Relationship with Existing Inventory Code

> **সিদ্ধান্ত (চূড়ান্ত, ২১ সেপ্টেম্বর ২০২৬): Item Master সম্পূর্ণ আলাদা মডিউল ও আলাদা `items` টেবিল।** `SparePart` মেইনটেন্যান্স স্টোরের নিজস্ব ক্যাটালগ হিসেবে যেমন আছে তেমনই থাকবে। আগের ড্রাফটে "SparePart-কে এক্সটেন্ড করা" সিদ্ধান্ত ছিল; বাস্তবায়নের পর দেখা যায় ৭,৯৮৪ আইটেম (চাল, সুতা, কেমিক্যাল) Spare parts স্ক্রিনে ঢুকে যাচ্ছে — Item Master একটা অ্যাডমিন-মেইনটেইনড লাইব্রেরি, স্টোরকিপারের পার্টস ক্যাটালগ না। তাই সিদ্ধান্ত উল্টে দেওয়া হয়েছে।

**বাস্তবায়িত কাঠামো** (`app/Modules/ItemMaster`):

| টেবিল | ভূমিকা |
|---|---|
| `items` | Item Master (Master Data §2.2) — `item_code`, `legacy_item_id`, `item_type`, `tracking_mode`, `uom_id` (UOM মাস্টারে FK), item_department/category/sub/detail FK, model/brand/manufacturer/origin, default store/HS code/vendor, reorder policy, `is_returnable` |
| `item_departments` | লাইব্রেরির নিজস্ব Department অক্ষ (Yarn Store, General Store…) + `default_item_type`; সংগঠনের `departments` থেকে আলাদা (Master Data §4) |
| `item_categories` | ৩-স্তর (self-referencing `parent_id`), company-owned; Settings → Master Data → "Item categories" থেকে মেইনটেইন হয়; spare part ক্যাটাগরি থেকে আলাদা |
| `item_fabric_attributes` / `item_yarn_attributes` / `item_chemical_attributes` | টাইপ-ভিত্তিক টেকনিক্যাল স্পেক (§2.3–2.5), 1:1 |

- API: `/items` (search, department/type/category ফিল্টার), `/items/form-options`; permission `itemmaster.item.view_any` (যে কেউ রিকুইজিশন দেয়) ও `itemmaster.item.manage` (Store Manager, Factory Manager ও উপরে)।
- UI: সাইডবারে আলাদা "Item Master" গ্রুপ → `/item-master` (index/create/detail/edit)।
- GRN Line (`goods_receipt_lines.item_id`) এই টেবিলকেই রেফারেন্স করে; Procurement/Requisition-এর সব লাইনও এটাকেই করবে।
- Legacy লাইব্রেরি `itemmaster:import-legacy-items` কমান্ড দিয়ে এই টেবিলে ওঠে।

**বিদ্যমান Inventory কোড থেকে যা এখনো রিইউজ হয়** (নতুন করে বানানো হয়নি): `Company → Factory → Warehouse → Store → Bin` হায়ারার্কি (Item-এর `default_store_id` এখানেই FK), UOM মাস্টার, Negative-stock সেটিং প্যাটার্ন, `NumberSequenceGenerator`, Approval/Notification/Audit।

**Item-এর স্টক লেজার — Phase 1-এ সিদ্ধান্ত ও বাস্তবায়ন:** পথ (ক) নেওয়া হয়েছে — Item-এর জন্য **নিজস্ব** `item_stock_balances` (item × bin × lot_no; QUANTITY মোডে lot_no = `''` একটাই pooled row) ও append-only `item_stock_transactions` (movement type vocabulary স্পেয়ার-পার্টস লেজারের সমান: RECEIPT/ISSUE/CONSUME/RETURN/ADJUSTMENT_IN/OUT/TRANSFER_IN/OUT/SCRAP/OPENING_BALANCE), সার্ভিস `App\Modules\ItemMaster\Services\ItemLedger` (row lock, weighted average শুধু inbound-এ, idempotency key, reversal opposing row, `inventory.allow_negative_stock` সেটিং, low-stock notification)। `InventoryBalance`/`InventoryTransaction` (স্পেয়ার পার্টস) অপরিবর্তিত। LOT-ট্র্যাকড আইটেমে প্রতিটি movement-এ lot_no বাধ্যতামূলক; ROLL/SERIAL আইটেম এই লেজারে refused — রোল-লেজার Phase 2-এ (§3)। Item-এর detail API (`GET /items/{id}`) `stock` ব্লক দেয় (on_hand/reserved/available/value/by_bin) এবং `GET /items/low-stock` + `below_reorder=1` ফিল্টার reorder alert-এর তালিকা দেয়। Reservation (`item_reservations`) Phase 3-এ Floor Requisition fulfilment-এর সাথে।

---

**পরবর্তী:** [Procurement Module](03_Procurement_Module.md) | [Floor/Store Requisition](04_Floor_Store_Requisition_Module.md) | [Shared Systems & Compliance](05_Shared_Systems_and_Compliance.md)
