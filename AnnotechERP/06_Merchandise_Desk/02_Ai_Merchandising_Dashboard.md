# Ai Merchandising Dashboard

> **স্ট্যাটাস:** ✅ সম্পন্ন (৯টা স্ক্রিন চালু; একটাই rule engine, Settings → AI Services-এ সার্ভিস ঠিক থাকলে AI-এর লিখিত বিশ্লেষণ। 06/03 আর 06/04 এই একই রানের ওপর চলে)
> **মডিউল:** [Merchandise Desk](00_Index.md) · **ফেজ ২:** বিক্রয় ও মার্চেন্ডাইজিং · **কী:** `06/02`

**⬅ ফিরে যান:** [Merchandise Desk](00_Index.md) | [AnnotechERP Index](../00_Index.md)

---

## ১. স্ক্রিন (Brickland ERP থেকে)

<!-- AUTO:screens:start -->
| # | স্ক্রিন | পাথ | ট্যাব | ফিল্ড | ডায়ালগ |
|---|---|---|---|---|---|
| 1 | Order Portfolio Overview | `/m/merchandising/ai-order-overview` | 1 | 0 | 0 |
| 2 | Order Completion Rate | `/m/merchandising/ai-order-completion` | 1 | 2 | 0 |
| 3 | Critical & At-Risk Orders | `/m/merchandising/ai-at-risk-critical-orders` | 1 | 2 | 0 |
| 4 | Delayed Order Register | `/m/merchandising/ai-delayed-orders` | 1 | 2 | 0 |
| 5 | Pending Activity Board | `/m/merchandising/ai-pending-activities` | 1 | 0 | 0 |
| 6 | Upcoming Deadline Board | `/m/merchandising/ai-upcoming-deadlines` | 1 | 0 | 0 |
| 7 | Buyer-Wise Order Status | `/m/merchandising/ai-buyer-wise-status` | 1 | 2 | 0 |
| 8 | Merchandiser Scorecard | `/m/merchandising/ai-merchandiser-performance` | 1 | 0 | 0 |
| 9 | Assisted Alerts & Advice | `/m/merchandising/ai-alerts-recommendations` | 1 | 0 | 0 |
<!-- AUTO:screens:end -->

## ২. প্রতিটা স্ক্রিনের লাইভ বিবরণ (ফিল্ড, ট্যাব, গ্রিড, ডায়ালগ, ভ্যালিডেশন)

<!-- AUTO:detail:start -->
#### Order Portfolio Overview

`/m/merchandising/ai-order-overview`

**ভিউ**
- সেকশন: Risk mix, Where the exposure sits
- বাটন/অ্যাকশন: Run analysis

#### Order Completion Rate

`/m/merchandising/ai-order-completion`

**ভিউ**

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন/প্লেসহোল্ডার |
|---|---|---|---|
| Search Order completion | search |  | Search order, buyer or style… |
| Items per page | select |  | 10, 25, 50, 100 |
- গ্রিড কলাম: Order#, Ship date, Production progress, Task completion, Next milestone, Delay prediction
- সেকশন: Order Completion %, Completion, order by order
- বাটন/অ্যাকশন: Run analysis, Order#, Ship date, Production progress, Task completion, Next milestone, Delay prediction

#### Critical & At-Risk Orders

`/m/merchandising/ai-at-risk-critical-orders`

**ভিউ**

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন/প্লেসহোল্ডার |
|---|---|---|---|
| Search At-risk and critical orders | search |  | Search order, buyer or style… |
| Items per page | select |  | 10, 25, 50, 100 |
- গ্রিড কলাম: Order#, Qty, Ship date, Risk, Progress, AI RECOMMENDED ACTION
- সেকশন: At-Risk & Critical Orders
- বাটন/অ্যাকশন: Run analysis, All (1), Critical, High, Order#, Qty, Ship date, Risk, Progress

#### Delayed Order Register

`/m/merchandising/ai-delayed-orders`

**ভিউ**

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন/প্লেসহোল্ডার |
|---|---|---|---|
| Search Delayed orders | search |  | Search order, buyer or style… |
| Items per page | select |  | 10, 25, 50, 100 |
- গ্রিড কলাম: Order#, Qty, Ship date, Forecast slip, Delay %, Band, AI RECOMMENDED ACTION
- সেকশন: Longest forecast slips, Delayed Orders
- বাটন/অ্যাকশন: Run analysis, Order#, Qty, Ship date, Forecast slip, Delay %, Band

