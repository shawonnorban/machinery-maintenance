# Pre-Budget — প্ল্যান (Knit Woven + Accessories Pre-Budget)

> **অবস্থা:** প্ল্যান চূড়ান্ত, কাজ শুরু হয়নি · **তারিখ:** ২০২৬-১১-১৯ · **উৎস:** Norban ERP-এর Knit Woven আর Accessories Pre-Budget
>
> **⬅ ফিরে যান:** [Knit Woven](00_Index.md) · [Accessories Pre-Budget](../34_Accessories_Pre_Budget/00_Index.md)

Norban-এর Knit Woven আর Accessories Pre-Budget মিলে AnnotechERP-এ একটা **Pre-Budget** মডিউল হবে, সাথে তার নিজের **Master Data** মডিউল। Norban-এর এলোমেলো দিকগুলো (১০টা বিষয়) ব্যবহারকারীর সাথে একটা একটা করে আলোচনা করে ঠিক করা হয়েছে। নিচে সেই সিদ্ধান্তগুলো আর তা থেকে বানানোর পরিকল্পনা।

---

## ১. এবারের কাজের সীমা

**এখন বানানো হবে** (সাইডবারে নতুন সেকশন **PRE BUDGET**):

| মডিউল | স্ক্রিন |
|---|---|
| **Pre-Budget** | All Requisition · Add Requisition · Draft Requisition · Create Budget · Knit/Dying Costing · Stock Yarn Use · Stock Yarn Declaration · Budget Finalized · Accessories Budget Pending · BOM List |
| **Master Data** | Accessories Item · Yarn Type · Wash Type · Additional Process · GSM · Sustainability · Order Type |

সাথে Requisition-এর **Column Setup** (Order Summary-র মতো), যা থাকবে Pre-Budget-এর Setup-এ।

**পরে, ভিন্নভাবে:** Budget Status, Budget Report, Yarn Report, Short Purchase, Excess Purchase, Audit Log স্ক্রিন, Accessories Report। এগুলোর নকশা পরে আলাদা আলোচনায় ঠিক হবে। তবে বাজেটের প্রতিটা বদল এখন থেকেই Audit Log-এ লেখা হবে, যাতে পরের রিপোর্টে ইতিহাস পাওয়া যায়।

---

## ২. সিদ্ধান্ত (Norban-এর ১০টা বিষয়)

| # | Norban-এ যা ছিল | সিদ্ধান্ত |
|---|---|---|
| ১ | Knit আর Woven দুটো হুবহু একই টেবিল | **একটা Requisition টেবিল**, Type ঘর (Knit = কেজি, Woven = গজ)। Woven বাছলে সুতার ঘর (Yarn Type, Yarn Count, Composition) লুকানো থাকবে; তার জায়গায় Fabric Mill, Width আর গজের হিসাব। |
| ২ | Requisition-এ Order Summary-র তথ্য আবার হাতে লেখা | **Requisition আর Order Summary আলাদা থাকবে।** Requisition হাতে লেখা হবে, Order Summary থেকে টানা হবে না। |
| ৩ | বাজেট একটা JSON-এ ছবির মতো রাখা; revision দুই রকম | বাজেট **হেডার + স্টাইল + লাইন + সাপ্লায়ারের দাম**, আলাদা টেবিলে। লাইন **Yarn Count + Composition** অনুযায়ী। Finalize-এর আগে Requisition বদলালে বাজেট **নিজে নিজে হালনাগাদ** হবে। অনুমোদনের আগে ইচ্ছামতো বদলানো যাবে (Audit Log-এ থাকবে), অনুমোদনের পর **শুধু Revise** (R1, R2…)। |
| ৪ | এক ক্লিকে Store, Costing, Accessories-এ পাঠানো; ৫–৬টা অবস্থা | পুরো প্রি-বাজেট **একজন মার্চেন্ডাইজার** করবেন, কোনো বিভাগে পাঠানো নেই। বাজেটের পাতায় **চেকলিস্ট** থাকবে, আর **সব অংশ সম্পূর্ণ হলে তবেই Finalize**। Store-এর লোক শুধু Stock Yarn Use আর Stock Yarn Declaration দেখবেন ও পূরণ করবেন। |
| ৫ | Store-এর স্টক হাতে লেখা | **স্টকের হিসাব আলাদা**, এই মডিউলের ভেতরে। AnnotechERP-এর Yarn Store-এর (15/03) সাথে যুক্ত না। মার্চেন্ডাইজার আর Store দুজনেই পূরণ করতে পারবেন। |
| ৬ | Short/Excess Purchase-এ কেনা পরিমাণ হাতে লেখা | রিপোর্টের সাথে **পরে** ঠিক হবে। |
| ৭ | তিনটা আলাদা অনুমোদন ব্যবস্থা, কাগজে সই | **Approval Center** দিয়ে, তিন ধাপে: **Merchandising Head → CFO → MD**। অঙ্ক অনুযায়ী ধাপ বদলাবে না, কাগজে সই লাগবে না। Reject হলে মন্তব্যসহ মার্চেন্ডাইজারের কাছে ফেরত। |
| ৮ | BOM-এর কপি টেবিল, group নম্বর দিয়ে জোড়া, অব্যবহৃত Accessories Requisition টেবিল | **এক BOM-এ এক বা একাধিক স্টাইল**, লাইন একবারই লেখা। কোনো স্টাইলের **Consumption আলাদা** দেওয়া যাবে। BOM-এর মোট খরচ নিজে নিজে বাজেটের **Accessories cost**-এ বসবে। BOM-এর আলাদা অবস্থা নেই: বাজেটের সাথে লক হয়, Revise হয়। কপি টেবিল আর পুরনো টেবিল বানানো হবে না। |
| ৯ | Accessories-এর দুটো প্রায় একই তালিকা; সব মাস্টার Norban-এর নিজের | AnnotechERP-এ যা আছে সেখান থেকে নেওয়া হবে (নিচে ৭ নম্বর অংশে)। **GSM, Sustainability, Order Type: Pre-Budget-এর নিজের তালিকা।** Accessories Item আর Name মিলে **একটা তালিকা**। **Norban-এর আসল তালিকাগুলো তুলে আনা হবে।** |
| ১০ | ফর্মুলা কোডে, ৬–৭ বার বদলাতে হয়েছে | Requisition-এর **Column Setup** (ফর্মুলা বদলানো, নতুন কলাম), Order Summary-র একই ইঞ্জিন দিয়ে। Woven-এর ফর্মুলা Knit-এর মতোই, তবে গজে। বাজেটের **Need to Purchase উপরে পুরো কেজি / গজে গোল** হবে। |

---

## ৩. কাজের ধারা

```
Add Requisition ──► All Requisition ──► Create Budget ──► চেকলিস্ট ──────────────► Finalize ──► Approval Center ──► অনুমোদিত (লক)
 (অর্ধেক হলে        (Complete করা)       (সেভ করলেই        ☐ Knit/Dying Costing       (তিনটাই ☑     Merch Head          │
  Draft-এ)                               PB-নম্বর বসে)      ☐ Stock Yarn Use            হলে চালু)     → CFO → MD          ▼
                                                            ☐ Accessories BOM                                       Revise → R1, R2…
                                                                (সব স্টাইলের)                  Reject ──► খসড়ায় ফেরত (মন্তব্যসহ)
```

**বাজেটের অবস্থা মাত্র চারটা:** খসড়া · অনুমোদনের অপেক্ষায় · অনুমোদিত · ফেরত এসেছে।

চেকলিস্টের ☑ কোনো আলাদা অবস্থা না, বাজেটের পাতায় দেখা যায়:
- **Knit/Dying Costing:** খরচ লিখে "সম্পূর্ণ" চাপলে ☑।
- **Stock Yarn Use:** লট দিয়ে "সম্পূর্ণ" চাপলে ☑। স্টক থেকে কিছু না নিলে "স্টক লাগবে না" চাপলেও ☑।
- **Accessories BOM:** বাজেটের প্রতিটা স্টাইল কোনো BOM-এ থাকলে নিজে নিজে ☑।

