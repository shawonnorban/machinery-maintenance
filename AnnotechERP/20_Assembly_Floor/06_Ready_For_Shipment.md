# Ready For Shipment

> **স্ট্যাটাস:** ✅ সম্পন্ন (২টার ২টা স্ক্রিন)
> **মডিউল:** [Assembly Floor](00_Index.md) · **ফেজ ৫:** প্রোডাকশন · **কী:** `20/06`

**⬅ ফিরে যান:** [Assembly Floor](00_Index.md) | [AnnotechERP Index](../00_Index.md)

---

## ১. স্ক্রিন (Brickland ERP থেকে)

<!-- AUTO:screens:start -->
| # | স্ক্রিন | পাথ | ট্যাব | ফিল্ড | ডায়ালগ |
|---|---|---|---|---|---|
| 1 | Ready for Shipment Dashboard | `/m/garments/ready-dashboard` | 1 | 6 | 0 |
| 2 | Finished Goods Stock | `/m/garments/finished-goods-stock` | 2 | 4 | 0 |
<!-- AUTO:screens:end -->

## ২. প্রতিটা স্ক্রিনের লাইভ বিবরণ (ফিল্ড, ট্যাব, গ্রিড, ডায়ালগ, ভ্যালিডেশন)

<!-- AUTO:detail:start -->
#### Ready for Shipment Dashboard

`/m/garments/ready-dashboard`

**ভিউ**

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন/প্লেসহোল্ডার |
|---|---|---|---|
| Search | text |  | Buyer, PO, style, order, barcode, carton no |
| Buyer All | select |  | All |
| Shipment Status Active (Ready > 0) Ready Partially Shipped Shipment Completed | select |  | Active (Ready > 0), Ready, Partially Shipped, Shipment Completed |
| Ship Date From | date |  |  |
| To | date |  |  |
| Include completed shipments (history) | checkbox |  |  |
- সেকশন: Ready for Shipment List
- বাটন/অ্যাকশন: Search, CSV

#### Finished Goods Stock

`/m/garments/finished-goods-stock`

**ট্যাব: Stock (0)**

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন/প্লেসহোল্ডার |
|---|---|---|---|
| Search | text |  | Buyer, PO, style, colour, size, document no |
| Include zero balance | checkbox |  |  |
- সেকশন: Stock by PO, Colour and Size
- বাটন/অ্যাকশন: Search, Stock (0), Ledger (0), CSV

**ট্যাব: Ledger (0)**

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন/প্লেসহোল্ডার |
|---|---|---|---|
| Search | text |  | Buyer, PO, style, colour, size, document no |
| Include zero balance | checkbox |  |  |
- সেকশন: Stock Ledger
- বাটন/অ্যাকশন: Search, Stock (0), Ledger (0), CSV
<!-- AUTO:detail:end -->

## ৩. বিদ্যমান কোড যা কাজে লাগতে পারে

- **20/04 Packing:** প্যাকড আউট ব্যাচ (`asm_packing_batches`, `final_packed_qty`), সিল করা কার্টন আর তাদের সাইজ ভাগ। Packing Out-এর সময় `PackingBook::bookIn()` লেজারে লেখে।
- **Merchandising-এর `merch_orders` / `merch_order_sizes`:** অর্ডার নম্বর, স্টাইলের নাম, শিপমেন্টের তারিখ, ফ্যাক্টরি আর রং অনুযায়ী অর্ডার পরিমাণ (`PackingBook`)।
- **20/03 Quality-র `asm_quality_outs`:** বিস্তারিততে PO-রঙের QC Pass।
- **শেয়ার করা UI:** `finishing.jsx`-এর Panel, Chip, CSV; `components/ui/tabs` (Stock / Ledger)।

## ৪. ডাটা মডেল (Entity ও Field)

মাইগ্রেশন `2027_02_12_000100_create_asm_fg_ledger.php`।

- **`asm_fg_ledger`** (ফিনিশড গুডস স্টকের লেজার, PO + রং + সাইজ অনুযায়ী):
  - `order_id`, `po_no`, `buyer_id`, `style`, `colour`, `size`, `batch_id`।
  - `doc_type`: PACKING_OUT (ইন), SHIPMENT (আউট), SHIPMENT_REVERSAL (ফেরত ইন); শেষ দুটো 20/07 লিখবে।
  - `doc_no`, `doc_id`, `qty_in`, `qty_out`, `posted_at`, `created_by`।
