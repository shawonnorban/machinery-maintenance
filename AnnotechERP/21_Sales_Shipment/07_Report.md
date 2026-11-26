# Report

> **স্ট্যাটাস:** ✅ সম্পন্ন (৩টার ৩টা স্ক্রিন)
> **মডিউল:** [Sales & Shipment](00_Index.md) · **ফেজ ৬:** শিপমেন্ট ও কমার্শিয়াল · **কী:** `21/07`

**⬅ ফিরে যান:** [Sales & Shipment](00_Index.md) | [AnnotechERP Index](../00_Index.md)

---

## ১. স্ক্রিন (Brickland ERP থেকে)

<!-- AUTO:screens:start -->
| # | স্ক্রিন | পাথ | ট্যাব | ফিল্ড | ডায়ালগ |
|---|---|---|---|---|---|
| 1 | Sales & Shipment Reports | `/m/sales-delivery/sd-report` | 1 | 31 | 0 |
| 2 | Order-wise Tracking | `/m/sales-delivery/order-tracking` | 1 | 1 | 0 |
| 3 | Notifications & Alerts | `/m/sales-delivery/sd-alerts` | 1 | 4 | 0 |
<!-- AUTO:screens:end -->

## ২. প্রতিটা স্ক্রিনের লাইভ বিবরণ (ফিল্ড, ট্যাব, গ্রিড, ডায়ালগ, ভ্যালিডেশন)

<!-- AUTO:detail:start -->
#### Sales & Shipment Reports

`/m/sales-delivery/sd-report`

**ভিউ**

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন/প্লেসহোল্ডার |
|---|---|---|---|
| Sales Order Summary | radio |  |  |
| Sales & Delivery Report | radio |  |  |
| Sales Order Balancing | radio |  |  |
| Challan Statement | radio |  |  |
| Sales Order Statement | radio |  |  |
| S/C Balance Summary | radio |  |  |
| Delivery Challan Summary | radio |  |  |
| Sales Order Report | radio |  |  |
| Delivery Challan Report | radio |  |  |
| Delivery Pending | radio |  |  |
| S/C Delivery Report | radio |  |  |
| S/C Receipt Report | radio |  |  |
| S/C Pending Report | radio |  |  |
| Shipment Status | radio |  |  |
| Shipment Pending | radio |  |  |
| Buyer-wise Sales | radio |  |  |
| Order-wise Delivery | radio |  |  |
| Order-wise Shipment | radio |  |  |
| Style-wise Shipment | radio |  |  |
| Monthly Sales Summary (by order month, BDT) | radio |  |  |
| Sales & Shipment Summary (BDT) | radio |  |  |
| Company | select |  | Brickland Composite Ltd. |
| Store | select |  | Chemical Store (ST-CHM), Cutting Sub-Store (ST-CUT), Dyeing Floor (ST-DYE), Finished Fabric Store (ST-FAB), Finished Goo |
| Item | select |  |  |
| Status | select |  | Approval: Draft, Approval: Submitted, Approval: Checked, Approval: Approved, Approval: Confirmed, Order: SO Draft, Order |
| Supplier/Customer | select |  | ACCESSORIES VIEW (SUP-10008), Arafah Knit Composite Ltd (SUP-10001), BCL (SUP-10), BCL COMPOSITE (SUP-9), CREATIVE TOUCH |
| Style | select |  |  |
| SO # | select |  |  |
| From Date | date |  |  |
| To Date | date |  |  |
| Export to Excel? | checkbox |  |  |
- সেকশন: Selection of Parameters
- বাটন/অ্যাকশন: Preview

#### Order-wise Tracking

`/m/sales-delivery/order-tracking`

**ভিউ**

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন/প্লেসহোল্ডার |
|---|---|---|---|
| Sales Order | select |  | All |
- সেকশন: Order
- বাটন/অ্যাকশন: Load, Reset

#### Notifications & Alerts

`/m/sales-delivery/sd-alerts`

