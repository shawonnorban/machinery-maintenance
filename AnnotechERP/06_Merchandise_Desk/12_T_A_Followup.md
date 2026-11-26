# T&A Followup

> **স্ট্যাটাস:** ✅ সম্পন্ন (G1 Merchandising TNA Follow-up চালু)
> **মডিউল:** [Merchandise Desk](00_Index.md) · **ফেজ ২:** বিক্রয় ও মার্চেন্ডাইজিং · **কী:** `06/12`

**⬅ ফিরে যান:** [Merchandise Desk](00_Index.md) | [AnnotechERP Index](../00_Index.md)

---

## ১. স্ক্রিন (Brickland ERP থেকে)

<!-- AUTO:screens:start -->
| # | স্ক্রিন | পাথ | ট্যাব | ফিল্ড | ডায়ালগ |
|---|---|---|---|---|---|
| 1 | Merchandising TNA Follow-up | `/m/merchandising/tna-followup-marchandising` | 1 | 0 | 0 |
<!-- AUTO:screens:end -->

## ২. প্রতিটা স্ক্রিনের লাইভ বিবরণ (ফিল্ড, ট্যাব, গ্রিড, ডায়ালগ, ভ্যালিডেশন)

<!-- AUTO:detail:start -->
#### Merchandising TNA Follow-up

`/m/merchandising/tna-followup-marchandising`

**ভিউ**
- বাটন/অ্যাকশন: Select a confirmed order…
<!-- AUTO:detail:end -->

## ৩. বিদ্যমান কোড যা কাজে লাগতে পারে

- **TNA Master (06/01):** `tna_task_groups`, `tna_tasks` (গ্রুপ, source_module, offset_days), `tna_templates` (অর্ডারের ধরন, গার্মেন্টের ধরন, লিড টাইমের সীমা, বায়ার), `tna_template_tasks` (প্রতিটা কাজের শিপমেন্ট থেকে দিন: "Negative is before shipment")।
- **অর্ডার (06/07):** `tna_template_id`, `conf_date`, `ship_date`, `po_no`; রং আর সাইজের ভাগ `merch_order_sizes`।
- যে মডিউলগুলো কাজ রেকর্ড করে: অর্ডার কনফার্মেশন, বাল্ক ফ্যাব্রিক বুকিং (06/11), ল্যাব ডিপ (06/10)।

## ৪. ডাটা মডেল (Entity ও Field)

- **`merch_order_tna`:** order_id, tna_task_id, offset_days, plan_date, actual_date, required, auto (মডিউল থেকে আসা), remarks, sort_order, updated_by। এক অর্ডারে একটা কাজ একবারই।
- **`merch_order_tna_events`:** order_id, user_id (মডিউলের স্বয়ংক্রিয় আপডেটে খালি), message। এটাই Remarks / History-র "Change history"।

## ৫. Workflow / Status ও Business Rules

- **কোন অর্ডার:** শুধু কনফার্মড বা উৎপাদনে থাকা অর্ডার। তালিকায় Brickland-এর মতো লেখা: "LPP-2026-000008 · LPP · 529 · PO 1531"।
- **Recalculate Plan Date** (নিশ্চিত করার পরে):
  - কোন কাজগুলো:
    - অর্ডারে বাঁধা টেমপ্লেট থাকলে সেটা।
    - না থাকলে অর্ডারের ধরন (BULK_NEW → NEW…) আর লিড টাইম (কনফার্ম থেকে শিপমেন্ট) যে টেমপ্লেটে মেলে। বায়ারের নিজের টেমপ্লেট আগে, সাধারণটা পরে।
    - তাও না থাকলে টাস্ক মাস্টারের প্রতিটা সক্রিয় কাজের নিজের দিন।
  - প্ল্যান তারিখ = শিপমেন্টের তারিখ + কাজের দিন (নেগেটিভ মানে আগে)।
  - অর্ডারে যে কাজ নেই সেটা যোগ হয়। খোলা কাজের তারিখ নতুন করে বসে, সম্পন্ন কাজের তারিখ বদলায় না। সব কাজ তারিখ অনুযায়ী সাজানো হয়।
  - ইতিহাসে লেখা হয়: "Order: n plan dates recalculated from shipment 12-Jan-2027, m milestones added"।
  - শিপমেন্টের তারিখ না থাকলে: "This order has no shipment date…"। মাস্টারে কিছু না থাকলে: "The TNA Master has no template…"।