#### Pending Activity Board

`/m/merchandising/ai-pending-activities`

**ভিউ**
- সেকশন: Pending Activities
- বাটন/অ্যাকশন: Run analysis

#### Upcoming Deadline Board

`/m/merchandising/ai-upcoming-deadlines`

**ভিউ**
- সেকশন: Upcoming Deadlines
- বাটন/অ্যাকশন: Run analysis, All (0), Milestones, Shipments

#### Buyer-Wise Order Status

`/m/merchandising/ai-buyer-wise-status`

**ভিউ**

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন/প্লেসহোল্ডার |
|---|---|---|---|
| Search Buyer-wise status | search |  | Search buyer… |
| Items per page | select |  | 10, 25, 50, 100 |
- গ্রিড কলাম: Buyer, Orders, Qty, Value, At risk, Avg health, Avg progress, On time
- সেকশন: Buyers carrying the exposure, Buyer-wise Status
- বাটন/অ্যাকশন: Run analysis, Buyer, Orders, Qty, Value, At risk, Avg health, Avg progress, On time

#### Merchandiser Scorecard

`/m/merchandising/ai-merchandiser-performance`

**ভিউ**
- সেকশন: Merchandiser Performance
- বাটন/অ্যাকশন: Run analysis

#### Assisted Alerts & Advice

`/m/merchandising/ai-alerts-recommendations`

**ভিউ**
- সেকশন: AI Alerts & Recommendations, Material is not secured on LPP-2026-000001, Material is not secured on LPP-2026-000002, LPP-2026-000001 is at high risk of missing LPP's date
- বাটন/অ্যাকশন: Run analysis, Open (3), High & critical, All, Acknowledge, Resolve, Dismiss
<!-- AUTO:detail:end -->

## ৩. বিদ্যমান কোড যা কাজে লাগতে পারে

- **অর্ডার:** `Order` (CONFIRMED / IN_PRODUCTION = লাইভ বুক), conf_date, ship_date, total_qty, unit_price, currency; `Style`, `Buyer`।
- **T&A:** `OrderTna` (plan_date, actual_date), `TnaTask` (is_sample, is_end, নাম), `TnaTaskGroup` (ডিপার্টমেন্ট), `TnaTaskRight` (can_update = owner)।
- **ম্যাটেরিয়াল:** `FabricBooking` (APPROVED), লাইনসহ `AccBooking` বা APPROVED `AccRequisition`, `AccPurchaseOrderLine` / `PurchasedFabricPoLine`, FINALIZED MRR।
- **অনুমোদন:** লাব ডিপের shade, স্যাম্পলের feedback, আর is_sample বা নামে "approv" থাকা T&A টাস্ক।
- **উৎপাদন:** কাটিং/সেলাই/শিপমেন্ট মডিউল এখনো নেই, তাই production progress = ০। মডিউল এলে `RuleEngine`-এর একটা জায়গায় যোগ হবে।

## ৪. ডাটা মডেল (Entity ও Field)

- **`merch_ai_runs`:** run_at, engine (RULE / CLAUDE), orders, tasks, alerts, narrative, run_by।
- **`merch_ai_order_scores`:** run_id, order_id, risk, health, band (LOW/MEDIUM/HIGH/CRITICAL), delay_probability, expected_delay, confidence, material_risk, production_risk, approval_risk, tna_risk, shipment_risk, production_progress, task_completion, tasks_total, tasks_done, next_milestone(+_date), factors (json, প্রতিটা ঝুঁকির বাক্য), action, action_source (RULE / CLAUDE), summary।
- **`merch_ai_task_scores`:** run_id, order_tna_id, order_id, rank, days_late, slip_days, slip_risk (%), priority (০–১০০), recommendation।
- **`merch_ai_alerts`:** company + order + kind unique; kind (SHIP_SOON / PAST_SHIP / MATERIAL / TASKS / APPROVAL), severity, title, detail, advice, status (OPEN / ACKNOWLEDGED / RESOLVED / DISMISSED), first_run_id, last_run_id, status_at, status_by।
- **Model:** `AiRun`, `AiOrderScore`, `AiTaskScore`, `AiAlert`। Engine: `Support/MerchAi/RuleEngine.php`, লেখক: `Support/MerchAi/ClaudeWriter.php`।

## ৫. Workflow / Status ও Business Rules

