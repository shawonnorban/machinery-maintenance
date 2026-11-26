# Operations

> **স্ট্যাটাস:** ✅ সম্পন্ন (৪টার ৪টা স্ক্রিন)
> **মডিউল:** [Colour Lab](00_Index.md) · **ফেজ ৫:** প্রোডাকশন · **কী:** `16/01`

**⬅ ফিরে যান:** [Colour Lab](00_Index.md) | [AnnotechERP Index](../00_Index.md)

---

## ১. স্ক্রিন (Brickland ERP থেকে)

<!-- AUTO:screens:start -->
| # | স্ক্রিন | পাথ | ট্যাব | ফিল্ড | ডায়ালগ |
|---|---|---|---|---|---|
| 1 | Test Certificate Directory | `/m/dyeing-lab/certificate-list` | 1 | 1 | 0 |
| 2 | Chemical Trial Workbench | `/m/dyeing-lab/chemical-trial-lab` | 1 | 4 | 0 |
| 3 | Certificate Issue Register | `/m/dyeing-lab/certificate-register` | 2 | 14 | 1 |
| 4 | In-House Fabric Testing | `/m/dyeing-lab/inh-fab-test` | 0 | 0 | 0 |
<!-- AUTO:screens:end -->

## ২. প্রতিটা স্ক্রিনের লাইভ বিবরণ (ফিল্ড, ট্যাব, গ্রিড, ডায়ালগ, ভ্যালিডেশন)

<!-- AUTO:detail:start -->
#### Test Certificate Directory

`/m/dyeing-lab/certificate-list`

**ভিউ**

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন/প্লেসহোল্ডার |
|---|---|---|---|
| Items per page | select |  | 10, 20, 50, 100 |
- গ্রিড কলাম: Certification Type ▼, Document Ref# ▼, Apply Date ▼, Issue Date ▼, Expiry Date ▼, Mail (To) ▼, Mail (Cc) ▼, Notify BF Days ▼, Apply By ▼, Action
- বাটন/অ্যাকশন: ⟳

#### Chemical Trial Workbench

`/m/dyeing-lab/chemical-trial-lab`

**ভিউ**

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন/প্লেসহোল্ডার |
|---|---|---|---|
| Ref: | text |  |  |
| From Date: | date |  |  |
| To Date: | date |  |  |
| Status -- Status -- Pending In Progress Completed Cancelled | select |  | Pending, In Progress, Completed, Cancelled |
- গ্রিড কলাম: Ref# ▼, Lab ▼, Trial Date ▼, Trial By ▼, Status, WorkFlow, Action
- বাটন/অ্যাকশন: Search

#### Certificate Issue Register

`/m/dyeing-lab/certificate-register`

**ট্যাব: Records 0**

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন/প্লেসহোল্ডার |
|---|---|---|---|
| Action | select |  | Issued, Handed Over, Returned, Renewed, Surrendered |
| Entry Date from | date |  |  |
| Entry Date to | date |  |  |
| Search The register every issued certificate is numbered and filed against — who | search |  | Search 0 records… |
| Items per page | select |  | 10, 25, 50, 100 |
- গ্রিড কলাম: Register No, Certificate, Entry Date, Action, Issued To, Buyer, Copies, Due Date, Return Date, Handed By, ACTION
- বাটন/অ্যাকশন: New register entry, Clear, Export, Register No, Certificate, Entry Date, Action, Issued To, Buyer, Copies, Due Date, Return Date, Handed By

**ট্যাব: New register entry**

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন/প্লেসহোল্ডার |
|---|---|---|---|
| Certificate* | select | ✔ | Select… |
| Entry Date* | date | ✔ |  |
| Action | select |  | Select…, Issued, Handed Over, Returned, Renewed, Surrendered |
| Issued To* | text | ✔ | SGS Bangladesh — audit team |
| Buyer | select |  | Select…, LPP (LPP) |
| Copies | number |  |  |
| Due Date | date |  |  |
| Return Date | date |  |  |
| Remarks | textarea |  |  |
- সেকশন: New register entry
- বাটন/অ্যাকশন: New register entry, Select…, Reset, Save register entry

**ডায়ালগ: New register entry**

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন |
|---|---|---|---|
| Certificate* | select | ✔ | Select… |
| Entry Date* | date | ✔ |  |
| Action | select |  | Select…, Issued, Handed Over, Returned, Renewed, Surrendered |
| Issued To* | text | ✔ |  |
| Buyer | select |  | Select…, LPP (LPP) |
| Copies | number |  |  |
| Due Date | date |  |  |
| Return Date | date |  |  |
| Remarks | textarea |  |  |
- খালি Save-এ মেসেজ: Certificate is required.

