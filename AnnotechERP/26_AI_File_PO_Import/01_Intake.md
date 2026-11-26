# Intake

> **স্ট্যাটাস:** ✅ সম্পন্ন (৫টার ৫টা স্ক্রিন)
> **মডিউল:** [AI File & PO Import](00_Index.md) · **ফেজ ৮:** পোর্টাল, AI ও অ্যানালিটিক্স · **কী:** `26/01`

**⬅ ফিরে যান:** [AI File & PO Import](00_Index.md) | [AnnotechERP Index](../00_Index.md)

---

## ১. স্ক্রিন (Brickland ERP থেকে)

<!-- AUTO:screens:start -->
| # | স্ক্রিন | পাথ | ট্যাব | ফিল্ড | ডায়ালগ |
|---|---|---|---|---|---|
| 1 | Universal File Intake | `/m/ai-file-import/omni-file-intelligence` | 1 | 3 | 0 |
| 2 | Document Recognition Engine | `/m/ai-file-import/recognition-engine` | 1 | 3 | 1 |
| 3 | Field Extraction Rules | `/m/ai-file-import/field-extraction` | 1 | 3 | 1 |
| 4 | Data Validation Engine | `/m/ai-file-import/validation-engine` | 1 | 3 | 1 |
| 5 | Import Approval Workflow | `/m/ai-file-import/approval-workflow` | 1 | 3 | 1 |
<!-- AUTO:screens:end -->

## ২. প্রতিটা স্ক্রিনের লাইভ বিবরণ (ফিল্ড, ট্যাব, গ্রিড, ডায়ালগ, ভ্যালিডেশন)

<!-- AUTO:detail:start -->
#### Universal File Intake

`/m/ai-file-import/omni-file-intelligence`

**ভিউ**
> 01 of 32 Demo data

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন/প্লেসহোল্ডার |
|---|---|---|---|
| Search Universal file intake | search |  | Search intake… |
| Filter by stage | select |  | All stages, Queued, Recognising, Extracting, Validating, Awaiting approval, Posted, Failed |
| Items per page | select |  | 10, 25, 50, 100 |
- গ্রিড কলাম: File, Recognised as, Buyer, Size, Confidence, Stage
- সেকশন: Universal intake
- বাটন/অ্যাকশন: Drop a file, File, Recognised as, Buyer, Size, Confidence, Stage

#### Document Recognition Engine

`/m/ai-file-import/recognition-engine`

**ভিউ**
> 02 of 32 Demo data

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন/প্লেসহোল্ডার |
|---|---|---|---|
| Search Document recognition signatures | search |  | Search signatures… |
| Filter by family | select |  | All families, Purchase order, Tech pack, Inquiry, Bill of materials, Cost sheet, Commercial document, Artwork, Productio |
| Items per page | select |  | 10, 25, 50, 100 |
- গ্রিড কলাম: Signature, Family, Buyer, Hit rate, Matched, ENABLED
- সেকশন: Recognition signatures
- বাটন/অ্যাকশন: New signature, Signature, Family, Buyer, Hit rate, Matched

**ডায়ালগ: New signature** (মডাল)

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন |
|---|---|---|---|
| Signature* | text | ✔ |  |
| Family | select |  | Select…, Purchase order, Tech pack, Inquiry, Bill of materials, Cost sheet, Commercial document, Artwork, Production rep |
| Buyer | select |  | Select…, H&M, Primark, Zara, Target, Uniqlo, Kmart, C&A, Landmark, Any |
| Marker | select |  | Select…, Header text, Logo region, Sheet name, Column run, File naming, Sender address |
| Hit rate | number |  |  |
| Times matched | number |  |  |
| Last matched | date |  |  |
- খালি Save-এ মেসেজ: Name the signature.
- invalid চিহ্নিত ফিল্ড: 1টা

#### Field Extraction Rules

`/m/ai-file-import/field-extraction`

**ভিউ**
> 03 of 32 Demo data

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন/প্লেসহোল্ডার |
|---|---|---|---|
| Search Field extraction rules | search |  | Search fields… |
| Filter by family | select |  | All families, Purchase order, Tech pack, Inquiry, Bill of materials, Cost sheet, Commercial document |
| Items per page | select |  | 10, 25, 50, 100 |
- গ্রিড কলাম: Field, Family, Found by, Type, Accuracy, MANDATORY
- সেকশন: Extraction rules
- বাটন/অ্যাকশন: New rule, Field, Family, Found by, Type, Accuracy

