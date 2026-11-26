# Gate Pass

> **স্ট্যাটাস:** ✅ সম্পন্ন
> **মডিউল:** [Gate Pass](00_Index.md) · **ফেজ ৩:** সাপ্লাই ও স্টোর · **কী:** `11/02`

**⬅ ফিরে যান:** [Gate Pass](00_Index.md) | [AnnotechERP Index](../00_Index.md)

---

## ১. স্ক্রিন (Brickland ERP থেকে)

<!-- AUTO:screens:start -->
| # | স্ক্রিন | পাথ | ট্যাব | ফিল্ড | ডায়ালগ |
|---|---|---|---|---|---|
| 1 | Dashboard | `/m/gate-pass/gp-dashboard` | 1 | 0 | 0 |
| 2 | Reports | `/m/gate-pass/gp-reports` | 1 | 7 | 0 |
<!-- AUTO:screens:end -->

## ২. প্রতিটা স্ক্রিনের লাইভ বিবরণ (ফিল্ড, ট্যাব, গ্রিড, ডায়ালগ, ভ্যালিডেশন)

<!-- AUTO:detail:start -->
#### Dashboard

`/m/gate-pass/gp-dashboard`

**ভিউ**
> 01 of 13
- গ্রিড কলাম: GATE PASS NO., OUT, EXPECTED RETURN, DESTINATION, TAKEN BY, BALANCE
- গ্রিড কলাম: GATE PASS NO., DATE, CATEGORY, TYPE, PURPOSE, DESTINATION, TAKEN BY, ITEMS, QTY, STATUS
- সেকশন: Gate Pass Movement, Return Status, Purpose Wise, Location Wise, Overdue Return (0), Latest Gate Passes

#### Reports

`/m/gate-pass/gp-reports`

**ভিউ**
> 13 of 13

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন/প্লেসহোল্ডার |
|---|---|---|---|
| Report | select |  | Select report, Gate Pass Register, OUT Report, IN Report, Pending Return, Overdue Return, Short Quantity Report, Locatio |
| From Date | date |  |  |
| To Date | date |  |  |
| Category | select |  | All, Main (Employee), Item |
| Gate Pass Type | select |  | All, OUT & IN, OUT ONLY |
| Location | select |  | All |
| Search | text |  | GP No., employee, item… |
- গ্রিড কলাম: GATE PASS NO., DATE, CATEGORY, TYPE, PURPOSE, DESTINATION, TAKEN BY, ITEMS, OUT QTY, RETURNED, SHORT, STATUS
- সেকশন: Gate Pass Register
- বাটন/অ্যাকশন: All, View, Excel, Print / PDF
<!-- AUTO:detail:end -->

## ৩. বিদ্যমান কোড যা কাজে লাগতে পারে

- 11/03–11/04-এর গেট পাস টেবিল আর `GatePassBook` (প্রতিটা pass-এর OUT, ফেরত, কম, বাকি, ফেরতের অবস্থা, তালিকার সারি)।
- PDF-এর জন্য শেয়ার করা `App\Shared\Pdf\TablePdf`; Excel-এর জন্য OpenSpout; donut-এর জন্য `StatusDonut`, bar chart-এর জন্য recharts।

## ৪. ডাটা মডেল (Entity ও Field)

নতুন টেবিল নেই। সব হিসাব গেট পাস, তার লাইন আর Gate IN থেকে পড়া হয়।

## ৫. Workflow / Status ও Business Rules

- **Dashboard:**
  - Create Gate Pass বাটন (যার create অনুমতি আছে)।
  - ৮টা tile:
    - Total Gate Pass: সব।
    - Today OUT: আজ বের হওয়া pass।
    - Today IN: আজকের IN এন্ট্রি।
    - Pending Return: ফেরতযোগ্য, অবস্থা OUT।
    - Partial Return।
    - Short Quantity: কম থাকা pass।
    - Overdue Return: ফেরতের তারিখ পেরিয়েছে অথচ পুরো ফেরেনি।
    - Closed / Completed।
  - Gate Pass Movement: শেষ ১৪ দিনের OUT বনাম IN।
  - Return Status donut: বাইরে যাওয়া ফেরতযোগ্য Item pass — Fully Returned, Partial, Short, Pending।
  - Purpose Wise, Location Wise bar: খসড়া, বাতিল আর প্রত্যাখ্যাত বাদে; Location শুধু Item pass।
  - Overdue Return টেবিল আর Latest Gate Passes (১০টা)।
- **Reports:**
  - Report বাছাই, তারপর From, To, Category, Gate Pass Type, Purpose, Location, Search; View।
  - কার্ডে শিরোনাম আর "কোম্পানি · n rows"; Excel আর Print / PDF; Total সারি।
  - খসড়া কোনো রিপোর্টে আসে না।
