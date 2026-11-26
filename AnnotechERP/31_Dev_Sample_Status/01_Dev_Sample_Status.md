# Dev Sample Status

> **স্ট্যাটাস:** ✅ সম্পন্ন (তিনটা স্ক্রিন চালু: View All Dev Sample, Add Dev Sample/Edit, Pivot Report; Raw Copy আর Print তালিকার বাটনে)
> **মডিউল:** [Dev Sample Status](00_Index.md) · **ফেজ ২:** বিক্রয় ও মার্চেন্ডাইজিং · **কী:** `31/01`

**⬅ ফিরে যান:** [Dev Sample Status](00_Index.md) | [AnnotechERP Index](../00_Index.md)

---

## ১. স্ক্রিন (Norban ERP থেকে)

<!-- AUTO:screens:start -->
| # | স্ক্রিন | পাথ | ট্যাব | ফিল্ড | ডায়ালগ |
|---|---|---|---|---|---|
| 1 | View All Dev Sample | `/dev-sample-status` | 1 | 8 | 0 |
| 2 | Add Dev Sample | `/dev-sample-status/create` | 1 | 43 | 0 |
| 3 | Pivot Report | `/dev-sample-status/report` | 1 | 3 | 0 |
<!-- AUTO:screens:end -->

## ২. প্রতিটা স্ক্রিনের লাইভ বিবরণ (ফিল্ড, ট্যাব, গ্রিড, ডায়ালগ, ভ্যালিডেশন)

<!-- AUTO:detail:start -->
#### View All Dev Sample

`/dev-sample-status`

**ভিউ**

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন/প্লেসহোল্ডার |
|---|---|---|---|
| Season | select |  | MFO-[508], MFO-[509], MFO-[510], 508, 509, 510, 511, Spring 2025, Summer 2025, SS-25, Autumn 2025, Winter 2025 |
| Buyer | select |  | ZXY, CnA, Carrefour, Adler, Canada Sp, Nakata, Tex-ass, Choice Discount, Malu, S.Oliver, GMS, Clover |
| Sample Status | select |  | All, SUBMISSION PENDING, SUBMITTED, CLARIFICATION ON PROCESS, CANCELLED, SELF DROP |
| Order Confirmation | select |  | All, CONFIRMED WITH DEVELOPMENT, CONFIRMED WITHOUT DEVELOPMENT, ORDER CONFIRMED, CONFIRMATION PENDING, NEGOTIATION ON PR |
| Price Status | select |  | All, OFFERED PRICE, PENDING TO OFFER, CANCELLED, SELF DROP, CONFIRMED |
| Styling / Job / Item / Color... | text |  |  |
| Per Page | select |  | 10, 20, 50, 100, 200, All |
| Columns[] | checkbox |  |  |
- গ্রিড কলাম: #, Season, Buyer, Customer Name, Brand, Department, Division, Styling, Image, Job, Item, Req. Category, Sample Type, Fabrication, Color, Sample QTY, Possible QTY, Actual Order qty, Target Price, FOB Price, TP RCV Date, Buyer Req. Date, Submission Deadline, Sub. Plan Date, Booking Date, Knitting Plan Date, Actual Grige Rcvd Date, Finish Fabric Delivery Plan Date, Finish Fabric Rcvd Date, Sample Sewing Plan Date, File Handover Date, Pattern Ready Date, Cutting Date, Print/EMB Date, Sewing Start Date, Sewing Output Date, Sample Submitted Date, AWB Tracking, Last Quote Date, Price Status, Sample Status, Order Confirmation, Current Order Situation, Remarks, Last Update, Actions
- সেকশন: Image Preview, Edit Record #, Edit
- বাটন/অ্যাকশন: 📈 Pivot Report, 🖨 Print, 📋 Raw Copy, + Add New Sample, Filter, 👁 Columns, All, Apply, Reset, ✎, ✕, Open full edit page, Cancel

#### Add Dev Sample

`/dev-sample-status/create`

