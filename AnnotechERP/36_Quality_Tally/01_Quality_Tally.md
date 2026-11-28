# Quality Tally

> **মডিউল:** [Quality Tally](00_Index.md) · **ফেজ ৫:** প্রোডাকশন · **কী:** `36/01`

**⬅ ফিরে যান:** [Quality Tally](00_Index.md)

---

## ১. স্ক্রিন (annotech-rmg থেকে)

| # | স্ক্রিন | ফোল্ডার | রুট | পারমিশন |
|---|---|---|---|---|
| 1 | Quality Dashboard | Tally | `/quality-tally/dashboard` | `qtally.view` |
| 2 | Tally Entry | Tally | `/quality-tally/tally` | `qtally.tally.create` |
| 3 | Tally Sheet | Reports | `/quality-tally/tally-sheet` | `qtally.view` |
| 4 | Tally History | Reports | `/quality-tally/history` | `qtally.view` |
| 5 | Jobs | Planning | `/quality-tally/jobs` | `qtally.view` / `qtally.job.manage` |
| 6 | Line Target | Planning | `/quality-tally/line-target` | `qtally.view` / `qtally.target.manage` |
| 7 | QC Assignment | Planning | `/quality-tally/qc-assignment` | `qtally.view` / `qtally.inspector.manage` |
| 8 | Defect Types | Setup (এডমিন) | `/quality-tally/defect-types` | `qtally.defect.manage` |

## ৪. ডাটা মডেল

`qt_defect_types` (section CUTTING/SEWING/QUALITY) · `qt_jobs` · `qt_line_targets` · `qt_inspectors` + `qt_inspector_lines` · `qt_entries` (এক ট্যাপ = এক রো, `client_uuid` unique) · `qt_entry_defects` (DHU-র লব)। লাইন কপি হয় না: Sewing = Capacity Planning-এর planning lines, Cutting/Quality = Company Management-এর production lines (line_type CUTTING / QUALITY)।

## ৫. Business Rules

- চারটা অ্যাকশন স্বাধীন: RFT, Defect, Rectify, Reject। Rectify/Reject Defect-কে কমায় না।
- Checked = RFT + Defect · Pass = RFT + Rectify, কিন্তু Checked-এর বেশি নয় · DHU = defect ট্যাগ ÷ Checked × ১০০ · RFT % = RFT ÷ Checked × ১০০ · কিছু চেক না হলে null, ০ নয়।
- Defect ট্যাপে অন্তত একটি ডিফেক্ট লাগবে, সেই লাইনের সেকশনের সক্রিয় ডিফেক্ট।
- শুধু এসাইন করা QC তার লাইনে ট্যাপ করতে পারে — Owner-ও না, যদি QC না হন।
- অফলাইন: ট্যাপ ডিভাইসে জমা থাকে, ৩০ সেকেন্ড পরপর ও নেট ফিরলে সিঙ্ক; একই `client_uuid` দুবার গোনা হয় না; `occurred_at` অনুযায়ী ঘণ্টায় বসে; ৩ দিনের বেশি পুরনো ট্যাপ নেওয়া হয় না।
- Undo: নিজের, আজকের, ওই লাইন-জবের শেষ ট্যাপ।
- একই গার্মেন্ট Cutting, Sewing, Quality তিন জায়গায় গোনা হয় — তাই সেকশন মিলিয়ে টার্গেট/পাস/অর্জন যোগ হয় না (ড্যাশবোর্ড, Line Target ফুটার, Jobs-এ সেকশন-ওয়ারি পাস)।
- Efficiency = পাস × SMV ÷ (ম্যানপাওয়ার × ঘণ্টা × ৬০) — একটি জব, SMV, ম্যানপাওয়ার না থাকলে null।
- ট্যাপ করা জব মোছা যায় না (বন্ধ করা যায়); ব্যবহৃত ডিফেক্ট মোছা বা সেকশন বদলানো যায় না।