**ভিউ**

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন/প্লেসহোল্ডার |
|---|---|---|---|
| Alert | select |  | All, Delivery Due, Delivery Overdue, Shipment Due, Shipment Overdue, Booking Pending, Document Pending, Certificate Expi |
| Severity | select |  | All, Critical, Warning |
| Search Live sales and shipment alerts with their kind, severity, document refere | search |  | Search 0 records… |
| Items per page | select |  | 10, 25, 50, 100 |
- গ্রিড কলাম: Alert, Reference, Party, Due / Expiry, Days, Detail, Cleared On
- বাটন/অ্যাকশন: Clear, Export, Alert, Reference, Party, Due / Expiry, Days, Detail, Cleared On
<!-- AUTO:detail:end -->

## ৩. বিদ্যমান কোড যা কাজে লাগতে পারে

- **নতুন টেবিল নেই।** তিনটা স্ক্রিনই 21/02–21/06-এর কাগজ থেকে পড়ে: অর্ডার আর সাইজ, চালান ও ফেরত, চেক বিল, সাব-কন্ট্রাক্টের কাগজ, শিপমেন্ট, বুকিং, ইনভয়েস, প্যাকিং, হস্তান্তর আর এক্সপোর্ট ডকুমেন্ট।
- **Support ক্লাস:**
  - `SalesBook`: নাম, `sizeFigures` (out / returned / net), `lineFigures` (returned / billed)।
  - `SubconBook::lines`: sent / received / rejected / পার্টির কাছে।
  - 21/05-এর shipped-এর নিয়ম: কনফার্মড বা বন্ধ শিপমেন্টের `shipped_qty`।
- **21/01-এর settings:**
  - `ship_against_delivered`: Available আর Balance।
  - `due_alert_days` (৭) আর `cert_alert_days` (৩০): সতর্কতার সময়সীমা।
  - `require_export_docs`: Document Pending।
- **20/09-এর Assembly Reports:** রিপোর্ট-সেটের লেআউট (বাঁদিকে রিপোর্টের তালিকা, ডানদিকে ঘর, নিচে টেবিল আর মোট), কিন্তু Brickland-এর মতো সব ঘর সবসময় দেখায়।

## ৪. ডাটা মডেল (Entity ও Field)

নতুন মাইগ্রেশন নেই। হিসাবগুলো প্রতিবার লাইভ টেবিল থেকে হয়:

- **অর্ডার সাইজের ভিত্তি (`facts`):** প্রতিটা অর্ডার সাইজে অর্ডার, লাইন আর সাইজের ঘর।
  - ডেলিভারি: out, returned, net।
  - শিপমেন্ট: planned (ক্যানসেল বাদে), shipped (কনফার্মড বা বন্ধ)।
  - মূল্য (BDT): অর্ডার = পরিমাণ × রেট × অর্ডারের রেট; ডেলিভারি = চালানের লাইন × চালানের রেট − ফেরতের মূল্য; শিপড = শিপড × শিপমেন্ট লাইনের রেট × অর্ডারের রেট।
  - Pending = অর্ডার পরিমাণ − max(নিট ডেলিভারি, শিপড), সাইজ ধরে।
  - অর্ডারের Delivery স্ট্যাটাস (WAITING / PARTIAL / DONE) আর Shipment স্ট্যাটাস (NOT_PLANNED / PLANNED / PARTIAL / SHIPPED), পুরো অর্ডার মিলিয়ে।
- **সতর্কতা কোথাও জমা থাকে না।** কাগজ এগোলে নিজেই মিটে যায়, তাই "Clear" বা "Dismiss" নেই।

## ৫. Workflow / Status ও Business Rules