**ডায়ালগ: New rule** (মডাল)

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন |
|---|---|---|---|
| Field* | text | ✔ |  |
| Family | select |  | Select…, Purchase order, Tech pack, Inquiry, Bill of materials, Cost sheet, Commercial document |
| Found by | select |  | Select…, Labelled value, Table column, Fixed region, Regular expression, Model inference |
| ERP column | text |  |  |
| Type | select |  | Select…, Text, Number, Date, Currency, Code |
| Accuracy | number |  |  |
- খালি Save-এ মেসেজ: Name the field.
- invalid চিহ্নিত ফিল্ড: 1টা

#### Data Validation Engine

`/m/ai-file-import/validation-engine`

**ভিউ**
> 04 of 32 Demo data

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন/প্লেসহোল্ডার |
|---|---|---|---|
| Search Data validation rules | search |  | Search validations… |
| Filter by kind | select |  | All kinds, Master lookup, Range, Format, Cross-field, Duplicate |
| Items per page | select |  | 10, 25, 50, 100 |
- গ্রিড কলাম: Validation, Kind, On failure, Triggered, Pass rate, ENABLED
- সেকশন: Validation rules
- বাটন/অ্যাকশন: New validation, Validation, Kind, On failure, Triggered, Pass rate

**ডায়ালগ: New validation** (মডাল)

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন |
|---|---|---|---|
| Validation* | text | ✔ |  |
| Kind | select |  | Select…, Master lookup, Range, Format, Cross-field, Duplicate |
| Applies to | select |  | Select…, Purchase order, Tech pack, Inquiry, Bill of materials, Cost sheet, Commercial document, Every family |
| On failure | select |  | Select…, Block the post, Warn and continue, Route to review |
| Times triggered | number |  |  |
| Pass rate | number |  |  |
- খালি Save-এ মেসেজ: Name the validation.
- invalid চিহ্নিত ফিল্ড: 1টা

#### Import Approval Workflow

`/m/ai-file-import/approval-workflow`

**ভিউ**
> 05 of 32 Demo data

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন/প্লেসহোল্ডার |
|---|---|---|---|
| Search Import approval steps | search |  | Search steps… |
| Filter by family | select |  | All families, Purchase order, Tech pack, Inquiry, Bill of materials, Cost sheet, Commercial document, Every family |
| Items per page | select |  | 10, 25, 50, 100 |
- গ্রিড কলাম: #, Step, Approver, Auto-skip, SLA, Pending, ENABLED
- সেকশন: Approval workflow
- বাটন/অ্যাকশন: New step, #, Step, Approver, Auto-skip, SLA, Pending

**ডায়ালগ: New step** (মডাল)

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন |
|---|---|---|---|
| Step* | text | ✔ |  |
| Applies to | select |  | Select…, Purchase order, Tech pack, Inquiry, Bill of materials, Cost sheet, Commercial document, Every family |
| Approver | select |  | Select…, Merchandiser, Planning, Commercial, Costing, Quality, Division head |
| Sequence | number |  |  |
| Auto-skip above | number |  |  |
| Service level | number |  |  |
| Pending now | number |  |  |
- খালি Save-এ মেসেজ: Name the step.
- invalid চিহ্নিত ফিল্ড: 1টা
<!-- AUTO:detail:end -->

## ৩. বিদ্যমান কোড যা কাজে লাগতে পারে

- **ERP resource kit:** `ResourceDefinition` + `RecordsGrid` / `DialogFormButton` / `EntryForm`। Buyer Gateway (25)-এর মতো একই ছাঁচ (`importScreen()`), টাইল কম্পোনেন্ট শেয়ার করা।
- **ফাইল আপলোড:** `StoreFileAttachment` (PDF, Excel, ছবি), route handler দিয়ে stream, `FileApiController`-এর পারমিশন ম্যাপ (`ai_intake_file`)।
- **Masters:** buyer master।

