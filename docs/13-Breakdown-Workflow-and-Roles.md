# ১৩. ব্রেকডাউন ওয়ার্কফ্লো ও রোল দায়িত্ব

এই ডকুমেন্টে Breakdown মডিউলের সম্পূর্ণ সাইন-অফ চেইন (৪ রোলের মধ্য দিয়ে যাওয়া অনুমোদন-প্রক্রিয়া), প্রতিটা রোলের নির্দিষ্ট দায়িত্ব ও পারমিশন, এবং ফ্রন্টএন্ডে বাটন ভিজিবিলিটি কীভাবে নিয়ন্ত্রিত হয় — তা লেখা আছে। এটা কোড থেকে সরাসরি যাচাই করে লেখা (guess করা হয়নি) — সোর্স রেফারেন্স প্রতিটা সেকশনের শেষে দেওয়া আছে।

## ১. চারটি রোল

| রোল | কোড | মূল কাজ |
|---|---|---|
| Line Chief | `LINE_CHIEF` | মেশিন বন্ধ হলে রিপোর্ট করা, টেকনিশিয়ানের কাজ সরেজমিনে যাচাই (Verify) করা, তারপর Engineer/Manager-এর কাছে অনুমোদনের জন্য পাঠানো (Submit for Approval) |
| Technician | `TECHNICIAN` | নিজে Work Order শুরু (self-claim) করে আসল মেরামতের কাজ করা, শেষ হলে কারণ/root cause লিখে Complete করা |
| Maintenance Engineer | `MAINTENANCE_ENGINEER` | রিপোর্ট Acknowledge করা (এতে Work Order অটো-রেইজ হয়ে যায়), এবং শেষে Final Approval দিয়ে Close করা |
| Maintenance Manager | `MAINTENANCE_MANAGER` | Engineer-এর সব পারমিশন + আরও ব্যবস্থাপনাগত পারমিশন (Engineer-এরই superset) |

Line Chief-এর কোনো Technician রেকর্ড থাকে না — তার লাইন/ডিপার্টমেন্ট কভারেজ সরাসরি `users.department_id`/`users.production_line_id` কলাম থেকে আসে (`BreakdownScopeGuard::coverageFor()`)।

## ২. সম্পূর্ণ ওয়ার্কফ্লো (ধাপে ধাপে)

```
Line Chief                      Engineer/Manager                 Technician
─────────                       ────────────────                 ──────────
রিপোর্ট করে
(REPORTED)
                    ──────▶      Acknowledge করে
                                 (ACKNOWLEDGED)
                                 → একই ক্লিকে Work Order
                                   অটো-রেইজ + লাইনের
                                   টেকনিশিয়ান রোস্টার হয়ে যায়
                                                        ──────▶   নিজে Work Order
                                                                  Start করে (self-claim)
                                                                  (IN_REPAIR)
                                                                  মেরামত করে, দরকার হলে
                                                                  Hold/Resume করে
                                                                  Complete Repair করে
                                                                  (REPAIRED)
                                                                  → এখানেই Failure Code/
                                                                    Root Cause/Corrective/
                                                                    Preventive Action লেখে
◀──────────────────────────────────────────────────────────────────────────
Verify করে
(VERIFIED)
  ├─ সন্তুষ্ট না হলে: Reject (Send Back)
  │  → সরাসরি IN_REPAIR-এ ফেরত, টেকনিশিয়ান আবার করে
  └─ সন্তুষ্ট হলে: Submit for Approval
     (PENDING_APPROVAL)
                    ──────▶      Final Approval
                                   ├─ Close → (CLOSED, শেষ)
                                   └─ Reject → IN_REPAIR-এ ফেরত
```

`resume_production` (উৎপাদন আবার চালু) ধাপটি ঐচ্ছিক — REPAIRED থেকে সরাসরি VERIFIED-এ যাওয়া যায়, অথবা PRODUCTION_RESUMED হয়ে তারপর VERIFIED। দুটোই Line Chief-এর Verify-এর আগের বৈধ অবস্থা।

**সোর্স:** `app/Modules/Breakdown/Models/Breakdown.php` (`STATUSES`, `TRANSITIONS`), `app/Modules/Breakdown/Actions/TransitionBreakdown.php`।

## ৩. স্ট্যাটাস চেইন (হুবহু কোড থেকে)