- **Sales & Shipment Reports (২১টা):**
  - প্রতিটা রিপোর্ট Brickland-এর `needs*` অনুযায়ী শুধু নিজের ঘর পড়ে। বাকিগুলো নিষ্ক্রিয় দেখায়, আর পাঠালেও বাদ যায়। স্ক্রিন খুললে প্রথম রিপোর্ট নিজে চলে।
  - **Status:** শুধু তার ধরনের রিপোর্টে খাটে। অর্ডারের রিপোর্টে Approval / Order / Delivery / Shipment; চালানের রিপোর্টে Challan; S/C রিপোর্টে S/C; Shipment Status-এ Shipment Stage। অন্য ধরন দিলে বার্তায় বলে কোনগুলো খাটে।
  - **তারিখের ঘর:**
    - অর্ডারভিত্তিক রিপোর্ট: SO Date।
    - চালান: Chalan Date।
    - S/C: S/C Date, Chalan Date বা MRR Date।
    - Shipment Status: Plan Date।
    - To, From-এর আগে নয়; সর্বোচ্চ ৩৬৬ দিন।
  - **মোট:** সংখ্যার কলামের মোট নিচের সারিতে (রেট, দিন আর গণনা বাদে)।
  - **Export to Excel?:** টিক দিলে Preview-এর সাথে CSV নামে। ফলের কার্ডের Export বাটনও আছে।
- **Order-wise Tracking:**
  - সব অর্ডার দেখায়, নতুনটা আগে; বন্ধ অর্ডারও।
  - Load করলে টাইলে: অর্ডার পরিমাণ, নিট ডেলিভারি, ফেরত, শিপড, Delivery Balance, Shipment Balance, Available; ডেলিভারি হওয়া মালের বিপরীতে শিপ হলে Balance-ও।
  - টাইমলাইনে তারিখ অনুযায়ী প্রতিটা ঘটনা:
    - অর্ডার আর অনুমোদনের চার ধাপ।
    - সংশোধন, S/C প্রোগ্রাম।
    - চালান, ফেরত, বিল।
    - S/C ডেলিভারি ও রিসিট।
    - শিপমেন্ট প্ল্যান, বুকিং, ইনভয়েস, প্যাকিং, এক্সপোর্ট ডকুমেন্ট, দুই ধরনের হস্তান্তর।
    - শিপমেন্ট কনফার্মেশন ও ক্লোজিং, অর্ডার ক্লোজিং।
  - ঠিকানায় `?so=SO-…` বসে, পেজ রিলোড ছাড়া; সেই লিংক খুললে সরাসরি লোড হয়।
- **Notifications & Alerts (৯ ধরন):**
  - Delivery Due (টার্গেট ≤ ৭ দিন, Warning) / Delivery Overdue (টার্গেট পার, Critical): কনফার্মড আর খোলা অর্ডারে বাকি থাকলে।
  - Shipment Due / Overdue: প্রস্তুতির মধ্যে থাকা শিপমেন্টের শিপমেন্ট তারিখ ধরে।
  - Booking Pending: সময়সীমার ভেতরের শিপমেন্ট এখনো PLANNING-এ।
  - Document Pending: settings-এ বাধ্যতামূলক হলে, EXP বা B/L / AWB বাকি।
  - Certificate Expiring: খোলা শিপমেন্টের সার্টিফিকেট বা মেয়াদওয়ালা এক্সপোর্ট ডকুমেন্ট, ৩০ দিনের মধ্যে বা পার।
  - Sub-Contract Overdue: টার্গেট পার, অথচ মাল পার্টির কাছে বা এখনো পাঠানো হয়নি।
  - Order Closing Pending: সব ডেলিভারি বা শিপ হয়েছে, অর্ডার এখনো খোলা।
  - প্রতিটার "Cleared On"-এ যে স্ক্রিনে মেটে তার নাম। টাইলে প্রতিটা ধরনের সংখ্যা: ০ হলে সবুজ, Critical থাকলে লাল। Critical আগে দেখায়।

## ৬. API ও পারমিশন

- **পারমিশন:** নতুন `salesshipment.report.view`। FACTORY_ADMIN, FACTORY_MANAGER, STORE_MANAGER আর AUDITOR পায় (VIEWER সব `.view` পায়)। STOREKEEPER পায় না।
- **API (`/sales-shipment/…`):**
  - `reports/options`
  - `reports/run` (POST: kind + ঘরগুলো)
  - `order-tracking`, `order-tracking/{so_no}`
  - `alerts`