## ৪. ডাটা মডেল (Entity ও Field)

মাইগ্রেশন `2027_03_25_000100_create_ai_import_intake`:
- **`ai_intake_files`:**
  - ফাইল (নাম, বা আপলোড করা ফাইলের ডাউনলোড পাথ)।
  - যেভাবে এসেছে (Email / Portal / Watched folder / SFTP / Manual)।
  - যা হিসেবে চেনা (৭টা ধরন বা Unrecognised), buyer (খালি = চেনা যায়নি)।
  - পাওয়ার দিন, আকার (MB), নিশ্চয়তা (%)।
  - ধাপ (Queued → Recognising → Extracting → Validating → Awaiting approval → Posted / Failed)।
- **`ai_signatures`:** নাম (অনন্য), ধরন (৮টা), buyer (খালি = Any), চিহ্ন (Header text / Logo region / Sheet name / Column run / File naming / Sender address), মেলার হার, কতবার মিলেছে, শেষ মিলেছে, চালু।
- **`ai_extraction_rules`:** ফিল্ড, ধরন (৬টা ডাটা-ধরন), যেভাবে পাওয়া (Labelled value / Table column / Fixed region / Regex / Model), ERP কলাম (`table.column`), টাইপ (Text / Number / Date / Currency / Code), নির্ভুলতা, বাধ্যতামূলক।
- **`ai_validations`:** নাম, ধরন (Master lookup / Range / Format / Cross-field / Duplicate), যাতে খাটে (৬টা ধরন বা Every family), ব্যর্থ হলে (Block / Warn / Review), কতবার চলেছে, পাসের হার, চালু।
- **`ai_approval_steps`:**
  - নাম, যাতে খাটে, অনুমোদনকারী (Merchandiser / Planning / Commercial / Costing / Quality / Division head)।
  - ক্রম (ধরন + ক্রম অনন্য), এর বেশি নিশ্চয়তায় নিজে বাদ (%), SLA (ঘণ্টা), অপেক্ষমাণ, চালু।

## ৫. Workflow / Status ও Business Rules

- **ইনটেক:**
  - ফাইল লাগে — আপলোড, বা শুধু নাম।
  - আপলোড হলে নাম আর আকার ফাইল থেকে আসে। হাতে লেখা আকার মানা হয় না। একটুও কিছু থাকা ফাইল কখনো 0.0 MB দেখায় না।
  - আপলোডের পাথ হতে হবে এই কোম্পানির ইনটেকের ফাইল।
  - ফাইল চেনার পরেই পড়া হয়: ধরন না থাকলে বা Unrecognised হলে Extracting / Validating / Awaiting approval / Posted আটকায়। Posted ফাইলকে পরে Unrecognised করাও আটকায়।
  - পাওয়ার দিন খালি রাখলে আজ; ভবিষ্যৎ আটকায়। নিশ্চয়তা ০–১০০%।
- **সিগনেচার:** নাম একবারই (বড়-ছোট হাতের পার্থক্য ধরে না)। শেষ মিলেছে ভবিষ্যতে হতে পারে না। গ্রিডের টগলে চালু / বন্ধ।
- **তোলার নিয়ম:** ERP কলাম `table.column` (ছোট হাতে করে রাখা)। এক ধরনে এক কলাম একটাই নিয়ম ভরে (অন্য ধরনে একই কলাম চলে)। গ্রিডের টগলে বাধ্যতামূলক।
- **যাচাই:** খালি রাখলে Every family আর Warn and continue।
- **অনুমোদনের ধাপ:**
  - ক্রম খালি রাখলে ওই ধরনের শেষে বসে; এডিটে খালি রাখলে আগের ক্রম থাকে।
  - এক ধরনে এক ক্রম একবারই। অন্য ধরনে সরালেও এটা চেক হয়।
  - SLA ১–৭২০ ঘণ্টা।

## ৬. API ও পারমিশন

পারমিশন: `aiimport.intake.view`, `aiimport.intake.manage` (Factory Admin / Factory Manager; Auditor শুধু দেখেন)।

