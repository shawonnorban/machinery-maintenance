# Leave & Overtime

> **স্ট্যাটাস:** ✅ সম্পন্ন (৫টার ৫টা স্ক্রিন — ক্রলের ৩টা আর Brickland-এ পরে যোগ হওয়া OT Correction ও OT Eligibility)
> **মডিউল:** [HR & Payroll](00_Index.md) · **ফেজ ৭:** ফাইন্যান্স ও HR · **কী:** `24/05`

**⬅ ফিরে যান:** [HR & Payroll](00_Index.md) | [AnnotechERP Index](../00_Index.md)

---

## ১. স্ক্রিন (Brickland ERP থেকে)

<!-- AUTO:screens:start -->
| # | স্ক্রিন | পাথ | ট্যাব | ফিল্ড | ডায়ালগ |
|---|---|---|---|---|---|
| 1 | Leave | `/m/hr-payroll/leave-application` | 2 | 1 | 0 |
| 2 | OT Request | `/m/hr-payroll/ot-request` | 1 | 6 | 0 |
| 3 | OT Approval | `/m/hr-payroll/ot-approval` | 2 | 0 | 0 |
<!-- AUTO:screens:end -->

## ২. প্রতিটা স্ক্রিনের লাইভ বিবরণ (ফিল্ড, ট্যাব, গ্রিড, ডায়ালগ, ভ্যালিডেশন)

<!-- AUTO:detail:start -->
#### Leave

`/m/hr-payroll/leave-application`

**ট্যাব: Records 0**
- বাটন/অ্যাকশন: Leave Status, Save application

**ট্যাব: Leave Status**

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন/প্লেসহোল্ডার |
|---|---|---|---|
| Search employees | search |  | Search… |
- সেকশন: Employee, Your Leave Balance
- বাটন/অ্যাকশন: Leave Status, Save application

#### OT Request

`/m/hr-payroll/ot-request`

**ভিউ**

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন/প্লেসহোল্ডার |
|---|---|---|---|
| Date* | date | ✔ |  |
| Regular Shift | select |  | Select…, Morning (06:00–14:00), General (08:00–17:00), Evening (14:00–22:00), Night (22:00–06:00) |
| OT Start* | time | ✔ |  |
| OT End* | time | ✔ |  |
| Reason* | textarea | ✔ | Shipment due, line behind target… |
| Search requests | search |  | Search by request no, line, section or date |
- গ্রিড কলাম: Request No, Date, Section / Line, OT Window, Employees, Hours, Status, Approver, Actions
- সেকশন: New OT Request, OT Requests
- বাটন/অ্যাকশন: Select…, Pick a department first, Pick a section first, Direct Approval, Send for Approval, Approve OT, Clear, All, Pending, Approved, Returned, Rejected

#### OT Approval

`/m/hr-payroll/ot-approval`

**ট্যাব: All Pending 0**
- গ্রিড কলাম: Request No, Date, Department / Section / Line, OT Window, Employees, Hours, Requested By, Status, Open
- বাটন/অ্যাকশন: My Inbox 0, All Pending 0, Decided

**ট্যাব: Decided**
- গ্রিড কলাম: Request No, Date, Department / Section / Line, OT Window, Employees, Hours, Decided, Status, Open
- বাটন/অ্যাকশন: My Inbox 0, All Pending 0, Decided
<!-- AUTO:detail:end -->

## ৩. বিদ্যমান কোড যা কাজে লাগতে পারে

- **Master Data (24/01):** `hr_leave_types` (দিন, লিঙ্গ, carry forward, accrual, balance check, অফ-ডে / ছুটির দিন ধরা), `hr_ot_rules` (Basic বা Gross ÷ divisor × multiplier; গ্রেড বা কর্মীর ধরনের জন্য), `hr_shifts`।
- **কর্মী (24/03):** গ্রেড, কর্মীর ধরন (Staff / Worker গাছ), লাইন, Basic pay line আর gross।
- **হাজিরা (24/04):** `AttendanceBook` — দিনের হিসাব; এখানে ছুটি আর OT-র নিয়ম যোগ হয়েছে।
- **অর্থবছর (23 Finance):** `acc_fiscal_years` — ছুটির বছর তার ক্যালেন্ডার অংশ (`cal_start`–`cal_end`)।
- **নোটিফিকেশন:** `NotificationDispatcher` — OT অনুরোধ আর সিদ্ধান্তের খবর।

