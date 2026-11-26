# Ai Order Risk & Health

> **স্ট্যাটাস:** ✅ সম্পন্ন (১০টা স্ক্রিন চালু; 06/02-এর একই রান আর engine-এর ওপর)
> **মডিউল:** [Merchandise Desk](00_Index.md) · **ফেজ ২:** বিক্রয় ও মার্চেন্ডাইজিং · **কী:** `06/03`

**⬅ ফিরে যান:** [Merchandise Desk](00_Index.md) | [AnnotechERP Index](../00_Index.md)

---

## ১. স্ক্রিন (Brickland ERP থেকে)

<!-- AUTO:screens:start -->
| # | স্ক্রিন | পাথ | ট্যাব | ফিল্ড | ডায়ালগ |
|---|---|---|---|---|---|
| 1 | Order Risk Rating | `/m/merchandising/ai-order-risk-score` | 1 | 2 | 0 |
| 2 | Order Health Rating | `/m/merchandising/ai-order-health-score` | 1 | 2 | 0 |
| 3 | Delay Likelihood Rating | `/m/merchandising/ai-delay-probability` | 1 | 2 | 0 |
| 4 | Material Risk Watch | `/m/merchandising/ai-material-risk` | 1 | 2 | 0 |
| 5 | Production Risk Watch | `/m/merchandising/ai-production-risk` | 1 | 2 | 0 |
| 6 | Approval Risk Watch | `/m/merchandising/ai-approval-risk` | 1 | 2 | 0 |
| 7 | Shipment Risk Watch | `/m/merchandising/ai-shipment-risk` | 1 | 2 | 0 |
| 8 | Risk Driver Analysis | `/m/merchandising/ai-risk-factor-analysis` | 1 | 0 | 0 |
| 9 | Expected Delay Forecast | `/m/merchandising/ai-expected-delay` | 1 | 2 | 0 |
| 10 | Recommended Action Advice | `/m/merchandising/ai-recommended-action` | 1 | 0 | 0 |
<!-- AUTO:screens:end -->

## ২. প্রতিটা স্ক্রিনের লাইভ বিবরণ (ফিল্ড, ট্যাব, গ্রিড, ডায়ালগ, ভ্যালিডেশন)

<!-- AUTO:detail:start -->
#### Order Risk Rating

`/m/merchandising/ai-order-risk-score`

**ভিউ**

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন/প্লেসহোল্ডার |
|---|---|---|---|
| Search Order risk scores | search |  | Search order, buyer or style… |
| Items per page | select |  | 10, 25, 50, 100 |
- গ্রিড কলাম: Order#, Risk, Health, Delay %, Material, Production, Approval, Shipment, Exp. delay, Band, AI RECOMMENDED ACTION
- সেকশন: Risk bands, Highest composite scores, Order Risk Score %
- বাটন/অ্যাকশন: Run analysis, All (2), Critical, High, Medium, Order#, Risk, Health, Delay %, Material, Production, Approval, Shipment, Exp. delay, Band

#### Order Health Rating

`/m/merchandising/ai-order-health-score`

**ভিউ**

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন/প্লেসহোল্ডার |
|---|---|---|---|
| Search Order health scores | search |  | Search order, buyer or style… |
| Items per page | select |  | 10, 25, 50, 100 |
- গ্রিড কলাম: Order#, Qty, Ship date, Health score, Progress, Risk, Band
- সেকশন: Weakest orders on the book, Order Health Score
- বাটন/অ্যাকশন: Run analysis, Order#, Qty, Ship date, Health score, Progress, Risk, Band

#### Delay Likelihood Rating

`/m/merchandising/ai-delay-probability`

**ভিউ**

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন/প্লেসহোল্ডার |
|---|---|---|---|
| Search Delay probability | search |  | Search order, buyer or style… |
| Items per page | select |  | 10, 25, 50, 100 |
- গ্রিড কলাম: Order#, Ship date, Delay probability, Expected delay, Confidence, Band
- সেকশন: Delay Probability %, Order by order
- বাটন/অ্যাকশন: Run analysis, Order#, Ship date, Delay probability, Expected delay, Confidence, Band