API:
- `/api/v1/erp/ai-intake-files`, `ai-signatures`, `ai-extraction-rules`, `ai-validations`, `ai-approval-steps`।
- `GET /api/v1/ai-import/options` — buyer-এর তালিকা।
- `POST /api/v1/ai-import/intake-files` — ফাইল আপলোড (৫০ MB পর্যন্ত; PDF, Excel, ছবি)।

## ৭. Frontend পেজ (লাইট থিম)

Route `/ai-file-import/[screen]`; সাইডবারে Overview-তে "AI File & PO Import", ভেতরে Intake ফোল্ডার।
- **Universal File Intake** (`omni-file-intelligence`):
  - কলাম: ফাইল (নিচে যেভাবে এসেছে), যা হিসেবে চেনা, buyer, আকার, নিশ্চয়তা (বার), ধাপ (রঙিন)।
  - ধাপ দিয়ে ফিল্টার।
  - টাইল: মোট, পোস্ট, ব্যর্থ, গড় নিশ্চয়তা।
- **Document Recognition Engine** (`recognition-engine`):
  - কলাম: সিগনেচার (নিচে চিহ্ন), ধরন, buyer, মেলার হার, মিলেছে, চালু টগল।
  - টাইল: মোট, চালু, গড় মেলার হার, ধরন আওতায়।
- **Field Extraction Rules** (`field-extraction`):
  - কলাম: ফিল্ড (নিচে ERP কলাম), ধরন, যেভাবে পাওয়া, টাইপ, নির্ভুলতা, বাধ্যতামূলক টগল।
  - টাইল: মোট, বাধ্যতামূলক, গড় নির্ভুলতা, ধরন আওতায়।
- **Data Validation Engine** (`validation-engine`):
  - কলাম: যাচাই (নিচে যাতে খাটে), ধরন, ব্যর্থ হলে, কতবার চলেছে, পাসের হার, চালু টগল।
  - টাইল: মোট, আটকায়, চালু, গড় পাসের হার।
- **Import Approval Workflow** (`approval-workflow`):
  - কলাম: #, ধাপ (নিচে যাতে খাটে), অনুমোদনকারী, নিজে বাদ (97.0%), SLA (8 h), অপেক্ষমাণ, চালু টগল।
  - টাইল: মোট, চালু, কারও অপেক্ষায় (যোগফল), ধরন আওতায়।
- **Grid kit:** number কলামে `decimals` (নির্দিষ্ট দশমিক) যোগ হয়েছে।

## ৮. Implementation Files

<!-- AUTO:impl:start -->
As-built (৫টার ৫টা স্ক্রিন):

**Backend (`app/Modules/AiImport`, নতুন মডিউল)**
- `Database/Migrations/2027_03_25_000100_create_ai_import_intake.php`
- `Models/IntakeFile.php`, `Signature.php`, `ExtractionRule.php`, `ImportValidation.php`, `ApprovalStep.php`
- `Resources/AiImportResource.php` (base), `IntakeFileResource.php`, `SignatureResource.php`, `ExtractionRuleResource.php`, `ValidationResource.php`, `ApprovalStepResource.php`
- `Support/ImportBook.php`, `Http/Controllers/Api/AiImportApiController.php`, `Routes/api.php`
- `app/Modules/Erp/Support/ResourceRegistry.php`, `PermissionSeeder.php`, `RoleSeeder.php`, `MenuRegistry.php` (মডিউল `AI_IMPORT`, ফোল্ডার `AI_IMPORT.INTAKE`), `app/Shared/Files/Http/Controllers/Api/FileApiController.php` (বদলানো)
- `lang/en/aiimport.php`, `lang/bn/aiimport.php` (নতুন); `lang/*/nav.php` (বদলানো)

**Frontend**
- `app/(app)/ai-file-import/[screen]/page.js`, `actions.js`, `screens.js` (নতুন)
- `app/api/ai-file-import/intake-file/route.js` (নতুন)
- `components/buyergateway/tiles.jsx`, `components/erp/records-grid.jsx` (বদলানো)

**Test**
- `tests/Feature/AiImport/AiImportIntakeTest.php` (নতুন)
<!-- AUTO:impl:end -->

### পরিকল্পিত নতুন/বদলানো ফাইল

উপরের as-built তালিকাই চূড়ান্ত।

## ৯. টেস্ট ও যাচাই