- **Packing Out থেকে ইন:** ব্যাচের সিল করা কার্টনের সাইজ ভাগ। Final Packed কার্টনের মোটের চেয়ে কম হলে ঘাটতি শেষ সাইজগুলো থেকে কাটা হয়।
- **আগে প্যাকড আউট হওয়া ব্যাচ:** মাইগ্রেশন একবার লেজারে তুলে দেয় (dev-এ PKG-20261107-0001, S ৬২)।
- **Ready-র সারি আলাদা টেবিলে নেই:** প্যাকড আউট ব্যাচ আর লেজার থেকে প্রতিবার হিসাব হয়।

## ৫. Workflow / Status ও Business Rules

- **Ready for Shipment-এর সারি:** প্রতিটা PO (অর্ডার) + রঙে একটা সারি।
  - Final Packed = প্যাকড আউট ব্যাচগুলোর Final Packed যোগফল।
  - Shipped = লেজারের Shipment আউট − Shipment Reversal।
  - Ready = Final Packed − Shipped।
  - On Open Shipments = এখন ০ (20/07-এর খোলা শিপমেন্ট এলে)।
  - কার্টন = সিল করা কার্টন। শিপমেন্টের তারিখ অর্ডার থেকে।
- **অবস্থা:** কিছু শিপ হয়নি → Ready; কিছু শিপ হয়েছে আর Ready > ০ → Partially Shipped; Ready ০ → Shipment Completed।
- **ফিল্টার:**
  - Search: বায়ার, PO, স্টাইল, অর্ডার, রং, প্যাকিং নম্বর, কার্টন নম্বর বা বারকোড, বান্ডেল নম্বর বা বারকোড।
  - Buyer, Shipment Status (খালি = চালু, অর্থাৎ Ready > ০), শিপ তারিখের সীমা।
  - "Include completed shipments (history)" দিলে Ready ০ সারিও দেখায়।
  - তারিখ উল্টো দিলে আটকায়।
- **হিসাব (ফিল্টার ছাড়া পুরো তালিকা থেকে):** Total Ready Qty, Ready Orders, Ready Styles, Total Cartons, Total Buyers (Ready > ০ সারি থেকে); Shipment Pending / Partially Shipped / Shipment Completed (সারির সংখ্যা)।
- **বিস্তারিত (View):** Order Qty থেকে Ready পর্যন্ত ৮টা পরিমাণ, প্যাক করা ব্যাচ (প্যাকড আউটের সময়, Final, Shipped, কার্টন, অবস্থা), শিপমেন্ট (20/07-এর পরে)।
- **Finished Goods Stock:**
  - PO + রং + সাইজ অনুযায়ী In, Out, Balance। শূন্য ব্যালেন্স লুকানো থাকে, চেকবক্স দিলে দেখায়।
  - লেজার নতুনটা আগে।
  - Search: বায়ার, PO, স্টাইল, রং, সাইজ, ডকুমেন্ট নম্বর, প্যাকিং নম্বর।
  - হিসাব: Stock Balance, Received from Packing, Shipped Out, POs in Stock (ব্যালেন্স > ০)।

## ৬. API ও পারমিশন

- **পারমিশন:** `assembly.ready.view`। FACTORY_ADMIN, FACTORY_MANAGER আর LINE_CHIEF পায়।
- **API (`ReadyApiController`):**
  - `GET /assembly/ready/dashboard?q=&buyer=&status=&from=&to=&all=`
  - `GET /assembly/ready/detail?key=` (সারির key, PO + রং)
  - `GET /assembly/ready/stock?q=&all=`
- **মেনু:** Assembly Floor-এ নতুন ফোল্ডার READY FOR SHIPMENT, ২টা স্ক্রিন: `/assembly-floor/ready-dashboard`, `/assembly-floor/finished-goods-stock`।

## ৭. Frontend পেজ (লাইট থিম)

