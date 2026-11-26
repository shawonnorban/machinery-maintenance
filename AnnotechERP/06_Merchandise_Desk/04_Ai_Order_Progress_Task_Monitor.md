# Ai Order Progress & Task Monitor

> **স্ট্যাটাস:** ✅ সম্পন্ন (৯টা স্ক্রিন চালু; 06/02-এর একই রান আর engine-এর ওপর)
> **মডিউল:** [Merchandise Desk](00_Index.md) · **ফেজ ২:** বিক্রয় ও মার্চেন্ডাইজিং · **কী:** `06/04`

**⬅ ফিরে যান:** [Merchandise Desk](00_Index.md) | [AnnotechERP Index](../00_Index.md)

---

## ১. স্ক্রিন (Brickland ERP থেকে)

<!-- AUTO:screens:start -->
| # | স্ক্রিন | পাথ | ট্যাব | ফিল্ড | ডায়ালগ |
|---|---|---|---|---|---|
| 1 | Overall Order Progress | `/m/merchandising/ai-overall-order-progress` | 1 | 0 | 0 |
| 2 | Task Completion Rate | `/m/merchandising/ai-task-completion` | 1 | 2 | 0 |
| 3 | Milestone Progress Tracking | `/m/merchandising/ai-milestone-tracking` | 1 | 0 | 0 |
| 4 | Department-Wise Progress | `/m/merchandising/ai-department-wise-progress` | 1 | 0 | 0 |
| 5 | Pending Task Queue | `/m/merchandising/ai-pending-tasks` | 1 | 0 | 0 |
| 6 | Overdue Task Queue | `/m/merchandising/ai-overdue-tasks` | 1 | 0 | 0 |
| 7 | Deadline Watch Board | `/m/merchandising/ai-deadline-monitoring` | 1 | 0 | 0 |
| 8 | Delay Forecast | `/m/merchandising/ai-delay-prediction` | 1 | 0 | 0 |
| 9 | Task Priority Advice | `/m/merchandising/ai-task-priority-recommendation` | 1 | 0 | 0 |
<!-- AUTO:screens:end -->

## ২. প্রতিটা স্ক্রিনের লাইভ বিবরণ (ফিল্ড, ট্যাব, গ্রিড, ডায়ালগ, ভ্যালিডেশন)

<!-- AUTO:detail:start -->
#### Overall Order Progress

`/m/merchandising/ai-overall-order-progress`

**ভিউ**
- সেকশন: Overall Order Progress
- বাটন/অ্যাকশন: Run analysis

#### Task Completion Rate

`/m/merchandising/ai-task-completion`

**ভিউ**

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন/প্লেসহোল্ডার |
|---|---|---|---|
| Search Task completion | search |  | Search order, buyer or style… |
| Items per page | select |  | 10, 25, 50, 100 |
- গ্রিড কলাম: Order#, Ship date, Production progress, Task completion, Next milestone, Delay prediction
- সেকশন: Task Completion %, Furthest behind on the calendar, Order by order
- বাটন/অ্যাকশন: Run analysis, Order#, Ship date, Production progress, Task completion, Next milestone, Delay prediction

#### Milestone Progress Tracking

`/m/merchandising/ai-milestone-tracking`

**ভিউ**
- সেকশন: Milestone Tracking
- বাটন/অ্যাকশন: Run analysis

#### Department-Wise Progress

`/m/merchandising/ai-department-wise-progress`

**ভিউ**
- সেকশন: Department-wise Progress
- বাটন/অ্যাকশন: Run analysis

#### Pending Task Queue

`/m/merchandising/ai-pending-tasks`

**ভিউ**
- সেকশন: Pending Tasks
- বাটন/অ্যাকশন: Run analysis

#### Overdue Task Queue

`/m/merchandising/ai-overdue-tasks`

**ভিউ**
- সেকশন: Overdue Tasks
- বাটন/অ্যাকশন: Run analysis

#### Deadline Watch Board

`/m/merchandising/ai-deadline-monitoring`

**ভিউ**
- সেকশন: Deadline Monitoring
- বাটন/অ্যাকশন: Run analysis, All (0), Milestones, Shipments

#### Delay Forecast

`/m/merchandising/ai-delay-prediction`

**ভিউ**
- সেকশন: Delay Prediction
- বাটন/অ্যাকশন: Run analysis