- **অবস্থা** (আজকের হিসাবে):
  - আসল তারিখ থাকলে Completed (Done ১, ১০০%)।
  - প্ল্যান পার হয়ে গেলে Overdue; আজ হলে Due Today; ৭ দিনের মধ্যে Pending; তার পরে Planned; প্ল্যান তারিখ না থাকলে Not Started।
  - D2G = প্ল্যান তারিখ পর্যন্ত বাকি দিন।
  - ফিল্টারে আরও আছে: "Waiting (not yet due)" আর "Due this week"।
- **স্বয়ংক্রিয় আপডেট:** কাজের source module যদি আমাদের তৈরি মডিউলের হয়, আসল তারিখ সেখান থেকে আসে। প্রতিবার অর্ডার খোলার সময় মিলিয়ে নেওয়া হয়, আর ইতিহাসে একবার লেখা হয়।
  - "Order Module": অর্ডারের কনফার্মের তারিখ।
  - "Fabric Booking": অর্ডারের প্রথম অনুমোদিত বাল্ক ফ্যাব্রিক বুকিংয়ের অনুমোদনের দিন।
  - "Lab Dip": অর্ডারের সব ল্যাব ডিপ শেড অনুমোদিত হলে শেষ সিদ্ধান্তের দিন।
  - অন্য মডিউল (Cutting, Sewing, LC…) এখনো তৈরি হয়নি, তাই সেগুলো দেখায় "Waiting for a transaction"।
- **View (একটা মাইলস্টোন):**
  - যে কাজ কোনো মডিউল রেকর্ড করে না, তার আসল তারিখ এখানে বসে। আজকের পরের তারিখ চলে না ("The actual date cannot be after today.")। "Mark complete today"-ও আছে।
  - মডিউলের কাজে তারিখ হাতে বসানো যায় না ("The Fabric Booking records this task — its date cannot be entered here.")।
  - মন্তব্য সব কাজেই লেখা যায়। প্রতিটা বদল ইতিহাসে যায় (সম্পন্ন, আবার খোলা, মন্তব্য)।

## ৬. API ও পারমিশন

- `GET /merchandise/tna-followup/orders`: অর্ডারের তালিকা।
- `GET /merchandise/tna-followup/{order_no}`: অর্ডারের তথ্য, মাইলস্টোন, KPI, স্টাইল/রং/সাইজ/ম্যাট্রিক্স, ডকুমেন্ট, মন্তব্য, ইতিহাস আর মডিউলের কার্ড। url-এ অর্ডার নম্বর থাকে, ULID নয়।
- `POST /merchandise/tna-followup/{order_no}/recalculate`, `PUT /merchandise/tna-followup/{order_no}/milestones/{id}` (actual_date, remarks, complete_today)।
- পারমিশন (নতুন): `merchandise.tna.view`, `merchandise.tna.manage`। FACTORY_ADMIN দুটোই পায়, FACTORY_MANAGER শুধু view।
- মেনু: ফোল্ডার `MERCHANDISE.TNA_FOLLOWUP` ("T&A FOLLOWUP"), ভেতরে `MERCHANDISE.TNA_FOLLOWUP_MERCH` → `/merchandise-desk/tna-followup-marchandising` (Brickland-এর পাথ হুবহু)।

## ৭. Frontend পেজ (লাইট থিম)