- **Run analysis** (order.manage): লাইভ বুকের প্রতিটা অর্ডার স্কোর হয়, খোলা T&A টাস্কের ক্রম ঠিক হয়, alert ওঠে বা বন্ধ হয়। সব স্ক্রিন শেষ রানটাই দেখায়, শুরুতে রানের লাইন থাকে ("20 Oct 2026, 07:55 · 2 orders, 22 tasks, 1 alerts")।
- **ঝুঁকি (০–১০০):** পাঁচটা factor-এর ওজনযুক্ত যোগফল, ওজন Brickland-এর Risk Driver Analysis থেকে নেওয়া: shipment ০.২২ + material ০.২২ + production ০.২৪ + T&A ০.১৮ + approval ০.১৪। elapsed = (আজ − conf) / (ship − conf)।
  - Material: চার ধাপ, প্রতিটা ২৫% (ফ্যাব্রিক বুকিং অনুমোদিত, অ্যাক্সেসরিজ বুকড, PO, স্টোরে রিসিভ)। ঝুঁকি = (১০০ − coverage) × (০.৬ + ০.৪ × elapsed)।
  - Production = (১০০ − prod) × min(১, elapsed × ১.০৫)। Shipment: তারিখ পেরোলে ১০০, নইলে elapsed × ১০০ − prod।
  - Approval: বাকি অনুমোদনের অনুপাত; ক্যালেন্ডারে কোনো অনুমোদন-মাইলস্টোন না থাকলে ৩০।
  - T&A: মেয়াদোত্তীর্ণ টাস্কের অনুপাত × ০.৭ + (elapsed − task completion) × ০.৩; কোনো T&A ক্যালেন্ডার না থাকলে ৪০।
  - Band: ৭৫+ Critical, ৬০+ High, ৪০+ Medium, বাকি Low।
- **Health** = ০.৬ × (১০০ − risk) + ০.৪ × গড়(production, task completion)।
- **Delay %:** logistic (risk − ৫৫) / ১২; তারিখ পেরোলে কমপক্ষে ৯৫।
- **Expected delay (দিন):** বাকি উৎপাদন আর বাকি ম্যাটেরিয়াল থেকে হিসাব, তারপর বাকি দিন বাদ।
- **Confidence:** ৪০ থেকে শুরু, যত ডাটা তত বেশি, সর্বোচ্চ ৯৫।
- **পরের পদক্ষেপ:** exposed (≤১৪ দিন, <৫০% তৈরি) → material → পিছিয়ে থাকা টাস্ক → approval → on track। Claude key থাকলে শীর্ষ ১৫টা অর্ডারের পদক্ষেপ Claude লেখে (`action_source` = CLAUDE), সাথে পুরো বইয়ের narrative।
- **টাস্কের ক্রম:** priority (০–১০০) = ০.৫ × অর্ডারের ঝুঁকি + দেরি (সর্বোচ্চ ৩০) + এই সপ্তাহে বাকি থাকলে বোনাস + মাইলস্টোন বোনাস (শুধু ৭ দিনের মধ্যে হলে)। সমান হলে বেশি দেরি আগে। slip = দেরি + ৩। slip risk = অর্ডারের delay probability (দেরি হলে পুরোটা, না হলে ছয় সপ্তাহে কমে)। পরামর্শ তিন রকম: "n day(s) past plan …", "Due in n days …", "Due <date> … — on plan."।
- **Alert:** SHIP_SOON, PAST_SHIP, MATERIAL (≤৪৫ দিন), TASKS, APPROVAL (≤৩০ দিন)। কাজ: Acknowledge / Resolve / Dismiss / Reopen। পরের রানে:
  - কারণ না থাকলে engine নিজেই RESOLVED করে।
  - ব্যবহারকারী RESOLVED করলেও কারণ থেকে গেলে আবার OPEN হয়।
  - DISMISSED আর ACKNOWLEDGED যেমন আছে তেমন থাকে।
- **Claude ছাড়া:** key না থাকলে, বা API ব্যর্থ হলে (refusal বা exception), rule engine-এর লেখাই থাকে আর কারণ log-এ যায়। নোট দেখায়: "No AI service is set up for order analysis, so these figures come from the rule engine …" (২০২৬-১১-১২ থেকে; আগে `.env`-এর key-র কথা বলত)।

## ৬. API ও পারমিশন

পারমিশন: দেখতে `merchandise.order.view`; Run analysis আর alert-এর কাজে `merchandise.order.manage`।