```
REPORTED → ACKNOWLEDGED → ASSIGNED → IN_REPAIR ⇄ ON_HOLD
                                          │
                                          ▼
                                      REPAIRED ⇄ PRODUCTION_RESUMED
                                          │             │
                                          └──────┬──────┘
                                                 ▼
                                             VERIFIED
                                            ↙        ↘
                                  (Line Chief reject)   PENDING_APPROVAL
                                          ↓            ↙            ↘
                                      IN_REPAIR   (Engineer reject)   CLOSED
                                                        ↓
                                                    IN_REPAIR

CANCELLED — REPORTED/ACKNOWLEDGED/ASSIGNED/IN_REPAIR/ON_HOLD যেকোনো একটা থেকে সরাসরি সম্ভব
            (একবার REPAIRED হয়ে গেলে আর Cancel করা যায় না — তখন Reject-ই একমাত্র "ফেরত পাঠানোর" পথ)
```

## ৪. দুই জায়গায় "Reject / ফেরত পাঠানো" — কেন আলাদা

| কোথায় | কে করে | কোন Status থেকে | Permission | ফাংশন |
|---|---|---|---|---|
| Line Chief-এর নিজের Verify ধাপে | শুধু Line Chief (নিজের লাইন/ডিপার্টমেন্টের মধ্যে) | REPAIRED / PRODUCTION_RESUMED | `breakdown.breakdown.verify` | `TransitionBreakdown::rejectVerification()` |
| Engineer/Manager-এর Final Approval ধাপে | শুধু Engineer/Manager | PENDING_APPROVAL | `breakdown.breakdown.close` | `TransitionBreakdown::reject()` |

দুটোই একই কারণ-সহ IN_REPAIR-এ ফেরত পাঠায় এবং একই timing ফিল্ডগুলো ক্লিয়ার করে (`repair_completed_at`, `production_resumed_at`, `verified_by`, `verified_at` → `null`), যাতে ফেরত পাঠানো একটা রেকর্ড ভুলভাবে "আগেই ভেরিফাইড/কমপ্লিট" দেখা না যায় এবং ডাউনটাইম রিপোর্ট স্টেল ডেটা না দেখায়। দুটোই ভেতরে একই প্রাইভেট হেল্পার ব্যবহার করে: `TransitionBreakdown::sendBackToRepair()`।

**কেন Line Chief নিজেই আগে থেকে reject করতে পারে না — এটা কেন যোগ করা হলো:** আগে Line Chief-এর Verify ধাপে শুধু "Verify" বাটন ছিল, "Reject" ছিল না। ফলে কাজ আসলে ঠিক না হলেও Line Chief-এর কাছে সিস্টেমে কোনো ফেরত-পাঠানোর উপায় ছিল না — হয় ভুলভাবে Verify করতে হতো, নয়তো সিস্টেমের বাইরে গিয়ে Engineer-কে বলতে হতো। এটা Verify ধাপটাকে একটা real checkpoint না বানিয়ে রাবার-স্ট্যাম্পে পরিণত করতো। এখন Line Chief নিজেই, নিজের অথরিটিতে, সরাসরি ফেরত পাঠাতে পারে।

**সোর্স:** `app/Modules/Breakdown/Actions/TransitionBreakdown.php` (`reject()`, `rejectVerification()`, `sendBackToRepair()`)।

## ৫. রোল-ভিত্তিক পারমিশন ম্যাট্রিক্স (Breakdown + সংশ্লিষ্ট Work Order permission)