- **স্টেজের নিয়ম (user 2026-11-28):** একটি জবের সব বান্ডেলের সব পার্ট কাটিং ট্যালিতে কমপ্লিট না হলে সুইংয়ে টার্গেট দেয়া যায় না; সুইং কমপ্লিট না হলে কোয়ালিটিতে না। **কাটিং কমপ্লিট** = বান্ডেল আছে, আর প্রতিটি বান্ডেলের প্রতিটি পার্ট (বান্ডেলের নিজের প্যানেল থাকলে শুধু সেটা) বান্ডেল Qty × per_piece পর্যন্ত চেক হয়েছে আর কোনো ডিফেক্ট খোলা নেই (Defect − Rectify − Reject ≤ 0) — অথবা ওপেনিং কাটিং অর্ডার Qty ঢেকে দেয়। **সুইং কমপ্লিট** = কাটিং কমপ্লিট, সুইং পাস > 0, কাটিং থেকে আসা আর চেক বাকি নেই (waiting = 0), সুইংয়ে ডিফেক্ট খোলা নেই — অথবা ওপেনিং সুইং অর্ডার Qty ঢাকে; কোয়ালিটি একই নিয়মে DONE। নতুন টার্গেটে (saveTarget/bulk) আটকায়, Copy Targets এমন জব বাদ দেয়; আগের টার্গেট এডিট করা যায়। পরের সেকশন জবটা দেখে (Pending orders-এ স্টেজ + "আসছে" বার্তা, Give target বন্ধ); টার্গেট পিকারে বন্ধ জব আসে না (ওয়েব) / বেছে নিলে কারণ দেখায় (মোবাইল)। `QtFlow::stage/opensFor/blockedReason`, `lookups.jobs[].stage/blocked`, `pending.rows[].stage/blocked`, `flow[].stage`।

## ৬. API ও পারমিশন

`/api/v1/quality-tally/*` (lookups, defect-types, jobs, targets, inspectors, dashboard, sheet, history, tally/context|summary) · ট্যাপ: `POST tally`, `tally/bulk`, `tally/undo` (throttle 600/মিনিট) · ব্রাউজার থেকে `/api/quality-tally/tally/*` প্রক্সি দিয়ে (server action নয়)। Platform: `GET|PUT /api/v1/platform/tenants/{company}/modules[/{code}]`।

## ৯. টেস্ট ও যাচাই

`tests/Feature/QualityTally/QualityTallyTest.php` — মডিউল লক/আনলক (Platform), ফর্মুলা, এসাইনমেন্ট, অফলাইন রিপ্লে, Undo, Sheet/Dashboard/Jobs মিল। ব্রাউজার: `AnnotechERP/tools/app_check/plans/qt01.json`, `qt02.json` (dev ডাটা রাখা আছে)।

## ১০. খোলা প্রশ্ন

1. **Inline QC বনাম End-line QC:** rmg-তে একই ফ্লোরে কয়েকটা চেকপয়েন্ট ছিল, আউটপুট গুনত একটা। এখানে প্রতি সেকশনে লাইনই চেকপয়েন্ট। Sewing-এ inline আর end-line আলাদা লাগবে কি?
2. **ERP-র সাথে যুক্ত করা:** পরে Job কি Merchandise order / Order Summary থেকে আসবে, আর Quality-র পাস কি Production Tracking-এর আউটপুট হবে?
3. **26DAL00101 / 26DAL00102-এর আসল ওপেনিং:** আগের ট্যাপ মিলিয়ে ওপেনিং বসানো হয়েছে (101: কাটিং 10; 102: কাটিং 6, সুইং 3)। আসল সংখ্যা Jobs → Edit → Opening pieces-এ বসাতে হবে।

4. **"কমপ্লিট"-এর সংজ্ঞা (2026-11-28):** রিজেক্ট হওয়া পার্ট আর পাস হবে না — তাই কমপ্লিট ধরা হয়েছে "সব পার্ট চেক শেষ, কোনো ডিফেক্ট খোলা নেই" (রিজেক্ট বাদ দিয়ে বাকিটা পিস হিসেবে যায়)। যদি চান রিজেক্টের বদলি পার্ট আবার চেক হয়ে পাস না হওয়া পর্যন্ত আটকে থাকবে, বা অর্ডার Qty-র সমান পিস না হওয়া পর্যন্ত — জানাবেন। আরও: বান্ডেল এখনো সব ঢোকানো না হলে (Cutting Input চলছে) কিন্তু ঢোকানোগুলো সব কমপ্লিট হলে সুইং খুলে যায় — বান্ডেল Qty ≥ অর্ডার Qty-ও শর্ত হবে কি না?

## ১২. দ্বিতীয় ধাপের পরিকল্পনা (rmg-এর বাকি অংশ; user 2026-11-24 "প্লানিং করে শুরু করো")