**Feature test (`AiImportIntakeTest.php`, ৪টা টেস্ট / ১২১টা assertion):** test2 DB-তে পাস; MenuAccess ৬ / ৩৪ পাস।
- **ইনটেক:**
  - picker-এ কোম্পানির buyer; ফাইল ছাড়া আটকায়।
  - নাম ছেঁটে সেভ; Queued, আজ, ১.৪ MB, ৯৬.২%।
  - ভবিষ্যতের দিন, ১০১% আর অজানা পথ আটকায়।
  - না চেনা ফাইল Extracting আটকায়; Unrecognised + Failed চলে; পরে Posted আটকায়।
  - চেনা ফাইল Posted + buyer চলে; Posted-কে Unrecognised করা আটকায়।
  - আপলোড: নিজের নামে দেখায়, আকার আপলোড থেকে (হাতে লেখা ৯৯ বাদ), ডাউনলোড হয়; ছোট ফাইল 0.1 MB।
  - অজানা পাথ আর ৫০ MB-এর বেশি আটকায়।
- **সিগনেচার, তোলার নিয়ম, যাচাই:**
  - সিগনেচার: নাম ছাড়া, একই নাম (অন্য হাতে লেখা), ভবিষ্যৎ আর Unrecognised ধরন আটকায়; buyer খালি = Any; টগল বন্ধ হয়; নিজের নামে আবার সেভ চলে।
  - নিয়ম: ফিল্ড ছাড়া আটকায়; `Order_Line.Quantity` → `order_line.quantity`; একই ধরনে একই কলাম আটকায়, অন্য ধরনে চলে; `quantity` (টেবিল ছাড়া) আর Artwork আটকায়; বাধ্যতামূলক টগল।
  - যাচাই: নাম ছাড়া আটকায়; খালি রাখলে Every; অজানা ফলাফল, Artwork আর ১২০% আটকায়।
- **অনুমোদনের ধাপ:**
  - নাম ছাড়া আটকায়; ক্রম নিজে বসে ১, ২ (PO) আর ১ (Cost sheet)।
  - PO-তে ২ আবার আটকায়; Cost sheet-এর ধাপকে PO-তে সরালে ক্রম ১-এর জন্য আটকায়; ক্রম ৩ দিয়ে সরানো চলে।
  - এডিটে খালি ক্রম আগেরটা রাখে; SLA ০ আর অজানা অনুমোদনকারী আটকায়; টগল বন্ধ হয়।
- **কে পারেন:**
  - Auditor দেখেন, ফাইল নামাতে পারেন; যোগ বা আপলোড করতে পারেন না। Manager পারেন।
  - Storekeeper দেখেন না, picker-ও না, ফাইলও নামাতে পারেন না।
  - অন্য কোম্পানি কিছু দেখে না, এই buyer নিতে পারে না, এই কোম্পানির আপলোড নিজের ইনটেকে বসাতে পারে না।

**UI চেক (`app_check/plans/ai01`, ২০২৬-১১-১২, owner; error 0; dev ডাটা রাখা আছে):**
- ইনটেক: PNG আপলোড (Tech pack, H&M, ৯১.৪%, Awaiting approval) ✔
  - scan_20260830_0004.jpg-কে চেনা ছাড়া Extracting করলে বার্তা আসে → Unrecognised + Failed ✔
  - primark-po-88412.pdf (PRIMARK, ১.৪ MB, Posted) ✔
  - টাইল ৩ / ১ / ১ / ৭০.৭% ✔
- সিগনেচার: নাম ছাড়া বার্তা; H&M PO header block আর Generic BOM sheet (Any); টগলে BOM বন্ধ → চালু ১ ✔
- তোলার নিয়ম: Order quantity (কলাম ছোট হাতে); একই কলামে দ্বিতীয় নিয়মে বার্তা → Ship date; টগলে বাধ্যতামূলক ✔
- যাচাই: Buyer master (Block) আর Ship date (Cross-field, PO) ✔
- অনুমোদন: PO-তে ১, ২ নিজে বসে; Division head (Every, ক্রম ১); PO-তে আবার ২ দিলে "Step 2 is already taken" ✔
- টাইল: অপেক্ষায় ৯; Auto-skip 97.0%, SLA 8 h ✔