| Method | Path |
|---|---|
| GET | `/merchandise/ai/snapshot` (run, claude, orders, tasks, alerts, deadlines, buyers, departments, owners, weights, totals) |
| POST | `/merchandise/ai/run` |
| POST | `/merchandise/ai/alerts/{alert}/{action}` (acknowledge, resolve, dismiss, reopen) |

AI: Settings → AI Services-এর "অর্ডার বুক বিশ্লেষণ" ফিচার যে সার্ভিসে (Claude, OpenAI বা Gemini) বাঁধা, সেটাই লেখে (২০২৬-১১-১২ থেকে; আগে `.env`-এর `ANTHROPIC_API_KEY`)।

## ৭. Frontend পেজ (লাইট থিম)

সাইডবার: Merchandise Desk → **AI MERCHANDISING DASHBOARD** (৯)। প্রতিটা স্ক্রিনের শুরুতে রানের লাইন, Run analysis, আর Claude-এর narrative বা rule-only নোট। কোনো রান না থাকলে দেখায় "Nothing analysed yet"।

1. **Order Portfolio Overview:**
   - KPI: Live orders, Order completion, At risk, Delayed orders, Pending activities, Order book value (USD), Average health, Open alerts।
   - "Risk mix": band অনুযায়ী বার, একই লালের হালকা→গাঢ় ধাপে।
   - "Where the exposure sits": শীর্ষ ১০, সাথে "buyer · ships date"।
2. **Order Completion Rate:** KPI ৩টা, আর "Order Completion %" (০–২০ … ৮০–১০০%)। টেবিল: Order# (buyer · style), Ship date (in n d / n d late), Production progress, Task completion (x/y closed), Next milestone, Delay prediction (+n d / On time)।
3. **Critical & At-Risk Orders:** KPI ৪টা; segment All (n) / Critical / High। টেবিল: Order#, Qty, Ship date, Risk (score · band), Progress, AI recommended action (উৎসসহ)।
4. **Delayed Order Register:** KPI ৩টা; "Longest forecast slips" শীর্ষ ১০। টেবিল: Order#, Qty, Ship date, Forecast slip, Delay %, Band, Action।
5. **Pending Activity Board:** KPI ৩টা। টেবিল: Task (department · owner), Order#, Plan date (late / in), Status, Owner, #, AI task priority recommendation।
6. **Upcoming Deadline Board:** তিন সপ্তাহের হিসাব; segment All (n) / Milestones / Shipments। টেবিল: Date, What, Order#, Order risk।
7. **Buyer-Wise Order Status:** "Buyers carrying the exposure" চার্ট। টেবিল: Buyer, Orders, Qty, Value, At risk, Avg health, Avg progress, On time।
8. **Merchandiser Scorecard:** owner-প্রতি Tasks, Closed, Closed on time, Pending, Overdue, Completion। কোনো owner না থাকলে Brickland-এর বার্তা দেখায়।
9. **Assisted Alerts & Advice:** KPI ৩টা; ভিউ Open / High & critical / All। প্রতিটা কার্ডে title, detail, advice, severity, status আর Acknowledge / Resolve / Dismiss / Reopen।

ফিল্টার আর segment পাতা রিলোড ছাড়াই বদলায়; URL-এ কোনো id থাকে না।

## ৮. Implementation Files

<!-- AUTO:impl:start -->
| স্তর | ফাইল |
|---|---|
| ব্যাকএন্ড | `app/Modules/Merchandise/Database/Migrations/2026_12_07_000100_create_merchandise_ai_runs.php`, `Models/AiRun.php`, `Models/AiOrderScore.php`, `Models/AiTaskScore.php`, `Models/AiAlert.php`, `Support/MerchAi/RuleEngine.php`, `Support/MerchAi/ClaudeWriter.php`, `Http/Controllers/Api/MerchAiApiController.php`, `Routes/api.php`, `config/services.php` (`anthropic`), `.env.example` (`ANTHROPIC_API_KEY`), `composer.json` (`anthropic-ai/sdk`) |
| মেনু | `app/Modules/Identity/Support/MenuRegistry.php` (`MERCHANDISE.AI_DASHBOARD`, `AI_RISK`, `AI_PROGRESS` ফোল্ডার; 06/02-এর ৯টা স্ক্রিন) |
| ফ্রন্টএন্ড | `frontend/src/app/(app)/merchandise-desk/[screen]/ai-merch.js` (`AI_SCREENS`, `AI_MANAGE`), `page.js`, `actions.js` (`runMerchAi`, `actMerchAiAlert`), `frontend/src/components/merchandise/ai/ai-kit.jsx` (`AiRunBar`, `KpiRow`, `Panel`, `BandBars`, `RankList`, `SegmentedGrid`, `AlertsBoard`), `ai-format.js`, `frontend/src/app/globals.css` (`--risk-*`) |
| ভাষা | `lang/{en,bn}/merchandise.php` (`ai.*` engine-এর বাক্য, `master.mai`, ফোল্ডার `ai_dashboard`/`ai_risk`/`ai_progress`, স্ক্রিনের নাম/বিবরণ) |
| টেস্ট | `tests/Feature/Merchandise/MerchandiseAiEngineTest.php` |
<!-- AUTO:impl:end -->