**খসড়া অবস্থায় বদল:** খসড়া (বা ফেরত আসা) বাজেটের কোনো অংশ বদলালে, যেমন স্টাইল যোগ হলো বা Requisition-এর পরিমাণ বদলাল, যে অংশের হিসাব তার উপর নির্ভর করে তার ☑ চলে যায়। সেটা আবার দেখে "সম্পূর্ণ" করতে হয়।

**Revise:** নতুন সংস্করণে আগের সব অংশ কপি হয়ে আসে, খসড়া হিসেবে। অনুমোদন না হওয়া পর্যন্ত আগের সংস্করণটাই চালু থাকে।

---

## ৪. কে কী করবেন (অনুমতি)

| রোল | যা করতে পারেন |
|---|---|
| মার্চেন্ডাইজার | Requisition, বাজেট, Costing, Stock, BOM, Finalize: সব |
| Store | শুধু Stock Yarn Use আর Stock Yarn Declaration দেখা ও পূরণ করা |
| Merchandising Head, CFO, MD | Approval Center-এ নিজের ধাপে Approve / Reject; Budget Finalized দেখা |
| Owner / Factory Admin | Column Setup (Order Summary-র মতো আলাদা অনুমতি), Master Data |

মার্চেন্ডাইজার কোন বায়ারের Requisition আর বাজেট দেখবেন, তা খোলা প্রশ্ন (১০ নম্বর অংশে)।

---

## ৫. স্ক্রিনগুলো

| স্ক্রিন | কী করে |
|---|---|
| **All Requisition** | Excel-এর মতো শিট (Order Summary-র মতো): Type দিয়ে ফিল্টার, প্রতি কলামে হেডার ফিল্টার, Bulk Edit, Copy Row, Repeat Order, কলাম আটকানো, লিখে খোঁজার dropdown, মোট-এর সারি। টিক দিয়ে **Create Budget**। |
| **Add Requisition** | কয়েকটা সারি একসাথে লেখা, সরাসরি হিসাব। Type বাছলে কলাম বদলায়। Save Draft অথবা Complete। CAD ছবি আপলোড। |
| **Draft Requisition** | অর্ধেক লেখা Requisition: Open, Delete। |
| **Create Budget** | বাছাই করা Requisition থেকে বাজেট। Yarn ভাগ (Count + Composition লাইন) আর Woven ভাগ (কাপড়ের লাইন): Total Req, Use from Stock, Need to Purchase, সাপ্লায়ারের দামের তুলনা, বাছাই করা সাপ্লায়ার, Value। খরচের অংশ, Grand FOB। Add Style / Remove Style। উপরে চেকলিস্ট আর Finalize বোতাম। Print। |
| **Knit/Dying Costing** | বাজেটের তালিকা (বাকি / সম্পূর্ণ)। খুললে Dollar Rate; প্রতি fabrication-এ Knit, Dye, Yarn Dyeing, AOP রেট; প্রতি স্টাইলে Embellishment আর CM রেট; নোট। "সম্পূর্ণ"। |
| **Stock Yarn Use** | বাজেটের তালিকা (বাকি / সম্পূর্ণ)। খুললে প্রতিটা বাজেট-লাইনে স্টকের লট: উৎস, Purchase / Stock Contract, মিল, Count, Composition, Origin, LC, Lot, পরিমাণ, একক, দাম, Value। কোনো লাইনের লট সেই লাইনের Total Req ছাড়াতে পারবে না। "সম্পূর্ণ" বা "স্টক লাগবে না"। |
| **Stock Yarn Declaration** | কোন বাজেটে কোন স্টকের সুতা ব্যবহার হলো, তার ঘোষণাপত্র (প্রিন্টযোগ্য)। |
| **Budget Finalized** | Finalize হওয়া বাজেট, অনুমোদনের ধাপসহ। অনুমোদনকারী এখান থেকেও Approve / Reject করতে পারবেন। Revise, Print। |
| **Accessories Budget Pending** | যে বাজেটের BOM বাকি (বাকি / আংশিক / সম্পূর্ণ)। খুলে স্টাইল বেছে BOM বানানো। |
| **BOM List** | সব BOM, খুলে দেখা ও বদলানো (বাজেট খসড়া থাকলে), প্রিন্ট। |

শিটের যে আচরণ Order Summary-তে ঠিক করা হয়েছে, সব এখানেও থাকবে:
- Bulk Edit মোড, উপরে KPI কার্ড নেই।
- ভেতরে পুরো সংখ্যা থাকে, দশমিক শুধু দেখানোর জন্য।
- একসাথে দুজন একই সারি বদলালে সতর্কবার্তা, আর নিজের বদলগুলো হারায় না।
- কলামের মাপ, চিকন বর্ডার, Excel-এর রং।

---

## ৬. ফর্মুলা (Column Setup-এর শুরুর মান)

| কলাম | Knit (কেজি) | Woven (গজ) |
|---|---|---|
| Order Qty | `[Quantity (Ticket)] * [PAC]` | একই |
| FOB Value | `[Order Qty] * [FOB Price]` | একই |
| Qty with % | `[Quantity (PCS)] * (1 + [Cutting %] / 100)` | একই |
| Consumption with Loss | `[CAD Consumption] * (1 + [Process Loss %] / 100)` (কেজি/ডজন) | একই সূত্র, গজ/ডজন |
| Bulk | `[Consumption with Loss] / 12 * [Feeding %] / 100 * [Qty with %]` | একই সূত্র, গজে |
| Total Req | `[Sample] + [Bulk]` | একই সূত্র, গজে |

**Column Setup-এ প্রতিটা কলামের সেটিং:**
- কোন Type-এ দেখাবে: Knit, Woven, দুটোই।
- বাকিগুলো Order Summary-র মতো: গ্রুপ, ধরন, তালিকা, দশমিক, মাপ, vertical, আটকানো, রঙের নিয়ম, লেখার রং, মোট, রোল অনুযায়ী দেখা / বদলানো।

**ফর্মুলা বদলালে:**
- খসড়া আর ফেরত আসা বাজেটের সব Requisition আবার হিসাব হয়, আর ইতিহাসে লেখা থাকে।
- অনুমোদিত বাজেটের Requisition বদলায় না। ওগুলো অনুমোদনের সময়ের হিসাবেই থাকে।

**বাজেটের হিসাব (কোডে):**
- Total Req = লাইনের সব Requisition-এর Total Req-এর যোগফল।
- Need to Purchase = Total Req − Use from Stock, **উপরে পুরো কেজি / গজে গোল**।
- Value = Need to Purchase × বাছাই করা সাপ্লায়ারের দাম।
- খরচ: সুতা, Knit, Dye, Yarn Dyeing, AOP, Embellishment, CM আর Accessories (BOM থেকে)। তারপর Grand FOB-এর সাথে তুলনা।

---

## ৭. মাস্টার ডাটা

**AnnotechERP থেকে নেওয়া হবে:**

| ঘর | মাস্টার |
|---|---|
| Factory | Factory |
| Buyer | Buyer (Merchandise Desk) |
| Brand, Season, Dept | সিস্টেম Lookup |
| Item | Garment Item |
| Fabric Type | Fabric Type |
| Fabric Composition, Composition | Fiber Composition |
| Parts | Garment Parts |
| Yarn Count | Knit Production-এর Yarn Count |
| Unit | UOM |
| Supplier, Yarn Mill, Fabric Mill | Supplier Master |

**Pre-Budget-এর নিজের (Master Data মডিউলে), Norban থেকে তুলে আনা:**
- **Accessories Item:** নাম, ক্যাটাগরি, ডিফল্ট Unit। Norban-এর ২২৬টা Item আর তাদের Name মিলিয়ে একটা তালিকা।
- **Yarn Type, Wash Type, Additional Process।**
- **GSM, Sustainability, Order Type:** Order Summary-র তালিকা থেকে আলাদা।