- **মেনু:** Sales & Shipment-এর নিচে নতুন ফোল্ডার "Report" (OPERATION-এর পাশে), ৩টা স্ক্রিন। মডিউলে এখন ৩২টা স্ক্রিন।

## ৭. Frontend পেজ (লাইট থিম)

- **কম্পোনেন্ট:** `components/sales-shipment/reports.jsx`।
  - `SalesReports`: Selection of Parameters, ফল নিচে মোটসহ।
  - `OrderTracking`: Order কার্ড, টাইল আর টাইমলাইন।
  - `SalesAlerts`: টাইল, Alert / Severity ফিল্টার আর তালিকা।
- **screens.js:** `sd-report`, `order-tracking` (`?so=`), `sd-alerts`।

## ৮. Implementation Files

<!-- AUTO:impl:start -->
As-built (৩টার ৩টা স্ক্রিন):

**Backend (`app/Modules/SalesShipment`)**
- `Http/Controllers/Api/SalesReportApiController.php` (নতুন: ২১টা রিপোর্ট)
- `Http/Controllers/Api/SalesWatchApiController.php` (নতুন: Order-wise Tracking, Notifications & Alerts)
- `Routes/api.php` (বদলানো)
- `app/Modules/Identity/Support/MenuRegistry.php`, `PermissionSeeder.php`, `RoleSeeder.php` (বদলানো)
- `lang/en/salesshipment.php`, `lang/bn/salesshipment.php` (বদলানো)

**Frontend**
- `frontend/src/components/sales-shipment/reports.jsx` (নতুন)
- `frontend/src/app/(app)/sales-shipment/[screen]/screens.js` (বদলানো)

**Test**
- `tests/Feature/SalesShipment/SalesShipmentReportTest.php` (নতুন)
<!-- AUTO:impl:end -->

### পরিকল্পিত নতুন/বদলানো ফাইল

উপরের as-built তালিকাই চূড়ান্ত।

## ৯. টেস্ট ও যাচাই

**Feature test (`SalesShipmentReportTest.php`):** test2 DB-তে পাস, ৩টা টেস্ট আর ১১১টা assertion। Sales & Shipment-এর সাত ফাইল মিলিয়ে ২৯টা টেস্ট, ৭৫৯টা assertion। MenuAccess + RoleMatrix-ও পাস (১৩টা টেস্ট)।
- **নমুনা ডাটা:** অর্ডার: M ৫০০ আর L ৩০০, ২ USD (রেট ১১০)। M-এর ৩০০ চালানে গেছে, ৪০ ফেরত (নিট ২৬০)। M-এর ২০০ শিপড; L-এর ৫০ ১২ তারিখের জন্য প্ল্যান করা। L-এর ১০০ প্রিন্টারের কাছে, ৬০ পাঠানো, টার্গেট ৬ তারিখ।
- **রিপোর্ট:**
  - অপশনে ২১টা রিপোর্ট; Sales & Delivery শুধু Store আর Item পড়ে।
  - Summary: ৮০০ / ১,৭৬,০০০ BDT / নিট ২৬০ / শিপড ২০০ / Pending ৫৪০ (M: ৫০০ − max(২৬০, ২০০) = ২৪০, L: ৩০০) / ১,১৮,৮০০ BDT। মোটের সারিতেও ৫৪০।
  - Sales & Shipment Summary: ডেলিভারির মূল্য ৫৭,২০০, শিপমেন্টের মূল্য ৪৪,০০০ BDT।
  - Delivery Pending: M ২৪০, ১২ দিন বাকি। Shipment Pending: M ৩০০, Available ৩০০; L-এ planned ৫০।
  - Challan Statement: ৩০০ / ফেরত ৪০ / নিট ২৬০ / ৫৭,২০০। From ৬ তারিখ দিলে খালি।
  - S/C Balance: চুক্তি ১০০, পাঠানো ৬০, পার্টির কাছে ৬০, বাকি ৪০।
  - Shipment Status: SHP-1-এ ২০০ / ২০০; SHP-2-এ শিপড ০।
  - Status "Delivery: Partially Delivered" → ২ সারি; "Delivered" → ০। Sales Order Report-এ "Challan: Draft" আটকায়, বার্তায় খাটে এমন ধরন বলে। যে রিপোর্ট Status পড়ে না সেখানে Status বাদ যায়। শেষ তারিখ শুরুর আগে হলে আটকায়।
  - AUDITOR দেখেন; STOREKEEPER চালাতে পারেন না।
