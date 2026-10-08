# Master Data Module

**Module Key:** `master.data`
**পরিচিতি:** এই মডিউল সিস্টেমের সব শেয়ার্ড রেফারেন্স ডাটা রাখে। Inventory, Procurement, Floor/Store Requisition — সবাই এই মডিউলকে **রেফারেন্স করে**, কিন্তু এই মডিউল কারো ওপর নির্ভর করে না (bottom-layer, no circular dependency)।

> **⚠️ গুরুত্বপূর্ণ আর্কিটেকচার নীতি — Real FK + Point-in-Time Snapshot Column (সংশোধিত)**
> *(আগের ড্রাফটে এখানে "কোনো FK না, শুধু কপি" নীতি ছিল — টিম রিভিউ শেষে বাতিল করা হয়েছে, কারণ বাকি প্ল্যাটফর্মের সব মডিউল real Eloquent FK ব্যবহার করে; দুই রকম কনভেনশন একসাথে রাখলে রক্ষণাবেক্ষণ কঠিন হতো।)*
> - Item/Vendor/Department/Store/UOM/HS Code — সবই স্বাভাবিক **Foreign Key** দিয়ে রেফারেন্স হবে (`item_id`, `vendor_id` ইত্যাদি সত্যিকারের `belongsTo`/`constrained()` রিলেশন), বাকি কোডবেসের কনভেনশনের সাথে সামঞ্জস্যপূর্ণভাবে।
> - ঐতিহাসিক রেকর্ডের অখণ্ডতা (Master Data পরে বদলালেও পুরনো ট্রানজেকশন যেন না বদলায়) রক্ষা হবে **blanket no-FK নীতি দিয়ে না, বরং নির্দিষ্ট ফিল্ডে**: যেসব ফিল্ড সময়ের সাথে বদলাতে পারে এবং পুরনো রেকর্ডে অপরিবর্তিত থাকা জরুরি (item_name, unit_cost/rate, technical spec) — সেগুলো ট্রানজেকশন লাইনেই (GRN Line, PR Line, Roll) আলাদা কলাম হিসেবে সরাসরি সংরক্ষিত হবে, FK-এর পাশাপাশি। যেমন GRN Line/Roll-এর `unit_cost` কলাম এমনিতেই আলাদা থাকে (দেখুন [Inventory Module §10](02_Inventory_Module.md#10-landed-cost--roll-cost-timing)) — এই একই প্যাটার্ন সব জায়গায় প্রযোজ্য হবে।
> - এর ফলে Item/Vendor deactivate বা edit হলেও পুরনো ট্রানজেকশনের স্ন্যাপশট কলামগুলো অক্ষত থাকে, অথচ FK থাকায় রিপোর্টিং/জয়েন সহজ থাকে এবং বাকি কোডবেসের সাথে একই কনভেনশন বজায় থাকে।
> - তাই এই ডকুমেন্টে "Store", "Department" ইত্যাদি যেখানেই উল্লেখ আছে, সেগুলো এখন থেকে নিজস্ব লুকআপ টেবিল + real FK হিসেবে ধরা হবে (আগের "শুধু dropdown ভ্যালু" নীতির বদলে)।
> - **Store হায়ারার্কি চূড়ান্ত:** বিদ্যমান কোডবেসে ইতিমধ্যে `Company → Factory → Warehouse → Store → Bin` হায়ারার্কি বাস্তবায়িত আছে (`app/Modules/Inventory` মডিউলে) — এই ডকুমেন্টের "Store" মানে **এই বিদ্যমান হায়ারার্কির `Store`-ই**, নতুন সমান্তরাল Store লুকআপ না (বিস্তারিত Section 5-এ)।
> - **Item Master-এর মূল উদ্দেশ্য (স্পষ্টীকরণ):** এটা একটা **নিয়ন্ত্রিত লাইব্রেরি**, ইউজারের টাইপ করার ঝামেলা কমানোর জন্য — ফ্রি-টেক্সট এন্ট্রি না। ইউজার Item Department সিলেক্ট করে তারপর সেই Department-এর আন্ডারে থাকা Item Library থেকেই একটা Item বেছে নেবে; লাইব্রেরির বাইরে থেকে কোনো Item সিলেক্ট/টাইপ করা যাবে না। ইউজারের কাঙ্ক্ষিত Item লাইব্রেরিতে না থাকলে Admin Item Library-তে সেটা যোগ করে দেবে। এই কারণেই Item Master real FK-ভিত্তিক একটা কন্ট্রোল্ড লুকআপ, ফ্রি-টেক্সট ফিল্ড না।
> - প্রজেক্টে কোনো Employee Master/Role নেই — সরাসরি সিস্টেমের বিদ্যমান **User** ব্যবহার হবে (login user)। RBAC, Approval, Notification, Audit Log, Document Numbering — এই সবগুলোই বর্তমান প্ল্যাটফর্মে ইতিমধ্যে ইমপ্লিমেন্ট করা আছে, তাই এই ডকুমেন্টেশনের স্কোপের বাইরে (বিস্তারিত [Shared Systems & Compliance](05_Shared_Systems_and_Compliance.md)-এ)।

**⬅ ফিরে যান:** [Main Index](00_Main_Index.md)

---

## 1. কেন Item Master আলাদা মডিউলে

Item Master একাধিক মডিউলের কমন ডিপেন্ডেন্সি:

| মডিউল | ব্যবহার (Reference/Lookup, real FK দিয়ে) |
|---|---|
| [Inventory](02_Inventory_Module.md) | Stock, Roll/Lot তৈরি, Reorder Level |
| [Procurement](03_Procurement_Module.md) | PR/PO Line-এ Item সিলেকশন |
| [Floor/Store Requisition](04_Floor_Store_Requisition_Module.md) | User Item Library থেকে Item লুকআপ করে Requisition করবে |
| BOM/Production (ভবিষ্যৎ) | Style BOM রেফারেন্স |

Item Master যদি Inventory-এর ভেতরে থাকতো, তাহলে Procurement/Requisition মডিউলকে Inventory-এর ওপর নির্ভর করতে হতো — সার্কুলার ডিপেন্ডেন্সি তৈরি হতো। তাই এটা স্বতন্ত্র থাকবে।

```
                 MASTER DATA MODULE (কারো ওপর নির্ভর করে না)
                              │
          ┌───────────────────┼───────────────────┐
          ▼                    ▼                    ▼
     INVENTORY            PROCUREMENT       FLOOR/STORE REQUISITION
   (রেফারেন্স করে)        (রেফারেন্স করে)         (রেফারেন্স করে)
```

---

## 2. Item Master — Base + Extension Model

### 2.1 প্রেক্ষাপট (Real Legacy Data থেকে শেখা)

ক্লায়েন্টের বিদ্যমান Item List (৭,৯৮৪টা আইটেম, ১৩টা Department) থেকে দেখা গেছে:

- Company, Department, Category, Sub Category, Detail Category — ৩-স্তরের হায়ারার্কি সব আইটেমের জন্য কমন এবং কার্যকর।
- কিন্তু Fabric/Yarn আইটেমের টেকনিক্যাল স্পেসিফিকেশন (GSM, Width, Count, Fiber%) কোনো আলাদা কলামে ছিল না — Item Name টেক্সটের ভেতরে গোঁজা ছিল (যেমন `"30/1 Bio Cotton::Sky Captain::30151..."`)। এটা ছিল **সাময়িক/সংক্ষিপ্ত এন্ট্রি পদ্ধতি**, চূড়ান্ত ডিজাইন না।
- ফলে GSM/Width/Count দিয়ে ফিল্টার-সর্ট-রিপোর্ট করা সম্ভব ছিল না।

তাই নতুন সিস্টেমে **Base + Extension Table** মডেল ব্যবহার হবে — কমন ফিল্ড এক জায়গায়, item_type-ভিত্তিক টেকনিক্যাল স্পেসিফিকেশন আলাদা এক্সটেনশন টেবিলে, নাম্বার ফিল্ড হিসেবে (টেক্সটে গোঁজা নয়)।

### 2.2 Base Table — `items`

| Column | উৎস / নোট |
|---|---|
| company_id | Multi-company সাপোর্ট |
| **item_department_id** | কোন Item Department-এর আন্ডারে আইটেম (Yarn Store, Garments Store, General Store...) — Section 4, সংগঠনের Department নয় |
| category / sub_category / detail_category | ৩-স্তরের হায়ারার্কি |
| item_id, item_code | Identifier |
| item_name, item_description | নাম + বিস্তারিত |
| unit | বেস ইউনিট |
| model, brand, origin | মূলত IT/Electronics/Machinery টাইপ আইটেমের জন্য |
| **item_type** | নতুন — Fabric / Yarn / Trims / Chemical / General / Spare; Department-এর `default_item_type` থেকে auto-suggest হবে (নিচে Section 2.6 দেখুন) |
| tracking_mode | quantity / lot / roll / serial |
| store_id (default) | ডিফল্ট স্টোর — Store Lookup List (Section 5)-এর `store_id`-এ real FK |
| reorder_level | |
| is_active | |

### 2.3 Extension Table — `fabric_attributes`
*(item_type = Fabric হলে বাধ্যতামূলক)*

| Column |
|---|
| item_id (FK) |
| fabric_type (Woven/Knit/Fleece/Rib) |
| composition |
| gsm |
| width |
| construction |
| finish |
| shrinkage |
| pantone |
| color_fastness |

### 2.4 Extension Table — `yarn_attributes`
*(item_type = Yarn হলে বাধ্যতামূলক)*

| Column |
|---|
| item_id (FK) |
| count |
| type |
| fiber |
| twist |
| color |

### 2.5 Extension Table — `chemical_attributes`
*(item_type = Chemical হলে)*

| Column |
|---|
| item_id (FK) |
| expiry_tracking (Yes/No) |
| hazard_info |
| batch_tracking (Yes/No) |

### 2.6 item_type Auto-Derivation Logic (গ্যাপ-ফিক্স — Stable Mapping)

> **সমস্যা ছিল:** আগে এই লজিক Department-এর **টেক্সট নাম** ম্যাচ করে কাজ করতো (যেমন Department = "Yarn Store" হলে item_type = Yarn)। কিন্তু Department একটা ফ্রি-এডিটেবল Lookup List হওয়ায় (দেখুন Section 4), কেউ যদি "Yarn Store"-কে "Yarn Godown" নামে রিনেম করে, তাহলে এই টেক্সট-ম্যাচিং নীরবে ভেঙে যাবে।
>
> **ফিক্স:** প্রতিটা Department Lookup এন্ট্রির সাথে একটা স্থায়ী `default_item_type` অ্যাট্রিবিউট থাকবে (Section 4 দেখুন) — Department রিনেম হলেও এই ম্যাপিং অক্ষত থাকবে, কারণ এটা নামের টেক্সট না, বরং সেই Lookup রেকর্ডের একটা ফিল্ড।

```
নতুন Item Entry-তে Department সিলেক্ট করা হলো
        ↓
সেই Department Lookup রেকর্ডের `default_item_type` ফিল্ড থেকে item_type সাজেস্ট হবে
        ↓
ইউজার confirm/override করতে পারবে (override করলে সেটাও Audit Log-এ থাকবে)
```

Category-ভিত্তিক অতিরিক্ত hint (যেমন Category-তে "Fabric" শব্দ থাকলে) সাজেশনকে আরও নির্ভুল করতে সহায়ক হিসেবে ব্যবহার করা যেতে পারে, কিন্তু **প্রাথমিক সোর্স অফ ট্রুথ Department-এর `default_item_type` ফিল্ড**, টেক্সট ম্যাচিং না।

### 2.7 Item Entry ফর্ম ফ্লো

```
User "New Item" ফর্ম ওপেন করবে
        ↓
Common fields পূরণ (Department, Category, Item Code, Item Name, Unit, Model, Brand, Origin...)
        ↓
item_type অটো-সাজেস্ট হবে, ইউজার confirm/override করবে
        ↓
   ┌─────────────────────┬─────────────────────┬────────────────────┐
   ▼                       ▼                      ▼
item_type = Fabric     item_type = Yarn      General/Chemical/Trims
   ↓                       ↓                      ↓
"Fabric Technical      "Yarn Technical         কোনো এক্সট্রা সেকশন
Spec" বাধ্যতামূলক       Spec" বাধ্যতামূলক        আসবে না
ওপেন হবে (GSM,          ওপেন হবে (Count,
Width, Composition)     Fiber, Twist)
   ↓                       ↓
পূরণ না করলে Save ব্লক    পূরণ না করলে Save ব্লক
```

### 2.8 Legacy Data Migration Plan

| ধাপ | কাজ |
|---|---|
| 0 | Department Lookup List ও Store Lookup List সিড করা হবে (legacy ফাইলের ইউনিক Department/Store নামগুলো দিয়ে), প্রতিটা Department-এ `default_item_type` অ্যাসাইন করা হবে |
| 1 | বিদ্যমান ৭,৯৮৪টা আইটেম as-is import হবে `item_master`-এ, item_type auto-derive সহ (Department-এর default_item_type থেকে) |
| 2 | item_type = Fabric/Yarn চিহ্নিত আইটেম আলাদা তালিকায় ফ্ল্যাগ হবে ("Needs Technical Spec") |
| 3 | Item Name টেক্সট থেকে সেমি-অটো পার্সিং (regex ভিত্তিক) — Count, Fiber%, GSM বের করার চেষ্টা |
| 4 | Store/Merchandising টিম ম্যানুয়ালি রিভিউ করে confirm/correct করবে |
| 5 | Technical Spec পূরণ না হওয়া পর্যন্ত সেই আইটেমে Roll Split/Inspection ফিচার সীমিত থাকবে, সিস্টেম ওয়ার্নিং দেখাবে |

### 2.9 লক্ষ্যণীয় ডাটা কোয়ালিটি ইস্যু (Legacy ফাইল থেকে)
- "Yarn Dyeing" ডিপার্টমেন্টের সব আইটেমে Category ফাঁকা ছিল — classification অসম্পূর্ণ, migration-এর সময় ঠিক করতে হবে।
- Unit ভুল বসানোর নজির পাওয়া গেছে (যেমন Electronics আইটেমে KG) — category-wise default unit suggestion থাকা উচিত নতুন সিস্টেমে।

### 2.10 Item Deactivation/পরিবর্তন হলে চলমান (In-Flight) ট্রানজেকশনের নিয়ম (গ্যাপ-ফিক্স)

Item এখন real FK (`item_id`) দিয়ে রেফারেন্স হয়, তাই Item Master-এ নাম/ইউনিট/কোড পরে বদলালে সেটা FK-এর মাধ্যমেই পুরনো ট্রানজেকশনে "দেখা" যাবে — কিন্তু `unit_cost`/`rate`-এর মতো ফিল্ড ট্রানজেকশন লাইনেই আলাদা স্ন্যাপশট কলামে থাকায় (উপরের আর্কিটেকচার নীতি দ্রষ্টব্য) সেগুলো প্রভাবিত হয় না। তাই প্রশ্নটা থাকে শুধু: কোনো Item Draft PR/Draft Floor Requisition-এ সিলেক্ট করা অবস্থায় Master Data-তে `is_active = No` হয়ে গেলে কী হবে?

**নিয়ম:**
- Submit/Approve-এর মুহূর্তে সিস্টেম Item এখনও Active কিনা তা রিভ্যালিডেট করবে; Inactive হলে ইউজারকে Warning দেখাবে এবং Item পরিবর্তন করতে বলবে (ব্লক করবে, Submit/Approve এগোতে দেবে না)।
- যেসব ট্রানজেকশন **ইতিমধ্যে সম্পূর্ণ/Approved/Closed হয়ে গেছে**, সেগুলোর স্ন্যাপশট কলামের ডাটা (unit_cost/rate/spec) **অপরিবর্তিত থাকবে** — Historical Record-এর অখণ্ডতা বজায় রাখার জন্য, Master Data-র পরবর্তী পরিবর্তন সেই কলামগুলোকে প্রভাবিত করবে না (যদিও FK-এর মাধ্যমে Item-এর বর্তমান নাম/কোড লিংক থেকেই যাবে)।

---

### 2.11 Item Categories — নিজস্ব ৩-স্তর তালিকা

Item-এর Category → Sub Category → Detail Category একটা আলাদা লুকআপ (`item_categories`, Settings → Master Data → "Item categories") — spare part ক্যাটাগরির সাথে শেয়ার করা হয় না। Company-owned (প্ল্যাটফর্ম সিড নেই); গভীরতা সর্বোচ্চ ৩, লুপ নিষিদ্ধ (save-এর সময় যাচাই)। Legacy ইমপোর্ট নামের থেকে কোড জেনারেট করে (সাব-লেভেলে প্যারেন্ট কোড প্রিফিক্স), একই নাম একই প্যারেন্টের নিচে এক রো।

## 3. Supplier / Vendor Master

> **সংশোধিত** — নতুন Entity না, বিদ্যমান Vendor মডিউল এক্সটেন্ড হবে।
>
> **সিদ্ধান্ত:** প্ল্যাটফর্মে ইতিমধ্যে একটা `Vendor` মডিউল আছে (`app/Modules/Vendor`, টেবিল `vendors`) — ওয়ার্ক অর্ডার/ওয়ারেন্টি/সার্ভিস কন্ট্রাক্টের ভেন্ডর এখান থেকেই আসে। Inventory/Procurement মডিউলের সাপ্লায়ারও **এই একই টেবিল**-এ থাকবে, নতুন আলাদা `suppliers` টেবিল তৈরি হবে না — একই সাপ্লায়ারকে দুই জায়গায় দুইবার এন্ট্রি করার প্রয়োজন এড়াতে।

বিদ্যমান `vendors` টেবিলের কলাম (আগে থেকেই আছে):

| Field | নোট |
|---|---|
| name, code | |
| vendor_type (SUPPLIER / SERVICE / BOTH) | Procurement-এর জন্য প্রাসঙ্গিক ভেন্ডর ফিল্টার করতে `vendor_type IN (SUPPLIER, BOTH)` ব্যবহার হবে |
| contact_name, phone, email, address | |
| country_of_origin | |
| tax_reference | TIN/BIN/Trade License-এর একটাকে ম্যাপ করা যায়, বাকিগুলোর জন্য নিচের নতুন কলাম লাগবে |
| status (ACTIVE / INACTIVE / BLACKLISTED) | নতুন প্রস্তাবিত "Approved status (Pending/Approved/Blacklisted)"-এর সাথে ওভারল্যাপ করে — নিচে দ্রষ্টব্য |
| notes | |

এই মডিউলের জন্য `vendors` টেবিলে **নতুন কলাম যোগ** করতে হবে:

| নতুন Field | নোট |
|---|---|
| bin, trade_license_no | `tax_reference`-এর পাশে; TIN হিসেবে `tax_reference`-ই ব্যবহার হবে |
| bank_id (FK), bank_branch, bank_account_no, bank_swift | `bank_id` এখন [Bank Master](01_Master_Data_Module.md#10-bank-master)-এর real FK, ফ্রি-টেক্সট bank_name না |
| supply_category (Fabric/Yarn/Trims/Chemical/Spare Parts/Service, multi-select) | `vendor_type`-এর থেকে আলাদা অক্ষ — `vendor_type` বলে "কী ধরনের সম্পর্ক" (Supplier/Service/Both), `supply_category` বলে "কী সাপ্লাই করে" |
| approval_status (Pending/Approved) | বিদ্যমান `status` (ACTIVE/INACTIVE/BLACKLISTED)-এর সাথে সাংঘর্ষিক নয়, আলাদা কনসার্ন — একটা "সক্রিয় কিনা", আরেকটা "যাচাই/অনুমোদিত কিনা" |
| default_currency, default_payment_type (FK → [Payment Type Master](01_Master_Data_Module.md#11-payment-type-master)) | `default_payment_terms` (কখন দিতে হবে, যেমন "Net 30") থেকে আলাদা — এটা "কীভাবে" দিতে হবে |
| rating | Supplier Performance Scorecard থেকে auto-link (বিস্তারিত [Procurement Module](03_Procurement_Module.md#15-supplier-performance-scorecard)-এ) |

Procurement মডিউলের সব জায়গায় (`Fabric Booking`, `RFQ`, `PO`, `GRN`) "Supplier"/"vendor_id" বলতে এই একই বিদ্যমান `Vendor` মডেলকেই বোঝাবে, real FK দিয়ে।

## 4. Item Department Master (Lookup List)

> **স্পষ্টীকরণ (২১ সেপ্টেম্বর ২০২৬): Item Department ≠ Department।** প্ল্যাটফর্মে ইতিমধ্যে সংগঠনের Department আছে (HR & Administration, IT, Finishing… — Factory-র আন্ডারে, Section/Production Line/Asset Location এগুলোকে রেফারেন্স করে)। Item Master-এর "Department" সেটা নয় — এটা লাইব্রেরির নিজস্ব অক্ষ: **কোথায় রাখা হয় ও কার কাছে চাওয়া হয়** (Yarn Store, Garments Store, General Store, Dyes Chemical Store…)। তাই আলাদা টেবিল `item_departments` (company-owned, Factory-বিহীন), Settings → Master Data → "Item departments"-এ মেইনটেইন হয়। `items.item_department_id` এটাকেই রেফারেন্স করে; সংগঠনের `departments` টেবিল অপরিবর্তিত।

- item_department_id, code, name — শুধু একটা সাধারণ Lookup List, কোনো User/Role-এর সাথে বাঁধা না
- **`default_item_type`** — নতুন ফিল্ড (গ্যাপ-ফিক্স); প্রতিটা Department এন্ট্রির সাথে একটা স্থায়ী item_type সাজেশন অ্যাট্রিবিউট থাকবে, যাতে Department রিনেম হলেও Item Entry-র সময় item_type auto-suggestion লজিক না ভাঙে (বিস্তারিত উপরে Section 2.6-এ)
- Item Master-এর `item_department_id` ফিল্ড এখান থেকে real FK দিয়ে রেফারেন্স করবে
- **গুরুত্বপূর্ণ নিয়ম:** Requisition ফর্মে Department dropdown সব সময় **সব Department** দেখাবে, যে User-ই লগইন করুক না কেন — বিস্তারিত [Floor/Store Requisition Module](04_Floor_Store_Requisition_Module.md)-এ
- **Approval Routing নোট:** কোন User কোন Department-এর "Head/Approver" তা এই Department Lookup List নির্ধারণ করে না — এই ম্যাপিং সম্পূর্ণভাবে বিদ্যমান Role/Permission প্ল্যাটফর্মে হ্যান্ডেল হবে (দেখুন [Shared Systems & Compliance](05_Shared_Systems_and_Compliance.md))। Department Lookup শুধু Item Classification আর Requisition Targeting-এর জন্য, Approval Hierarchy-এর জন্য না।

## 5. Store (Existing Warehouse Hierarchy Reuse)

> **সংশোধিত।**
>
> **কারণ:** আগের ড্রাফটে এখানে একটা নতুন, ফ্ল্যাট "Store Lookup List" প্রস্তাব করা হয়েছিল। রিভিউতে ধরা পড়েছে যে বিদ্যমান কোডবেসে (`app/Modules/Inventory`) ইতিমধ্যে একটা সম্পূর্ণ, কার্যকর হায়ারার্কি আছে — নতুন সমান্তরাল Store এনটিটি বানানো হবে না, এটাই রিইউজ হবে।

**হায়ারার্কি (বিদ্যমান, real FK):**
```
Company → Factory → Warehouse → Store → Bin
```
- `Factory` — একটা কোম্পানির একাধিক ফ্যাক্টরি থাকতে পারে (`app/Modules/Tenancy/Models/Factory.php`)
- `Warehouse` — প্রতিটা Factory-র নিজস্ব Warehouse(গুলো)
- `Store` — Warehouse-এর আন্ডারে (যেমন Yarn Store, Garments Store, General Store — এগুলো এখন `Store` রেকর্ড, আলাদা লুকআপ ভ্যালু না)
- `Bin` — Store-এর ভেতরে সবচেয়ে ছোট স্টক লোকেশন, ব্যালেন্স এই লেভেলেই রাখা হয়

**Item Master-এর জন্য প্রভাব:**
- Item Master-এর `store_id` এখন সরাসরি বিদ্যমান `Store` মডেলে real FK (নতুন কোনো টেবিল না)
- store_type (General / Bonded / Sub-store) — [Shared Systems — Bonded Warehouse](05_Shared_Systems_and_Compliance.md#3-bangladesh-rmg-compliance--bonded-warehouse--ud-up)-এর জন্য `Store`-এ কলাম **যোগ হয়ে গেছে** (as built, Phase 2); Bond/UD-UP-এর `bonded_stores` ফিল্টার এই কলামের ওপরেই চলে
- Legacy ফাইলে থাকা Store নামগুলো (Yarn Store, Garments Store, General Store...) migration-এর সময় বিদ্যমান `stores` টেবিলেই সিড হবে, নতুন টেবিলে না
- Department Lookup List (Section 4) অপরিবর্তিত থাকবে — Department-এর সমতুল্য কোনো বিদ্যমান এনটিটি কোডবেসে নেই, তাই এটা সত্যিই নতুন

**চূড়ান্ত:** Item Master-এর নিজস্ব `items` টেবিল (আলাদা মডিউল `app/Modules/ItemMaster`); `SparePart` মেইনটেন্যান্স স্টোরের ক্যাটালগ হিসেবে অপরিবর্তিত। Item-এর স্টক ব্যালেন্স/লেজার কোথায় থাকবে — Phase 1 (GRN Complete)-এ সিদ্ধান্ত, দেখুন [Inventory Module §16](02_Inventory_Module.md#16-relationship-with-existing-inventory-code)।

## 6. Order / Style / Cutting Program Master

- order_id, style_no, buyer, order_qty, ship_date
- style_id → BOM রেফারেন্স
- cutting_program_id → style_id, program_qty, cutting_date
- Status: Confirmed / In Progress / Closed / Cancelled

> এই মাস্টার অন্য মডিউলে (Merchandising/Order module) থাকলে সেখান থেকে reference নেওয়া হবে; না থাকলে এই স্কোপে minimal version তৈরি করতে হবে।

### 6.1 যেভাবে তৈরি হয়েছে (Phase 3B)

কোডবেসে Merchandising মডিউল নেই, তাই ন্যূনতম সংস্করণটাই তৈরি হয়েছে — `app/Modules/Production`:

| টেবিল | কী রাখে |
| --- | --- |
| `production_orders` | বায়ারের নিজের `order_no` (জেনারেট করা হয় না), buyer, order_qty, ship_date, status |
| `production_styles` | order-এর প্রতিটি স্টাইল ও তার পরিমাণ |
| `production_style_bom_lines` | প্রতি গার্মেন্টে কত লাগে (`consumption_per_unit`) + পরিকল্পিত `wastage_percent` |
| `production_cutting_programs` | `CP-{FACTORY}-{YYYY}-{SEQ}`, program_qty, cutting_date, status |

তিনটি সচেতন সিদ্ধান্ত:

- **টেবিলের নামে `production_` উপসর্গ।** খালি `orders` নামটা অ্যাপ্লিকেশনের অর্ধেক দাবি করতে পারত, আর পরে কেউ ওটা পারচেজ অর্ডার ভেবে বসত।
- **অর্ডার নম্বর টাইপ করা হয়, তৈরি হয় না।** ফ্যাক্টরি "H&M 4471-23" বলেই ডাকে; একই জিনিসের দ্বিতীয় পরিচয় বানিয়ে লাভ নেই। শুধু কাটিং প্রোগ্রামের নিজস্ব সিরিজ আছে।
- **BOM স্টাইলের সাথে, অর্ডারের সাথে নয়।** এক অর্ডারে কয়েকটি স্টাইল থাকে, প্রতিটির কনজাম্পশন আলাদা — "এই অর্ডারে কত কাপড় লাগবে" প্রশ্নের উত্তর স্টাইল ধরে ধরেই সম্ভব।

BOM পুরোটা একসাথে প্রতিস্থাপিত হয় (`ManageOrder::saveBom`), লাইন ধরে ধরে নয় — নাহলে রিকোয়ারমেন্ট হিসাব অর্ধেক বদলানো বিল পড়ে ফেলত। কাটিং প্রোগ্রামের যোগফল স্টাইলের পরিমাণ ছাড়াতে পারে না (বাতিল প্রোগ্রাম গোনা হয় না)।

**ইচ্ছাকৃতভাবে যা নেই:** কস্টিং, স্যাম্পলিং, শিপমেন্ট, প্যাকিং। আলাদা মার্চেন্ডাইজিং সিস্টেম এলে এই টেবিলগুলো তার রেফারেন্সে পরিণত হবে — সেটা সহজ রাখতেই এগুলো পাতলা।

## 7. UOM & Conversion Master

- uom_code, uom_name
- conversion_factor (যেমন 1 Roll = X Yard = Y Meter)
- item-wise default purchase UOM বনাম stock UOM
- PO তে purchase UOM ব্যবহার হবে, GRN/Inventory-তে stock UOM-এ auto-convert হবে

## 8. HS Code & Tax Master

- hs_code, description, duty_rate, vat_rate
- item_master-এ hs_code (optional, মূলত Import আইটেমের জন্য) যুক্ত হবে
- বিস্তারিত ব্যবহার: [Shared Systems & Compliance](05_Shared_Systems_and_Compliance.md) → Bonded Warehouse/UD-UP সেকশন

## 9. Item Selection UX নোট (গ্যাপ-ফিক্স)

বড় Department-এ (যেমন legacy ডাটায় "General Store"-এ ৫,০০০+ আইটেম) সব আইটেম একসাথে ড্রপডাউনে দেখানো বাস্তবসম্মত না। Item Selection ফিল্ডে **Search/Typeahead** থাকা আবশ্যক (item_name/item_code দিয়ে সার্চ), এবং Category/Sub Category/Detail Category দিয়ে আরও ফিল্টার করার অপশন থাকা উচিত — এটা [Floor/Store Requisition](04_Floor_Store_Requisition_Module.md) ও Procurement-এর Item Selection উভয় জায়গাতেই প্রযোজ্য।

## 10. Bank Master

> **নতুন — Accounts-এর জন্য প্রস্তুতি। কেন এখনই:** Finance টিমের GL/Chart of Accounts sign-off এখনো বাকি, কিন্তু সেটার জন্য অপেক্ষা না করে এখনই বেসিক Accounts মাস্টার ডাটা বানিয়ে রাখা হচ্ছে — sign-off আসলে শুধু ডাটা (real account code ইত্যাদি) বসাতে হবে, কাঠামো/কোড আগে থেকেই তৈরি থাকবে (বিস্তারিত [Shared Systems §5](05_Shared_Systems_and_Compliance.md#5-implementing-with-these-gaps-open)-এ)।

- bank_code, bank_name
- is_active
- Admin দ্বারা Add/Edit/Deactivate

**প্রভাব:** Vendor Master (Section 3)-এর Bank Details এখন ফ্রি-টেক্সট `bank_name` না, বরং এই লাইব্রেরি থেকে `bank_id` (real FK) — Branch/A-C No./Swift এখনো Vendor রেকর্ডেই থাকবে (এগুলো ভেন্ডর-স্পেসিফিক, লাইব্রেরিতে না)।

## 11. Payment Type Master

*(নতুন — Accounts-এর জন্য প্রস্তুতি)*

"টাকা কীভাবে দেওয়া হচ্ছে/নেওয়া হচ্ছে" — এটা Vendor Master-এর `default_payment_terms` (যেমন "Net 30", কখন দিতে হবে) থেকে **আলাদা জিনিস**, দুটো গুলিয়ে ফেলা যাবে না।

- payment_type_code, name (উদাহরণ: Cash, Bank Transfer, Cheque, LC, Mobile Banking, Credit)
- is_active

**ব্যবহার:** PO, Advance Payment, Invoice Settlement-এ পেমেন্ট রেকর্ড করার সময় এই লাইব্রেরি থেকে সিলেক্ট হবে।

## 12. GL Account Master / Chart of Accounts Skeleton

*(নতুন — Accounts-এর জন্য প্রস্তুতি)*

Finance টিমের চূড়ান্ত Chart of Accounts এখনো নেই, কিন্তু কাঠামোটা এখনই বানিয়ে রাখা যায় যাতে GL পোস্টিং কোড লেখা শুরু করা যায়:

- account_code, account_name
- account_category (Asset / Liability / Income / Expense / Equity — স্ট্যান্ডার্ড ৫ ক্যাটাগরি)
- parent_account_id (nullable, self-FK — হায়ারার্কি সাপোর্টের জন্য, যেমন "Stock A/C" একটা প্যারেন্টের আন্ডারে থাকতে পারে)
- is_active

**ব্যবহার:** [Shared Systems §2](05_Shared_Systems_and_Compliance.md#2-accounting--gl-integration-high-level)-এর Event → Debit/Credit ম্যাপিং টেবিল এখন থেকে এই লাইব্রেরির `account_id`-কে real FK দিয়ে রেফারেন্স করবে, হার্ডকোড অ্যাকাউন্ট কোড না। Finance sign-off আসলে প্লেসহোল্ডার/Suspense অ্যাকাউন্টের বদলে real account_code/name বসানো হবে — এটা তখন শুধু একটা **ডাটা আপডেট**, কোনো কোড/মাইগ্রেশন বদলাতে হবে না।

---

**পরবর্তী:** [Inventory Module](02_Inventory_Module.md) | [Procurement Module](03_Procurement_Module.md) | [Floor/Store Requisition](04_Floor_Store_Requisition_Module.md)