- **১০টা রিপোর্ট:**
  1. **Gate Pass Register:** GATE PASS NO., DATE, CATEGORY, TYPE, PURPOSE, DESTINATION, TAKEN BY, ITEMS ("Item (10 Pcs)"), OUT QTY, RETURNED, SHORT, STATUS।
  2. **OUT Report:** OUT-এর তারিখ ধরে; OUT TIME, VEHICLE NO., OUT BY।
  3. **IN Report:** প্রতিটা Gate IN; ENTRY = Partial / Final / Final — short (কারণ); RECEIVED BY।
  4. **Pending Return:** বাইরে থাকা ফেরতযোগ্য pass; OUT DATE, EXPECTED RETURN, BALANCE, RETURN।
  5. **Overdue Return:** একই, তারিখ পেরোনো; DAYS OVERDUE।
  6. **Short Quantity Report:** কম থাকা প্রতিটা লাইন, কারণ আর অবস্থা।
  7. **Location-wise, 8. Purpose-wise, 9. Employee-wise:** বাইরে যাওয়া pass গুনে OUT, ফেরত, কম আর বাকি; কর্মী-ভিত্তিকে কার্ড, ডিপার্টমেন্ট আর পদবি।
  10. **Item-wise:** আইটেম আর ইউনিট ধরে।
- **শুধু-OUT pass:** বাকি সবসময় শূন্য (কিছু ফেরার কথা নয়)।

## ৬. API ও পারমিশন

| Method | Path |
|---|---|
| GET | `/gate-pass/dashboard` |
| GET | `/gate-pass/reports/options`, `/reports/{report}`, `/reports/{report}/excel`, `/reports/{report}/pdf` |

- report = register, out, in, pending, overdue, short, location, purpose, employee, item।
- পারমিশন: যেকোনো gatepass পারমিশন (view, create, approve, gate)।

## ৭. Frontend পেজ (লাইট থিম)

- সাইডবার: Gate Pass → Dashboard (প্রথম, "01 of 13") আর Reports (শেষ, "13 of 13")। এরা কোনো ফোল্ডারে নেই, Brickland-এর মতো। crawler এদের "Gate Pass" গ্রুপে রেখেছিল।
- রিপোর্ট পাতার ভেতরেই বদলায়। Excel আর PDF route handler `/api/gate-pass/excel/rpt-<report>` (`?as=pdf`) দিয়ে।

## ৮. Implementation Files

<!-- AUTO:impl:start -->
As-built:

**Backend**
- `app/Modules/GatePass/Http/Controllers/Api/GatePassReportApiController.php`
- `app/Modules/GatePass/Support/GatePassBook.php`
- `app/Modules/GatePass/Routes/api.php`
- `app/Modules/Identity/Support/MenuRegistry.php`

**Frontend**
- `frontend/src/components/gatepass/dashboard.jsx`, `report.jsx`
- `frontend/src/app/(app)/gate-pass/[screen]/{page,screens,actions}.js`
- `frontend/src/app/api/gate-pass/excel/[kind]/route.js` (`rpt-*`, `?as=pdf`)
- `lang/en/gatepass.php`, `lang/bn/gatepass.php` (`gp.dash.*`, `rpt.*`)

**Tests**
- `tests/Feature/GatePass/GatePassFlowTest.php`
<!-- AUTO:impl:end -->

### পরিকল্পিত নতুন/বদলানো ফাইল

_তৈরি হয়ে গেছে।_

## ৯. টেস্ট ও যাচাই

- **`test_the_dashboard_the_list_and_the_reports`:**
  - ৫টা pass (মেয়াদ পেরোনো, আংশিক, কম ও বন্ধ, শুধু-OUT, খসড়া)।
  - ৮টা tile, ফেরতের donut, ১৪ দিনের চলাচল, purpose, overdue, latest।
  - Register-এর মোট 33 / 12 / 2 আর subtitle।
  - OUT, IN-এর এন্ট্রি, Pending-এর মোট, Overdue (৪ দিন), Short, Location, Purpose, Employee, Item।
  - ফিল্টার; Excel, PDF (%PDF), অচেনা রিপোর্টে 404।
- পুরো Gate Pass + MenuAccess + RoleMatrix + ApiGateCoverage + AccessControlConfiguration: OK (৩৩টা টেস্ট, ৪০৮টা assertion)।
- **ব্রাউজার (dev):**
  - Dashboard (Total 2, Today IN 2, Short 1, Closed 1; donut-এ Short 1)।
  - Short Quantity Report (GP-2026-000002, Polo Shirt 5 / 4 / 1, Missing, Closed) আর তার Excel ডাউনলোড।
  - ক্রমিক নম্বর 01 / 02 / 03 / 13।

## ১০. খোলা প্রশ্ন

- Brickland-এর tile "Short Quantity" pass গোনে নাকি পরিমাণ, তা একটা উদাহরণ (দুটোই ১) থেকে বোঝা যায়নি। এখানে কম থাকা pass গোনা হয়।

## ১১. As-built নোট

- **✅** (২০২৬-১০-২৪)। লাইভ প্রোব: `tools/erp_crawler/plans/gp01.json`, `gp10.json`।
- Gate Pass মডিউল সম্পূর্ণ (১৩টা স্ক্রিন)।