- **Order-wise Tracking:**
  - টাইল: ৮০০ / নিট ২৬০ / শিপড ২০০ / Delivery Balance ৫৪০ / Shipment Balance ৬০০ / Available ৬০০।
  - প্রথম ঘটনা Sales Order; অনুমোদন, S/C প্রোগ্রাম, চালান, ফেরত, S/C ডেলিভারি, শিপমেন্ট আর কনফার্মেশন আছে, তারিখের ক্রমে। চালান: ৩০০ পিস, ৬৬,০০০ BDT।
  - অজানা অর্ডারে 404।
- **সতর্কতা:**
  - ৫টা: Booking Pending, Certificate Expiring (১২ দিন), Document Pending, Shipment Due (৪ দিন), Sub-Contract Overdue (Critical, ২ দিন পার, sc-pending-এ মেটে)। Critical আগে দেখায়; টাইলের গণনা মেলে।
  - EXP আর B/L দিলে Document Pending চলে যায়। ১৪ তারিখে Delivery Due (৬ দিন, Warning) আর Shipment Overdue (২ দিন পার, Critical) আসে।
  - STOREKEEPER দেখতে পারেন না।

**UI চেক (`app_check/plans/sr01`, `sr02`; error 0, dev ডাটা দিয়ে):**
- **Sales & Shipment Reports:**
  - স্ক্রিন খুললে প্রথম রিপোর্ট (Sales Order Summary) নিজে চলে: SO-20261107-0001, ১,১০০ পিস, ৪,৯৫০.০০ BDT, নিট ৭৮০, শিপড ৯০০, Pending ১২০।
  - যে ঘর রিপোর্ট পড়ে না সেটা নিষ্ক্রিয় দেখায়।
  - Sales & Shipment Summary: Poly Bag 12x16, ডেলিভারির মূল্য ৩,৫১০, শিপমেন্টের মূল্য ৪,০৫০; মোটের সারিসহ।
  - Challan Statement: DC-20261107-0001: ৮০০ / ২০ ফেরত / ৭৮০ / বিল ৮০০।
  - S/C Balance: SC-20261107-0001 (Square Textiles): ২২০ / পাঠানো ২২০ / গৃহীত ২১০ / রিজেক্ট ৫ / পার্টির কাছে ১০।
  - Delivery Pending: দুই সাইজ (১২০ আর ২০০), ১৬ দিন বাকি। Shipment Pending: 12x16-এর ২০০।
  - Shipment Status: SHP-1 (৯০০ / ৯০০ / ৯০০, BKG-2026-000001, MAERSK KOWLOON, Closed) আর SHP-2 (২০০, Planned)।
  - Sales Order Report-এ "Challan: Draft" দিলে বার্তা আসে; "Delivery: Partially Delivered" দিলে দুই সারি।
- **Order-wise Tracking:** SO-20261107-0001 Load করলে টাইলে ১,১০০ / ৭৮০ / ২০ / ৯০০ / ৩২০ / ২০০ / ২০০। টাইমলাইনে ২৫টা ঘটনা, অর্ডার থেকে অনুমোদন, সংশোধন, S/C, চালান, ফেরত, বিল হয়ে শিপমেন্টের কাগজ পর্যন্ত। `?so=SO-20261107-0001` দিয়ে খুললে সরাসরি লোড হয়।
- **Notifications & Alerts:** দুটো Certificate Expiring: COO-EPB-778812 (১২ দিন বাকি, COO স্ক্রিনে মেটে) আর OT-22.HBD.11873 (১৭ দিন বাকি, Certificate Management)। বাকি আটটা টাইলে ০ (সবুজ)। নোটে ৭ আর ৩০ দিনের সময়সীমা দেখায়।