#### In-House Fabric Testing

`/m/dyeing-lab/inh-fab-test`

_লাইভ ডাটা নেই (ক্রল হয়নি)।_
<!-- AUTO:detail:end -->

## ৩. বিদ্যমান কোড যা কাজে লাগতে পারে

- **Screen kit (`/erp/{resource}`):** Certification Type, Equipment, Certificate আর Issue Register চারটাই resource, নতুন controller লাগেনি।
- **`RecordsGrid`:** তালিকাগুলো এটা দিয়েই।
  - Fabric Test-এ Shrinkage আর Rubbing-এর দুই সারির হেডার (15/11-এ যোগ হওয়া `group`)।
  - WorkFlow কলামে বাটন বসাতে নতুন যোগ হয়েছে কলামের `render(row)` আর `read(row)` (খোঁজা/CSV-র লেখা)। শুধু client কম্পোনেন্টে ব্যবহার হয়।
- **অন্য মডিউলের master:**
  - Floor (Company Management): ল্যাব।
  - Item master: `items` আর `item_departments` (Category আর Dye/Chem)।
  - Supplier (Material Procurement), origin country, buyer, order, fabric type, fibre composition (Merchandise)।
  - Company documents (Company Management): Upload Doc-এর 📎।
- **Reminder:** Vendor-এর `AlertExpiringCoverage`-এর ধাঁচে দৈনিক কমান্ড।

## ৪. ডাটা মডেল (Entity ও Field)

মাইগ্রেশন: `2027_01_23_000100_create_colour_lab.php` (নতুন মডিউল `app/Modules/ColourLab`)।