- **Merchandising TNA Follow-up** (`/merchandise-desk/tna-followup-marchandising?order=<অর্ডার নম্বর>`): একটাই কাজের পাতা। অর্ডার আর ভিউ বদলালে পাতা রিলোড হয় না।
  - **উপরে:**
    - Order No* বাছাই, সাথে লেখা "Selecting an order loads its buyer, style, quantity, shipment and calendar."।
    - ছয়টা KPI (Total Tasks, Completed, In Progress, Pending, Overdue, Due Today) আর Overall Progress।
  - **Order Information** (শুধু দেখার): Order No, Buyer, Buyer A/C, Style No, PO No / Reference, Order Booking Date, Shipment Date, Shipment Month, Order Quantity, Garment Type, TNA Template, Season। সাথে অবস্থা, Print আর Export (CSV)।
  - **Time & Action (TNA) Schedule:** "n of N milestones · as of আজ", আর আটটা লোকাল ট্যাব।
    - **TNA Schedule:** খোঁজা, ডিপার্টমেন্ট (টাস্ক গ্রুপ), অবস্থা, Overdue / Due today / Due this week বাটন, আর Recalculate Plan Date। কলাম: SL, Task / Milestone (নিচে সোর্সের লেখা), Department, Plan Date, Actual Date, D2G, Required, Done, Completion %, Status, Source Module, Related Doc, Action (View)।
    - **Gantt View:** মাস ধরে টাইমলাইন। প্ল্যান তারিখ (দাগ), আসল তারিখ (সবুজ বিন্দু), দেরি (লাল রেখা) আর আজকের রেখা।
    - **Style-wise, Color-wise, Size-wise, Color × Size Matrix:** অর্ডারের পরিমাণ, শতাংশ আর মোটসহ। ভাগ না থাকলে বার্তা দেখায়।
    - **Related Documents:** পারচেজ অর্ডার আর অর্ডারের সব ফ্যাব্রিক বুকিং (নম্বর, তারিখ, অবস্থা)।
    - **Remarks / History:** মাইলস্টোনের মন্তব্য, আর পরিবর্তনের ইতিহাস (সময়, কে বা সিস্টেম, কী বদলেছে)।
  - **Automatic update from modules:** প্রতিটা মডিউলের কাজের কার্ড। "Auto updated · Completed" (সবুজ), না হলে "Waiting for a transaction" (অবস্থা অনুযায়ী রং)।
  - **View ডায়ালগ:** কাজের নাম, "ডিপার্টমেন্ট · updated from মডিউল", Plan / Actual / D2G / Status / Completion, Source Transaction। তারপর আসল তারিখ (শুধু হাতে বসানোর কাজে) অথবা মডিউলের নোট, Remarks, "Mark complete today" আর Save।

## ৮. Implementation Files

<!-- AUTO:impl:start -->
| স্তর | ফাইল |
|---|---|
| ব্যাকএন্ড | `app/Modules/Merchandise/Database/Migrations/2026_11_20_000100_create_merchandise_order_tna.php`, `Models/OrderTna.php`, `Models/OrderTnaEvent.php`, `Http/Controllers/Api/TnaFollowupApiController.php`, `Routes/api.php` |
| পারমিশন ও মেনু | `app/Modules/Identity/Database/Seeders/PermissionSeeder.php`, `RoleSeeder.php`, `app/Modules/Identity/Support/MenuRegistry.php` |
| ফ্রন্টএন্ড | `frontend/src/app/(app)/merchandise-desk/[screen]/tna.js` (`TNA_SCREENS`, `TNA_MANAGE`), `page.js`, `actions.js` (`loadTna`, `recalculateTna`, `saveTnaMilestone`), `frontend/src/components/merchandise/tna-followup-desk.jsx` (`TnaFollowupDesk`, `Gantt`, `QtyTable`, `ShareTable`) |
| ভাষা | `lang/{en,bn}/merchandise.php` (`master.tfu`, `tna`, ফোল্ডার `tna_followup`, স্ক্রিনের নাম/বিবরণ) |
| টেস্ট | `tests/Feature/Merchandise/MerchandiseTnaFollowupTest.php` |
<!-- AUTO:impl:end -->

### পরিকল্পিত নতুন/বদলানো ফাইল

- Cutting, Sewing, Finishing, Packing, Shipment, Store, LC, Knitting আর Dyeing মডিউল তৈরি হলে তাদের লেনদেন `TnaFollowupApiController::recorded()`-এ যোগ হবে (প্রতিটা মডিউলের নিজের key)।

## ৯. টেস্ট ও যাচাই

- **`MerchandiseTnaFollowupTest`** (২টা টেস্ট, ৫১ assertion; আজকের তারিখ ১৬ অক্টোবর ২০২৬ ধরে):
  - অর্ডারের তালিকার লেখা; ক্যালেন্ডারের আগে অর্ডারের তথ্য; রং আর ম্যাট্রিক্স।
  - টেমপ্লেট না মিললে টাস্ক মাস্টারের দিন থেকে পাঁচটা মাইলস্টোন, তারিখ অনুযায়ী (মাস্টারে আগে থাকা Ex-Factory ক্যালেন্ডারে শেষে)। অবস্থা Completed/Overdue/Pending/Planned, D2G -64/6/16, KPI, মডিউলের কার্ড।
  - "Rcv Order Confirm" অর্ডারের কনফার্ম থেকে নিজে থেকে সম্পন্ন।
  - মডিউলের কাজে হাতের তারিখ আর ভবিষ্যতের তারিখ আটকায়; হাতে বসানো তারিখ আর মন্তব্য সেভ হয়; "complete today" আর আবার খোলা।
  - বাল্ক বুকিং অনুমোদিত হলে "Bulk Fabric Booking" নিজে থেকে সম্পন্ন, ডকুমেন্টে বুকিং আসে।
  - শিপমেন্ট পিছালে শুধু খোলা কাজের তারিখ বদলায়; ইতিহাসের লেখাগুলো যাচাই করা হয়।
  - অর্ডারে বাঁধা না থাকলেও লিড টাইম আর ধরন মিলিয়ে টেমপ্লেট বাছাই; শিপমেন্টের তারিখ না থাকলে বার্তা।
  - টেকনিশিয়ান ঢুকতে পারেন না, ম্যানেজার শুধু দেখতে পারেন।