- **কোড:** `components/assembly/ready.jsx` (`ReadyNav`, `ReadyDashboard`, `FinishedGoods`)। একই `assembly-floor/[screen]` রুট।
- **ওপরের বাটন:** Ready Dashboard আর Finished Goods Stock। Brickland-এর তৃতীয় বাটন "Shipment Entry" 20/07 বানানোর সময় যোগ হবে।
- **Ready Dashboard:** ৮টা টাইল; ফিল্টারের সারি (Search, Buyer, Shipment Status, Ship Date From / To, Search, history চেকবক্স); Ready for Shipment List (SL, PO, Buyer, Style, Color, Ready Qty, Shipped, Carton, Shipment Date, Status, View) আর CSV; View-তে বিস্তারিতের ডায়ালগ। খোঁজা পেজ রিলোড ছাড়াই হয়।
- **Finished Goods Stock:** ৪টা টাইল; Search আর "Include zero balance"; Stock (n) / Ledger (n) ট্যাব (রিলোড ছাড়া বদলায়), প্রতিটায় CSV; স্টকে Total সারি; লেজারে ইন সবুজ, আউট লাল।
- **Packing Dashboard (20/04):** Ready for Shipment প্যানেলে "Open" লিংক আর প্রবাহের Ready ধাপ এখন এই ড্যাশবোর্ডে যায়।

## ৮. Implementation Files

<!-- AUTO:impl:start -->
As-built (২টার ২টা স্ক্রিন):

**Backend**
- `app/Modules/Assembly/Database/Migrations/2027_02_12_000100_create_asm_fg_ledger.php`
- `app/Modules/Assembly/Models/AsmFgLedger.php`
- `app/Modules/Assembly/Http/Controllers/Api/ReadyApiController.php`
- `app/Modules/Assembly/Support/PackingBook.php` (বদলানো: `bookIn()`)
- `app/Modules/Assembly/Http/Controllers/Api/PackingApiController.php` (বদলানো: Packing Out লেজারে লেখে)
- `app/Modules/Assembly/Routes/api.php` (বদলানো)
- `app/Modules/Identity/Support/MenuRegistry.php`, `PermissionSeeder.php`, `RoleSeeder.php` (বদলানো)
- `lang/en/assembly.php`, `lang/bn/assembly.php` (বদলানো)

**Frontend**
- `frontend/src/components/assembly/ready.jsx` (নতুন)
- `frontend/src/components/assembly/packing.jsx` (বদলানো: Ready-র Open লিংক)
- `frontend/src/app/(app)/assembly-floor/[screen]/screens.js`, `actions.js` (বদলানো)

**Test**
- `tests/Feature/Assembly/AssemblyReadyTest.php`
<!-- AUTO:impl:end -->

### পরিকল্পিত নতুন/বদলানো ফাইল

উপরের as-built তালিকাই চূড়ান্ত।

## ৯. টেস্ট ও যাচাই

**Feature test (`tests/Feature/Assembly/AssemblyReadyTest.php`):** test2 DB-তে পাস, ২টা টেস্ট, ১২৬টা assertion। Assembly-র ১৭টা টেস্টই একসাথে পাস (৮৬৪টা assertion); MenuAccessTest + RoleMatrixTest (১৩টা, ৮৪টা assertion) পাস।
- `test_packing_out_books_stock_and_the_ready_dashboard_reads_it`:
  - S ১০ + M ১০ এক কার্টনে, Final ১৮ → লেজারে S ১০, M ৮ (ঘাটতি শেষ সাইজ থেকে)।
  - একটা সারি: Final ১৮, Shipped ০, Ready ১৮, ১ কার্টন, শিপ তারিখ, Ready; ৮টা হিসাব; বায়ারের তালিকা; ভেতরের ঘর (batch_ids, search) বাইরে যায় না।
  - ফিল্টার: বায়ার, অবস্থা, কার্টন বারকোড, বান্ডেল বারকোড, প্যাকিং নম্বর, না-মেলা লেখা, শিপ তারিখের সীমা; উল্টো তারিখ আটকায়।
  - বিস্তারিত: অর্ডার ৬০০, QC Pass ২০, Packing In ২০, Final ১৮, কার্টন ১, Ready ১৮, ২ বান্ডেল; ব্যাচ; অচেনা key আটকায়।
  - শিপমেন্ট আউট (20/07 যেভাবে লিখবে): ৫ → Partially Shipped, Ready ১৩; সব → তালিকা থেকে সরে যায়, history-তে Shipment Completed।
