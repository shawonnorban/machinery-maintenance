# Reports

> **স্ট্যাটাস:** ✅ সম্পন্ন (১টার ১টা স্ক্রিন — ১৩টা রিপোর্ট)
> **মডিউল:** [Colour Lab](00_Index.md) · **ফেজ ৫:** প্রোডাকশন · **কী:** `16/02`

**⬅ ফিরে যান:** [Colour Lab](00_Index.md) | [AnnotechERP Index](../00_Index.md)

---

## ১. স্ক্রিন (Brickland ERP থেকে)

<!-- AUTO:screens:start -->
| # | স্ক্রিন | পাথ | ট্যাব | ফিল্ড | ডায়ালগ |
|---|---|---|---|---|---|
| 1 | Lab Test Reports | `/m/dyeing-lab/dyeing-lab-reports` | 1 | 2 | 0 |
<!-- AUTO:screens:end -->

## ২. প্রতিটা স্ক্রিনের লাইভ বিবরণ (ফিল্ড, ট্যাব, গ্রিড, ডায়ালগ, ভ্যালিডেশন)

<!-- AUTO:detail:start -->
#### Lab Test Reports

`/m/dyeing-lab/dyeing-lab-reports`

**ভিউ**

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন/প্লেসহোল্ডার |
|---|---|---|---|
| Export to Excel? | select |  | Fabric Test — Fabric Test Register, Fabric Test — Dia and GSM Variance Statement, Fabric Test — Colour Fastness Statemen |
| Export to Excel? | checkbox |  |  |
- সেকশন: Selection of Parameters
- বাটন/অ্যাকশন: 📄 Preview
<!-- AUTO:detail:end -->

## ৩. বিদ্যমান কোড যা কাজে লাগতে পারে

- **উৎস:** 16/01-এর সব টেবিল: ফ্যাব্রিক টেস্ট, ট্রায়াল আর তার লাইন, সার্টিফিকেট আর ইস্যু রেজিস্টার। ল্যাবের নাম আর অবস্থান আসে ফ্লোর আর বিল্ডিং থেকে।
- **ফলাফলের গ্রিড:** `RecordsGrid`-এর `plain` মোড, মানে সব সারি এক পাতায় আর পেজার নেই, Brickland-এর টেবিলের মতো। কলামের `render`/`read` (16/01-এ যোগ হয়েছিল) দিয়ে অবস্থার মান শব্দে দেখায়।

## ৪. ডাটা মডেল (Entity ও Field)

রিপোর্টের নিজের কোনো টেবিল নেই। তবে Brickland-এর দুটো রিপোর্ট এমন তথ্য পড়ে যা 16/01-এর ফ্যাব্রিক টেস্টে ছিল না। তাই মাইগ্রেশন `2027_01_24_000100_add_fastness_and_lab_to_fabric_tests.php` তিনটা ঘর যোগ করেছে:
- `light` আর `perspiration` (গ্রে-স্কেল গ্রেড): Colour Fastness Statement-এর জন্য।
- `lab_floor_id`: Lab Master List-এ প্রতিটা ল্যাবের টেস্টের সংখ্যার জন্য।

তিনটাই In-House Fabric Testing-এর শিটে যোগ হয়েছে।

## ৫. Workflow / Status ও Business Rules

- **কীভাবে পড়ে:** তালিকা থেকে একটা রিপোর্ট বাছুন, তারপর Preview। রিপোর্ট সেই মুহূর্তে টেবিল থেকে পড়ে। Brickland-এর মতো আর কোনো ঘর নেই।
- **URL:** বাছাই করা রিপোর্ট `?report=<key>` হিসেবে URL-এ থাকে, পেজ reload হয় না। লিংক খুললে সরাসরি সেই রিপোর্ট আসে।
- **Export to Excel?:** টিক দিলে Preview গ্রিডটা CSV ফাইল হিসেবে নামিয়ে দেয়।
- **Fabric Test-এর রিপোর্ট:**
  - Result = Summary (Pass / Conditional / Fail)।
  - Failed and Conditional-এ Summary কলামে সিদ্ধান্তের নোট, না থাকলে Remarks।
  - Pass rate = পাস ÷ যেগুলোর ফল দেওয়া হয়েছে। ফলহীনগুলো Pending হিসেবে আলাদা।