#### Material Risk Watch

`/m/merchandising/ai-material-risk`

**ভিউ**

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন/প্লেসহোল্ডার |
|---|---|---|---|
| Search Material Risk | search |  | Search order, buyer or style… |
| Items per page | select |  | 10, 25, 50, 100 |
- গ্রিড কলাম: Order#, Ship date, Material Risk, Composite risk, Band, AI RECOMMENDED ACTION
- সেকশন: Material Risk, Worst exposed, Material Risk, order by order
- বাটন/অ্যাকশন: Run analysis, Order#, Ship date, Material Risk, Composite risk, Band

#### Production Risk Watch

`/m/merchandising/ai-production-risk`

**ভিউ**

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন/প্লেসহোল্ডার |
|---|---|---|---|
| Search Production Risk | search |  | Search order, buyer or style… |
| Items per page | select |  | 10, 25, 50, 100 |
- গ্রিড কলাম: Order#, Ship date, Production Risk, Composite risk, Band, AI RECOMMENDED ACTION
- সেকশন: Production Risk, Worst exposed, Production Risk, order by order
- বাটন/অ্যাকশন: Run analysis, Order#, Ship date, Production Risk, Composite risk, Band

#### Approval Risk Watch

`/m/merchandising/ai-approval-risk`

**ভিউ**

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন/প্লেসহোল্ডার |
|---|---|---|---|
| Search Approval Risk | search |  | Search order, buyer or style… |
| Items per page | select |  | 10, 25, 50, 100 |
- গ্রিড কলাম: Order#, Ship date, Approval Risk, Composite risk, Band, AI RECOMMENDED ACTION
- সেকশন: Approval Risk, Worst exposed, Approval Risk, order by order
- বাটন/অ্যাকশন: Run analysis, Order#, Ship date, Approval Risk, Composite risk, Band

#### Shipment Risk Watch

`/m/merchandising/ai-shipment-risk`

**ভিউ**

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন/প্লেসহোল্ডার |
|---|---|---|---|
| Search Shipment Risk | search |  | Search order, buyer or style… |
| Items per page | select |  | 10, 25, 50, 100 |
- গ্রিড কলাম: Order#, Ship date, Shipment Risk, Composite risk, Band, AI RECOMMENDED ACTION
- সেকশন: Shipment Risk, Worst exposed, Shipment Risk, order by order
- বাটন/অ্যাকশন: Run analysis, Order#, Ship date, Shipment Risk, Composite risk, Band

#### Risk Driver Analysis

`/m/merchandising/ai-risk-factor-analysis`

**ভিউ**
- সেকশন: Risk Factor Analysis, What each score is made of
- বাটন/অ্যাকশন: Run analysis, All (2), Critical, High, Medium, Cards, Table

#### Expected Delay Forecast

`/m/merchandising/ai-expected-delay`

**ভিউ**

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন/প্লেসহোল্ডার |
|---|---|---|---|
| Search Expected delay | search |  | Search order, buyer or style… |
| Items per page | select |  | 10, 25, 50, 100 |
- গ্রিড কলাম: Order#, Qty, Ship date, Forecast slip, Delay %, Band, AI RECOMMENDED ACTION
- সেকশন: Expected Delay
- বাটন/অ্যাকশন: Run analysis, Order#, Qty, Ship date, Forecast slip, Delay %, Band

#### Recommended Action Advice

`/m/merchandising/ai-recommended-action`

**ভিউ**
- সেকশন: AI Recommended Action
- বাটন/অ্যাকশন: Run analysis, All (2), Claude (0), Rule engine (2)
<!-- AUTO:detail:end -->

## ৩. বিদ্যমান কোড যা কাজে লাগতে পারে

- **Engine আর snapshot:** 06/02-এ বানানো `RuleEngine` আর `/merchandise/ai/snapshot`। এই গ্রুপ নতুন কোনো টেবিল বা API যোগ করে না।