#### Task Priority Advice

`/m/merchandising/ai-task-priority-recommendation`

**ভিউ**
- সেকশন: AI Task Priority Recommendation
- বাটন/অ্যাকশন: Run analysis
<!-- AUTO:detail:end -->

## ৩. বিদ্যমান কোড যা কাজে লাগতে পারে

- **Engine আর snapshot:** 06/02-এ বানানো `RuleEngine` আর `/merchandise/ai/snapshot` (orders, tasks, deadlines, departments)। নতুন টেবিল বা API নেই।
- **T&A:** `OrderTna`, `TnaTask` (is_start/is_end = মাইলস্টোন), `TnaTaskGroup` (ডিপার্টমেন্ট), `TnaTaskRight` (owner)।

## ৪. ডাটা মডেল (Entity ও Field)

- `merch_ai_task_scores`: rank, days_late, slip_days, slip_risk (%), priority (০–১০০), recommendation। অর্ডারের দিকে `merch_ai_order_scores`-এর production_progress, task_completion, tasks_total/done, next_milestone। বিস্তারিত 06/02-এ।

## ৫. Workflow / Status ও Business Rules

- **Priority:** ০.৫ × অর্ডারের ঝুঁকি + দেরি (সর্বোচ্চ ৩০) + এই সপ্তাহে বাকি থাকলে বোনাস + মাইলস্টোন বোনাস। ফলে গুরুতর অর্ডারে শুক্রবারের টাস্ক সুস্থ অর্ডারে মঙ্গলবারের টাস্কের আগে আসে (Brickland-এর নোট)।
- **Slip:** দেরি + ৩ দিন; slip risk = অর্ডারের delay probability।
- **Milestone Tracker:** শুধু মাইলস্টোন, ছয় সপ্তাহ। **Deadline Monitor:** মাইলস্টোন আর শিপমেন্ট একসাথে, তিন সপ্তাহ।
- **Department:** T&A Task Group অনুযায়ী; গ্রুপ না থাকলে "Unassigned"।
- **উৎপাদনের ধাপ:** Cutting, Input, Sewing, Iron, Finishing, Shipped এখন ০%, কারণ উৎপাদন মডিউল নেই।

## ৬. API ও পারমিশন

পারমিশন: দেখতে `merchandise.order.view`, Run analysis-এ `merchandise.order.manage`। API: 06/02-এর `/merchandise/ai/snapshot` আর `/merchandise/ai/run`।

## ৭. Frontend পেজ (লাইট থিম)

সাইডবার: Merchandise Desk → **AI ORDER PROGRESS & TASK MONITOR** (৯)। প্রতিটা স্ক্রিনের শুরুতে রানের লাইন আর Run analysis।

1. **Overall Order Progress:** KPI Overall order progress, Task completion, Forecast slippage; প্রতিটা অর্ডারের কার্ড: band, "buyer · style · qty · ships date (in nd / nd past)", Delay prediction (+n days), Production progress আর ছয়টা ধাপ, Task completion (x% (d/t)), Next milestone।
2. **Task Completion Rate:** "Task Completion %" — "d of t tasks closed on the live book …" আর চার্ট; "Furthest behind on the calendar" (শীর্ষ ১৫, "d/t closed · n overdue · ships"); grid Order#, Ship date, Production progress, Task completion, Next milestone, Delay prediction।
3. **Milestone Tracker:** Date, What, Type, Order# (buyer), Order risk — ছয় সপ্তাহ; নিচে Brickland-এর নোট।
4. **Department-Wise Progress:** KPI Task completion, Pending tasks, Overdue tasks; "Where the calendar stands, by department" (Done / Pending / Overdue স্তূপ বার, শীর্ষ ১২, লেজেন্ডসহ); grid Department, Tasks, Done, Pending, Overdue, Completion।
5. **Pending Task List:** KPI Pending, Overdue, Due in 7 days; grid Task (department · owner), Order#, Plan date, Status, Owner, #, AI task priority recommendation।
6. **Overdue Task List:** KPI Overdue, Pending, Forecast slippage; "Most overdue" (শীর্ষ ১৫, "task — order"); grid #, Task, Order#, Plan date, Delay prediction, Slip risk, Priority, Advice।
7. **Deadline Monitor:** All (n) / Milestones / Shipments; Date, What, Type, Order#, Order risk; নিচে নোট।
8. **Delay Prediction:** KPI Forecast slippage, Overdue, Pending; "The tasks driving the slip" (শীর্ষ ১৫); grid Task, Order#, Plan date, Delay prediction, Slip risk, Advice।
9. **Task Priority Advice:** KPI Pending, Overdue, Due in 7 days; "Top of the list" (শীর্ষ ১৫, priority স্কোর, "#rank · owner · nd late"); grid #, Task, Order#, Plan date, Delay prediction, Slip risk, Priority, Advice; নিচে নোট।