**তুলে আনার নিয়ম:**
- Norban-এর Requisition-এ যেসব Buyer, Brand, Fabric Type ইত্যাদি আছে, সেগুলো AnnotechERP-এর মাস্টারে নেই এমন হলে তালিকা দেখানো হবে। কোনটা যোগ হবে, তা ব্যবহারকারী ঠিক করবেন।
- আগের সিদ্ধান্তমতো Norban-এর Requisition আর বাজেটের ডাটা আনা হবে না, শুধু মাস্টার।

---

## ৮. ডাটা মডেল (নতুন টেবিল, `pb_` দিয়ে শুরু)

| টেবিল | কী রাখে |
|---|---|
| `pb_groups`, `pb_columns`, `pb_column_events` | Requisition-এর Column Setup (Order Summary-র `os_*`-এর মতো) + কোন Type-এ দেখাবে |
| `pb_requisitions` | Type, স্থিতি (Draft / Complete), কলামের মান (jsonb, Order Summary-র মতো), version, CAD ছবি |
| `pb_budgets` | Budget No (PB-…), revision নম্বর, আগের সংস্করণ, Buyer, Contract No, অবস্থা, চেকলিস্টের তিনটা ☑ (কে, কখন), খরচের অংশ, Dollar Rate, নোট |
| `pb_budget_requisitions` | কোন বাজেটে কোন Requisition |
| `pb_budget_lines` | ভাগ (Yarn / Woven), Yarn Count + Composition (Woven-এ কাপড়), Total Req, Use from Stock, Need to Purchase, বাছাই করা সাপ্লায়ার, দাম, Value |
| `pb_budget_line_prices` | প্রতি লাইনে প্রতিটা সাপ্লায়ারের দাম (তুলনা) |
| `pb_budget_costs` | Costing-এর রেট: প্রতি fabrication-এ Knit / Dye / Yarn Dyeing / AOP, প্রতি স্টাইলে Embellishment / CM |
| `pb_stock_lots` | Stock Yarn Use-এর লট (Declaration এখান থেকেই) |
| `pb_boms`, `pb_bom_styles`, `pb_bom_lines`, `pb_bom_line_styles` | BOM; তার স্টাইল আর প্রতি স্টাইলের পরিমাণ; লাইন (Item, Supplier, Nominated, Consumption/pc, Extra %, Unit, Rate); কোনো স্টাইলের আলাদা Consumption |
| `pb_accessory_items` | Accessories Item মাস্টার |
| `pb_changes` | Audit Log: কোন বাজেট বা Requisition, কোন ঘর, আগের মান, নতুন মান, কে, কখন |

**নিজের তালিকা:** `pb_list_values` — একটা টেবিল, `list` ঘর দিয়ে আলাদা: YARN_TYPE, WASH_TYPE, ADDITIONAL_PROCESS, GSM, SUSTAINABILITY, ORDER_TYPE। (প্রথমে সিস্টেম Lookup ভাবা হয়েছিল; নিজের টেবিলে রাখলে Master Data মডিউলের স্ক্রিনেই সব থাকে আর Access Control-এর Lookup তালিকায় ভিড় হয় না।)

**সংস্করণ:** Revise হলে `pb_budgets`-এ নতুন সারি হয়: একই Budget No, revision + 1। লাইন, খরচ, লট আর BOM নতুন সংস্করণে কপি হয়। পুরনোটা শুধু দেখা যায়।

**URL-এ নম্বর:** বাজেট `/pre-budget/budgets/PB-CNA-1126-0001`, revision `…-R1`। Requisition-এর নম্বর দিয়ে খোলা হবে, ULID দিয়ে না।

---

## ৯. বানানোর ধাপ

| ধাপ | কাজ | শেষে যাচাই |
|---|---|---|
| ১ | মডিউলের কাঠামো, সাইডবারে PRE BUDGET সেকশন, অনুমতি, Master Data (চারটা Lookup + Accessories Item + GSM / Sustainability / Order Type), Norban থেকে মাস্টার তুলে আনা | মাস্টারের স্ক্রিন, তুলে আনা তালিকা |
| ২ | Requisition: Column Setup (Order Summary-র ইঞ্জিন ভাগাভাগি করে), Knit / Woven কলাম, All / Add / Draft Requisition | ফর্মুলা Norban-এর সাথে মেলানো, শিটের আচরণ |
| ৩ | Create Budget: লাইন ভাগ, সাপ্লায়ারের দাম, Need to Purchase গোল করা, Add / Remove Style, Requisition বদলালে হালনাগাদ, Print | Norban-এর একই Requisition থেকে একই বাজেট-অঙ্ক |
| ৪ | Knit/Dying Costing আর Stock Yarn Use (+ Declaration), চেকলিস্ট, Store-এর অনুমতি | Store লগইনে শুধু দুটো স্ক্রিন |
| ৫ | Accessories Budget Pending, BOM (একাধিক স্টাইল, স্টাইলের আলাদা Consumption), BOM List, বাজেটে Accessories cost | BOM-এর খরচ বাজেটে |
| ৬ | Finalize → Approval Center (তিন ধাপ), Reject → ফেরত, Revise R1, Budget Finalized | পুরো ধারা ব্রাউজারে, তিন অনুমোদনকারী দিয়ে |

প্রতিটা ধাপের শেষে:
- ফিচার টেস্ট লেখা হবে।
- নিজে ব্রাউজারে চালিয়ে দেখা হবে (ui.mjs)। নমুনা ডাটা dev-এ রেখে দেওয়া হবে।
- এই ডকুমেন্টের As-built অংশ হালনাগাদ হবে।

---

## ১০. খোলা প্রশ্নের উত্তর (২০২৬-১১-১৯)

- **কে কোন বায়ারের Requisition আর বাজেট দেখবেন:** Order Summary-র মতো **Merchandising Team** অনুযায়ী। মার্চেন্ডাইজার নিজের বায়ার, Team Head তার টিমের, Department Head তার ডিপার্টমেন্টের সব টিমের।
- **Budget No-এর গঠন:** Norban-এর মতো: `PB-<বায়ারের কোড>-<মাস বছর>-<ক্রম>`, যেমন PB-CNA-1026-0018।
- **অনুমোদিত বাজেটের Requisition:** **লক** হবে। বদলাতে হলে আগে বাজেট Revise করতে হবে।
- **Woven-এর কাপড়ের লাইন:** Norban-এর মতো **Style + Composition + Fabrication** অনুযায়ী। প্রতিটা স্টাইলের কাপড় আলাদা লাইন, একই Fabrication দুই স্টাইলে থাকলেও দুই লাইন। লাইনে দেখাবে Total Fabric Qty (গজ)। ব্যবহারকারীর দেখানো নমুনা: 2261582 আর 2261513, একই 261243 Micro Fleece, তবু আলাদা লাইন।

## ১১. As-built নোট

**ধাপ ১ ✅ (২০২৬-১১-১৯): কাঠামো, সাইডবার, অনুমতি, Master Data**
- মডিউল `app/Modules/PreBudget`: টেবিল `pb_list_values`, `pb_accessory_items` (মাইগ্রেশন `2027_04_03_000100`)। স্ক্রিন-কিটের resource: `pb-accessory-items`, `pb-yarn-types`, `pb-wash-types`, `pb-additional-processes`, `pb-gsms`, `pb-sustainabilities`, `pb-order-types`।
- কোনো মান মোছা যায় না, Status দিয়ে বন্ধ করা হয়। নাম নিজের তালিকায় একবারই থাকে (বড়-ছোট হাতের অক্ষর আলাদা ধরা হয় না)। Accessories Item-এর কোড ফাঁকা রাখলে পরের `ACC-0001` নম্বর বসে, আর একক UOM মাস্টার থেকে।
- সাইডবার: নতুন সেকশন **PRE BUDGET** (Sales & Merchandising-এর পরে), মডিউল `PRE_BUDGET` (স্ক্রিন ধাপ ২ থেকে) আর `PRE_BUDGET_MASTER` (৭টা স্ক্রিন, `/pre-budget-master/*`)।
- অনুমতি: `prebudget.view`, `view_all`, `create`, `update`, `delete`, `stock`, `columns.manage`, `master.view`, `master.manage`।
  - FACTORY_ADMIN পায় সব।
  - FACTORY_MANAGER পায় view, view_all আর master.view।
  - STORE_MANAGER আর STOREKEEPER পায় শুধু `prebudget.stock`।