**ভিউ**

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন/প্লেসহোল্ডার |
|---|---|---|---|
| Season | select |  | MFO-[508], MFO-[509], MFO-[510], 508, 509, 510, 511, Spring 2025, Summer 2025, SS-25, Autumn 2025, Winter 2025 |
| Buyer | select |  | ZXY, CnA, Carrefour, Adler, Canada Sp, Nakata, Tex-ass, Choice Discount, Malu, S.Oliver, GMS, Clover |
| Customer Name | text |  |  |
| Brand | select |  | ADMIRAL, ARENA, FILA, MISTRAL, ABC, DACKS, ADMIRAL SPORTS, ELLIES, MIAM, BARCA, BAYERN MUNICH, BEAR |
| Department | select |  | Baby, Boys, Girls, Kids, Kids-Boys, Kids-Girls, Ladies, Mens, Toddler Boys, Toddler Girls, Unisex, Women |
| Division | text |  |  |
| Styling | text |  |  |
| Image Upload | file |  |  |
| Job No. | text |  |  |
| Item | select-multiple |  | Dress, Hoody Jacket, Jog Pant/ Pajama, Legging, Long pant, Others, Penty, Polo, Rompper, Scarf, Serifino, Serifino T-Shi |
| Item | textarea |  |  |
| Required Category | select |  | Sample, SAMPLE WITH PRICE |
| Sample Type | select |  | Proto/Initial Development, PP Sample, Revised Proto, Strike Off, SMS Samples, Fabric Hanger Only, Price Only, Shipment S |
| Fabrication | textarea |  |  |
| Color | select |  | 0100, 01D3, 0406, 100, 10654 FLAMINGO 16-1450 TCX, 11-0601 TCX, 142 BITTER CHOCOLATE 19-1317 TPG, 15322,B1067, 210, 2140 |
| Quantity | number |  |  |
| TP RCV Date | date |  |  |
| Buyer Requested Date | date |  |  |
| Submission Deadline | date |  |  |
| Sample Sub. Plan Date | date |  |  |
| Booking Date | date |  |  |
| Knitting Plan Date | date |  |  |
| Actual Grige Rcvd Date | date |  |  |
| Finish Fabric Delivery Plan Date | date |  |  |
| Finish Fabric Rcvd Date | date |  |  |
| Sample Sewing Plan Date | date |  |  |
| File Handover Date | date |  |  |
| Pattern Ready Date | date |  |  |
| Cutting Date | date |  |  |
| Print/EMB Date | date |  |  |
| Sewing Start Date | date |  |  |
| Sewing Output Date | date |  |  |
| Sample Submitted Date | date |  |  |
| AWB Tracking | text |  |  |
| Sample QTY (Buyer) Pcs | number |  |  |
| Possible QTY Pcs | number |  |  |
| Actual Order qty Pcs | number |  |  |
| Last Price Quote Date | date |  |  |
| Price Status | select |  | OFFERED PRICE, PENDING TO OFFER, CANCELLED, SELF DROP, CONFIRMED |
| Sample Status | select |  | SUBMISSION PENDING, SUBMITTED, CLARIFICATION ON PROCESS, CANCELLED, SELF DROP |
| Order Confirmation | select |  | CONFIRMED WITH DEVELOPMENT, CONFIRMED WITHOUT DEVELOPMENT, ORDER CONFIRMED, CONFIRMATION PENDING, NEGOTIATION ON PROCESS |
| Current Order Situation | select |  | On Fabric Booking Stage, On Knitting Stage, On Dyeing & Finishing Stage, On Cutting Stage, On Print/Emb stage, On Sewing |
| Remarks | textarea |  |  |
- সেকশন: Add Color with Quantity
- বাটন/অ্যাকশন: ← Back to List, + Add Color & Qty, Add, + Add (Label – $), ✓ Save Record, Cancel

#### Pivot Report

`/dev-sample-status/report`

**ভিউ**

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন/প্লেসহোল্ডার |
|---|---|---|---|
| Season | select |  | All Seasons, MFO-[508], MFO-[509], MFO-[510], 508, 509, 510, 511, Spring 2025, Summer 2025, SS-25, Autumn 2025 |
| Buyer | select |  | All Buyers, ZXY, CnA, Carrefour, Adler, Canada Sp, Nakata, Tex-ass, Choice Discount, Malu, S.Oliver, GMS |
| Detail Show[] | checkbox |  |  |
- গ্রিড কলাম: Buyer, CONFIRMED WITH DEVELOPMENT, CONFIRMED WITHOUT DEVELOPMENT, ORDER CONFIRMED, CONFIRMATION PENDING, NEGOTIATION ON PROCESS, PENDING TO OFFER, DROP, CANCELLED, SELF DROP, Grand Total
- গ্রিড কলাম: Row Labels, SUBMITTED, SUBMISSION PENDING, CANCELLED, SELF DROP, Grand Total
- সেকশন: STYLE NAME/NUMBER, STYLE NAME/NUMBER (Detail by Buyer)
- বাটন/অ্যাকশন: ← View All, Apply
<!-- AUTO:detail:end -->

