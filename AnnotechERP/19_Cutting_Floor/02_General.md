# General

> **স্ট্যাটাস:** ✅ সম্পন্ন (৩টার ৩টা স্ক্রিন)
> **মডিউল:** [Cutting Floor](00_Index.md) · **ফেজ ৫:** প্রোডাকশন · **কী:** `19/02`

**⬅ ফিরে যান:** [Cutting Floor](00_Index.md) | [AnnotechERP Index](../00_Index.md)

---

## ১. স্ক্রিন (Brickland ERP থেকে)

<!-- AUTO:screens:start -->
| # | স্ক্রিন | পাথ | ট্যাব | ফিল্ড | ডায়ালগ |
|---|---|---|---|---|---|
| 1 | New Cutting | `/m/cutting/new-cutting` | 1 | 23 | 0 |
| 2 | Cutting List | `/m/cutting/cutting-list` | 1 | 1 | 0 |
| 3 | Bundle Card List | `/m/cutting/bundle-card-list` | 1 | 6 | 0 |
<!-- AUTO:screens:end -->

## ২. প্রতিটা স্ক্রিনের লাইভ বিবরণ (ফিল্ড, ট্যাব, গ্রিড, ডায়ালগ, ভ্যালিডেশন)

<!-- AUTO:detail:start -->
#### New Cutting

`/m/cutting/new-cutting`

**ভিউ**

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন/প্লেসহোল্ডার |
|---|---|---|---|
| PO No * | text | ✔ | Search PO |
| Style * Select PO first | select |  | Select PO first |
| Buyer | text |  | Auto |
| Color | text |  | Auto |
| Garment | text |  | Auto |
| Fabric Type | text |  | Auto |
| GSM | text |  | Auto |
| Dia/Width | text |  | Auto |
| Order Quantity | text |  | Auto |
| Cutting Number * | text | ✔ | 1, 2, 3… |
| Bundle Number (Auto) | text |  |  |
| Size * | text | ✔ | Select or type |
| Serial From * | number | ✔ |  |
| Serial To * | number | ✔ |  |
| Parts Name * | text | ✔ | Select or type |
| Batch Number * | text | ✔ | Select or type |
| From Date | date |  |  |
| To Date | date |  |  |
| From Date | search |  | Search Bundle, Cutting No, PO, Style, Size, Batch, Serial No… |
| From Date | select |  | All status, Created, Printed, Reprinted, Cancelled |
| Page setup | select |  | Label — 50 × 75 mm, Label — 75 × 75 mm, Label — 75 × 100 mm, Custom size |
| Select all | checkbox |  |  |
| Serial No | number |  |  |
- গ্রিড কলাম: SL, Date, PO No., Style No., Color, Cutting No., Bundle No., Size, Parts Name, Batch No., Serial From, Serial To, Qty, Cutting Status, Barcode, Status, Print Date, Action
- সেকশন: 1 Order Information, 2 Bundle Information, 3 Bundle List, 4 Bundle Card Preview, 5 Today's Summary, 6 Serial Search
- বাটন/অ্যাকশন: Add to List, Update, Clear, Search, Print Selected (0), Reprint Selected (0), Print

#### Cutting List

`/m/cutting/cutting-list`

**ভিউ**

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন/প্লেসহোল্ডার |
|---|---|---|---|
| Search cutting list | search |  | Search PO, Style, Buyer, Color… |
- গ্রিড কলাম: SL, PO, Style, Buyer, Color, Order Qty, Already Cut, Balance, Bundles, Last Cut, Action
- সেকশন: 1 Cutting List
- বাটন/অ্যাকশন: Search

#### Bundle Card List

`/m/cutting/bundle-card-list`

**ভিউ**

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন/প্লেসহোল্ডার |
|---|---|---|---|
| From Date | date |  |  |
| To Date | date |  |  |
| From Date | search |  | Search Bundle, PO, Style, Size, Serial No… |
| From Date | select |  | All status, Created, Printed, Reprinted, Cancelled |
| Page setup | select |  | Label — 75 × 100 mm, Custom size |
| Select all | checkbox |  |  |
- গ্রিড কলাম: SL, Bundle No., PO No., Style No., Color, Size, Serial From, Serial To, Total Qty, Barcode, Status, Print Date, Action
- সেকশন: 1 Bundle Card List
- বাটন/অ্যাকশন: Search, Print Selected (0), Reprint Selected (0), Print, Reprint, Cancel
<!-- AUTO:detail:end -->