- Norban থেকে তুলে আনা: `Database/Data/norban_masters.json`। এতে আছে Yarn Type ৩৬৫, Wash Type ২৩, Additional Process ৮৪, GSM ৩৭, Sustainability ৩৯, Order Type ১৩ (Norban-এ ২৬টা, নকল নাম বাদ গেছে) আর Accessories Item ২২৬।
  - এর মধ্যে ৪৮টা Item-এর ডিফল্ট একক Norban-এর BOM-এ সবচেয়ে বেশি ব্যবহার হওয়া একক থেকে নেওয়া (CON হয়েছে CONE, GROSS হয়েছে GRS)।
  - এগুলো `PreBudgetMasterSeed` দিয়ে `erp:seed-masters`-এ ঢোকে। dev-এর DAL কোম্পানিতে ঢোকানো হয়েছে।
- টেস্ট: `tests/Feature/PreBudget/PreBudgetMasterTest.php` (৪টা)।
- জানা সমস্যা (আগে থেকেই ছিল): একাধিক সারি থাকলে RecordsGrid-এর মাস্টার পাতায় React "unique key" সতর্কবার্তা আসে। Material Procurement-এর Accessories Master-এও একই সতর্কবার্তা আসে।

**ধাপ ২ ✅ (২০২৬-১১-১৯): Requisition আর Column Setup**
- টেবিল: `pb_groups`, `pb_columns` (`applies_to` = KNIT / WOVEN / দুটোই), `pb_requisitions` (`entry_type`, `status` DRAFT/COMPLETE, `serial_no`, values jsonb, version), `pb_changes`, `pb_column_events` (মাইগ্রেশন `2027_04_03_000200`)।
- শুরুর ৪৮টা কলাম (`Support/RequisitionCatalog`): Norban-এর ঘর আর ফর্মুলা। Yarn Type, Yarn Count আর Yarn Composition শুধু Knit-এর; Fabric Mill আর Width শুধু Woven-এর।
  - Woven সারিতে সুতার ঘর রাখা যায় না: পাঠালেও সার্ভার মুছে দেয়, আর শিটে ঘরটা ডোরাকাটা ও বন্ধ থাকে।
  - Norban-এর রিকুইজিশনের "Stock Yarn" কলাম রাখা হয়নি। স্টক এখন Stock Yarn Use-এ দেওয়া হয় (ধাপ ৪)।
- স্ক্রিন: `/pre-budget/all-requisition`, `add-requisition`, `draft-requisition`, `column-setup`।
  - Add Requisition-এ দুটো বোতাম: **Save Draft** আর **Complete**।
  - Complete-এ সারির Entry Type অনুযায়ী সব বাধ্যতামূলক ঘর লাগে। কিছু বাকি থাকলে বার্তা আসে, যেমন "Row 1 (2261582): fill Artwork to complete it"। S/N বসে Complete-এর সময়।
  - Draft Requisition-এ টিক দিয়ে Complete করা যায়।
- শিট: Order Summary-র `sheet.jsx`-এ এখন একটা `config` prop আছে। এতে থাকে listPath, artwork-এর ঠিকানা, `applies`, `saveModes` আর `listActions`। Order Summary আগের মতোই চলে, তার নিজের মানই ডিফল্ট।
- Column Setup: Order Summary-র স্ক্রিনই ব্যবহার হয়, সাথে "Entry Type" বাছাই (Knit / Woven / দুটোই)।
- একসাথে দুটো প্রথম পেজ খুললে শুরুর কলাম দুবার তৈরি হতো। এখন company-row লক দিয়ে আটকানো, Order Summary-তেও।
- টেস্ট: `tests/Feature/PreBudget/RequisitionTest.php` (৪টা)।
- **পরের বদলগুলো, ব্যবহারকারীর কথামতো (২০২৬-১১-১৯):**
  - Fabrication নিজে নিজে তৈরি হয়: Fabric Type + Fabric Composition + GSM। এটা একটা ফর্মুলা, মাইগ্রেশন `000300`।
  - তালিকা আর Add পাতায় সারির উচ্চতা ৬০px।
  - ডিফল্ট কলাম: Norban-এর ২১টা আর Entry Type।
  - ড্রপডাউনে তালিকার বাইরের মান পেস্ট হয় না, সতর্কবার্তা আসে।
  - সারির কপি বোতাম: শুধু কপি করে, Ctrl+V চাপলে পুরো সারি বসে, আর্টওয়ার্কসহ।
- **Draft Requisition, Norban-এর নিয়মে (২০২৬-১১-১৯):**
  - ড্রাফট মানে পুরো Add Requisition পাতা, যেমন লেখা হয়েছে তেমনই (`pb_requisition_drafts`, মাইগ্রেশন `000400`/`000500`)।
  - সেভ হয় তিনভাবে: বদল থাকলে প্রতি ২ মিনিটে, Save Draft চাপলে, আর পাতা ছাড়লে (pagehide-এ keepalive fetch, `/api/pre-budget/drafts`)।
  - ঠিকানায় ড্রাফটের নম্বর বসে (`?draft=N`), তাই রিলোড করলে একই ড্রাফট খোলে। উপরে লেখা থাকে "Draft D-N saved HH:MM"।
  - Draft Requisition পাতা একটা তালিকা: D-নম্বর, কবে সেভ, কয় সারি, Entry Type, বায়ার, স্টাইল, Open আর Delete।
  - সবার ড্রাফট দেখার অনুমতি যাঁর আছে (prebudget.view_all), তাঁর তালিকায় "Saved by" কলাম থাকে। বাকিরা শুধু নিজের ড্রাফট দেখেন।
  - Complete করলে রিকুইজিশন সেভ হয় আর ড্রাফট সরে যায় (soft delete), তাই সেই নম্বর আর কখনো দেওয়া হয় না।
  - অন্য ট্যাবে ড্রাফট Complete বা মুছে ফেলা হলে এই পাতা জানিয়ে দেয় (409), আর সেই ড্রাফটে সেভ থেমে যায়।
  - আগের DRAFT-অবস্থার রিকুইজিশন সারিগুলো D-1-এ সরানো হয়েছে।
  - টেস্ট: RequisitionTest-এ এখন ৫টা টেস্ট।

**ধাপ ৩ ✅ (২০২৬-১১-১৯): Create Budget**
- টেবিল: `pb_budgets` (budget_no, budget_seq, revision_no, parent_id, status, buyer, Contract No, Discount %, suppliers {YARN, WOVEN}, version), `pb_budget_requisitions`, `pb_budget_lines`। শেষেরটায় শুধু লাইনে যা লেখা হয়: Need, PI Qty, Price, সাপ্লায়ারের দাম, শর্টেজের সিদ্ধান্ত আর মন্তব্য (মাইগ্রেশন `000600`)।
- লাইনগুলো রাখা হয় না। প্রতিবার Requisition থেকে হিসাব হয় (`Support/BudgetSheet`)।
  - Knit লাইন: Yarn Count + Composition অনুযায়ী।
  - Woven লাইন: Style + Fabric Composition + Fabrication অনুযায়ী।
  - তাই খসড়া অবস্থায় Requisition বদলালে বাজেটও সাথে সাথে বদলায়।
- Norban-এর হিসাব:
  - Need to Purchase = Total Req − স্টক, উপরে পুরো কেজি বা গজে গোল।
  - PI Qty না লিখলে Need-ই ধরা হয়।
  - Total Stock/Now Purchase = স্টক + PI।
  - Excess/Shortage = Total Stock/Now Purchase − Total Req। শর্টেজে Purchase বাছলে Short Purchase Value যোগ হয়।
  - Price না লিখলে সবচেয়ে কম সাপ্লায়ার-দাম ধরা হয়।
  - PI Value = PI Qty × Price।
  - Grand Total = PI Value + স্টকের মূল্য + Short Purchase Value। এটাই খরচের তালিকায় Yarn Cost।