- **Chemical Trial-এর রিপোর্ট:**
  - ট্রায়ালের Result লাইন থেকে আসে: কোনো লাইন Fail হলে Fail, কোনো লাইন Re-trial হলে Re-trial, সব Pass হলে Pass, নইলে Pending।
  - Batch (kg) = লাইনের Sample Qty-র যোগফল।
  - Dosage = আসল ডোজ, না থাকলে দাবি করা ডোজ।
  - Awaiting Sign-off দেখায় খোলা ট্রায়াল আর সেটা কিসের অপেক্ষায়: Start, লাইনের ফল, নাকি Complete।
- **Certification-এর রিপোর্ট:**
  - অবস্থা: Inactive, Expired (মেয়াদ শেষ), Renewal Due (Notify BF Days-এর ভেতরে) অথবা Valid।
  - Renewal Due List-এ শুধু সক্রিয়গুলোর মধ্যে Due আর Expired, যার মেয়াদ সবচেয়ে কাছে সেটা আগে।
- **Lab Master List:** কোনো ট্রায়াল, টেস্ট বা সার্টিফিকেট যে ফ্লোরগুলোর নাম নেয়, তাদের তালিকা আর গণনা।

## ৬. API ও পারমিশন

- পারমিশন `colourlab.report.view` (নতুন)। FACTORY_ADMIN আর FACTORY_MANAGER পায়।
- `GET /colour-lab/reports/{report}`: ১৩টা key, অজানা হলে 404। ফেরত দেয় `columns` (key আর type) আর `rows`।
  - type `date`, `number`, `int` আর `pct` ছাড়া অন্য type (যেমন summary, trial_status, cert_status) মানে frontend মানটা `colourlab.<type>.<value>` থেকে শব্দে দেখায়।

## ৭. Frontend পেজ (লাইট থিম)

- **মেনু:** Colour Lab → Reports → Lab Test Reports।
- **পেজ:** Brickland-এর "Dyeing Lab Reports"।
  - "Selection of Parameters" ঘেরা অংশে রিপোর্টের select ("Fabric Test — Fabric Test Register" ধরনের লেবেল) আর Export to Excel?।
  - মাঝখানে Preview।
  - নিচে রিপোর্টের নাম আর গ্রিড। খালি হলে "No items to display"।
- **`RecordsGrid`:** URL-এর `report` প্যারামিটার এখন ফিল্টার হিসেবে ধরা হয় না, তাই খালি রিপোর্টে "no match" বার্তা আসে না। Store & Material-এর রিপোর্টেও একই সুবিধা।

## ৮. Implementation Files

<!-- AUTO:impl:start -->
As-built (১টার ১টা স্ক্রিন):

**Backend**
- `app/Modules/ColourLab/Http/Controllers/Api/ColourLabReportApiController.php`
- `app/Modules/ColourLab/Database/Migrations/2027_01_24_000100_add_fastness_and_lab_to_fabric_tests.php`
- `app/Modules/ColourLab/Models/LabFabricTest.php`, `Http/Controllers/Api/ColourLabApiController.php` (light, perspiration, lab)
- `app/Modules/ColourLab/Routes/api.php`
- `app/Modules/Identity/Support/MenuRegistry.php`, `Database/Seeders/PermissionSeeder.php`, `RoleSeeder.php`
- `lang/en|bn/colourlab.php`

**Frontend**
- `frontend/src/components/colourlab/lab.jsx` (`LabReports`; Fabric Test শিটে Lab, Light, Perspiration)
- `frontend/src/app/(app)/colour-lab/[screen]/screens.js`
- `frontend/src/components/erp/records-grid.jsx` (`report` প্যারামিটার ফিল্টার নয়)

**Test**
- `tests/Feature/ColourLab/ColourLabTest.php` (`test_lab_reports`)
<!-- AUTO:impl:end -->

### পরিকল্পিত নতুন/বদলানো ফাইল

উপরের as-built তালিকাই চূড়ান্ত।

## ৯. টেস্ট ও যাচাই