| অ্যাকশন | API Endpoint | Permission | Line Chief | Technician | Engineer/Manager |
|---|---|---|---|---|---|
| রিপোর্ট করা | `POST /breakdowns` | `breakdown.breakdown.create` | ✅ | ❌ | ✅ |
| Acknowledge (+ অটো Work Order রেইজ) | `POST .../acknowledge` | `breakdown.breakdown.acknowledge` | ❌ | ❌ | ✅ |
| নির্দিষ্ট টেকনিশিয়ান Assign (ম্যানুয়াল ওভাররাইড) | `POST .../assign` | `breakdown.breakdown.assign` | ❌ | ❌ | ✅ |
| Arrival রেকর্ড | `POST .../arrive` | `breakdown.breakdown.repair` | ❌ | ✅ | ✅ |
| Work Order Start (self-claim) | `POST /work-orders/{id}/start` | `work_order.work_order.start` | ❌ | ✅ | ✅ |
| Reopen Repair / Hold / Resume | `POST .../start-repair` ইত্যাদি | `breakdown.breakdown.repair` | ❌ | ✅ | ✅ |
| Complete Repair (Failure Code/Root Cause/Corrective/Preventive Action) | `POST .../complete-repair` | `breakdown.breakdown.repair` | ❌ | ✅ | ✅ |
| Resume Production | `POST .../resume-production` | `breakdown.breakdown.repair` | ❌ | ✅ | ✅ |
| **Verify** | `POST .../verify` | `breakdown.breakdown.verify` | ✅ | ❌ | ❌ |
| **Reject at Verify** (নতুন) | `POST .../reject-verification` | `breakdown.breakdown.verify` | ✅ | ❌ | ❌ |
| **Submit for Approval** | `POST .../submit-for-approval` | `breakdown.breakdown.verify` | ✅ | ❌ | ❌ |
| **Final Approval / Close** | `POST .../close` | `breakdown.breakdown.close` | ❌ | ❌ | ✅ |
| Final Approval Reject | `POST .../reject` | `breakdown.breakdown.close` | ❌ | ❌ | ✅ |
| Cancel | `POST .../cancel` | `breakdown.breakdown.close` | ❌ | ❌ | ✅ |
| অতিরিক্ত Work Order রেইজ | `POST .../work-order` | `work_order.work_order.create` | ❌ | ❌ | ✅ |

> Engineer/Manager `breakdown.breakdown.repair`/`work_order.work_order.start`-ও ধারণ করে (Technician-এর সেট Engineer-এর মধ্যে মার্জ করা), যাতে গভীর রাতে দরকার হলে ম্যানেজার নিজেও মেরামতের ধাপ চালাতে পারে — কিন্তু বাস্তবে এই বাটনগুলো মূলত Technician-ই ব্যবহার করে।

**সোর্স:** `app/Modules/Identity/Database/Seeders/RoleSeeder.php` (`$technician`, `$lineChief`, `$engineer`, `$maintenanceManager` অ্যারে), `app/Modules/Breakdown/Http/Controllers/Api/BreakdownApiController.php` (প্রতিটা মেথডের `$this->allow(...)`/`step()` কল)।

## ৬. ফ্রন্টএন্ডে বাটন ভিজিবিলিটি

`BreakdownActions` কম্পোনেন্ট প্রতিটা বাটন **দুটো শর্তেই** গেট করে — শুধু ব্রেকডাউনের বর্তমান `status` নয়, বরং লগইন করা ইউজারের `/auth/permissions`-ও:

```js
const ACTION_PERMISSIONS = {
  acknowledge: "breakdown.breakdown.acknowledge",
  assign: "breakdown.breakdown.assign",
  arrive: "breakdown.breakdown.repair",
  startWorkOrder: "work_order.work_order.start",
  startRepair: "breakdown.breakdown.repair",
  hold: "breakdown.breakdown.repair",
  completeRepair: "breakdown.breakdown.repair",
  resume: "breakdown.breakdown.repair",
  resumeProduction: "breakdown.breakdown.repair",
  verify: "breakdown.breakdown.verify",
  rejectVerification: "breakdown.breakdown.verify",
  submitForApproval: "breakdown.breakdown.verify",
  close: "breakdown.breakdown.close",
  reject: "breakdown.breakdown.close",
  cancel: "breakdown.breakdown.close",
  raiseWorkOrder: "work_order.work_order.create",
};
```

একইভাবে `/breakdowns` লিস্ট পেজের "Report breakdown" বাটনও `breakdown.breakdown.create` না থাকলে দেখানো হয় না।

**গুরুত্বপূর্ণ:** এটা শুধু UX (ক্লিক করে 403 পাওয়ার আগেই বাটন লুকানো) — আসল নিরাপত্তা ব্যাকএন্ডে, প্রতিটা এন্ডপয়েন্টে আলাদাভাবে এনফোর্স করা (ওপরের টেবিলের `$this->allow(...)` কলগুলো)। ফ্রন্টএন্ড বাইপাস করে সরাসরি API কল করলেও ব্যাকএন্ড একইভাবে 403 দেবে।

**সোর্স:** `frontend/src/components/breakdowns/breakdown-actions.jsx`, `frontend/src/app/(app)/breakdowns/page.js`, `frontend/src/app/(app)/breakdowns/[breakdownId]/page.js`।