- Identity আর পুরো Fabric Booking-সহ রিগ্রেশন: ১০২টা টেস্ট, ৭৬৮ assertion সবুজ।
- **ব্রাউজার যাচাই** (২০২৬-১০-১৬, owner লগইন):
  - dev মাস্টারে ২৫টা কাজে Brickland-এর মতো দিন আর source module বসানো হয়েছে, আর TNGEN90 টেমপ্লেটে সব কাজ রাখা হয়েছে। dev অর্ডারে কনফার্মের তারিখ ৮ অক্টোবর আর PO-1001।
  - বিবরণ আর খালি অবস্থা ✔; অর্ডার বাছাই → url `?order=Order`, Order Information-এর সব ঘর ✔
  - Recalculate → নিশ্চিতকরণ → ২৫টা মাইলস্টোন, তারিখ অনুযায়ী (১৪ অক্টোবর থেকে ১২ জানুয়ারি) ✔
  - "Rcv Order Confirm" (Order confirmed on 08-Oct-2026) আর "Bulk Fabric Booking" (BFB-00001 approved on 15-Oct-2026) নিজে থেকে সম্পন্ন ✔
  - Overdue বাটন → কিছু নেই ✔; "Issue PI…" View → আসল তারিখ ১৫ অক্টোবর আর মন্তব্য → Completed ✔
  - Ex-Factory View-এ "The Shipment Module records this task…" ✔
  - Gantt, Style-wise (FLOW STYLE-01 · Shirt · 1,000), ভাগ না থাকায় ম্যাট্রিক্সে বার্তা ✔
  - Related Documents-এ PO আর তিনটা বুকিং ✔; ইতিহাসে ছয়টা লাইন ✔; মডিউলের কার্ড ✔
  - ক্যালেন্ডার আর ইতিহাস dev DB-তে রাখা আছে।

## ১০. খোলা প্রশ্ন

- Brickland-এর ডিপার্টমেন্ট (Merchandising, Production, Store, Packing, Logistics) টাস্কের নিজের ঘর বলে মনে হয়। এখানে টাস্ক গ্রুপের নাম ডিপার্টমেন্ট হিসেবে দেখানো হয়। আলাদা ঘর লাগবে কিনা, SME ঠিক করবেন।
- Brickland-এ "Planning থেকে আসা তারিখ" Recalculate-এ বদলায় না। Planning মডিউল এখনো নেই, তাই এখানে এই নিয়ম নেই।
- "In Progress" (আংশিক সম্পন্ন): কাজের আংশিক অগ্রগতি কোনো মডিউল এখনো দেয় না, তাই সংখ্যাটা ০।

## ১১. As-built নোট

- TNA Master-এর দিনের নিয়ম মেনে চলা হয়েছে: "Negative is before shipment"। প্রথম খসড়ায় দিকটা উল্টো ধরা হয়েছিল, dev টেমপ্লেটের -90 দেখে ঠিক করা হয়েছে।
- ক্যালেন্ডারের ক্রম টাস্ক মাস্টারের ক্রম নয়, তারিখের ক্রম। একই দিনের কাজে মাস্টারের ক্রম মানা হয়। Recalculate সম্পন্ন কাজের তারিখ না বদলিয়েও তাদের জায়গা ঠিক করে দেয়।
- ভাষার কী `master.tna` আগে থেকেই TNA টেমপ্লেট স্ক্রিনের, তাই এই স্ক্রিনের লেখা `master.tfu`-তে রাখা।
- Related Documents-এ Brickland শুধু পারচেজ অর্ডার দেখায়। এখানে অর্ডারের ফ্যাব্রিক বুকিংও আসে, কারণ সেগুলো এখন তৈরি।