## ৩. বিদ্যমান কোড যা কাজে লাগতে পারে

- **উৎস (Norban):** `app/Models/DevelopmentSampleStatus.php` (ভ্যালিডেশন, স্ট্যাটাস তালিকা, KPI হিসাব), `app/Http/Controllers/DevelopmentSampleStatusController.php` (তালিকা, এক-ঘর এডিট, পিভট রিপোর্টের গাছ), `resources/views/dev_sample_status/*` (index, create, edit, report, raw_copy)। লাইভ স্ক্রিনশট `tools/erp_crawler/out/norban/ds_*.png`।
- **AnnotechERP-এর মাস্টার:** বায়ার = `buyers`, আইটেম = `item_types` (গার্মেন্ট আইটেম), রঙ = `colours`। Brand, Season আর গার্মেন্ট Department-এর আলাদা মাস্টার নেই, তাই Access Control → Lookup Table Master-এ (`lookup_tables`/`lookup_values`) তিনটা নতুন সিস্টেম টেবিল: `BRAND`, `SEASON`, `GARMENT_DEPARTMENT`।
- **ছবি:** `StoreFileAttachment` (টাইপ `ds_sample_image`) + `FileApiController`-এর view/manage match; আপলোড `/api` route handler দিয়ে (সার্ভার অ্যাকশনের ১ MB সীমা)।
- **লুক:** `SearchingData`, `StatCard`, `FormSection`; কলাম বাছাইয়ের জন্য নতুন শেয়ার করা `components/erp/column-view.jsx` (Order Summary আর Knit Woven-ও এটা নেবে)।

## ৪. ডাটা মডেল (Entity ও Field)

মাইগ্রেশন `app/Modules/DevSample/Database/Migrations/2026_12_20_000100_create_dev_samples.php`, টেবিল `ds_samples` (ULID, `company_id`, soft delete):