**Feature test:** `ColourLabTest::test_lab_reports`, test2 DB-তে পাস। পুরো ফাইল: ৪টা টেস্ট, ১৪৯টা assertion।
- **Fabric Test:**
  - Register-এ ৩টা টেস্ট।
  - Variance-এ "72 / 71.5 (-0.5)", "180 / 176 (-4)" আর "-4.5 / -3"।
  - Fastness-এ Light 4, Perspiration 3-4।
  - Failed-এ শুধু Fail-টা, নোট "Spirality 6%"-সহ।
  - Pass rate: ৩টা টেস্ট, ১টা পাস, ১টা ফেল, ১টা Pending, হার 50%।
- **Trial:**
  - Register-এ Fail (একটা লাইন ফেল), Batch 10।
  - Recipe-তে ৩টা লাইন।
  - Usage-এ ২টা ট্রায়াল, মোট 15।
  - Awaiting-এ একটা ট্রায়াল Start-এর অপেক্ষায়, একটা লাইনের ফলের।
- **Certification:**
  - Register-এ Renewal Due আর Valid।
  - Renewal Due-তে OTS-1, বাকি ১০ দিন।
  - Movement-এ SGS।
- **Lab:** LAB-1, ২টা ট্রায়াল, ৩টা টেস্ট।
- **বাধা:** অজানা রিপোর্টে 404। LINE_CHIEF 403 পায়।

**Regression:** `MenuAccessTest` আবার পাস।

**dev DB smoke:** ১৩টা রিপোর্টই error ছাড়া চলেছে।

**UI চেক (`AnnotechERP/tools/app_check/plans/clr01`–`clr02`):** error ০।
- Fabric Test Register, Dia & GSM Variance, Chemical Trial Register, Chemical Usage, Renewal Due আর Lab Master চালানো হয়েছে, dev-এর 16/01 ডাটার সঙ্গে মিলেছে।
- Excel টিক দিলে `certificate-movement.csv` নেমেছে।
- `?report=trials-awaiting` সরাসরি খোলে, খালি হলে "No items to display"।
- Fabric Test শিটে Lab, Light আর Perspiration ঘর আছে।
- রিপোর্ট কিছু লেখে না, তাই dev-এ নতুন কাগজ তৈরি হয়নি।

**প্রিন্ট:** Brickland-এর এই স্ক্রিনে Print নেই, শুধু Preview আর Export to Excel।

## ১০. খোলা প্রশ্ন

১. **Chemical Trial Register-এর Buyer:** Brickland রিপোর্টে কলামটা আছে, কিন্তু ট্রায়ালের ফর্মে কোনো Buyer ঘর নেই। তাই কলামটা ফাঁকা থাকে। ট্রায়ালে Buyer রাখা হবে কিনা জানাবেন।

২. **Lab Master List-এর In Charge:** ল্যাবের দায়িত্বপ্রাপ্ত ব্যক্তি রাখার কোনো জায়গা নেই (ল্যাব মানে ফ্লোর), তাই কলামটা ফাঁকা থাকে।

৩. **Light আর Perspiration:** Brickland-এর Fabric Test তালিকায় এই দুটো কলাম নেই, শুধু রিপোর্টে আছে। আমরা শিটে ঘর দুটো যোগ করেছি, তালিকায় দেখাইনি।

৪. **Renewal Due-এর সীমা:** প্রতিটা সার্টিফিকেটের নিজের Notify BF Days দিয়ে ঠিক হয়। কারখানা সবার জন্য একটাই সীমা চাইলে সেটা setting হবে।

## ১১. As-built নোট

- **Brickland probe (`erp_crawler/plans/clr01`–`clr02`):**
  - select-এ ১৩টা রিপোর্ট, চারটা গ্রুপে।
  - প্রতিটা বেছে Preview চাপা হয়েছে। ফল একই পেজে আসে (`?report=N`)।
  - ডেমোতে কোনো সারি নেই, তবে প্রতিটা রিপোর্টের কলাম পাওয়া গেছে। আমাদের কলাম হুবহু সেই অনুযায়ী।
- **Report key:** Brickland নম্বর (1–13) ব্যবহার করে। আমরা পড়া যায় এমন key ব্যবহার করি (`fabric-test-register` ধরনের), URL-এ ULID বা নম্বরের বদলে।