## ১০. খোলা প্রশ্ন

১. **২০টা রিপোর্টের কলাম:** Brickland-এর demo-তে ডাটা নেই, আর কলাম সার্ভারে তৈরি হয় (JS-এ নেই)। তাই শুধু "Sales & Shipment Summary (BDT)"-এর ১১টা কলাম হুবহু পাওয়া গেছে। বাকি ২০টা রিপোর্টের কলাম রিপোর্টের নাম, Brickland-এর নোট আর আমাদের টেবিল থেকে ডিজাইন করা। আপনার কাছে আসল রিপোর্টের নমুনা (PDF বা Excel) থাকলে দিন, কলাম মিলিয়ে দেব।

২. **Summary রিপোর্টের Section / Particular:** Brickland-এর মোটের সারিতে "Total · All orders" দেখা যায়, কিন্তু অন্য সারিতে কী থাকে তা দেখা যায়নি। আমরা Section = অর্ডার লাইনের সেকশন আর Particular = আইটেম রেখেছি। এটা কি অর্ডারের ধরন (General / Inter-company / Sub-contract) অনুযায়ী হওয়া উচিত?

৩. **তারিখের ঘর কোন তারিখ ধরবে:** Delivery Pending আর Shipment Pending-এ এখন SO Date ধরে, যাতে ডিফল্ট সময়সীমায় (মাসের ১ তারিখ থেকে আজ) সামনের টার্গেটের অর্ডার বাদ না পড়ে। Days কলামটা টার্গেট তারিখ থেকে। Brickland-এ কি টার্গেট বা শিপমেন্ট তারিখ ধরে ফিল্টার হয়?

৪. **সতর্কতা কাকে পাঠানো হবে:** এখন সতর্কতা শুধু স্ক্রিনে দেখায়। নোটিফিকেশন (বেল আইকন বা ইমেইল) হিসেবেও পাঠানো দরকার কি, আর কোন রোলকে?

## ১১. As-built নোট

- **Brickland প্রোব:**
  - RSC টুল দিয়ে নেওয়া হয়েছে তিনটা স্ক্রিনের spec: রিপোর্টের তালিকা আর প্রতিটার `needs*`, Status-এর অপশন, নোট; Tracking-এর কলাম, ঘর আর নোট; Alerts-এর কলাম, ৯ ধরন আর টাইল।
  - `rprobe.mjs` দিয়ে ২১টা রিপোর্টই চালানো হয়েছে। demo-তে ডাটা নেই, তাই শুধু Summary-র কলাম পাওয়া গেছে; বাকিগুলো খোলা প্রশ্ন ১-এ।
- **নতুন টেবিল নেই:** সব হিসাব প্রতিবার লাইভ কাগজ থেকে, 21/02–21/06-এর একই নিয়মে (SalesBook, SubconBook, 21/05-এর shipped)।
- **Pending সাইজ ধরে:** প্রতিটা সাইজে max(নিট ডেলিভারি, শিপড), তারপর যোগ। তাই অর্ডার পর্যায়ের "অর্ডার − শিপড"-এর চেয়ে কম হতে পারে (dev ডাটায় ১২০ বনাম ২০০), যখন কোনো সাইজে ডেলিভারি শিপমেন্টের বেশি।
- **UI চেকে ধরা পড়া:** Delivery Pending, Shipment Pending আর Shipment Status টার্গেট বা শিপমেন্টের তারিখ ধরে ফিল্টার হচ্ছিল। ডিফল্ট To Date আজ, তাই সামনের তারিখের সব বাদ পড়ছিল আর রিপোর্ট খালি আসছিল। এখন অর্ডারের SO Date আর শিপমেন্টের Plan Date ধরে (খোলা প্রশ্ন ৩)।
- **Alerts-এ Severity কলাম নেই:** Brickland-এর ৭টা কলামই রাখা হয়েছে। গুরুত্ব বোঝা যায় ফিল্টার, টাইলের রং আর ক্রম থেকে।