## ১০. খোলা প্রশ্ন

তিনটার উত্তরই পাওয়া গেছে (২০২৬-১১-১২), আর বানানো হয়েছে — নিচের "আসল AI দিয়ে পড়া" দেখুন:
১. **আসল AI দিয়ে ফাইল পড়া:** হ্যাঁ। সব AI-এর জন্য API key লাগবে; কোন AI কাজ করবে, তা আলাদা settings থেকে ঠিক হবে (Claude, OpenAI, Gemini)। আপলোড হলেই নিজে থেকে পড়বে, settings থেকে বন্ধ করা যায়।
২. **আর কোন ধরনের ফাইল:** হ্যাঁ — ইমেইল (.eml/.msg), CSV আর Word (.docx) নেওয়া হয়।
৩. **অনুমোদন কোথায়:** হ্যাঁ — ERP-র Approval Center-এ (entity `IMPORT_FILE`)।

## ১১. As-built নোট

- **লাইভ প্রোব:** `tools/erp_crawler/out/ai01`। Brickland-এর ক্রম 01–05 of 32; আমাদের সাইডবারে এখন যতগুলো বানানো হয়েছে, ততগুলোর মধ্যে ক্রম।
- **Brickland থেকে পার্থক্য:**
  - ইনটেকে ফাইল সত্যি আপলোড করা যায়, আকার নিজে বসে। Brickland-এ শুধু নাম লেখা।
  - অনুমোদনের ক্রম খালি রাখলে নিজে বসে।
  - ইনটেকের ধাপের badge রঙিন, কিন্তু Brickland-এর মতো আইকন নেই (kit-এর badge)।
- **Test আর UI চেকে ধরা পড়া, ঠিক করা হয়েছে:**
  - খুব ছোট আপলোড 0.0 MB দেখাত। এখন কমপক্ষে 0.1 MB, আপলোড endpoint আর সারি একই নিয়মে (`ImportBook::sizeMb`)।
  - ফর্মের `accept`-এ CSV / Word / ইমেইল ছিল, কিন্তু ফাইল-স্টোর সেগুলো নেয় না (415)। এখন `accept` আর hint মিলিয়ে দেওয়া হয়েছে।

### আসল AI দিয়ে পড়া (২০২৬-১১-১২)

- **Settings → AI Services** (`/system-settings/ai-services`, পারমিশন `settings.company.manage`):
  - **Services ট্যাব:** প্রতি প্রোভাইডারে একটা সার্ভিস — মডেল, API key, চালু / বন্ধ।
    - Key এনক্রিপ্ট করে রাখা হয়, কখনো ফেরত পাঠানো হয় না; শুধু শেষ চারটা অক্ষর (`••••ABCD`) দেখায়।
    - এডিটে key খালি রাখলে আগেরটাই থাকে। নতুন key বা মডেল দিলে আগের পরীক্ষার ফল মুছে যায়।
    - **Test connection** একটা ছোট প্রশ্ন করে, ফল সারিতে লিখে রাখে।
  - **Features ট্যাব:** "ইমপোর্ট করা ফাইল পড়া" আর "অর্ডার বুক বিশ্লেষণ" — প্রতিটা কোন সার্ভিস ব্যবহার করবে (বা AI নেই), নিজে থেকে চলবে কিনা, চালু কিনা।
- **পড়ার ধাপ** (`AiImport/Services/IntakeReader.php`, queue job `ReadIntakeFile`):
  - আপলোড সেভ হলেই (নিজে-চলা চালু থাকলে) বা **Read with AI** চাপলে পড়া শুরু হয়। সারির ধাপ Queued → Recognising।
  - AI বলে: ধরন, কোম্পানির কোন বায়ার, নিশ্চয়তা, এক-দুই লাইনের সারাংশ, আর ওই ধরনের চালু extraction rule-গুলোর মান। মানগুলো `ai_intake_values`-এ থাকে।
  - তারপর:
    - চেনা না গেলে Failed।
    - বায়ার না পেলে বা বাধ্যতামূলক মান না পেলে Validating — কারণ নোটে লেখা থাকে।
    - নইলে Awaiting approval, Approval Center-এ রিকোয়েস্ট যায়। অনুমোদনে Posted, প্রত্যাখ্যানে Failed, ফেরত পাঠালে Validating। Workflow না থাকলে সাথে সাথে Posted।
  - মিলে যাওয়া সিগনেচারের "কতবার মিলেছে" এক বাড়ে।
  - AI ব্যর্থ হলে বা রাজি না হলে Failed, সহজ ভাষায় কারণসহ ("Claude refused the API key — …")। কিছু অনুমান করে বসানো হয় না।