- পাতায় আরও থাকে:
  - Item Wise Summary, Fabric Type / Fabrication summary, Delivery Date summary।
  - Gross FOB, Discount/TPA %, Net FOB, Avg FOB।
  - খরচের ভাগ। এর মধ্যে Knitting, Dyeing, Embellishment, CM আর Accessories এখন ০, ধাপ ৪ আর ৫-এ আসবে। % হিসাব হয় Net FOB-এর উপর।
- Budget No তৈরি হয় সাথে সাথে: `PB-<বায়ারের কোড>-<MMYY>-<ক্রম>`। ক্রমিক নম্বর কখনো আবার ব্যবহার হয় না (soft delete)।
- নিয়ম:
  - একটা বাজেটে শুধু একজন বায়ারের রিকুইজিশন থাকে।
  - একটা রিকুইজিশন একবারে একটাই বাজেটে থাকতে পারে।
  - বাজেটে থাকা রিকুইজিশন মোছা যায় না।
  - খসড়া বাজেট মোছা যায়। মুছলে রিকুইজিশনগুলো আবার অন্য বাজেটে নেওয়া যায়।
- স্ক্রিন: `/pre-budget/create-budget`।
  - বাজেট না খুললে রিকুইজিশন বাছার তালিকা আসে: Buyer, Brand, Style, Item আর Job No দিয়ে ফিল্টার, তারপর টিক দিয়ে Create Budget।
  - বাজেট খোলে `?budget=PB-…` দিয়ে। Load বাছাই, Add Style, Remove Style, সাপ্লায়ার কলাম যোগ বা বাদ, Save আর Delete থাকে।
  - All Requisition-এ টিক দিয়েও Create Budget করা যায়।
  - Finalize-এর চেকলিস্ট এখন শুধু দেখায়, কাজ শুরু হবে ধাপ ৪ থেকে ৬-এ।
- বদলের ইতিহাস `pb_changes`-এ থাকে (subject BUDGET)। কেউ এর মধ্যে বাজেট সেভ করে থাকলে নিজের সেভ ফেরত আসে (version)।
- টেস্ট: `BudgetTest` (৩টা), আর ফিক্সচার আলাদা trait-এ: `PreBudgetFixture`। Pre-Budget-এর সব মিলিয়ে ১২টা টেস্ট পাস।
- dev-এ রাখা ডাটা: PB-CNA-1126-0001। এতে ব্যবহারকারীর নিজের দেওয়া মানগুলোও আছে: সাপ্লায়ার Nahar আর Square, Price 2.5, PI 1900।

**ধাপ ৪ ✅ (২০২৬-১১-১৯): Knit/Dying Costing, Stock Yarn Use আর Stock Yarn Declaration**
- **টেবিল** (মাইগ্রেশন `000700`):
  - `pb_budgets`-এ নতুন ঘর: Dollar Rate, দুটো নোট, আর costing ও stock কবে, কে সম্পূর্ণ করেছেন, কোন ভিত্তিতে (`*_basis`)।
  - `pb_budget_costs`: ফ্যাব্রিকেশনের রেট (BDT/kg) আর স্টাইলের Embellishment ও CM (USD/ডজন)।
  - `pb_budget_badges`: **Badge Cost** (ব্যবহারকারীর অনুরোধে)।
  - `pb_stock_lots`, আর `pb_budget_lines.no_stock`।
- **Knit/Dying Costing** (`/pre-budget/knit-dying-costing`):
  - Dollar Rate।
  - প্রতি ফ্যাব্রিকেশনে Knitting, Dyeing Finishing, Yarn Dyeing আর AOP-এর রেট (BDT/kg)। পরিমাণ = Knit রিকুইজিশনের Total Req কেজি। মূল্য BDT-তে, তারপর Dollar Rate দিয়ে ভাগ করে USD।
  - Avg knitting / dyeing প্রতি কেজি, দুটো নোট।
  - প্রতি স্টাইলে Embellishment আর CM (USD/ডজন, ডজন = Order Qty ÷ ১২), আর CM Cost প্রতি ডজন।
  - **Badge Cost** সারি: SL, Style No (একটা বা কয়েকটা স্টাইল), Order Qty/pcs (ফাঁকা রাখলে স্টাইলগুলোর qty), Qty/Dz = pcs ÷ ১২, Wastage % (factor, যেমন 1.030), Qty/Dz With % = Qty/Dz × Wastage, Badge cost/Dz, মোট = With % × cost/Dz, Supplier Name (Supplier Master) আর Remarks।
  - Save দিলে কাজ খোলা থাকে। Complete করতে লাগবে Dollar Rate, প্রতিটা ফ্যাব্রিকেশনের Knitting ও Dyeing রেট, আর প্রতিটা স্টাইলের CM।
- **বাজেটের খরচের ভাগে নতুন লাইন:**
  - Knitting আর Dyeing Finishing।
  - Yarn Dyeing আর AOP, যদি ০-র বেশি হয়।
  - Embellishment-এর ঠিক নিচে **Badge Cost**।
  - Total Cost (Fab + Acce + Embl + Badge) আর CM।
- **Stock Yarn Use** (`/pre-budget/stock-yarn-use`):
  - প্রতিটা লাইনে লট: Purchase / Stock Contract, Yarn Mill (Supplier Master), Origin (Local / Import), LC, Lot, Qty, Price, Value। না হলে "No stock"।
  - Complete করতে প্রতিটা লাইনের উত্তর লাগবে। প্রতিটা লটে Qty আর Origin লাগবে, আর এক লাইনের লট মিলে তার Total Req ছাড়াতে পারবে না।
  - লট দিলে বাজেটের Use from Stock-এ বসে, আর Need to Purchase কমে যায়।
  - Store (`prebudget.stock`) সব বাজেটের স্টক দেখতে ও পূরণ করতে পারে, কিন্তু Costing-এ যেতে পারে না।
- **Stock Yarn Declaration** (`/pre-budget/stock-yarn-declaration`): Import / Local / Grand Total-এর সারাংশ, LOCAL YARN আর IMPORT YARN তালিকা, আর Print।
- **চেকলিস্ট:** বাজেটের পাতায় Costing আর Stock সম্পূর্ণ হলে টিক পড়ে, আর সেখান থেকে সরাসরি স্ক্রিনে যাওয়া যায়। Complete-এর পরে বাজেট বদলালে (স্টাইল, ফ্যাব্রিকেশন বা লাইন) সেই অংশ আবার খোলা হয়ে যায়, কারণ basis md5 মেলে না।
- **পরে বদলানো হয়েছে (ব্যবহারকারীর কথামতো, ২০২৬-১১-১৯):**
  - Embellishment, CM আর Badge Cost এখন **স্টাইল অনুযায়ী এক সারি**, Norban-এর মতো। একই স্টাইলের সব রিকুইজিশনের Order Qty যোগ হয়।
  - Badge Cost টেবিলে আর স্টাইল বাছতে হয় না। Embellishment টেবিলের মতোই প্রতিটা স্টাইলের সারি নিজে আসে। ঘরগুলো: Wastage, cost/Dz, Supplier আর Remarks।
  - এর জন্য `pb_budget_costs`-এ STYLE সারির key এখন স্টাইলের নাম, আর তাতে badge-এর চারটা ঘর যোগ হয়েছে। `pb_budget_badges` টেবিল মুছে দেওয়া হয়েছে (মাইগ্রেশন `000800`)। আগে যেসব costing সম্পূর্ণ ছিল, সেগুলো সম্পূর্ণই আছে।