## ৭. Line/Department হার্ড রেস্ট্রিকশন (`BreakdownScopeGuard`)

Line Chief এবং Technician শুধু নিজের লাইন/ডিপার্টমেন্টের ব্রেকডাউনেই রিপোর্ট/repair/verify করতে পারে — অন্য লাইনের ব্রেকডাউনে হাত দিলে 403। Engineer/Manager (`breakdown.breakdown.assign` ধারণকারী যে কেউ) এই রেস্ট্রিকশন থেকে সম্পূর্ণ মুক্ত (exempt) — রাত ২টায় যে জেগে আছে তাকে যেকোনো লাইনে পাঠানোর সুবিধার জন্য।

কভারেজ আসে দুইভাবে:
- **Technician:** নিজের `Technician` রেকর্ডের `department_id`/`production_line_id` থেকে।
- **Line Chief:** সরাসরি `User` রেকর্ডের `department_id`/`production_line_id` থেকে (তার কোনো Technician রেকর্ড নেই)।

দুটোই `null` হলে ("কভারেজ নেই") — unrestricted ধরা হয়।

**সোর্স:** `app/Modules/Breakdown/Services/BreakdownScopeGuard.php`।

## ৮. নোটিফিকেশন

| ইভেন্ট | কখন পাঠানো হয় | কাদের কাছে |
|---|---|---|
| `BREAKDOWN_AWAITING_VERIFICATION` | `completeRepair()` অথবা `resumeProduction()`-এর পর | ওই লাইন/ডিপার্টমেন্ট কভার করা Line Chief-রা |
| `BREAKDOWN_AWAITING_APPROVAL` | `submitForApproval()`-এর পর | ফ্যাক্টরির সব Engineer + Manager |

**সোর্স:** `app/Modules/Notification/Services/MaintenanceNotifier.php` (`breakdownAwaitingVerification()`, `breakdownAwaitingApproval()`), `app/Modules/Notification/Models/Notification.php` (`EVENT_TYPES`)।

## ৯. ডেমো ডেটা — Line 1 (Sewing / Production) স্কোপড সেটআপ

`database/seeders/DemoTenantSeeder.php`-তে একটা বাস্তব, রেস্ট্রিকটেড উদাহরণ সেট করা আছে (`scopeToLineOne()`), যাতে হার্ড লাইন-রেস্ট্রিকশন সত্যিই কিছু আটকায় — শুধু আনরেস্ট্রিকটেড ডেমো না:

| এন্টিটি | ডেমো লগইন | স্কোপ |
|---|---|---|
| Line Chief | `linechief@delta.test` | Sewing / Production Department → Line 1 |
| Technician (Karim Mia, EMP-1001) | `technician@delta.test` | Sewing / Production Department → Line 1 |
| মেশিন | Juki DDL-9000C (`SEW-DHK-00401`) | Sewing / Production Department → Line 1 |
| Manager | `maintenance@delta.test` | আনরেস্ট্রিকটেড (সব লাইন) |
| Engineer | `engineer@delta.test` | আনরেস্ট্রিকটেড (সব লাইন) |

সব ডেমো অ্যাকাউন্টের পাসওয়ার্ড: `password123`।

## ১০. প্রাসঙ্গিক টেস্ট (রেফারেন্স)

- `tests/Feature/Breakdown/BreakdownLifecycleTest.php` — পুরো স্ট্যাটাস চেইন, verify/reject/close-এর সব শাখা।
- `tests/Feature/Breakdown/BreakdownScopeGuardTest.php` — লাইন/ডিপার্টমেন্ট রেস্ট্রিকশন, ৪০৩ কেসগুলো।
- `tests/Feature/Breakdown/LineOneWorkflowTest.php` — এই ডকুমেন্টের ঠিক এই ওয়ার্কফ্লোটাই, Line 1-এর বাস্তব ফিক্সচার দিয়ে, প্রতিটা ধাপে ভুল রোলের 403 নিশ্চিত করে একবারে এন্ড-টু-এন্ড।
- `tests/Feature/Api/BreakdownApiTest.php`, `tests/Feature/Breakdown/BreakdownApiTest.php` — API-লেভেল কভারেজ।
- `tests/Feature/Identity/RoleMatrixTest.php` — প্রতিটা পারমিশন অন্তত একটা রোলে আছে কি না, ভুল পারমিশন কোনো রোলে নেই কি না।