### পরিকল্পিত নতুন/বদলানো ফাইল

- **উৎপাদন:** কাটিং/সেলাই/প্যাকিং/শিপমেন্ট মডিউল এলে `RuleEngine`-এ production progress আর shipped qty যোগ হবে।

## ৯. টেস্ট ও যাচাই

- **`MerchandiseAiEngineTest`:** ১টা টেস্ট, ৪৪টা assertion।
  - রান না থাকলে snapshot ফাঁকা।
  - সম্মিলিত ঝুঁকি = পাঁচটা ওজনযুক্ত factor-এর যোগফল (ওজনের যোগফল ১); অনুমোদন-মাইলস্টোন না থাকলে approval ৩০।
  - দেরি হওয়া টাস্কের slip risk = অর্ডারের delay probability; তার priority দূরের মাইলস্টোনের চেয়ে বেশি।
  - দুটো অর্ডার (১০ দিন বাকি আর ৮০ দিন বাকি) → CRITICAL আর LOW। সাথে delay %, expected delay, "exposed" পদক্ষেপ, material আর approval-এর বাক্য, health।
  - টাস্কের ক্রম আর পরামর্শ; totals, buyers, departments, deadlines।
  - চারটা alert; Acknowledge / Dismiss / Resolve; পরের রানে status-এর নিয়ম।
  - FACTORY_MANAGER শুধু দেখতে পারে, STORE_MANAGER দেখতেও পারে না। key ছাড়া engine RULE।
- **ব্রাউজার যাচাই** (২০২৬-১০-২০, owner লগইন):
  - সাইডবারে AI MERCHANDISING DASHBOARD (৯) ✔
  - Run analysis → "2 orders, 22 tasks, 1 alerts", সাথে rule-only নোট ✔
  - Overview: ৮টা KPI (Order book value 8,015 USD, Average health 46.7), Risk mix, শীর্ষ ঝুঁকি ✔
  - Completion, At-risk (খালি বার্তা), Delayed, Pending (২২টা টাস্ক, "1 day past plan on Order…"), Upcoming (Shipments segment), Buyer-wise, Merchandiser ✔
  - Alerts: TASKS alert Acknowledge → "All"-এ Acknowledged, সাথে Resolve/Dismiss ✔
  - console-এ কোনো ত্রুটি নেই। রান আর alert dev DB-তে রাখা আছে।

## ১০. খোলা প্রশ্ন

- **উৎপাদনের অগ্রগতি:** উৎপাদন মডিউল নেই, তাই ০%। ফলে নতুন অর্ডারেও কিছু production risk থাকে।
- **Claude:** dev-এ key নেই, তাই শুধু rule engine দিয়ে যাচাই হয়েছে। key দিলে narrative আর পদক্ষেপ Claude লিখবে; ব্যর্থ হলে rule engine-এর লেখাই থাকবে।
- **Merchandiser Scorecard:** owner আসে T&A Task Rights (can_update) থেকে। dev-এ কেউ সেট করা নেই, তাই খালি বার্তা দেখায়।

## ১১. As-built নোট

- **06/02 ✅** (২০২৬-১০-২০)। Brickland-এর তিনটা AI গ্রুপ একটাই রানের ওপর চলে। তাই engine, টেবিল আর snapshot API একবারই বানানো হয়েছে; 06/03 আর 06/04 শুধু নতুন স্ক্রিন যোগ করে।
- ঝুঁকির band ক্রমিক মাত্রা, তাই চার্টে একই লালের চারটা ধাপ (`--risk-low` … `--risk-critical`), dark mode-এ আলাদা ধাপ।