## ৪. ডাটা মডেল (Entity ও Field)

- `merch_ai_order_scores`-এর risk, health, band, delay_probability, expected_delay, confidence, material/production/approval/tna/shipment_risk, factors (json), action, action_source, summary। বিস্তারিত 06/02-এ।

## ৫. Workflow / Status ও Business Rules

- **সম্মিলিত ঝুঁকি:** পাঁচটা factor-এর ওজনযুক্ত যোগফল — Delivery exposure ২২%, Material coverage ২২%, Production pace ২৪%, Time & action ১৮%, Approvals outstanding ১৪%। Risk Driver কার্ডে প্রতিটা factor-এর অবদান দেখায় "+২২.০ (২২%)" হিসেবে, যেমন Brickland-এ।
- **Risk Watch (৪টা):** "n of N orders are scoring 60 or above on this factor"; Worst exposed = সেই factor-এ শীর্ষ ১৫।
- **Health:** ঝুঁকির উল্টো দিক (06/02-এর সূত্র), সবচেয়ে দুর্বলটা আগে।
- **Recommended Action:** উৎস Claude বা Rule engine; key না থাকলে সব Rule engine।

## ৬. API ও পারমিশন

পারমিশন: দেখতে `merchandise.order.view`, Run analysis-এ `merchandise.order.manage`। API: 06/02-এর `/merchandise/ai/snapshot` আর `/merchandise/ai/run` (snapshot-এ `weights` আর `totals.factor_hot`/`factor_avg`-এ `tna` যোগ হয়েছে)।

## ৭. Frontend পেজ (লাইট থিম)

সাইডবার: Merchandise Desk → **AI ORDER RISK & HEALTH** (১০)। প্রতিটা স্ক্রিনের শুরুতে 06/02-এর মতো রানের লাইন আর Run analysis।

1. **Order Risk Rating:** KPI Orders scored, Average risk score, Average confidence; "Risk bands" (লালের চার ধাপ) আর "Highest composite scores" (শীর্ষ ১৫, "buyer · health · slip"); grid All (n) / Critical / High / Medium — Order#, Risk, Health, Delay %, Material, Production, Approval, Shipment, Exp. delay, Band, AI recommended action।
2. **Order Health Rating:** KPI Average health, Orders scored, Average risk; "Weakest orders on the book"; grid Order#, Qty, Ship date, Health score, Progress, Risk, Band।
3. **Delay Likelihood Rating:** KPI Average delay probability, Total expected delay (n days), Average confidence; "Delay Probability %" চার্ট; grid Order#, Ship date, Delay probability, Expected delay, Confidence, Band।
4–7. **Material / Production / Approval / Shipment Risk Watch:** factor-এর বিবরণ আর "n of N orders …" বাক্য, "Worst exposed" (শীর্ষ ১৫, "buyer · composite · ships"), grid "<Factor>, order by order" — Order#, Ship date, <Factor>, Composite risk, Band, Action।
8. **Risk Driver Analysis:** "Risk Factor Analysis" (চারটা sub-score-এর গড় — Material, Production, Approval, Shipment — বার চার্টে); "What each score is made of" — All (n) / Critical / High / Medium, আর **Cards / Table**। কার্ডে ঝুঁকি, band, buyer · style · qty, ship, নয়টা স্ট্যাট, পাঁচটা factor (T&A সহ: অবদান, ওজন, বার আর বাক্য), AI recommended action (উৎসসহ) আর summary।
9. **Expected Delay Forecast:** KPI Total expected delay, Average delay probability, Orders scored; grid Order#, Qty, Ship date, Forecast slip, Delay %, Band, Action।
10. **Recommended Action Advice:** All (n) / Claude (n) / Rule engine (n); প্রতিটা কার্ডে order, band, buyer, style, উৎস, পদক্ষেপ, summary আর "Risk · health · slip · ships"।

সব segment আর Cards/Table পাতা রিলোড ছাড়াই বদলায়।

## ৮. Implementation Files