ক্রম: আগে ভিত্তি (হিসাব ঠিক করে), তারপর রিপোর্ট, তারপর লাইভ, তারপর অ্যাক্সেস, শেষে Final Inspection ও Route। প্রতিটা ফেজ শেষে: টেস্ট, ব্রাউজারে নিজে ক্লিক করে দেখা, dev ডাটা রেখে দেওয়া।

**ফেজ A — হিসাবের ভিত্তি**
- A1 Job-এ `size` ও `due_date` (জবের পরিচয় = job no + PO + colour + size)।
- A2 শিফট: HR & Payroll-এর শিফট (`hr_shifts`) থেকে; ট্যাবলেটে শিফট বাছাই (চলতি শিফট আপনা থেকে), প্রতিটা ট্যাপে `shift_id`; রাত পার হওয়া শিফটের মাঝরাতের পরের ট্যাপ শিফট শুরুর দিনে গোনা হবে।
- A3 Line Attendance স্ক্রিন: তারিখ + সেকশন ধরে সব লাইনের উপস্থিতি ও ওভারটাইম মিনিট (শিফট অনুযায়ী), একবারে সেভ। Efficiency = পাস × SMV ÷ Σ(উপস্থিতি × (শিফটের মিনিট + OT)); হাজিরা না থাকলে টার্গেটের ম্যানপাওয়ার × ঘণ্টা।
- A4 টার্গেট একবারে (Plan the day গ্রিড) + Shortfall: আগের নির্ধারিত দিনের ঘাটতি দেখানো, এক ক্লিকে যোগ — নিজে থেকে যোগ হয় না।

**ফেজ B — অ্যানালিটিক্স ও রিপোর্ট**
- B1 Quality Analytics স্ক্রিন: তারিখের রেঞ্জ (≤৯০ দিন) — দৈনিক DHU/RFT/পাস ট্রেন্ড, টার্গেট বনাম অর্জন, লাইন-ওয়ারি, top defects, QC-ওয়ারি পারফরম্যান্স।
- B2 Reports (Reporting মডিউলের রিপোর্ট জব, PDF/Excel): Tally Sheet, Daily Quality Summary, Defect Analysis, QC Performance।

**ফেজ C — লাইভ**
- C1 রিয়েলটাইম: ট্যাপ হলেই ড্যাশবোর্ড/ট্যাবলেট আপডেট (Reverb)।
- C2 TV ডিসপ্লে: লাইন বা সেকশনের বোর্ড, লগইন ছাড়া একটা লিংকে (Production Tracking-এর মত লিংক দেওয়া/বাতিল)।
- C3 অ্যালার্ট: ঘণ্টার টার্গেটে পিছিয়ে থাকা লাইন, due date কাছে কিন্তু পাস কম এমন জব → নোটিফিকেশন; সীমা Settings-এ।

**ফেজ D — অ্যাক্সেস**
- D1 ম্যানেজার-লাইন: `qtally.view_all` না থাকলে শুধু নিজের লাইন (ড্যাশবোর্ড, শিট, হিস্টোরি, টার্গেট, অ্যানালিটিক্স)।
- D2 QC এসাইনমেন্ট ইতিহাস: কে কবে কোন লাইনে দেওয়া/সরানো হয়েছে।

**ফেজ E — Final Inspection**
- E1 AQL Standards (বায়ার-ওয়ারি লেভেল ও AQL) + ISO 2859-1 টেবিল (লট সাইজ → কোড লেটার → স্যাম্পল, accept/reject)।
- E2 AQL লট ইন্সপেকশন: প্ল্যান ইন্সপেকশনের সাথে সেভ; ফেল হলে আবার ইন্সপেকশন (attempt), এডিট নয়।
- E3 Pass/Fail চেক (মেটাল ডিটেক্টর, নিডল চেক)।
- E4 Waiver: `qtally.approve` + লিখিত কারণ; ফিরিয়ে নেওয়া যায়।

**ফেজ F — Route ও চেকপয়েন্ট**
- F1 চেকপয়েন্ট: সেকশনে Inline / End-line; আউটপুট গোনে একটাই; ট্যাপে স্ট্যাম্প।
- F2 Job Route: জব কোন সেকশন কোন ক্রমে যাবে; সেকশনের মাঝে WIP (আগের সেকশনের পাস − পরের সেকশনের চেক) ও পাইপলাইন ভিউ।

## ১১. As-built নোট