## ৪. ডাটা মডেল (Entity ও Field)

মাইগ্রেশন `2027_03_14_000100_create_hr_leave_overtime`:
- **`hr_leave_applications`:** রেফ নং (LV-000001…), অর্থবছর, ধরন (DAILY / EARLY_DEPARTURE), কর্মী, ছুটির প্রকার, উদ্দেশ্য, শুরু–শেষ, দিন, আগে যাওয়ার সময় আর ঘণ্টা, আবেদন ও অনুমোদনের তারিখ, দায়িত্ব কে পালন করবেন, যোগদানের তারিখ, অবস্থা (PENDING / APPROVED / REJECTED / CANCELLED), নিশ্চিত, কারণ (ইংরেজি ও বাংলা)।
- **`hr_ot_limits`:** কোম্পানির OT সীমা — শিফট শেষের কত পরে OT, গ্রেস, General / Worker রাউন্ডিং, OT বিরতি আর কখন থেকে, ন্যূনতম, দিনে সর্বোচ্চ; কে বদলেছেন।
- **`hr_grade_ot`:** গ্রেড General OT পায় কিনা (না থাকলে পায়)।
- **`hr_employee_ot`:** স্টাফের Worker OT চালু কিনা, নোট।
- **`hr_ot_audit`:** সীমা, গ্রেড আর কর্মীর প্রতিটা বদল, কারণসহ।
- **`hr_ot_requests` / `hr_ot_request_employees`:** অনুরোধ নং (OTR-000001…), তারিখ, বিভাগ / সেকশন / লাইন, শিফট, OT শুরু–শেষ, মিনিট, কারণ, অনুমোদন (DIRECT / ROUTED), কার কাছে, অবস্থা (PENDING / APPROVED / REJECTED / RETURNED), সিদ্ধান্তের নোট, কে কখন; কর্মীরা (অনুমোদনে বাদ দেওয়া হলে `removed`)।
- **`hr_ot_corrections` / `hr_ot_correction_history`:** দিনের সংশোধিত OT ঘণ্টা আর হার, কারণ; প্রতিটা Save / Reset।
- **`hr_attendance_days`-এ নতুন:** ছুটির দিনের `leave_type_id`, আর OT-র ধরন `ot_type` (GENERAL / WORKER)।

## ৫. Workflow / Status ও Business Rules

**ছুটি (`Support/LeaveBook`):**
- HR লেখেন, তাই সরাসরি Approved সেভ করা যায় (ডিফল্ট Approved); অনুমোদন হলেই বাকি ছুটি থেকে কাটে। Records-এ মোছা যায়, এডিট নেই (Brickland-এর মতো)।
- **Daily:** শুরু–শেষ (দিন = দুই তারিখসহ গোনা), যোগদানের তারিখ = শেষের পরের দিন (বদলানো যায়)। **Early Departure:** তারিখ, সময়, ঘণ্টা (৮ পর্যন্ত), উদ্দেশ্য।
- আটকায়: একই কর্মীর একই ধরনের ছুটি তারিখে মিলে গেলে (অপেক্ষায় বা অনুমোদিত); ছুটির প্রকার অন্য লিঙ্গের হলে; অর্থবছরের বাইরে; নিজেই নিজের দায়িত্ব; balance check থাকা প্রকারে বাকির বেশি হলে (ফর্মেও সতর্কবার্তা "You don't have enough balance.")।
- **হাজিরায়:** অনুমোদিত Daily ছুটির দিনগুলো Leave (পাঞ্চ থাকলেও); সাপ্তাহিক ছুটি বা ছুটির দিন ভেতরে পড়লে শুধু তখনই Leave যখন প্রকারটা সেগুলো ধরে। অনুমোদিত Early Departure ঐ দিনের আগে বের হওয়া মাফ করে। ছুটি মুছলে দিনগুলো আবার পাঞ্চ থেকে।
- **Leave Status:** প্রাপ্য (প্রকারের দিন; ন্যূনতম চাকরিকাল পূর্ণ না হলে ০; accrual থাকলে প্রতি ১৮ দিন উপস্থিতিতে ১ দিন, প্রকারের দিন পর্যন্ত; দিন না থাকলে সীমা নেই), সমন্বয় (আগের বছর থেকে carry forward, প্রকারের carry-forward দিন পর্যন্ত), নেওয়া (অনুমোদিত আবেদন), বাকি = প্রাপ্য + সমন্বয় − নেওয়া; ঘণ্টা = দিন × ৮।