- **যেভাবে পাঠানো হয়:**
  - PDF আর ছবি যেমন আছে তেমনই যায়। Excel / CSV (PhpSpreadsheet), Word, ইমেইল (ভেতরের অ্যাটাচমেন্টসহ) আর Outlook .msg লেখা হিসেবে যায়।
  - বেশি লেখা হলে কেটে না দিয়ে ফেরত দেওয়া হয়।
  - ইনটেকের সীমা ২০ MB। বাকি ERP-র ফাইল-নিয়ম (১০ MB, ছবি / PDF / Excel) বদলায়নি; শুধু ইনটেক বেশি type নেয়।
- **সার্ভিস অনুযায়ী:**
  - Claude: Anthropic-এর PHP SDK, structured output। যে মডেলে চলে সেখানে server-side refusal fallback।
  - OpenAI: Responses API, strict JSON schema।
  - Gemini: generateContent, `responseJsonSchema`।
- **ইনটেক স্ক্রিন:**
  - প্রতিটা সারিতে **Read with AI** বোতাম। ফাইলের নিচের লাইনে AI-এর নোট (না থাকলে কীভাবে এসেছে)।
  - এডিটে শুধু-দেখার ঘর: Read by, Note, What the AI read, Values found।
- **টেস্ট:**
  - `tests/Feature/AiCore/AiServicesTest.php` (৫ / ৬৭) আর `tests/Feature/AiImport/AiImportReadingTest.php` (৬)। AiImport-এর মোট ১০ টেস্ট / ২২১ assertion পাস।
  - Approval, MenuAccess আর Merchandise AI পাস।
  - Files / FileAttachment-এর ৭টা error পুরোনো `imagejpeg` পরিবেশের সমস্যা; বাকি ১৩টা পাস।
  - OpenAI আর Gemini-এর অনুরোধ fake করে পরীক্ষা করা হয়েছে (খরচ নেই)।
- **UI চেক (`plans/ai01b`, `ai01c`):**
  - নকল key দিয়ে Claude সার্ভিস যোগ করা হলো। Test connection আসল Anthropic API-তে গেল আর 401 পেল, অর্থাৎ SDK-র অনুরোধ ঠিক আছে। বার্তা দেখাল "Claude refused the API key …"।
  - ফাইল পড়ার কাজে Claude বাছা হলো।
  - PDF আপলোড করলে নিজে থেকে পড়া শুরু হলো → Failed, একই পরিষ্কার নোটসহ। Read with AI চাপলে আবার পড়ল।
- **পথে ঠিক করা:**
  - SDK-র কাঁচা error (JSON-সহ) সারিতে দেখাত। এখন 401 / 403 / 404 / 429 / 5xx প্রতিটার জন্য পরিষ্কার বার্তা।
  - ইমেইলের CSV অ্যাটাচমেন্ট বাদ পড়ত। এখন নাম থাকা সব অংশই অ্যাটাচমেন্ট হিসেবে পড়া হয়।
  - Approval engine `approval_request_id` লেখে; আলাদা migration দিয়ে কলামটা যোগ হয়েছে।
- **dev ডাটা:** নকল key-র Claude সার্ভিস আর একটা পড়তে-ব্যর্থ PDF রাখা আছে। UI চেকের সময় dev-এ একটা Gemini সার্ভিস (`gemini-2.5-flash`) যোগ হয়েছে, যা UI চেক যোগ করেনি — সেটা যেমন ছিল তেমনই রাখা হয়েছে।
- **নিরাপত্তা নোট:** `composer audit`-এ পুরোনো symfony প্যাকেজের ৭টা advisory দেখায় (PhpSpreadsheet-এর নয়)। এগুলো আলাদা করে আপডেট করা দরকার।