- 2026-11-24: মডিউল তৈরি; Platform-এ "Modules" ট্যাব (প্রথম লাইসেন্সড মডিউল); Defect Types-এ তিন সেকশনের স্ট্যান্ডার্ড তালিকা এক ক্লিকে লোড হয়।
- 2026-11-24 ফেজ A ✅: Job-এ size ও due date (দিন বাকি / পেরিয়ে গেছে); ট্যাপে HR শিফট (চলতি শিফট আপনা থেকে, অফলাইন কিউ-তেও শিফট যায়, নাইট শিফটের মাঝরাতের পরের ট্যাপ শিফট শুরুর দিনে); নতুন স্ক্রিন **Line Attendance** (লাইন × শিফট: লোক ও OT মিনিট); Efficiency = উপার্জিত মিনিট (জব-ওয়ারি পাস × SMV) ÷ Σ লোক × (শিফট মিনিট + OT), হাজিরা না থাকলে টার্গেটের ম্যানপাওয়ার × ঘণ্টা — ট্যাবলেট, ড্যাশবোর্ড, টালি শিট, রিপোর্টে; Line Target-এ **Plan the day** গ্রিড ও আগের নির্ধারিত দিনের ঘাটতি (+ চাপলে যোগ)।
- 2026-11-24 ফেজ B ✅: **Quality Analytics** (রেঞ্জ ≤৯০ দিন, এক সেকশন: দিনওয়ারি টার্গেট বনাম পাস ও DHU চার্ট + টেবিল, লাইন-ওয়ারি, top defects, QC-ওয়ারি); **Reports** স্ক্রিন — Reporting মডিউলের রানার দিয়ে ৪টা রিপোর্ট (Daily quality summary, Hourly tally, Defect analysis, QC performance) Excel / PDF / CSV, বড় হলে পেছনে তৈরি হয়ে "My exports"-এ।
- 2026-11-25 ✅ **কাটিং প্যানেল ভিত্তিক টালি** (user সংশোধন: "এমপ্লয়ি টালি দিয়ে প্যানেল ভিত্তিক চেক করবে, সেই রেকর্ড গুলোই ইন্সপেকশন এ থাকবে"): টালি এন্ট্রিতে কাটিং টেবিল বাছলে কাটিং মোড — লট (গার্মেন্ট, কাটিং নং, ব্যাচ নং, কাট পরিমাণ) একবার, বান্ডেল (নং, সিরিয়াল, পরিমাণ, সাইজ), হাতের প্যানেল বাছাই, তারপর প্রতিটি প্যানেলে **OK** বা **Reject** (ফর্মের কোড B … Y1 বাছাই); প্রতিটি ট্যাপে লট, বান্ডেল ও প্যানেল সেভ হয় (qt_entries)। **Cut Panel Inspection** এখন শুধু রিপোর্ট: লট অনুযায়ী (দিন, টেবিল, জব, কাটিং নং, ব্যাচ) ট্যাপ থেকে কাগজের মতো গ্রিড + A4 প্রিন্ট — হাতে লেখার ফর্ম ও তার টেবিল বাদ। Setup → **Panels** (গার্মেন্ট অনুযায়ী প্যানেল) থাকছে; ড্যাশবোর্ডের Cutting কার্ড কাটিং ট্যাপ থেকে।
- 2026-11-25: টালির ট্যাপ এখন সাথে সাথে গোনে (সার্ভারের উত্তরের অপেক্ষা নেই; ব্যর্থ হলে অফলাইন কিউ); টালি এন্ট্রির উপরের বার এলাইন ঠিক; চ্যাট বাবল টেনে সরানো যায় (জায়গা ব্রাউজারে মনে থাকে)।
- 2026-11-25 ✅ **প্রতি সেকশনের নিজের ম্যানেজার, নিজের ইনপুট ও টার্গেট; QC শুধু চেক করে** (user: "এখানে qc এর কাজ বেশি হয়ে গেছে…"): নতুন ফোল্ডার **Input** — **Cutting Input** (কাটিং ডিপার্টমেন্টের বান্ডেল কার্ড স্ক্যান বা হাতে লেখা; Cutting Floor-এর কার্ড হলে জব, প্যানেল, সাইজ, সিরিয়াল, পরিমাণ নিজে বসে; প্রতিটি একটি কাটিং QC টেবিলে যায়; এক কার্ড একবারই; এক জবে এক গার্মেন্ট টাইপ; চেক হয়ে গেলে ফেরত নেওয়া যায় না) এবং **Line Input** (সুইং: কাটিংয়ের সম্পূর্ণ পিস; কোয়ালিটি: সুইংয়ের পাস — যত অপেক্ষায় তার বেশি নয়, লাইন যত চেক করেছে তার নিচে ফেরত নয়)। কাটিং QC কার্ড স্ক্যান করে প্যানেল ধরে RFT / Defect / Rectify / Reject — চার বাটন সব সেকশনে একই; উপরের আগের নোটের লট-ফর্ম ও OK/Reject বাতিল। **সম্পূর্ণ পিস** = সাইজ অনুযায়ী গার্মেন্টের সব প্যানেলের পাসের সবচেয়ে কমটা। **Job Flow** — জব কাটিং থেকে কোয়ালিটি: প্যানেল, সম্পূর্ণ পিস, ইনপুট, পাস, মাঝে কত অপেক্ষায়। পারমিশন: `qtally.cutting.manage`, `qtally.sewing.manage`, `qtally.quality.manage` (নিজের সেকশনের ইনপুট, টার্গেট, হাজিরা); `qtally.target.manage` = সব সেকশন। Cut Panel Inspection-এ Rectify কলাম।
- 2026-11-25 ✅ **তিন ডিপার্টমেন্ট আলাদা মেনু; টার্গেটই ইনপুট** (user: "সুইং ও কোয়ালিটি তে আলাদা ভাবে ইনপুট থাকে না লাইন টার্গেট দিয়েই সেট হবে"): মেনু ফোল্ডার **Overview** (Dashboard, Job Flow, Analytics, Reports — `qtally.view`), **Cutting**, **Sewing**, **Quality** (প্রতিটিতে নিজের Dashboard, Tally, Line Target, Attendance, Tally Sheet, History; কাটিংয়ে Cutting Input ও Cut Panel Inspection বাড়তি), **Planning**, **Setup**। ঠিকানা `/quality-tally/{cutting|sewing|quality}-{dashboard|tally|target|attendance|sheet|history}`। আগের নোটের **Line Input বাদ** (টেবিল `qt_line_inputs` উঠে গেছে, যা দেওয়া ছিল তা ওই দিনের টার্গেট হয়েছে)। **Line Target**-এ উপরে **Pending orders** (অর্ডার, পাস, বাকি, আগের সেকশন থেকে প্রস্তুত, ওই দিনের টার্গেট, due date) — সেখান থেকেই "Give target"। সুইং/কোয়ালিটি QC শুধু ওই দিন টার্গেট দেওয়া জবে ট্যাপ করে, আর আগের সেকশনে যত পাস হয়েছে তার বেশি চেক করতে পারে না। পারমিশন `qtally.{section}.view|tally|manage`; API-ও অন্য সেকশনের ডাটা দেয় না (403), ঠিকানা লিখলে 404। রোল: `QT_{CUTTING|SEWING|QUALITY}_MANAGER` ও `_QC`; Cutting/Production/Quality ডিপার্টমেন্টের রোলও নিজের সেকশনেই সীমিত। dev-এ ৬ জন ইউজার: cutting|sewing|quality `.manager` / `.qc` @delta.test।
- 2026-11-25 ✅ **user-এর পাঁচ উত্তর, দুই শিফট, QR ইনপুট:** (১) কাটিং টার্গেট **প্যানেলে**, (২) টার্গেটের বেশি চেক **চলবে**, (৩) **Jobs** সেকশন ম্যানেজাররা রাখেন (`qtally.job.manage`; মেনুতে Planning → Jobs), **QC Assignment** শুধু Owner / Factory Admin / Management, (৪) জোড়া পার্ট **দুইবার**: Panels-এ `Per piece` (Sleeve 2) — বান্ডেলে পার্টের সংখ্যা = Qty × Per piece, সম্পূর্ণ পিস = min(pass ÷ per piece), টার্গেটের সাজেশনও তাই, (৫) **ওপেনিং পিস**: Job-এ `opening_cut / opening_sewing / opening_quality` (আগের সেকশনের বেশি নয়), Job Flow ও Pending orders-এ গোনা হয়; পুরনো বান্ডেলহীন ট্যাপগুলোকে তাদের লেখা বান্ডেল নম্বরে বান্ডেল বানিয়ে যুক্ত করা হয়েছে। **শিফট** (পুরো সিস্টেম): শুধু **DAY 08:00–17:00 (বিরতি 13:00–14:00)** ও **NIGHT 17:00–08:00 (বিরতি 01:00–02:00)**; HR Shift Master-এ নতুন ঘর Break Start, downtime/availability হিসাব এখন বিরতির আসল সময়ে; পুরনো শিফটের সব রেকর্ড (হাজিরা, রোস্টার, প্ল্যান, নিটিং, ট্যাপ) এ দুটিতে সরানো, Capacity Planning-এর Overtime সারি বাদ, Finishing-এর A/B/C/General ও Prototype-এর Evening/General → Day/Night। **Cutting Input:** পাতায় শুধু স্ক্যান বাক্স; কার্ড স্ক্যান করলে **পপআপে** সব তথ্য, জব নিজে মেলে, Enter = Give to QC, পরের কার্ড স্ক্যান করলে এটি দিয়ে ওটি খোলে; আগে দেওয়া / অচেনা কার্ডও পপআপে; **Manual entry** আলাদা পপআপ। স্ক্যান ও Give সরাসরি `/api/quality-tally` proxy দিয়ে। **জব মেলানো** (কার্ডে Job No নেই): একই অর্ডার+কালারের কার্ড আগে যে জবে → Style+Color+PO → Style+Color (PO বিরোধ না হলে); দুটো জব সমান মিললে আন্দাজ করে না, ম্যানেজার একবার বাছেন, পরের কার্ড নিজে যায়।
- 2026-11-25 ✅ **কার্ডের নমুনা ও উত্তর:** বান্ডেল কার্ডের বারকোডে শুধু বান্ডেল নম্বর (`BND202611250006`) — যেমন আছে তেমনই স্ক্যান হয়, প্রতি কার্ডে একটি পার্ট। **Cutting Input-এ টেবিল নেই:** যে QC টেবিল বান্ডেলে প্রথম ট্যাপ করে বান্ডেল তার (`qt_cut_bundles.line_id` nullable; একমাত্র ট্যাপ Undo করলে আবার সবার); Cut Qty এখন লট ধরে (জব + Cutting No + Batch)। **Manual entry-তে প্যানেলের তালিকা** (গার্মেন্ট ধরে চিপ; জব বাছলে তার গার্মেন্ট নিজে আসে)। **নাইট ৮ ঘণ্টা রেগুলার, বাকি OT:** `hr_shifts.working_hours` = 8; রেগুলার শেষ = শুরু + কাজের ঘণ্টা + বিরতি (নাইটে 02:00 AM) — OT, early out, OT request-এর ডিফল্ট, OT শিট ও রিপোর্ট সব এটা ধরে (`AttendanceBook::regularEnd`)। **সময় সব জায়গায় AM/PM** (`frontend/src/lib/clock.js`, `App\Shared\Support\Clock`; ভেতরের মান ২৪ ঘণ্টাই)। **Toast উপরে মাঝখানে**, ভুলের বার্তা বড় ও লাল (পুরো সিস্টেম)। Attendance মেনু আপাতত থাকছে; Job হাতে লেখাই থাকছে (Order Summary থেকে নয়)।
- 2026-11-25 ✅ **শিফট HR থেকে, এক লাইনে লাইন লুকানো, নাইট 08:00 PM–05:00 AM:** নাইট শিফট 20:00–05:00 (আগে ভুলে 17:00–08:00 দেওয়া ছিল), বিরতি 01:00–02:00, ৮ ঘণ্টা, OT 05:00 AM থেকে। ট্যালিতে শিফট বাছাই নেই: QC-র HR employee-র রোস্টার, না থাকলে তার স্থায়ী শিফট, সেটাও না থাকলে ঘড়ির সময় (`QtBook::shiftOf`); দুই শিফটের মাঝের সময় আগের শিফটের OT (17:00–20:00 ডে, 05:00–08:00 নাইট), নাইটের সকাল দুপুর ১২টা পর্যন্ত আগের দিনের প্রোডাকশন তারিখে। QC-র এক সেকশনে একটাই লাইন হলে লাইন ঘর দেখায় না। Attendance-এ শিফট আগের মতো (ম্যানেজারের)।
- 2026-11-28 ✅ **স্টেজের নিয়ম ও স্ট্যাটাস:** উপরের Business Rules দেখুন; টেস্ট `test_sewing_takes_a_job_only_once_cutting_has_finished_it_and_quality_only_once_sewing_has` (১৪/১৪ পাস); setUpFloor-এ সুইং টার্গেট এখন বান্ডেল কাটিং শেষ হওয়ার পরে।