**OT (`Support/OtBook`, `AttendanceBook`):**
- **কে পায়:** General OT — শ্রমিক (কর্মীর ধরনের গাছ Staff নয়), গ্রেড আছে, গ্রেডের General OT চালু, আর OT নিয়ম আছে (গ্রেডের, নইলে কর্মীর ধরনের, নইলে সাধারণ)। Worker OT — স্টাফ যাঁর Worker OT চালু, আর তাঁর ধরনের (বা উপরের ধরনের) জন্য লেখা OT নিয়ম আছে। কারণ লেখা থাকে: "Graded worker · OT-STD · Basic ÷ 208 × 2", "No grade — no general OT", "Staff — no general OT", "Grade 4 — General OT switched off"।
- **গোনা:** শিফট শেষ + start-after থেকে OUT পর্যন্ত (সাপ্তাহিক ছুটি / ছুটির দিনে পুরো কাজের সময়); বিরতি-কখন-থেকে পেরোলে OT বিরতি বাদ; রাউন্ডিং ধাপে নিচে (পরের ধাপের গ্রেসের মধ্যে থাকলে উপরে); ন্যূনতমের কম হলে ০; দিনের সর্বোচ্চে থামে। রাউন্ডিং শুধু পুরো ঘণ্টায় (৬০, ১২০…)।
- **অনুমোদিত অনুরোধ থাকলে:** ঐ কর্মীর ঐ দিনের OT শুধু অনুমোদিত সময়ের ভেতরে।
- **হার:** নিয়মের মজুরি (Basic — কর্মীর Basic pay line, নইলে gross থেকে বের করা Basic; বা Gross) ÷ divisor × multiplier।
- **OT Correction:** তারিখ, বিভাগ, কর্মী (কোড / নাম / কার্ড), দেখাবে (OT আছে এমন দিন / সব হাজিরা) → টাইল (OT পাওয়া কর্মী, OT ঘণ্টা ও মূল, OT মজুরি ও মূল, সংশোধিত) → সারিতে সংশোধিত ঘণ্টা, হার, কারণ (বাধ্যতামূলক) → Save; Reset (কারণ লাগে) হাজিরার OT-তে ফেরায়; History (কখন, কে, কাজ, ঘণ্টা, হার, পরিমাণ, কারণ)।
- **OT Eligibility:** OT সীমা (৮টা, Save Limits; শেষ কে বদলেছেন); গ্রেড অনুযায়ী General OT (সুইচ — আগে কারণ লিখতে হয়, অডিটে যায়); কর্মী তালিকা — বিভাগ, শ্রেণি, OT, কর্মী দিয়ে ছাঁকা; টাইল (কর্মী, General OT, Staff / No Grade, Worker OT); স্টাফের Worker OT সুইচ আর নোট (শ্রমিকে "Not applicable")।
- **OT Request:** তারিখ, বিভাগ → সেকশন → লাইন (সেকশনের লাইন, বা বিভাগের সেকশনহীন লাইন); লাইন বাছলে শিফট বসে, OT শুরু = শিফট শেষ; OT শেষ (মাঝরাত পেরোতে পারে, সর্বোচ্চ ১২ ঘণ্টা); কারণ; লাইনের সক্রিয় কর্মী (মোট, সক্রিয়, বাছা; সবাই টিক দেওয়া, খোঁজা যায়)। অনুমোদন: Direct Approval (OT Approval পারমিশন থাকলে — সেভ করলেই Approved) বা Send for Approval (OT Approval থাকা কাউকে)। একই দিনে একই কর্মী অন্য অপেক্ষমাণ / অনুমোদিত অনুরোধে থাকলে আটকায়। তালিকা — সর্বশেষ ২০০টা, অবস্থা অনুযায়ী ছাঁকা, খোঁজা, View (বিবরণ আর কর্মী: অনুমোদিত, আসল, প্রদেয় ঘণ্টা, হার, পরিমাণ), ফেরত আসাটায় Edit → Resubmit।
- **OT Approval:** My Inbox (আমার কাছে পাঠানো অপেক্ষমাণ), All Pending (OT Approval থাকলে), Decided। Review-এ কর্মীর টিক তুলে বাদ দেওয়া যায়; Approve n জন (অন্তত একজন), Reject বা Return (নোট বাধ্যতামূলক)। অনুরোধকারী নোটিফিকেশন পান; অনুমোদনে ঐ দিনের হাজিরা আবার হিসাব হয়।