- টেস্ট: `BudgetWorkTest` (৩টা)। Pre-Budget-এর সব মিলিয়ে ১৫টা টেস্ট।
- ব্রাউজারে যাচাই: পুরো একটা Knit সারি Excel থেকে পেস্ট, আর্টওয়ার্ক আপলোড, তারপর Complete। All Requisition-এ S/N 1 এসেছে, মোট 982.500। একটা Woven সারি Draft হিসেবে রাখা হয়েছে। dev-এ রাখা ডাটা: UI-2261582 (Complete আর Draft) আর UI-2261513 WOVEN (Draft)।

### ধাপ ৫ — Accessories Budget Pending আর BOM List (২০২৬-১১-১৯)

- **টেবিল** (মাইগ্রেশন `000900`):
  - `pb_boms`: বাজেট, ক্রম (A1, A2 …), DRAFT / FINAL, version।
  - `pb_bom_styles`: BOM-এর স্টাইল। একটা স্টাইল একসাথে একটাই BOM-এ থাকতে পারে (unique budget + style)।
  - `pb_bom_lines`: প্রতিটা স্টাইলের নিজের এক্সেসরিজ লাইন। ফলে প্রতিটা স্টাইলের Consumption আলাদা হতে পারে।
- **BOM নম্বর:** বাজেট নম্বরের পরে A1, A2, যেমন `PB-CNA-1126-0003-A1`। URL-এ এই নম্বরটাই যায়, id নয়।
- **স্টাইলের তথ্য প্রতিবার বাজেটের রিকুইজিশন থেকে আসে:**
  - Order Qty = সেই স্টাইলের রিকুইজিশনগুলোর Order Qty যোগ।
  - Style Value = তাদের FOB Value যোগ।
  - FOB = Style Value ÷ Order Qty।
  - Brand, Job No আর Artwork আসে প্রথম রিকুইজিশন থেকে।
- **লাইনের ঘর:** Item (Accessory Items master), Item Code, Supplier (Supplier Master), Nominated / Non Nominated, Item Order Qty (ফাঁকা থাকলে স্টাইলের Order Qty), Extra % (শুরুতে ২), Rate, Unit (UOM master), B.Cons, I.Cons, Remarks। Item বাছলে তার Unit নিজে বসে যায়।
- **হিসাব (Norban-এর মতো):**
  - Qty with % = Item Order Qty × (১ + Extra %)।
  - Cost/Pc ইউনিট অনুযায়ী:
    - DZN = Rate ÷ 12 × B।
    - GRS = Rate ÷ 144 × B।
    - PAIR = Rate ÷ 2 × B।
    - দৈর্ঘ্য বা ওজনের একক = Rate × B।
    - CONE / BOX / KG ইত্যাদি = Rate ÷ I × B।
    - PCS / SET = Rate × I ÷ B (I না থাকলে Rate ÷ B)।
    - ROLL = Rate ÷ B।
  - Cost/Doz = Cost/Pc × 12।
  - Total Value = Qty with % × Cost/Pc।
  - Acc Cost % = Total Value ÷ Style Value।
- **ওয়ার্কশিট:**
  - বাজেটের স্টাইলগুলো চিপ হিসেবে দেখায়। টিক দিলে স্টাইলটা এই BOM-এ আসে। অন্য BOM-এ থাকা স্টাইল ধূসর থাকে।
  - প্রতিটা স্টাইলের TOTAL সারিতে Add Row আছে, আর "Copy rows from…" দিয়ে অন্য স্টাইলের সারি কপি করা যায়। কপি করলে Item Order Qty ফাঁকা হয়, তাই এই স্টাইলের নিজের Order Qty বসে।
  - নিচের বারে Total Order Qty, Order Value, Accessories Cost আর Avg/Pcs, সাথে Save Draft আর Save Final Costing।
  - Final করতে প্রতিটা স্টাইলের অন্তত একটা লাইন লাগবে। প্রতিটা লাইনে Item, Supplier, Unit, Rate > 0 আর B.Cons > 0 লাগবে, আর প্যাকের এককে (CONE ইত্যাদি) I.Cons-ও লাগবে।
  - শুধু ড্রাফট BOM মোছা যায়। বাজেট অনুমোদনের অপেক্ষায় বা অনুমোদিত হলে BOM লক হয়ে যায়।
- **বাজেটের সাথে সংযোগ:**
  - সব BOM-এর Total Value (ড্রাফট সহ) বাজেটের **Accessories Cost**-এ বসে।
  - চেকলিস্টের BOM-এ টিক পড়ে তখনই, যখন বাজেটের প্রতিটা স্টাইল কোনো Final BOM-এ থাকে।
  - বাজেট থেকে কোনো স্টাইল সরালে সেটা তার BOM থেকেও সরে যায়, আর খালি হয়ে যাওয়া BOM মুছে যায়।
- **Accessories Budget Pending:** বাজেটের তালিকা, Pending / Partial / Created অনুযায়ী ফিল্টার। একটা বাজেট খুললে তার স্টাইল আর BOM দেখা যায়, আর New BOM বা "Finish Remaining Style(s)" বাটন থাকে।
- **BOM List:** প্রতিটা BOM-স্টাইলের একটা সারি, খোঁজার বাক্স, Draft / Final ফিল্টার আর নিচে মোট।
- **কোড:** `BomSheet` (হিসাব), `BomApiController`, আর `FindsBudgets` trait (বাজেট খোঁজা, স্কোপ, লক আর লগ — বাজেট আর BOM দুটো কন্ট্রোলারই এটা ব্যবহার করে)।
- **টেস্ট:** `BomTest` (২টা)। Pre-Budget-এর মোট ১৭টা টেস্ট, সব পাস।
- **ব্রাউজারে যাচাই:**
  - PB-CNA-1126-0003-এ (UI-2261513 WOVEN স্টাইল যোগ করে) A1 বানানো হয়েছে, দুটো স্টাইল নিয়ে। Sewing Thread CONE (0.75, B 50, I 3000) আর Additional Care Label PCS (0.028)। Woven স্টাইলে thread-এর B.Cons 40।
  - Final করার পর মোট $289.17, যা হাতে করা হিসাবের সাথে মেলে। বাজেটের Accessories Cost $289, আর চেকলিস্টে BOM-এ টিক।
  - এই ডাটা dev-এ রাখা আছে।

### Acc Budget Column Setup, Unit Cost Rules আর নতুন মেনু (২০২৬-১১-১৯)

- **ব্যবহারকারীর সিদ্ধান্ত:** Requisition-এর মতো Column Setup। পুরো কোম্পানির জন্য একটাই সেটআপ। মূল কলাম লক নয়, মানে Qty with %, Cost/Pc, Cost/Doz, Total Value আর Acc Cost %-এর ফর্মুলাও বদলানো যায়।
- **টেবিল** (মাইগ্রেশন `001000`):
  - `pb_groups`, `pb_columns` আর `pb_column_events`-এ `sheet` কলাম (REQ / BOM) যোগ হয়েছে। unique key-তেও sheet আছে।
  - BOM লাইনের মান এখন `pb_bom_lines.values` (jsonb)-এ, কলাম key অনুযায়ী। আগের typed কলামগুলো এখানে সরানো হয়েছে, আর dev ডাটার মোট ($289.17) আগের মতোই আছে।
  - নতুন টেবিল `pb_unit_rules`: প্রতিটা ইউনিটের একটা ফর্মুলা।
- **শুরুর কলাম (`BomCatalog`):**
  - Style গ্রুপ: SL, Brand, Job No, Style No, Artwork, Order Qty, FOB, Style Value। এগুলো বাজেট থেকে আসে। এই গ্রুপে নতুন কলাম যোগ করা যায় না।
  - Accessories গ্রুপ: Item, Item Code, Supplier, Nominated, Item Order Qty, Extra %, Qty with % (ফর্মুলা), Cost/Pc = `[Unit Rule Cost]`, Cost/Doz, Total Value, Acc Cost %, Rate, Unit, B.Cons, I.Cons, Remarks। এছাড়া লুকানো Unit Rule Cost কলাম।
  - Total ধরা কলামগুলো (kpi SUM) স্টাইলের TOTAL সারিতে যোগফল দেখায়।
  - Required চিহ্নিত কলাম Final-এর সময় বাধ্যতামূলক।