| গ্রুপ | ফিল্ড |
|---|---|
| পরিচয় | `serial_no` (কোম্পানিতে ১, ২, ৩ … — তালিকার #, URL-এ এটাই), `created_by`, `updated_by` |
| Basic Information | `season`, `buyer_id` → buyers, `customer_name`, `brand`, `department`, `division`, `styling`, `image_file_id` → file_attachments, `job_no` |
| Sample Details | `items` (json, নামের তালিকা), `required_category`, `sample_type`, `fabrication` (text), `colours` (json `[{name, qty}]`) |
| Dates | `tp_rcv_date`, `buyer_requested_date`, `submission_deadline`, `sub_plan_date` |
| Production & Sample Tracking | `booking_date`, `knitting_plan_date`, `actual_grige_rcvd_date`, `finish_fabric_delivery_plan_date`, `finish_fabric_rcvd_date`, `sample_sewing_plan_date`, `file_handover_date`, `pattern_ready_date`, `cutting_date`, `print_emb_date`, `sewing_start_date`, `sewing_output_date`, `sample_submitted_date`, `awb_tracking` |
| Quantity & Price | `sample_qty`, `possible_qty`, `actual_order_qty` (int); `target_prices`, `fob_prices` (json `[{label, price}]`); `target_price`, `fob_price` (decimal 12,4 — দ্বিতীয় লেভেল, না থাকলে প্রথম); `last_price_quote_date`, `last_price_quote_by` → users |
| Status | `price_status`, `sample_status`, `order_confirmation`, `current_order_situation`, `remarks` |

ইনডেক্স: (`company_id`, `serial_no`) unique; (`company_id`, `buyer_id`), `season`, `sample_status`, `order_confirmation`, `price_status`।

## ৫. Workflow / Status ও Business Rules

- **নির্দিষ্ট তালিকা (Norban-এর মতো, কোডে; লেবেল lang-এ):**
  - Required Category: SAMPLE, SAMPLE_WITH_PRICE
  - Sample Type: PROTO, PP, REVISED_PROTO, STRIKE_OFF, SMS, FABRIC_HANGER, PRICE_ONLY, SHIPMENT, OTHERS
  - Price Status: OFFERED, PENDING_OFFER, CANCELLED, SELF_DROP, CONFIRMED
  - Sample Status: SUBMISSION_PENDING, SUBMITTED, CLARIFICATION, CANCELLED, SELF_DROP
  - Order Confirmation: CONFIRMED_WITH_DEV, CONFIRMED_WITHOUT_DEV, ORDER_CONFIRMED, CONFIRMATION_PENDING, NEGOTIATION, PENDING_OFFER, DROP, CANCELLED, SELF_DROP
  - Current Order Situation: FABRIC_BOOKING, KNITTING, DYEING_FINISHING, CUTTING, PRINT_EMB, SEWING, READY_TO_SEND, SENT_TO_BUYER, CANCELLED, SELF_DROP
- **দাম:** Target/FOB-এ কয়েকটা "লেবেল – $" লাইন (যেমন BIO 2.48, CONV 2.30)। প্রথম লাইন = সামারির দাম (KPI-র গড় আর FOB Value), দ্বিতীয় লাইন (না থাকলে প্রথম) = রাখা `target_price`/`fob_price`।
- **Last Price Quote Date:** "কে দিয়েছে" সবসময় যিনি সেভ করছেন, ইনপুট থেকে নেওয়া হয় না। তারিখ বদলালে বা নতুন দিলে বদলায়; তারিখ মুছলে মুছে যায়।
- **KPI (ফিল্টার করা সেটের ওপর):** Total Orders; Order Submission Pending (SUBMISSION_PENDING); Order Submitted (SUBMITTED); Order Dropped (অর্ডার কনফার্মেশন DROP/SELF_DROP/CANCELLED); Order Confirmed (ORDER_CONFIRMED); Total Sample Qty; Total Possible Qty; Avg Target Price ও Avg FOB Price (possible qty দিয়ে ভারিত; কোনো সারিতে qty না থাকলে দাম-থাকা সারির সাধারণ গড়); Total FOB Value = Σ possible qty × প্রথম লেভেলের FOB।
- **কে কী দেখে:** `devsample.view_all` থাকলে সবার স্যাম্পল; না থাকলে শুধু নিজের তৈরি (Norban-এর "team.scope"-এর সমতুল্য; টিম লিডার নিয়ম §১০-এ)।
- **ঘরে এডিট:** তালিকার যেকোনো ঘরে ক্লিক → শুধু সেই ফিল্ডের ছোট ডায়ালগ (PATCH, শুধু পাঠানো ফিল্ড বদলায়)। ছবি, রঙ, আইটেম আর দামের ঘর পুরো Edit ফর্মে যায়।
- **মোছা:** soft delete; ছবি থেকে যায়।
- **পিভট রিপোর্ট:** (১) Buyer × Order Confirmation গণনা, সারি/কলাম/গ্র্যান্ড টোটালসহ; অজানা বা ফাঁকা মান "(Blank)"। (২) Detail by Buyer × Sample Status (SUBMITTED, SUBMISSION PENDING, CANCELLED, SELF DROP, Grand Total), সাথে ঐচ্ছিক স্তর Sample Type → Job No → Styling → Item → Color → Fabrication। একাধিক আইটেম বা রঙ থাকলে প্রতিটা আলাদা সারি পায়, কিন্তু উপরের টোটালে স্যাম্পলটা একবারই গোনা হয়।
- **Raw Copy:** ফিল্টার করা সব সারি ট্যাব-বিভক্ত লেখা হিসেবে, Excel-এ পেস্ট করার জন্য (কপি বাটন)।
- **Print:** দেখানো কলামগুলো নিয়ে ব্রাউজার প্রিন্ট (`lib/print-document`)।

## ৬. API ও পারমিশন

রুট `app/Modules/DevSample/Routes/api.php`, প্রিফিক্স `/api/v1/dev-sample`:

| রুট | কাজ | পারমিশন |
|---|---|---|
| GET `/lookups` | বায়ার, আইটেম, রঙ, Brand/Season/Department, নির্দিষ্ট তালিকা | devsample.view |
| GET `/samples` | ফিল্টার (season, buyer, sample_status, order_confirmation, price_status, search), পাতা (10/20/50/100/200/all), KPI | devsample.view |
| GET `/samples/{serial}` | একটা স্যাম্পল | devsample.view |
| POST `/samples` | নতুন | devsample.create |
| PATCH `/samples/{serial}` | পুরো বা আংশিক এডিট | devsample.update |
| DELETE `/samples/{serial}` | মোছা | devsample.delete |
| POST `/samples/image` | ছবি আপলোড → file id | devsample.create বা .update |
| GET `/report` | পিভট (season, buyer, detail[]) | devsample.report |

পারমিশন: `devsample.view`, `.create`, `.update`, `.delete`, `.view_all`, `.report`। রোল: FACTORY_ADMIN সব; FACTORY_MANAGER view, view_all, report।

## ৭. Frontend পেজ (লাইট থিম)

- রুট `frontend/src/app/(app)/dev-sample-status/[screen]/` — `list` (View All Dev Sample), `new` (Add Dev Sample, `?edit=<serial>` হলে Edit), `report` (Pivot Report)। ছবি `app/api/dev-sample/image/route.js`।
- কম্পোনেন্ট `frontend/src/components/devsample/`: `sample-list.jsx` (১০টা KPI, Searching Data, Columns বাছাই, Print, Raw Copy, ঘরে এডিট), `sample-form.jsx` (Basic Information, Sample Details, Dates, Production & Sample Tracking, Quantity & Price, Status; রঙ-qty আর দামের লাইন), `pivot-report.jsx`।
- মেনু: MenuRegistry মডিউল `DEV_SAMPLE` (SALES_MERCHANDISING), ফোল্ডার নেই, তিনটা স্ক্রিন। লেবেল `lang/{en,bn}/devsample.php`।

## ৮. Implementation Files

<!-- AUTO:impl:start -->
As-built:

**Backend**
- `app/Modules/DevSample/Database/Migrations/2027_04_01_000100_create_dev_samples.php`
- `app/Modules/DevSample/Models/DevSample.php`
- `app/Modules/DevSample/Support/DevSampleOptions.php`
- `app/Modules/DevSample/Support/DevSampleReport.php`
- `app/Modules/DevSample/Http/Controllers/Api/DevSampleApiController.php`
- `app/Modules/DevSample/Routes/api.php`
- `app/Modules/Identity/Support/MenuRegistry.php`
- `app/Modules/Identity/Database/Seeders/PermissionSeeder.php`
- `app/Modules/Identity/Database/Seeders/RoleSeeder.php`
- `app/Modules/Identity/Database/Seeders/LookupSeeder.php`
- `app/Modules/Audit/Providers/AuditServiceProvider.php`
- `app/Shared/Files/Http/Controllers/Api/FileApiController.php`
- `lang/en/devsample.php`
- `lang/bn/devsample.php`
- `lang/en/nav.php`
- `lang/bn/nav.php`

**Frontend**
- `frontend/src/app/(app)/dev-sample-status/[screen]/page.js`
- `frontend/src/app/(app)/dev-sample-status/[screen]/screens.js`
- `frontend/src/app/(app)/dev-sample-status/[screen]/actions.js`
- `frontend/src/app/api/dev-sample/image/route.js`
- `frontend/src/components/devsample/common.js`
- `frontend/src/components/devsample/sample-list.jsx`
- `frontend/src/components/devsample/sample-form.jsx`
- `frontend/src/components/devsample/pivot-report.jsx`
- `frontend/src/components/erp/column-view.jsx`

**Tests**
- `tests/Feature/DevSample/DevSampleTest.php`
- `tests/Feature/Identity/MenuAccessTest.php`

**Docs tooling**
- `AnnotechERP/tools/source/norban_screens.json`
- `AnnotechERP/tools/erp_crawler/norban_fields.py`
- `AnnotechERP/tools/build_erp_docs.py`
<!-- AUTO:impl:end -->

### পরিকল্পিত নতুন/বদলানো ফাইল

Backend: `app/Modules/DevSample/{Database/Migrations, Database/Seeders/DevSampleMasterSeed.php, Http/Controllers/Api/DevSampleApiController.php, Models/DevSample.php, Support/DevSampleOptions.php, Support/DevSampleReport.php, Routes/api.php}`, `MenuRegistry`, `PermissionSeeder`, `RoleSeeder`, `LookupSeeder` (BRAND/SEASON/GARMENT_DEPARTMENT), `FileApiController`, `ErpMasterDataSeeder::SEEDS`, `AuditServiceProvider::AUDITED`, `lang/{en,bn}/devsample.php`, `lang/{en,bn}/nav.php`।
Frontend: উপরের §৭। Tests: `tests/Feature/DevSample/DevSampleTest.php`।

## ৯. টেস্ট ও যাচাই

- দামের লেভেল (দ্বিতীয় লাইন রাখা), রঙ-qty ম্যাপ, quote-by সবসময় সেভকারী আর তারিখ মুছলে মুছে যায়।
- KPI: ভারিত গড়, FOB Value, ড্রপ/কনফার্মড গণনা, ফিল্টার মেনে।
- `view_all` ছাড়া অন্যের স্যাম্পল তালিকায় নেই আর খুললে 404।
- আংশিক PATCH অন্য ফিল্ড ছোঁয় না; অজানা স্ট্যাটাস ফেরত।
- পিভট: (Blank), একাধিক রঙে টোটাল একবার।
- ব্রাউজার (ui.mjs): নতুন স্যাম্পল (ছবি, দুই রঙ, দুই লেভেল দাম), ঘরে এডিট, কলাম বাছাই, পিভট — dev DB-তে রেখে দেওয়া।

## ১০. খোলা প্রশ্ন

- **টিম লিডার:** Norban-এ টিম লিডার নিজের টিমের সবার স্যাম্পল দেখে। AnnotechERP-এ এখন `view_all` (সবার) আর নিজের — এই দুটো। টিমভিত্তিক দেখা লাগলে কোন টিম (HR ডিপার্টমেন্ট?) ধরে হবে?
- **নতুন আইটেম/রঙ লিখে যোগ:** Norban-এ আইটেম ঘরে নতুন নাম লিখে যোগ করা যায়। এখানে আইটেম আর রঙ শুধু মাস্টার থেকে বাছাই; নতুন হলে আগে মাস্টারে যোগ করতে হবে। চলবে কিনা।

## ১১. As-built নোট

**সম্পন্ন (২০২৬-১১-১৭):** ব্যবহারকারীর অনুরোধ "Order Summary, Dev Sample Status, Knit Woven, Accessories Pre-Budget AnnotechERP তে ইমপ্লিমেন্ট" — প্রথমটা Dev Sample Status (নির্ভরতার ক্রমে)।
- **Norban থেকে যা যেমন আছে:** সব ফিল্ড আর ছয়টা বক্স, ১০টা KPI কার্ড আর তাদের হিসাব (ভারিত গড়, FOB Value), দামের লেভেল (প্রথমটা সারাংশ, দ্বিতীয়টা রাখা), quote-by সবসময় সেভকারী, ঘরে এডিট, কলাম বাছাই (Norban-এর ডিফল্ট ১৩টা কলাম), Print, Raw Copy, পিভট রিপোর্টের দুটো টেবিল আর ছয়টা স্তর, soft delete।
- **ইচ্ছাকৃত পার্থক্য:**
  - স্ট্যাটাস আর টাইপ কোড হিসেবে রাখা (`SUBMISSION_PENDING` …), লেবেল lang-এ — বাংলায় আলাদা লেবেল দেখায়। Norban-এ ইংরেজি লেখাই রাখা হতো।
  - বায়ার `buyers` FK; আইটেম আর রঙ AnnotechERP-এর মাস্টার থেকে; Brand, Season, Department নতুন lookup table থেকে (Access Control → Lookup Table Master-এ কোম্পানি নিজের মান যোগ করে)।
  - স্যাম্পলের নম্বর কোম্পানির নিজের ক্রমিক (`serial_no`), URL-এ এটাই (`?edit=12`), মোছা নম্বর আর ফিরে আসে না।
  - "কে দেখে": Norban-এর টিম-স্কোপের বদলে `devsample.view_all` বা নিজের (§১০)।
  - কলাম বাছাই ব্রাউজারে রাখা (Norban-এ সেশনে)।
- **যাচাইয়ে পাওয়া আর সারানো:**
  - Postgres-এ `FOR UPDATE` আর `max()` একসাথে চলে না → ক্রমিক নম্বর নেওয়ার সময় কোম্পানির সারি লক।
  - ফাঁকা নামের রঙের লাইন 422 দিচ্ছিল → Norban-এর মতো চুপচাপ বাদ।
  - lang-এর `attrs`-এ `colours.*.name` কী next-intl ভেঙে দিচ্ছিল (INVALID_KEY) → lang-এ সমতল কী, কন্ট্রোলারে wildcard-এ ম্যাপ।
  - প্রথমবার seeder-এর ক্লাসনাম শেল-এস্কেপিংয়ে চলেনি (চুপচাপ) → আবার চালিয়ে যাচাই করা হয়েছে যে টেবিল আর পারমিশন আছে।
- **dev DB-তে রেখে দেওয়া (ব্রাউজার যাচাই):** Delta Apparels-এ Brand-এর ৫টা মান (Divided, George, Next, Primark, Tom Tailor); স্যাম্পল #১ — H&M, NW7431 OPT-1, ছবি, Polo Shirt + Tank Top, Black ১২০ + Beige ৮০, Target BIO ২.৪৮ / CONV ২.৩০, FOB BIO ৩.১৫, quote ১৭-Nov; পরে তালিকার ঘর থেকে Remarks বদলানো।