## ৬. API ও পারমিশন

- **পারমিশন:** নতুন `hrpayroll.leave.view` / `.manage`, `hrpayroll.ot.view` / `.manage` / `.approve`। FACTORY_ADMIN সব; FACTORY_MANAGER leave.view, ot.view, ot.approve; AUDITOR দুটো view।

| Method | Path |
|---|---|
| GET | `/hr-payroll/leave/options`, `/hr-payroll/leave`, `/hr-payroll/leave/balances` |
| POST / DELETE | `/hr-payroll/leave`, `/hr-payroll/leave/{ref}` |
| GET | `/hr-payroll/ot/options`, `/ot/eligibility`, `/ot/correction`, `/ot/correction/history`, `/ot/requests`, `/ot/line-employees`, `/ot/approvals` |
| POST | `/hr-payroll/ot/limits`, `/ot/grade`, `/ot/employee`, `/ot/correction`, `/ot/correction/reset`, `/ot/requests`, `/ot/requests/{no}/decide` |
| PUT | `/hr-payroll/ot/requests/{no}` (ফেরত আসা অনুরোধ আবার পাঠানো) |

- **নোটিফিকেশন:** `HR_OT_REQUESTED` (অনুমোদনকারীকে), `HR_OT_DECIDED` (অনুরোধকারীকে)।
- **মেনু:** HR & Payroll-এ নতুন ফোল্ডার LEAVE & OVERTIME (Dye House-এর মতো স্টাইলে), Brickland-এর ক্রমে ৫টা স্ক্রিন।

## ৭. Frontend পেজ (লাইট থিম)

- **কম্পোনেন্ট:** `components/hrpayroll/leave-overtime.jsx` — `LeaveApplication` (Records / Leave Status / Save application ট্যাব, রিলোড ছাড়া), `OtCorrection` (History dialog), `OtEligibility`, `OtRequest` (View dialog), `OtApproval` (Review dialog)।
- **ঠিকানা:** `/hr-payroll/leave-application`, `/ot-correction`, `/ot-eligibility`, `/ot-request`, `/ot-approval`। OT Request-এর হেডারে "OT Approval", OT Approval-এর হেডারে "OT Request" বাটন (Brickland-এর মতো)।

## ৮. Implementation Files

<!-- AUTO:impl:start -->
As-built (৫টার ৫টা স্ক্রিন):

**Backend (`app/Modules/HrPayroll`)**
- `Database/Migrations/2027_03_14_000100_create_hr_leave_overtime.php`
- `Support/LeaveBook.php` (বাকি ছুটি), `Support/OtBook.php` (OT যোগ্যতা, হার, সীমা, অনুমোদিত সময়)
- `Support/AttendanceBook.php` (ছুটি আর নতুন OT নিয়ম — বদলানো)
- `Http/Controllers/Api/HrLeaveApiController.php`, `Http/Controllers/Api/HrOvertimeApiController.php`
- `Routes/api.php` (বদলানো)
- `app/Modules/Identity/Database/Seeders/PermissionSeeder.php`, `RoleSeeder.php`, `app/Modules/Identity/Support/MenuRegistry.php`, `app/Modules/Notification/Models/Notification.php`, `lang/{en,bn}/notification.php` (বদলানো)
- `lang/en/hrpayroll.php`, `lang/bn/hrpayroll.php` (`lo`, `emp.screens`, `emp.descriptions`)

**Frontend**
- `frontend/src/components/hrpayroll/leave-overtime.jsx` (নতুন)
- `app/(app)/hr-payroll/[screen]/employee.js`, `actions.js` (বদলানো)

**Test**
- `tests/Feature/HrPayroll/HrLeaveOvertimeTest.php` (নতুন); `HrAttendanceTest.php` (কর্মীদের গ্রেড — বদলানো)
<!-- AUTO:impl:end -->

### পরিকল্পিত নতুন/বদলানো ফাইল

উপরের as-built তালিকাই চূড়ান্ত।

## ৯. টেস্ট ও যাচাই