- **Unit Cost Rules:**
  - কোম্পানি প্রতিটা ইউনিটের ফর্মুলা নিজে লেখে, যেমন DZN = `[Rate] / 12 * [B.Cons]`। শুরুর মানগুলো Norban-এর হিসাব থেকে নেওয়া।
  - নিয়মে শুধু টাইপ করা আর স্টাইলের কলাম পড়া যায়, ফর্মুলা কলাম নয়।
  - সারির হিসাবের ক্রম: স্টাইলের ঘর আর টাইপ করা মান → ইউনিটের নিয়ম থেকে Unit Rule Cost → তারপর সব ফর্মুলা। সার্ভার (`BomColumns::work`) আর ব্রাউজার একইভাবে হিসাব করে।
  - Final-এর সময় যাচাই হয়: ইউনিটের নিয়ম আছে কি না, আর Total Value বের হচ্ছে কি না।
- **বাজেটের সাথে সংযোগ:** বাজেটের Accessories Cost হলো `value` (Total Value) কলামের যোগফল, তার ফর্মুলা যা-ই হোক।
- **কোড:** `BomColumns` (শিট), `BomColumnApiController` (ColumnApiController-এরই BOM সংস্করণ, সাথে নিয়ম সেভ ও মোছা), রুট `/pre-budget/bom-columns…`।
- **নতুন মেনু (ব্যবহারকারীর দেওয়া):**
  - PRE BUDGET → Pre-Budget মডিউলে ৯টা স্ক্রিন।
  - Master Data মডিউলে তিনটা: Requisition Column Setup, Acc Budget Column Setup আর Pre-Budget Master Data।
  - Pre-Budget Master Data-তে ৭টা তালিকা ট্যাবে, পাতা না বদলে খোলে (`?tab=`)।
  - পুরনো ঠিকানাগুলো next.config-এ redirect করা হয়েছে।
- **টেস্ট:** BomTest-এ নতুন টেস্ট (নিয়ম বদলালে হিসাব বদলায়, নিজের কলাম যোগ করে Total Value-এর ফর্মুলায় ব্যবহার করা যায়)। Pre-Budget-এর সব টেস্ট পাস।
- **ব্রাউজারে যাচাই:** A1 আবার Final করে সেভ করা হয়েছে, মোট $289.17। DZN নিয়মে একটা নোট সেভ করা হয়েছে।

### ধাপ ৬ — Finalize, Approval Center, Revise আর Budget Finalized (২০২৬-১১-২০)

- **Approval Center-এ যুক্ত করা:** বাজেট এখন `Approvable` (entity `PRE_BUDGET`)। মাইগ্রেশন `001100`-এ যোগ হয়েছে approval_status, approval_request_id, submitted_at/by, approved_at/by আর revised_at।
- **chain:**
  - প্রথম Finalize-এর সময় কোম্পানির জন্য "Pre-Budget Approval" workflow নিজে থেকে তৈরি হয়: Merchandising Head → CFO → MD, প্রতিটা আলাদা level, টাকার অঙ্কে কোনো ভাগ নেই।
  - কে সই করবেন তা ঠিক করা হয় Approval Center → Approval Workflow → Action Assignment-এ (নাম, role বা user দিয়ে)।
  - কোনো ধাপে সই করার কেউ না থাকলে Finalize আটকে যায় এবং কোন ধাপ খালি তা বলে দেয়।
  - কোম্পানি workflow বন্ধ করে দিলে Finalize করলেই বাজেট অনুমোদিত হয়।
- **Finalize:**
  - চেকলিস্ট (Costing, Stock, প্রতিটা স্টাইল Final BOM-এ) সম্পূর্ণ না হলে আটকে যায়।
  - পাঠানোর পর বাজেট PENDING থাকে। তখন বাজেট, তার BOM আর রিকুইজিশন কোনোটাই বদলানো যায় না।
- **অনুমোদনের ফল:**
  - তিন ধাপ অনুমোদন হলে বাজেট APPROVED। রিকুইজিশন লক থাকে (বাজেটের PENDING বা APPROVED অবস্থায়)।
  - Reject করলে (কারণ লিখতে হয়) বাজেট RETURNED হয়: বদলে আবার Finalize করা যায়।
  - Withdraw: যিনি পাঠিয়েছেন তিনি ফিরিয়ে নিলে বাজেট আবার DRAFT হয়।
- **Revise:**
  - APPROVED বাজেটের একটা কপি তৈরি হয় (-R1, -R2 …), DRAFT হিসেবে। কপিতে থাকে রিকুইজিশন, লাইনে টাইপ করা মান, কস্টিং, স্টক লট আর BOM।
  - পুরনো বাজেট REVISED হয়ে ইতিহাস হিসেবে থাকে।
  - REVISED বাজেটের রিকুইজিশন লক থাকে না। এগুলো কাজের তালিকা আর Accessories Budget Pending-এ দেখায় না।
- **সইকারীর দেখার অধিকার:** CFO বা MD Pre-Budget-এর অনুমতি বা merchandising team-এর বাইরে হলেও, যে বাজেটে তাঁর সই লাগবে বা তিনি সই করেছেন সেটা খুলতে পারেন (`readable`, `signedBudgetIds`)। Store-এর মতো বাকিরা আগের মতোই "অনুমতি নেই" পান।
- **Budget Finalized স্ক্রিন** (`/pre-budget/budget-finalized`):
  - তালিকা: আমার অনুমোদনের জন্য / অপেক্ষায় / অনুমোদিত / ফেরত / রিভাইজড / সব। প্রতিটা সারিতে কোন ধাপে অপেক্ষা, কে পাঠিয়েছেন আর কে অনুমোদন দিয়েছেন।
  - বাজেট খুললে: অনুমোদনের প্যানেল (প্রতিটা ধাপ, কে কখন কী মন্তব্যে সই করেছেন, ইতিহাস, Approve/Reject/Withdraw/Revise), আর ট্যাবে Pre-Budget, Knit/Dying Costing, Stock Yarn Declaration ও Accessories BOM (পড়ার জন্য)।
- **Create Budget পাতা:** Finalize বাটন চালু হয়েছে, অনুমোদনের প্যানেল যোগ হয়েছে, আর রিভিশনগুলোর লিংক দেখায়।
- **টেস্ট:** `BudgetApprovalTest` (২টা)। Pre-Budget-এর মোট ২০টা টেস্ট।
- **ব্রাউজারে যাচাই:**
  - dev-এ PB-CNA-1126-0003: Costing আর Stock সম্পূর্ণ করা হয়েছে।
  - সইকারী ঠিক করা হয়েছে: Merchandising Head = Nasrin Akter, CFO = Delwar Hossain, MD = Kamrul Hasan।
  - Rahim Uddin Finalize করেছেন, তারপর তিনজন নিজ নিজ লগইনে Approve দিয়েছেন। বাজেট এখন APPROVED, ডাটা রাখা আছে।
- ~~**পরে করা হবে:** Norban-এর Budget, Cost, Stock আর Accessories Print (রিপোর্টের সাথে)।~~ নিচে "প্রিন্ট" অংশে করা হয়েছে।

### প্রিন্ট (as-built, 2026-11-20)

Norban-এর ৭টা PDF (dompdf) নামিয়ে দেখে হুবহু একই নকশায় বানানো হয়েছে। ব্রাউজারের নিজের প্রিন্ট ব্যবহার করা হয়েছে, আলাদা সার্ভার PDF নয়।

- **রুট:** `/print/pre-budget/{kind}`, অ্যাপের মেনু আর হেডার ছাড়া।
  - পাতা খোলার পরেই প্রিন্টের ডায়ালগ আসে। উপরের টুলবারে Print / Close থাকে, কাগজে ছাপা হয় না।
  - PDF-এর নাম Norban-এর মতো: `Budget-PB-…`, `Cost-…`, `Costing-…`, `Accessories-Budget-…`, `Knit-Woven-Requisition`।