| টেবিল | ফিল্ড |
|---|---|
| `lab_cert_types` | code (কোম্পানিতে অনন্য), name, issuing_body, active |
| `lab_equipment` | code (অনন্য), name, manufacturer, serial_no, active |
| `lab_certificates` | cert_type_id, equipment_id, document_ref (অনন্য), upload_doc, company_document_id, apply_date, applied_by (user), issue_date, valid_date, mail_to, mail_cc, notify_days (ডিফল্ট 30), notified_for, lab_floor_id, active, remarks |
| `lab_cert_register` | register_no (CIR-YYYYMM####), certificate_id, entry_date, action (ISSUED / HANDED_OVER / RETURNED / RENEWED / SURRENDERED), issued_to, buyer_id, copies, due_date, return_date, handed_by, remarks |
| `lab_trials` + `lab_trial_lines` | trial_no (TRL-…), lab_floor_id, trial_date, trial_by, status (PENDING / IN_PROGRESS / COMPLETED / CANCELLED); লাইনে item_department_id, item_id, supplier_id, brand, origin_country_id, price, claim_dose, dose_unit (% / g/l / ml/l / g), claim_cost, claim_ratio, cert_type_id, actual_dose, actual_cost, actual_ratio, result (PENDING / PASS / FAIL / RETRIAL), comments, sample_qty, required_by |
| `lab_fabric_tests` | test_no (LFT-…), test_date, buyer_id, order_id, style_no, fabric_type_id, composition_id, colour, lot_no, qty, drying (TUMBLE / LINE / FLAT / DRIP), summary (PASS / FAIL / CONDITIONAL), std/act dia, std/act gsm, coil_no, shrink_l/w, spirality, stitch_length, wash, rub_dry, rub_wet (গ্রে-স্কেল গ্রেড), rh, remarks, status (DRAFT / SUBMITTED / APPROVED / REJECTED), decision_note, decided_at/by |

## ৫. Workflow / Status ও Business Rules

- **Certificate:**
  - Brickland-এর তারকা দেওয়া সব ঘর বাধ্যতামূলক: type, equipment, ref, upload doc, apply date, apply by, issue date, valid date, DyeLab।
  - Issue Date আবেদনের আগে হতে পারে না। Valid Date ইস্যুর পরে হতে হবে।
  - Mail (To/Cc)-তে কমা দিয়ে আলাদা আসল ইমেইল।
  - Document Ref # কোম্পানিতে অনন্য।
  - রেজিস্টারে কপি ইস্যু হয়ে থাকলে মোছা যায় না, শুধু নিষ্ক্রিয় করা যায়।
- **Reminder:** `colour-lab:certificate-reminders` প্রতিদিন 06:35-এ চলে।
  - Valid Date-এর Notify BF Days দিন আগে থেকে, সক্রিয় সার্টিফিকেটের Mail (To)-তে মেল যায়, Cc সহ।
  - এক Valid Date-এর জন্য একবারই যায় (`notified_for`)।
  - Valid Date বদলালে (নবায়ন) আবার একটা পাঠায়।
- **Register:**
  - নম্বর এন্ট্রির মাস অনুযায়ী ক্রমে, সেভের transaction-এ বসে। সেভ না হলে নম্বর নষ্ট হয় না।
  - Due Date আর Return Date এন্ট্রির তারিখের আগে হতে পারে না।
  - Copies অন্তত 1।
- **Trial:**
  - অন্তত একটা লাইন লাগে। লিকার রেশিও 1:N আকারে।
  - Dye/Chem বাছলে আইটেমের brand, default vendor আর origin নিজে থেকে বসে।
  - ধাপ: Start Trial (Pending → In Progress), তারপর Complete। Complete হয় শুধু প্রতিটা লাইনের ফল (Pass/Fail/Re-trial) দেওয়া থাকলে।
  - Cancel করা যায় Pending বা In Progress অবস্থায়। Delete শুধু Pending-এ।
  - Completed বা Cancelled হলে শুধু পড়া যায়।
- **Fabric Test:**
  - Customer আর Test Date বাধ্যতামূলক। Order হতে হবে সেই Customer-এর।
  - Submit-এর আগে Summary লাগে।
  - Submitted → Approve বা Reject, নোটসহ।
  - Reject হলে ঠিক করে আবার Submit করা যায়। Approved বা Submitted হলে শুধু পড়া যায়।
  - Delete শুধু Draft-এ।

## ৬. API ও পারমিশন

পারমিশন:
- `colourlab.view`: দেখা।
- `colourlab.manage`: কাজ করা।
- `colourlab.approve`: Fabric Test approve/reject।

FACTORY_ADMIN তিনটাই পায়। FACTORY_MANAGER পায় view আর approve।

| Route | কাজ |
|---|---|
| `/erp/lab-cert-types`, `/erp/lab-equipment`, `/erp/lab-certificates`, `/erp/lab-cert-register` | screen kit (list / create / update / delete) |
| `GET /colour-lab/options` | ফর্মের সব বাছাই, filed documents সহ |
| `GET /colour-lab/items?department=` | কোনো department-এর আইটেম, brand/vendor/origin সহ |
| `/colour-lab/trials` (+ `{no}`, `{no}/start|complete|cancel`) | ট্রায়াল |
| `/colour-lab/fabric-tests` (+ `{no}`, `{no}/submit|approve|reject`) | ফ্যাব্রিক টেস্ট |

## ৭. Frontend পেজ (লাইট থিম)

- **মেনু:** নতুন মডিউল **Colour Lab**, LABORATORY section-এ। Operations ফোল্ডারে চারটা স্ক্রিন Brickland-এর ক্রমে।
- **Test Certificate Directory:**
  - "Certification List" (Add New), কলাম Brickland-এর মতো। Edit আর Delete।
  - "Dyeing Lab Certification" ফর্ম, দুই কলামে লেবেল পাশে।
  - Type আর Equipment-এর পাশে ✚, যা "New Certification Type" / "New Equipment" dialog খোলে।
  - Upload Doc-এর পাশে 📎, যা Company Management-এর filed document বাছায়।
  - বাটন: Back To List, Save, Cancel।
- **Certificate Issue Register:** Records (Action, Entry Date from/to, Clear, Search, Export) আর New register entry। এডিট হয় জায়গাতেই ("Edit register entry")।
- **Chemical Trial Workbench:**
  - "Trial Operation List": New Trial; Ref, From/To আর Status দিয়ে Search; WorkFlow কলামে Start Trial বা Complete; Action-এ Edit, Print, Cancel Trial, Delete।
  - "Trial Operation" ফর্ম: Brickland-এর ১৮টা কলামের লাইন-গ্রিড, Add Row, Print।
- **In-House Fabric Testing:**
  - "Dyeing Lab Fabric Test List" (সবুজ Add New), Shrinkage(%) আর Rubbing-এর দুই সারির হেডার।
  - WorkFlow-এ Submit বা Approve, Action-এ Edit, Reject, Delete।
  - Brickland-এর নিজের Add New "Page not found" দেয়। তাই শিটটা তালিকার কলাম ধরে বানানো হয়েছে: লটের অংশ আর ফলাফলের অংশ।
- **Print:** Chemical Trial Sheet, landscape, সঙ্গে তিনটা সই।

## ৮. Implementation Files

<!-- AUTO:impl:start -->
As-built (৪টার ৪টা স্ক্রিন):

**Backend (নতুন মডিউল)**
- `app/Modules/ColourLab/Database/Migrations/2027_01_23_000100_create_colour_lab.php`
- `app/Modules/ColourLab/Models/LabCertType.php`, `LabEquipment.php`, `LabCertificate.php`, `LabCertRegister.php`, `LabTrial.php`, `LabTrialLine.php`, `LabFabricTest.php`
- `app/Modules/ColourLab/Resources/ColourLabResource.php`, `CertTypeResource.php`, `EquipmentResource.php`, `CertificateResource.php`, `RegisterResource.php`
- `app/Modules/ColourLab/Http/Controllers/Api/ColourLabApiController.php`
- `app/Modules/ColourLab/Support/ColourLab.php`
- `app/Modules/ColourLab/Console/RemindExpiringCertificates.php`, `routes/console.php` (দৈনিক 06:35)
- `app/Modules/ColourLab/Routes/api.php`
- `app/Modules/ColourLab/Database/Seeders/ColourLabMasterSeed.php`, `database/seeders/ErpMasterDataSeeder.php`
- `app/Modules/Erp/Support/ResourceRegistry.php`
- `app/Modules/Identity/Support/MenuRegistry.php`, `Database/Seeders/PermissionSeeder.php`, `RoleSeeder.php`
- `lang/en|bn/colourlab.php`, `lang/en|bn/nav.php`

**Frontend**
- `frontend/src/app/(app)/colour-lab/[screen]/page.js`, `screens.js`, `actions.js`
- `frontend/src/components/colourlab/lab.jsx`
- `frontend/src/components/erp/records-grid.jsx` (কলামের `render`/`read`)

**Test**
- `tests/Feature/ColourLab/ColourLabTest.php`
<!-- AUTO:impl:end -->

### পরিকল্পিত নতুন/বদলানো ফাইল

উপরের as-built তালিকাই চূড়ান্ত।

## ৯. টেস্ট ও যাচাই

**Feature test:** `ColourLabTest`, ৩টা টেস্ট, ১১০টা assertion, test2 DB-তে পাস।
- **Certificate:**
  - খালি সেভে "Certification Type is required."।
  - Code আবার দিলে আটকায়।
  - Issue < Apply আটকায়। Valid ≤ Issue আটকায়।
  - ভুল ইমেইল দিলে "not-an-address is not an email address."।
  - একই Ref আবার দিলে আটকায়।
- **Register:**
  - Certificate ছাড়া আটকায়। Due Date এন্ট্রির আগে হলে আটকায়।
  - নম্বর CIR-…0001, তারপর 0002। এডিটে নম্বর বদলায় না।
  - ইস্যু হওয়া সার্টিফিকেট মোছা যায় না।
- **Reminder:** প্রথম রানে 1, দ্বিতীয় রানে 0। Valid Date বদলালে `notified_for` মুছে যায় আর আবার 1। মেয়াদ দূরে সরালে 0।
- **Trial:**
  - লাইন ছাড়া আটকায়। ভুল রেশিও দিলে "Line 1: a liquor ratio is written 1:N"।
  - নম্বর TRL-…0001।
  - Pending থেকে Complete আটকায়। Start করলে In Progress।
  - Pending লাইন থাকলে Complete আটকায়। ফল দিলে Complete হয়।
  - Completed হলে এডিট আটকায়।
  - Cancel আর Delete কাজ করে।
- **Fabric Test:**
  - Customer ছাড়া আটকায়। অন্য Customer-এর অর্ডার আটকায়। গ্রেড 6 আটকায়।
  - Summary ছাড়া Submit আটকায়।
  - Submitted অবস্থায় এডিট আটকায়।
  - Reject নোটসহ, তারপর এডিট, Submit, Approve; সিদ্ধান্তদাতার নাম আসে।
  - Approved হলে Delete আটকায়।
- **পারমিশন:** LINE_CHIEF 403 পায়।

**Regression:** `MenuAccessTest` আর `RoleMatrixTest` আবার পাস। `colourlab.approve` প্রথমে কোনো মেনুতে ছিল না, MenuAccessTest সেটা ধরেছে। এখন In-House Fabric Testing মেনুতে আছে।

**UI চেক (`AnnotechERP/tools/app_check/plans/cl01`–`cl02`):** error ০। dev-এ রাখা ডাটা:

| কাগজ | বিবরণ | অবস্থা |
|---|---|---|
| Certification Type OTS / Equipment SPEC-01 | ✚ dialog দিয়ে তৈরি: OEKO-TEX Standard 100 — OEKO-TEX; Spectrophotometer Datacolor 650 (DC650-22871) | Active |
| OTS-2026-BD-118 | Apply 15 Oct, Issue 28 Oct, Valid 25 Nov 2026, Ayesha Khatun, Ground Floor (A-GF), Notify 30, Mail lab@ / compliance@, Cc gm@ | Active। reminder একবার গেছে (log) |
| CIR-2026110001 | SGS Bangladesh - audit team, LPP, 2 কপি, Due 9 Nov, Handed By Ayesha Khatun | Issued |
| TRL-2026110001 | Ground Floor (A-GF), ACETIC ACID GLACIAL 1.2 g/l → 1.0 (Pass), ADASIL 5018 2% → 2.4 (Re-trial) | Completed |
| LFT-2026110001 | LPP, 770MR, Black, লট B-1118, 25, Tumble Dry, Dia 72/71.5, GSM 180/176, Shrinkage -4.5/-3, SP 3.5 | Approved ("Within LPP limits") |

- **যে বাধাগুলো দেখা গেছে:**
  - খালি সার্টিফিকেট সেভে ৯টা "… is required."।
  - ভুল রেশিও দিলে "Line 2: …"।
  - Pending লাইন থাকলে Complete আটকায়।
  - Summary ছাড়া Submit আটকায়।
- **প্রিন্ট:** Chemical Trial Sheet-এর preview দেখা হয়েছে: হেডার, দুই লাইন, তিন সই, ঠিক আছে।
- **Master seed:** `ColourLabMasterSeed` সাতটা সার্টিফিকেট ধরন আর চারটা যন্ত্র দেয় (`erp:seed-masters`)।

## ১০. খোলা প্রশ্ন

১. **Notify BF Days:** Brickland-এর তালিকায় কলামটা আছে, কিন্তু ফর্মে কোনো ঘর নেই। আমরা ফর্মে ঘরটা যোগ করেছি (ডিফল্ট 30), আর রিমাইন্ডার সেটা মেনে চলে।

২. **Lab / DyeLab:** Brickland ডেমোতে "Select Lab Floor" ফাঁকা দেখায়। মনে হয় ল্যাব হিসেবে চিহ্নিত ফ্লোরই সেখানে আসে। আমাদের ফ্লোর master-এ এমন চিহ্ন নেই, তাই সব ফ্লোর দেখায়।

৩. **Upload Doc:** Brickland-এ ঘরটা লেখা যায়, পাশে 📎 "Pick the filed document"। আমরা 📎 দিয়ে Company Management-এর filed document বাছাই করাই। আসল ফাইল আপলোড এখনো নেই।

৪. **In-House Fabric Testing:** Brickland-এর Add New "Page not found" দেয়। শিট বানানো হয়েছে তালিকার কলাম ধরে। Summary (Pass/Fail/Conditional), Drying (Tumble/Line/Flat/Drip), গ্রে-স্কেল গ্রেড আর Submit → Approve/Reject ধাপ আমাদের অনুমান।

৫. **Trial-এর ধাপ:** Brickland ডেমোতে কোনো ট্রায়াল সেভ করা যায় না (Lab-এর তালিকা ফাঁকা), তাই WorkFlow কলামে কী দেখায় তা দেখা যায়নি। Start Trial আর Complete আমাদের অনুমান, Brickland-এর অবস্থাগুলোর সঙ্গে মিলিয়ে।

৬. **খালি সেভের বার্তা:** Brickland প্রথম ভুলটাই দেখায়। আমরা সব একসাথে দেখাই।

## ১১. As-built নোট

- **Brickland probe (`erp_crawler/plans/cl01`–`cl04`):**
  - তালিকা, Add New পেজ (`/new`), দুটো ✚ dialog আর Register-এর ফর্ম দেখা হয়েছে।
  - ডেমোতে Lab, Equipment আর Certificate-এর তালিকা ফাঁকা, তাই কোনো কিছু সেভ করে দেখা যায়নি।
  - In-House Fabric Testing পেজ লোড হতে ৯০ সেকেন্ডের বেশি নেয়, আর Add New 404 দেয়।
- **নতুন মডিউল:** `ColourLab` ফোল্ডার নিজে থেকেই খুঁজে নেওয়া হয়। Migration, route আর console কমান্ড আলাদা নিবন্ধন লাগেনি।
- **Scheduler:** নতুন সূচির পর scheduler কন্টেইনার restart করা হয়েছে।