**Feature test (`HrLeaveOvertimeTest.php`, ৩টা টেস্ট, ১৩৮টা assertion):** test2 DB-তে পাস। HrPayroll-এর পুরো স্যুট ১৭টা টেস্ট (৬৩৪ assertion) সবুজ, AttendanceDevice ২/৮৪, Notification ৪৬/১১৫, Access ২৭/১৮৪ (২০২৬-১১-১১)।
- **ছুটি:** বাধ্যতামূলক ঘরের বার্তা; CL ৯–১০ নভেম্বর Approved → LV-000001, দুই দিনই Leave (পাঞ্চ থাকলেও), যোগদান ১১ তারিখ; তারিখ মিলে যাওয়া, পুরুষের Maternity, বছরের বাইরের তারিখ আটকায়; Early Departure-এ Staff One-এর ৬০ মিনিট আগে বের হওয়া মাফ; বাকি — CL ১০ − ২ = ৮ (৬৪ ঘণ্টা), SL ১৪ − ১ ঘণ্টা = ১৩.৮৮, LWP সীমাহীন; balance check-এ ৮ দিনের বেশি আটকায়; মুছলে দিন আবার Present। Factory Manager দেখেন, লেখেন না; Storekeeper দেখেন না।
- **OT যোগ্যতা আর সংশোধন:** কারণসহ কর্মীর অবস্থান; Worker One-এর ৩ ঘণ্টা OT, হার Basic ÷ ২০৮ × ২; ৯০ মিনিট রাউন্ডিং আটকায়; ৩০ মিনিট পরে শুরু আর ২ ঘণ্টা সর্বোচ্চে ২.০; ৪০ মিনিট পরে শুরু, ১৫ মিনিট গ্রেস, ৪৫ মিনিট ন্যূনতমে ১ ঘ ৫০ মি → ২ ঘণ্টা; তিনবার অডিট; গ্রেড ৪ বন্ধ (কারণ ছাড়া আটকায়) → OT নেই, আবার চালু; স্টাফ নিয়ম যোগ করে Staff One-এর Worker OT চালু ("OT-STAFF · Gross ÷ 208 × 1"), শ্রমিকের জন্য আটকায়; সংশোধন (কারণ ছাড়া আটকায়) ৪ ঘণ্টা × ১০০ = ৪০০, সারাংশ, Reset, History (RESET, SAVE)। Factory Manager সীমা বদলাতে পারেন না।
- **OT অনুরোধ আর অনুমোদন:** অনুমোদনকারীর তালিকায় Factory Manager; লাইনের ২ জন আর শিফট; অনুমোদনকারী ছাড়া, লাইনের বাইরের কর্মী আটকায়; OTR-000001 পাঠানো (২ জন × ২ ঘণ্টা = ৪), অনুমোদনকারী নোটিফিকেশন পান; একই দিনে একই কর্মী আবার আটকায়; নোট ছাড়া Return আটকায়, নোটসহ Return → অনুরোধকারী জানেন; এডিট করে আবার পাঠানো, দ্বিতীয়বার আটকায়; সবাইকে বাদ দিয়ে Approve আটকায়, Worker Two-কে বাদ দিয়ে Approve; Worker One-এর ৩ ঘণ্টা OT অনুমোদিত সময়ে ২ ঘণ্টা (আসল ৩.১৭, প্রদেয় ২); Direct Approval (১৭:০০–০১:০০ = ৮ ঘণ্টা); Auditor দেখেন কিন্তু অনুরোধ / সিদ্ধান্ত পারেন না; Storekeeper দেখেন না।
- **24/04-এর টেস্ট:** কর্মীদের গ্রেড ৪ দেওয়া হয়েছে (গ্রেড ছাড়া General OT নেই) — ২/৮০ পাস।