<!-- AUTO:impl:start -->
| স্তর | ফাইল |
|---|---|
| ব্যাকএন্ড | engine, টেবিল আর snapshot API 06/02-এর সাথে শেয়ার করা: [02_Ai_Merchandising_Dashboard.md](02_Ai_Merchandising_Dashboard.md) দেখুন (`Support/MerchAi/RuleEngine.php`, `Http/Controllers/Api/MerchAiApiController.php`) |
| মেনু | `app/Modules/Identity/Support/MenuRegistry.php` (`MERCHANDISE.AI_RISK` ফোল্ডার; ১০টা স্ক্রিন) |
| ফ্রন্টএন্ড | `frontend/src/app/(app)/merchandise-desk/[screen]/ai-monitor.js` (`AI_MONITOR_SCREENS`, `AI_MONITOR_MANAGE`), `page.js`, `ai-merch.js` (শেয়ার করা `view`, `grid`, `orderRow`, `taskRows`), `frontend/src/components/merchandise/ai/ai-kit.jsx` (`FactorBoard`, `ActionBoard`, `Segments`, `BandChip`) |
| ভাষা | `lang/{en,bn}/merchandise.php` (`master.mai`, স্ক্রিনের নাম/বিবরণ) |
| টেস্ট | `tests/Feature/Merchandise/MerchandiseAiEngineTest.php` |
<!-- AUTO:impl:end -->

### পরিকল্পিত নতুন/বদলানো ফাইল

- উৎপাদন মডিউল এলে engine-এ production progress যোগ হবে (06/02 দেখুন)।

## ৯. টেস্ট ও যাচাই

- **`MerchandiseAiEngineTest`** (06/02-এর সাথে শেয়ার করা): সম্মিলিত ঝুঁকি পাঁচটা ওজনযুক্ত factor-এর যোগফল, ওজনের যোগফল ১, অনুমোদন-মাইলস্টোন না থাকলে approval ৩০।
- **রিগ্রেশন:** AI engine, MenuAccess, RoleMatrix, ApiGateCoverage, AccessControlConfiguration, IconCatalogue — ২৯টা টেস্ট, ২২৬টা assertion সবুজ।
- **ব্রাউজার যাচাই** (২০২৬-১০-২০, owner লগইন):
  - সাইডবারে AI ORDER RISK & HEALTH (১০) ✔; Run analysis → "2 orders, 22 tasks, 1 alerts" ✔
  - Order Risk Rating: Orders scored ২, Average risk 25.7, Average confidence 75%; Risk bands, Highest composite scores, ১১ কলামের grid ✔
  - Health, Delay Likelihood, চারটা Risk Watch ("1 of 2 orders are scoring 60 or above…", Worst exposed), Expected Delay ✔
  - Risk Driver Analysis: চারটা sub-score-এর চার্ট; কার্ডে পাঁচটা factor-এর অবদান (যেমন LPP-2026-000001: +14.9 (22%) Material coverage …) আর AI recommended action ✔; Table ভিউ ✔
  - Recommended Action: All (2) / Claude (0) / Rule engine (2); Claude-এ "No order in this view." ✔
  - console-এ কোনো ত্রুটি নেই। রানটা dev DB-তে রাখা আছে।

## ১০. খোলা প্রশ্ন

- **ওজন:** Brickland-এর কার্ড থেকে ওজন নেওয়া হয়েছে (২২/২২/২৪/১৮/১৪)। sub-score-এর সূত্র Brickland দেখায় না, তাই সেগুলো নিজের।
- **উৎপাদন:** উৎপাদন মডিউল নেই, তাই Production Risk শুধু সময় থেকে আসে।

## ১১. As-built নোট

- **06/03 ✅** (২০২৬-১০-২০)। নতুন backend নেই; engine-এ T&A factor যোগ আর ওজন Brickland-এর মতো করা হয়েছে, যাতে কার্ডের যোগফল মেলে।
- লাইভ প্রোব: `tools/erp_crawler/plans/ai2.json` (দশটা স্ক্রিনের শিরোনাম, KPI, কলাম, Risk Driver কার্ড আর Recommended Action কার্ড)।