## ৩. বিদ্যমান কোড যা কাজে লাগতে পারে

- **মার্চেন্ডাইজ অর্ডার:** `merch_orders` (po_no, স্টাইল, বায়ার, item = গার্মেন্ট) আর `merch_order_sizes` (রং × সাইজ × পরিমাণ)। PO বাছলে স্টাইল আসে, স্টাইল বাছলে বায়ার, রং আর অর্ডারের পরিমাণ আসে।
- **ব্যাচ নং-এর তালিকা:** Dye House-এর ব্যাচ নম্বর (`dye_batches`) আর আগের কার্ডের ব্যাচ নং। কাটিং হয় ফিনিশড ফেব্রিক স্টোর (18/05) থেকে নেওয়া ব্যাচে।
- **নম্বর:** `DyeBatches::nextNumber` (BND-YYYYMMDD-####, প্রতিদিন নতুন করে)।
- **নতুন মডিউল:** `app/Modules/Cutting` (ModuleServiceProvider নিজে থেকেই রুট আর মাইগ্রেশন তোলে)।

## ৪. ডাটা মডেল (Entity ও Field)

মাইগ্রেশন `app/Modules/Cutting/Database/Migrations/2027_02_07_000100_create_cut_bundles.php`। 19/01–19/03-এর তিনটা গ্রুপ একই টেবিল ব্যবহার করে:
- **`cut_bundles`:**
  - bundle_no (BND-YYYYMMDD-####), cut_date
  - order, po_no, style, buyer, colour, garment, fabric_type, gsm, dia_width
  - cutting_no, size, serial_from, serial_to, qty (= to − from + 1), parts_name, batch_no
  - cutting_status (REGULAR / ADDITIONAL / PARTIAL_ADDITIONAL), additional_qty
  - status (CREATED / PRINTED / REPRINTED / CANCELLED), print_count, printed_at
- **বারকোড:** বান্ডেল নং থেকে হাইফেন বাদ দিয়ে (BND202611060001), Code 128।

## ৫. Workflow / Status ও Business Rules

**এই গ্রুপ (19/02):** New Cutting, Cutting List, Bundle Card List।

- **PO → স্টাইল → রং:** PO নং ডাটালিস্ট থেকে বা টাইপ করে দেওয়া যায়।
  - এক PO-তে একটাই স্টাইল থাকলে সেটা নিজে বসে, এক স্টাইলে একটাই রং থাকলেও।
  - রং অর্ডারের রং-সাইজ তালিকায় না থাকলে আটকায়।
  - অর্ডারের পরিমাণ সেই রঙের সব সাইজের যোগফল। রং-সাইজ তালিকা না থাকলে অর্ডারের মোট পরিমাণ।
- **কার্ড যোগ করা:**
  - Cutting Number, Size, Serial From/To, Parts Name আর Batch Number বাধ্যতামূলক। পরিমাণ সিরিয়াল থেকে হিসাব হয়।
  - সাইজ আর ব্যাচ নং বড় হাতের অক্ষরে রাখা হয়।
  - একই অর্ডার, রং, সাইজ আর পার্টসের চালু কার্ডে সিরিয়াল ওভারল্যাপ করতে পারে না। অন্য পার্টস (যেমন Front Panel) একই সিরিয়াল নিতে পারে।
  - যোগ করার পরে পরের কার্ডের Serial From আগের কার্ডের শেষ সিরিয়াল + ১ থেকে নিজে বসে।
- **অতিরিক্ত কাটিং:** একই রং আর পার্টসে আগে যা কাটা হয়েছে, তা রঙের অর্ডার পরিমাণের সাথে তুলনা হয়।
  - পুরোটা অর্ডারের মধ্যে থাকলে Regular।
  - কিছু অংশ সীমা ছাড়ালে Partial Additional, আর ছাড়ানো অংশটুকু additional_qty।
  - আগেই সীমা পার হয়ে গেলে পুরো কার্ড Additional।
- **প্রিন্ট:**
  - Print শুধু Created কার্ডে চলে, Reprint শুধু Printed/Reprinted কার্ডে। প্রতিবার print_count বাড়ে আর সময় লেখা হয়।
  - লেবেল ৫০×৭৫, ৭৫×৭৫, ৭৫×১০০ মিমি বা নিজের মাপে, এক লেবেলে একটা কার্ড।
- **বাতিল:** কার্ড রেকর্ডে থাকে, কিন্তু সব হিসাব থেকে বাদ যায়, আর তার সিরিয়াল আবার ব্যবহার করা যায়। বাতিল কার্ড এডিট হয় না।
- **এডিট:** বাতিল নয় এমন কার্ড (প্রিন্ট হলেও) এডিট করা যায়। নম্বর একই থাকে, আর পরিমাণ ও অতিরিক্ত আবার হিসাব হয়।
- **Cutting List:** PO/স্টাইল/রং অনুযায়ী অর্ডার পরিমাণ, কাটা হয়েছে, বাকি, অতিরিক্ত, অতিরিক্ত %, বান্ডেল সংখ্যা আর শেষ কাটিং।
  - একটা গার্মেন্ট একবারই কাটা ধরা হয়: যে পার্টস সবচেয়ে বেশি কাটা হয়েছে, সেটাই "কাটা হয়েছে"।
  - Bundle বাটন সেই PO আর রং বসিয়ে New Cutting খোলে (`?pick=<PO-রং>`, ULID নয়)।
- **Dashboard:** আজকের কাটিং, মোট বাকি (Cutting List-এর বাকির যোগফল), আজকের বান্ডেল আর পরিমাণ, প্রিন্টের অপেক্ষায়, প্রিন্ট হয়েছে, আর সর্বশেষ ১০টা কার্ড।

## ৬. API ও পারমিশন

- **পারমিশন:**
  - `cutting.bundle.view` / `cutting.bundle.manage`
  - FACTORY_ADMIN, FACTORY_MANAGER আর LINE_CHIEF দুটোই পায়। STORE_MANAGER শুধু view পায়।
- **API (`CutBundleApiController`):**
  - `GET /cutting/options`, `GET /cutting/dashboard`, `GET /cutting/cutting-list`
  - `GET /cutting/serial?serial=&order_id=&colour=&size=`
  - `GET|POST /cutting/bundles`, `GET|PUT /cutting/bundles/{no}`
  - `POST /cutting/bundles/print` (`bundles[]`, `reprint`), `POST /cutting/bundles/{no}/cancel`
- **মেনু:** নতুন মডিউল Cutting Floor (PRODUCTION গ্রুপ)। ফোল্ডার CUTTING-এ Dashboard, New Cutting, Cutting List; ফোল্ডার BUNDLE CARD-এ Bundle Card List আর Bundle Entry।

## ৭. Frontend পেজ (লাইট থিম)

- **কোড:** `frontend/src/app/(app)/cutting-floor/[screen]/` (page, screens, actions), `components/cutting/bundles.jsx` (`BundleEntry`, `BundleList`), `lib/bundle-label.js`।
- **লেবেল:** `lib/bundle-label.js`-এ নিজেদের লেখা Code 128 বারকোড (SVG) আর লেবেলের প্রিন্ট, কারণ কোনো লাইব্রেরি নেই। লেবেল আলাদা উইন্ডোতে খোলে আর নিজে প্রিন্ট শুরু করে।
- **New Cutting / Bundle Entry:** Brickland-এর মতো একই বেঞ্চ।
  - বাঁ দিকে ১ Order Information, ২ Bundle Information (Add to List / Update / Clear), ৩ Bundle List।
  - ডান দিকে ৪ Bundle Card Preview, ৫ Today's Summary (সাইজ অনুযায়ীও), ৬ Serial Search।
  - প্রিভিউ লিখতে লিখতেই কার্ড দেখায়। তালিকার সারিতে চাপলে সেই কার্ড দেখায়।
- **Bundle List:** তারিখ, খোঁজ (বান্ডেল, কাটিং নং, PO, স্টাইল, সাইজ, সিরিয়াল), অবস্থা আর কাটিং অবস্থা দিয়ে খোঁজা যায়।
  - Print / Reprint Selected, লেবেলের মাপ, আর সারাংশ (বান্ডেল · পিস · অতিরিক্ত)।
  - প্রতিটা সারিতে বারকোড, Print / Reprint / Edit / Cancel।
- **Bundle Card List:** শুধু তালিকা অংশটা।
- **Dashboard আর Cutting List:** টাইল আর গ্রিড। Cutting List-এর প্রতিটা সারিতে Bundle বাটন।

## ৮. Implementation Files

<!-- AUTO:impl:start -->
As-built (19/01–19/03 একসাথে, একই ব্যাকএন্ড):

**Backend**
- `app/Modules/Cutting/Database/Migrations/2027_02_07_000100_create_cut_bundles.php`
- `app/Modules/Cutting/Models/CutBundle.php`
- `app/Modules/Cutting/Http/Controllers/Api/CutBundleApiController.php`
- `app/Modules/Cutting/Routes/api.php`
- `app/Modules/Identity/Support/MenuRegistry.php`, `PermissionSeeder.php`, `RoleSeeder.php` (বদলানো)
- `lang/en/cutting.php`, `lang/bn/cutting.php` (নতুন), `lang/en/nav.php`, `lang/bn/nav.php` (বদলানো)

**Frontend**
- `frontend/src/app/(app)/cutting-floor/[screen]/page.js`, `screens.js`, `actions.js`
- `frontend/src/components/cutting/bundles.jsx`
- `frontend/src/lib/bundle-label.js`

**Test**
- `tests/Feature/Cutting/CuttingBundleTest.php`
<!-- AUTO:impl:end -->

### পরিকল্পিত নতুন/বদলানো ফাইল

উপরের as-built তালিকাই চূড়ান্ত।

## ৯. টেস্ট ও যাচাই

**Feature test (`tests/Feature/Cutting/CuttingBundleTest.php`):** test2 DB-তে পাস, ৪টা টেস্ট, ৮০টা assertion।
- `test_a_card_is_numbered_counted_from_its_serials_and_never_overlaps`:
  - options-এ PO, স্টাইল, বায়ার, গার্মেন্ট আর রঙের পরিমাণ আসে। পরের নম্বর BND-…-0001।
  - উল্টো সিরিয়াল, অর্ডারে নেই এমন রং আর পার্টস ছাড়া আটকায়।
  - প্রথম কার্ড ৩৬ পিস: রং Navy, সাইজ S আর ব্যাচ B-7095 বড় হাতের অক্ষরে, Regular।
  - ওভারল্যাপ আটকায়, কিন্তু অন্য পার্টস একই সিরিয়াল নিতে পারে। এডিট করলে পরিমাণ বদলায়, নম্বর একই থাকে।
  - সিরিয়াল দিয়ে খোঁজা যায়।
- `test_what_is_cut_beyond_the_colours_quantity_is_additional`:
  - রঙের পরিমাণ ১০০: ৬০ পিস Regular, পরের ৬০-এর ২০ Partial Additional, পরের ১০ Additional।
  - Cutting List-এ ১০০ / ১৩০ কাটা / বাকি ০ / অতিরিক্ত ৩০ (৩০%) / ৪টা বান্ডেল। Red-এর বাকি ৩৫।
- `test_cards_are_printed_reprinted_and_cancelled`:
  - Dashboard-এ প্রিন্টের অপেক্ষায় ২, আজকের কাটিং ৭০, বাকি ৩০।
  - প্রিন্ট না হওয়া কার্ডের রিপ্রিন্ট আর দুবার প্রিন্ট আটকায়। রিপ্রিন্টে print_count ২ হয়।
  - বাতিল কার্ড হিসাব থেকে বাদ যায়, আর তার সিরিয়াল আবার নেওয়া যায়। বাতিল কার্ড আবার বাতিল বা এডিট আটকায়।
- `test_who_may_cut`: LINE_CHIEF কাটতে পারে। STORE_MANAGER দেখতে পারে, কাটতে পারে না। TECHNICIAN কিছুই পায় না।

**Regression:** `MenuAccessTest` (৬টা, ৩৪টা) আর `RoleMatrixTest` (৭টা, ৫০টা) পাস।

**UI চেক (`AnnotechERP/tools/app_check/plans/cut01`), dev অ্যাপে (Delta Apparels):**
- **New Cutting:**
  - PO-SS27-01 লিখতেই স্টাইল SS27-TEE01, বায়ার LPP, গার্মেন্ট Ladies Tee আর অর্ডার পরিমাণ ১৯০০ বসেছে। রং Navy Blue।
  - কার্ড: কাটিং ১, সাইজ S, ১–৩৬, Back Panel, ব্যাচ B-26110011। Add to List দিলে BND-20261106-0001 হয়েছে।
  - এরপর Serial From নিজে ৩৭ বসেছে। ৩৭–৭২ দিয়ে BND-20261106-0002।
  - প্রিভিউতে বারকোড আর সব তথ্য। তালিকার সারাংশ: ২টা বান্ডেল · ৭২ পিস · অতিরিক্ত ০।
- **লেবেল প্রিন্ট:** প্রথম কার্ড বেছে Print Selected দেওয়া হয়েছে। ৫০×৭৫ মিমির লেবেলের PDF ধরে এনে দেখা হয়েছে: কোম্পানির নাম, Code 128 বারকোড, PO/স্টাইল/বায়ার/রং, বান্ডেল নং থেকে ব্যাচ নং পর্যন্ত সব, আর তারিখ। কার্ড এখন Printed।
- **Dashboard:** আজকের কাটিং ৭২, মোট বাকি ১,৮২৮ পিস, আজ ২টা বান্ডেল, প্রিন্টের অপেক্ষায় ১, প্রিন্ট হয়েছে ১। সর্বশেষ কার্ডের তালিকায় দুটো।
- **Cutting List:** PO-SS27-01 / Navy Blue: ১৯০০ / ৭২ / ১৮২৮ / ০ / ০.০০% / ২ / ০৬-১১-২০২৬, সাথে Bundle বাটন।
- **Bundle Card List:** দুটো কার্ড, প্রথমটা Printed (০৬/১১/২০২৬ ১৯:২১)।
- **error:** ০। সব রেকর্ড dev-এ রাখা হয়েছে।

## ১০. খোলা প্রশ্ন

১. **PO কোথা থেকে আসে:** PO নং এখন মার্চেন্ডাইজ অর্ডারের `po_no` থেকে আসে (প্রতি অর্ডারে একটা)। Brickland-এ একই স্টাইলের অনেক PO আছে (যেমন "11565705 (BOTTOM)", "E-COM")। অর্ডারের ভেতরে আলাদা PO তালিকা লাগবে কিনা জানাবেন।

২. **অতিরিক্তের সীমা:** অতিরিক্ত গোনা হয় রঙের মোট পরিমাণ ধরে, সাইজ ধরে নয়, আর একেক পার্টস আলাদা করে। সাইজ ধরে হিসাব করতে হবে কিনা, বা কোনো ছাড়ের % (যেমন ৩%) থাকবে কিনা জানাবেন।

৩. **ফেব্রিক ইস্যুর সাথে যোগ:** ব্যাচ নং এখন তালিকা থেকে বা টাইপ করে দেওয়া যায়। এটা ফেব্রিক স্টোরের কাটিং ইস্যু (18/05)-এর লট থেকেই বাধ্যতামূলক আসবে কিনা জানাবেন।

৪. **প্রিন্টের পরে এডিট:** প্রিন্ট হওয়া কার্ডও এডিট করা যায় (Brickland-এর মতো Edit বাটন আছে)। এডিটের পরে কার্ড আবার Created হবে (রিপ্রিন্ট লাগবে) কিনা জানাবেন।

## ১১. As-built নোট

- **Brickland প্রোব (`erp_crawler/plans/ct01`–`ct03`):**
  - ডেমোতে আসল ডাটা ছিল: BND-20261102-0183, ২০২৬১১০২ তারিখে ১৮৩টা কার্ড, কাটিং তালিকা।
  - বেঞ্চের ছয়টা অংশ, লেবেলের মাপ (৫০×৭৫ / ৭৫×৭৫ / ৭৫×১০০ / Custom), কাটিং অবস্থা (Regular / Additional (any) / Additional / Partial Additional) আর কার্ডের নকশা প্রিভিউ থেকে নেওয়া হয়েছে।
- **তিনটা সাব-মডিউল একসাথে:** Dashboard (19/01) আর Bundle Entry (19/03) একই বান্ডেল-কার্ড ডাটার ওপর চলে। Bundle Entry আর New Cutting Brickland-এ হুবহু এক স্ক্রিন, তাই 19/01–19/03 একসাথে বানানো হয়েছে।
- **19/04 Reports বাকি:** Bundle / PO-wise / Style-wise / Size-wise Bundle Report। এগুলো এই কার্ড থেকেই পড়বে।