**UI চেক (`app_check/plans/hr05`, ২০২৬-১১-১১, owner; error 0; dev ডাটা রাখা আছে):**
- Employee Assignment-এ Mohammad Sayem আর Rokeya Begum-কে Sewing → Line Production → Line 1, Jr. Operator, Grade 4 ✔
- Leave: Rokeya Begum CL ১২–১৩ নভেম্বর Approved → LV-000001 ✔ Leave Status-এ CL ১০ / নেওয়া ২ / বাকি ৮ ✔
- OT Eligibility: ৭টা গ্রেড (সব চালু), Sayem / Shawonoor / Rokeya General OT ("OT-WKR · Basic ÷ 208 × 2"), Poritosh আর Hasan "No grade — no general OT" ✔
- OT Request: Line 1-এর ২ জন, শিফট General, ১৭:০০–১৯:০০, Direct Approval → OTR-000001 Approved ✔ View dialog ✔ OT Approval → Decided-এ দেখা যায় ✔
- OT Correction: ১০ নভেম্বর সব হাজিরা, Mohammad Sayem ১ ঘণ্টা × ৳৮৮.৬০ কারণসহ সেভ ✔ History-তে "Saved" ✔

## ১০. খোলা প্রশ্ন

১. **শিফটের "OT Approval" ঘর:** seed করা সব শিফটে এটা চালু, কিন্তু Brickland হাজিরা থেকে OT নিজেই দেয় (OT Correction-এ ৫১১ জনের OT, কোনো অনুরোধ ছাড়া)। তাই এখানে অনুরোধ ছাড়াও OT হয়; অনুমোদিত অনুরোধ থাকলে শুধু তার সময়ের ভেতরেরটুকু। শিফটে "OT Approval" চালু থাকলে অনুরোধ ছাড়া OT একেবারে বন্ধ রাখতে চাইলে জানাবেন।

২. **অপেক্ষমাণ ছুটি:** Brickland-এর Leave স্ক্রিনে এডিট বা Approve নেই — Pending সেভ করলে পরে অনুমোদনের পথ নেই (মুছে আবার লিখতে হয়)। Approval Center-এ ছুটির অনুমোদন চাইলে জানাবেন।

৩. **ছুটির হিসাব:** ছুটির বছর = অর্থবছরের ক্যালেন্ডার অংশ (জানুয়ারি–ডিসেম্বর); এক দিন = ৮ ঘণ্টা; Earn Leave-এ accrual চালু করলে প্রতি ১৮ দিন উপস্থিতিতে ১ দিন। আলাদা নিয়ম হলে জানাবেন।

৪. **Adjusted (সমন্বয়):** এখানে আগের বছর থেকে carry forward। Brickland-এ হাতে সমন্বয়ের আলাদা খাতা থাকলে সেটা দেখা যায়নি — দরকার হলে জানাবেন।

৫. **Records-এ Status কলাম:** Brickland-এর কলাম তালিকায় নেই, কিন্তু টেবিলের বর্ণনায় "status" আছে; Pending আর Approved আলাদা চেনার জন্য রাখা হয়েছে।

৬. **চূড়ান্ত পে-রোলে তালা:** ✅ মিটেছে — 24/06-এ Brickland-এর মতো তালা নেই; চূড়ান্ত স্লিপ "Adjustment Required" হয়, পার্থক্য Payroll Adjustment-এ তোলা হয়।

## ১১. As-built নোট

- **OT Correction আর OT Eligibility:** ক্রলে (৩টা স্ক্রিন) ছিল না, Brickland-এ পরে যোগ হয়েছে; লাইভ প্রোব থেকে বানানো (RSC props, JS chunk-এর লেখা — `tools/erp_crawler/out/lo05`, `lo05js`, স্ক্রিনশট `lo05shots`)। সাইডবারের ক্রম Brickland-এর: Leave, OT Correction, OT Eligibility, OT Request, OT Approval। উপরের AUTO তালিকায় শুধু ক্রলের ৩টা।
- **24/04-এর সাথে:** OT এখন OT Eligibility থেকে (আগে শুধু শ্রমিক / স্টাফ দিয়ে, পুরো ঘণ্টায়) — গ্রেড ছাড়া শ্রমিক General OT পান না; রাউন্ডিং আর অন্য সীমা বদলানো যায়। ছুটির দিনগুলো হাজিরায় Leave হয়।
- **OT Request-এর লাইন:** dev-এর অনেক লাইন কোনো সেকশনের নিচে নেই, তাই সেকশন বাছার পর বিভাগের সেকশনহীন লাইনগুলোও দেখানো হয়।
- **UI চেকে ধরা পড়া:** মেনু lang-এর আগে sync হওয়ায় সাইডবারে কাঁচা key দেখাচ্ছিল — আবার sync করে ঠিক। OT Eligibility-তে Yes / No চিপ পুরো কলাম জুড়ে ছিল — ঠিক করা হয়েছে।