- `test_finished_goods_stock_and_its_ledger_and_who_may`: স্টক S ১০ / M ৬, হিসাব; শিপমেন্টের পর S ০ লুকায়, "Include zero balance"-এ দেখায়; লেজার নতুনটা আগে; খোঁজা; LINE_CHIEF দেখতে পারে, STORE_MANAGER আর TECHNICIAN পায় না।

**UI চেক (`AnnotechERP/tools/app_check/plans/rs01`), dev অ্যাপে:**
- Packing Dashboard-এর Ready প্যানেলের "Open" → Ready for Shipment Dashboard।
- টাইল: ৬২ পিস, ১ অর্ডার, ১ স্টাইল, ২ কার্টন, ১ বায়ার, Shipment Pending ১।
- সারি: PO-SS27-01 · LPP · SS27-TEE01 · Navy Blue · ৬২ · ২ কার্টন · ২০-১২-২০২৬ · Ready। View-তে বিস্তারিত (অর্ডার ১,৯০০, QC Pass ৬২, …, Ready ৬২; PKG-20261107-0001)।
- কার্টন বারকোড CTN202611070002 দিয়ে খুঁজলে সারি আসে; "zzz" দিলে "Nothing matches"।
- Finished Goods Stock: ব্যালেন্স ৬২, S সাইজের সারি আর Total; Ledger ট্যাবে Packing Out-এর লাইন।
- **error:** ০।

**প্রিন্ট:** Brickland-এর এই দুই স্ক্রিনে Print বাটন নেই, CSV আছে।

## ১০. খোলা প্রশ্ন

১. **Final Packed কম হলে কোন সাইজ কমবে:** Final Packed সিল করা কার্টনের মোটের চেয়ে কম হলে এখন ঘাটতিটা শেষ সাইজগুলো থেকে কাটা হয় (যেমন S ১০ + M ১০, Final ১৮ → স্টকে S ১০, M ৮)। কোন সাইজ থেকে কাটা হবে, নাকি Final Packed সবসময় কার্টনের সমান হবে, জানাবেন।

২. **একটা সারি = PO + রং:** Brickland-এর মতো Ready তালিকার প্রতিটা সারি এক অর্ডার/PO আর এক রং। আলাদা স্টাইল একই PO-তে থাকলে সেটা অর্ডার আলাদা হওয়ায় আলাদা সারি হয়। সাইজ অনুযায়ী Ready দেখানো লাগবে কিনা জানাবেন (সাইজভিত্তিক হিসাব এখন Finished Goods Stock-এ আছে)।

৩. **ফিনিশড গুডস স্টোর:** Brickland টাইলে "Finished Goods Store" লেখে। এখন একটাই স্টোর ধরা হয়েছে, Store/Inventory মডিউলের কোনো স্টোরের সাথে যুক্ত নয়। ফ্যাক্টরি বা গুদাম অনুযায়ী আলাদা FG স্টোর লাগবে কিনা জানাবেন।

## ১১. As-built নোট

- **Brickland প্রোব (`erp_crawler/plans/rs01` আর দুই স্ক্রিনের JS চাঙ্ক):** ডেমোতে কিছুই প্যাকড আউট নেই, তাই কলাম, ৮টা টাইল, অবস্থা (READY / PARTIALLY_SHIPPED / SHIPMENT_COMPLETED), বিস্তারিতের ৮টা পরিমাণ, লেজারের ডকুমেন্টের ধরন (Packing Out, Shipment, Shipment Reversal) চাঙ্ক থেকে নেওয়া।
- **20/07 Shipment যুক্ত হয়েছে:** শিপমেন্ট `asm_fg_ledger`-এ SHIPMENT (আউট) আর SHIPMENT_REVERSAL (ফেরত) লেখে। "On Open Shipments" এখন খোলা (Planned / Confirmed) শিপমেন্টের পরিমাণ, Available = Ready − On Open Shipments। বিস্তারিতে শিপমেন্টের তালিকা, সারিতে "Shipment" বাটন (Shipment Entry খোলে, PO আর রং URL-এ: `?po=…&colour=…`), ওপরে "Shipment Entry" বাটন যোগ হয়েছে। সারির হিসাব এখন `Support/ReadyBook.php`-এ, Shipment-ও সেটা ব্যবহার করে।
- **খোঁজা:** Brickland URL-এ ফিল্টার রাখে। আমাদের নিয়মে পেজ রিলোড ছাড়াই খোঁজা হয়, তাই ফিল্টার URL-এ যায় না।