## ৮. Implementation Files

<!-- AUTO:impl:start -->
| স্তর | ফাইল |
|---|---|
| ব্যাকএন্ড | engine, টেবিল আর snapshot API 06/02-এর সাথে শেয়ার করা: [02_Ai_Merchandising_Dashboard.md](02_Ai_Merchandising_Dashboard.md) দেখুন (`Support/MerchAi/RuleEngine.php`, `Http/Controllers/Api/MerchAiApiController.php`) |
| মেনু | `app/Modules/Identity/Support/MenuRegistry.php` (`MERCHANDISE.AI_PROGRESS` ফোল্ডার; ৯টা স্ক্রিন) |
| ফ্রন্টএন্ড | `frontend/src/app/(app)/merchandise-desk/[screen]/ai-monitor.js` (`AI_MONITOR_SCREENS`, `AI_MONITOR_MANAGE`), `page.js`, `ai-merch.js` (শেয়ার করা `view`, `grid`, `orderRow`, `taskRows`), `frontend/src/components/merchandise/ai/ai-kit.jsx` |
| ভাষা | `lang/{en,bn}/merchandise.php` (`master.mai`, স্ক্রিনের নাম/বিবরণ) |
| টেস্ট | `tests/Feature/Merchandise/MerchandiseAiEngineTest.php` |
<!-- AUTO:impl:end -->

### পরিকল্পিত নতুন/বদলানো ফাইল

- উৎপাদন মডিউল এলে engine-এ production progress যোগ হবে (06/02 দেখুন)।

## ৯. টেস্ট ও যাচাই

- **`MerchandiseAiEngineTest`** (06/02-এর সাথে শেয়ার করা): টাস্কের ক্রম, দেরি, slip, ডিপার্টমেন্ট আর পরামর্শ; দেরি হওয়া টাস্কের slip risk = অর্ডারের delay probability, আর তার priority দূরের মাইলস্টোনের চেয়ে বেশি; departments আর deadlines।
- **রিগ্রেশন:** 06/03-এর সাথে একই রান — ২৯টা টেস্ট, ২২৬টা assertion সবুজ।
- **ব্রাউজার যাচাই** (২০২৬-১০-২০, owner লগইন):
  - সাইডবারে AI ORDER PROGRESS & TASK MONITOR (৯) ✔
  - Overall Order Progress: KPI 0% / 12% / 6 days; দুটো অর্ডারের কার্ড, ছয়টা ধাপ, Task completion 12% (3/25), Next milestone Ex-Factory · 12 Jan 2027 ✔
  - Task Completion, Milestone Tracker, Department-Wise (General Process 2·1·1, Bulk Production 1·12·0 …), Pending, Overdue, Deadline Monitor, Delay Prediction ✔
  - Task Priority Advice: Top of the list (#1 Yarn Booking (Bulk) 10.9 · due in 4 d), grid-এ Slip risk % আর Priority, নিচে নোট ✔
  - console-এ কোনো ত্রুটি নেই।

## ১০. খোলা প্রশ্ন

- **উৎপাদনের ধাপ:** Cutting … Shipped এখন ০%; উৎপাদন মডিউল এলে engine থেকে আসবে।
- **Priority-র সূত্র:** Brickland শুধু স্কোর দেখায় (যেমন ৬৬), সূত্র নয়। এখানে নিজের সূত্র, Brickland-এর নোটের নিয়ম মেনে।

## ১১. As-built নোট

- **06/04 ✅** (২০২৬-১০-২০)। নতুন backend নেই; টাস্কের slip_risk আর priority এখন সংখ্যা (Brickland-এর "71%" আর "66"-এর মতো)।
- লাইভ প্রোব: `tools/erp_crawler/plans/ai2.json` (নয়টা স্ক্রিনের শিরোনাম, KPI, কলাম, কার্ড আর নোট)।