- **কাগজ আর স্কেল:**
  - A4 Landscape, মার্জিন 8/8/11/8 mm। মার্জিন শিটের ভেতরে রাখা: পাশে ভেতরের ফাঁকা জায়গা, উপরে-নিচে টেবিলের header/footer সারি, যা প্রতিটা পাতায় বসে। @page-এর margin 0। তাই ডায়ালগে Margins "None" বাছলেও বা প্রিন্টার কিনারা না ছাপলেও কিছু কাটে না (ইউজারের প্রিন্টে বাঁ দিক কাটছিল, 2026-11-20)। "printed …" লাইন প্রতিটা পাতার নিচে fixed।
  - শিট একটা নির্দিষ্ট চওড়ায় সাজিয়ে পাতার মাপে ছোট করা হয়, স্কেল Norban-এর PDF-এর মতো: বাজেট 96/122, Cost আর Accessories 96/107।
  - Budget Portrait-এ পুরোটা এক পাতায় আঁটাতে আরও ছোট করা হয়।
- **ফন্ট:** Norban-এর `resources/fonts` থেকে Segoe UI আর Calibri, regular ও bold (`public/fonts/print`)।
  - Cost, Declaration আর Requisition Calibri-তে ছাপা হয়, বাকিগুলো Segoe UI-তে।
- **কোন প্রিন্ট:**
  - `budget` (Pre-Budget Approval):
    - হেডারের ডানে Item Wise Summary।
    - Knit Yarn ও Woven Fabric টেবিল, সাপ্লায়ারের দামসহ, নিচে মোটের লাইন।
    - Fabric Type বা Fabrication সারাংশ (Create Budget-এ যেটা বেছে রাখা থাকে), Delivery Date সারাংশ, Woven-এর দুটো সারাংশ।
    - FOB বক্স আর খরচের ভাঙা হিসাব। কস্টিং বা BOM না হলে "Pending" দেখায়।
    - পাতার নিচে সইয়ের নাম: Prepared By, তারপর অনুমোদনের ধাপগুলো (প্রথম ধাপ Checked By নামে)।
    - `?orientation=portrait` দিলে লম্বালম্বি।
  - `cost` (Knitting & Dyeing / Embellishment Cost):
    - গড় ধূসর ঘরে, মোট কালো ঘরে।
    - যে খরচের রেট নেই সেটা ছাপা হয় না। Badge এই ERP-র নিজের অংশ, রেট থাকলে একই নকশায় আলাদা টেবিলে ছাপা হয়।
    - `?view=costing` (Knit/Dying Costing পাতার প্রিন্ট) দিলে নিচে "… | printed …" লাইন থাকে।
  - `accessories`: বাজেটের সব BOM, অথবা `&bom=PB-…-A1` দিলে একটা BOM।
    - কলাম আসে Acc Budget Column Setup থেকে (দেখানো কলামগুলো, তাদের চওড়ার অনুপাতে)।
    - Brand / Job No / Style No ঘোরানো, প্রতি স্টাইলের নিচে হলুদ TOTAL সারি।
  - `declaration`: Stock Yarn Budget Declaration।
  - `stock-use`: Use from Stock, স্টোরের হালকা নকশায়, নিচে "printed" লাইন।
  - `requisitions`: All Requisition-এর প্রিন্ট।
    - স্ক্রিনে যে কলাম দেখানো আছে সেগুলো, যে সারি ফিল্টার করা আছে সেগুলো। কিছু টিক দেওয়া থাকলে শুধু সেগুলো।
    - সারিগুলো STYLE অনুযায়ী ভাগ করা, উপরে TTL সারাংশ।
    - কলামের চওড়া Norban-এর মাপে। অনেক কলাম হলে পাতা ছোট হয়ে আঁটে।
- **বাটন:**
  - Budget Finalized (Budget / Portrait / Cost / Accessories Print)।
  - Create Budget (Print, Portrait)।
  - Knit/Dying Costing, Stock Yarn Use, Stock Yarn Declaration (Print)।
  - Accessories: বাজেটের BOM তালিকায় Accessories Print আর প্রতিটা BOM-এর প্রিন্ট আইকন; BOM পাতায় Print BOM।
  - All Requisition (OrderSheet-এর নতুন `print` config)।
- **Backend-এ যোগ:**
  - বাজেটের body-তে `prepared_by` আর `woven_delivery_summary`।
  - কে প্রিন্ট পড়তে পারবেন তা API-ই ঠিক করে, তাই সইকারীরাও খুলতে পারেন।

### Norban-এর রিকুইজিশন তালিকা (as-built, 2026-11-20)

ইউজার বলেছেন Norban-এর Buyer, Brand, Season, Dept, Order Type, Item, Wash Type, Fabric Type, Fabric Composition, Fabrication, GSM, Sustainability, Parts, Yarn Type, Yarn Count, Yarn Composition আর Additional Process Annotech-এ আনতে।

- **Pre-Budget-এর নিজের নতুন তালিকা (ইউজারের সিদ্ধান্ত):** Item, Fabric Type, Fabric Composition, Yarn Composition, Parts, Yarn Count।
  - `pb_list_values` তালিকা: ITEM, FABRIC_TYPE, FABRIC_COMPOSITION, YARN_COMPOSITION, PARTS, YARN_COUNT। Resource `pb-items` ইত্যাদি।
  - Pre-Budget Master Data-তে প্রতিটার আলাদা ট্যাব। ট্যাবের ক্রম রিকুইজিশনের কলামের ক্রমে।
  - রিকুইজিশনের ড্রপডাউন এখন এই তালিকাগুলো থেকে পড়ে। Merchandise আর Knitting-এর master আগের মতোই আছে।
  - Norban-এ Composition শুধু লেখা, ফাইবারে ভাগ করা নয়। Fabric আর Yarn Composition Norban-এর মতোই আলাদা দুটো তালিকা।
- **Migration `2027_04_03_001200`:**
  - কোম্পানির Column Setup-এ এই ৬টা কলামের উৎস বদলে নতুন তালিকায় দিয়েছে।
  - আগের রিকুইজিশনে যে মান ব্যবহার হয়েছে, সেগুলো নতুন তালিকায় তুলে রেখেছে, যাতে পুরনো রিকুইজিশন বৈধ থাকে।
- **Norban-এর মান** (`Data/norban_masters.json`) `PreBudgetMasterSeed` দিয়ে যায় (`erp:seed-masters`)। যে নাম আগে আছে সেটা আবার যোগ হয় না।
  - **Buyer (ইউজারের সিদ্ধান্ত):** Buyer master-এ যায়। একই buyer অন্য বানানে থাকলে (CnA আর C&A) যোগ হয় না।
    - দেশ ঠিকানা থেকে নেওয়া হয়, না পেলে Bangladesh।
    - অফিস: কোম্পানির নাম অনুযায়ী প্রথম buyer office।
    - ঠিকানা না থাকলে PI Address "N/A"।
  - **Brand, Season, Dept:** কোম্পানির lookup মান হিসেবে যায়।
- **আগে থেকেই ছিল:** Order Type, Wash Type, GSM, Sustainability, Yarn Type আর Additional Process ২০২৬-১১-১৯-এ আনা হয়েছিল।
- **Fabrication:** Norban-এ এর কোনো master নেই। এটা Fabric Type + Fabric Composition + GSM জুড়ে তৈরি হয়, আমাদেরও তাই।
- **Master Data-র তালিকা:** এখন পাতা ভাগ করা আর কলাম অনুযায়ী সাজানো যায় (`plain` সরানো হয়েছে), কারণ Parts ৯৫৫টা।
- **dev-এ (DAL) যোগ হয়েছে:**
  - Item ২৪, Fabric Type ১৩১, Fabric Composition ৩১, Yarn Composition ৭১৭, Parts ৯৫৪, Yarn Count ৮৫।
  - Buyer ১৫৬।
  - Brand / Season / Dept-এর নতুনগুলো।
