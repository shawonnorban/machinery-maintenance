# Dashboard

> **স্ট্যাটাস:** ✅ সম্পন্ন
> **মডিউল:** [Gate Pass](00_Index.md) · **ফেজ ৩:** সাপ্লাই ও স্টোর · **কী:** `11/04`

**⬅ ফিরে যান:** [Gate Pass](00_Index.md) | [AnnotechERP Index](../00_Index.md)

---

## ১. স্ক্রিন (Brickland ERP থেকে)

<!-- AUTO:screens:start -->
| # | স্ক্রিন | পাথ | ট্যাব | ফিল্ড | ডায়ালগ |
|---|---|---|---|---|---|
| 1 | Gate Pass Approval | `/m/gate-pass/gp-approval` | 1 | 0 | 0 |
| 2 | Gate Pass Out | `/m/gate-pass/gp-out` | 1 | 1 | 0 |
| 3 | Gate Pass In | `/m/gate-pass/gp-in` | 1 | 1 | 0 |
| 4 | Return Reconciliation | `/m/gate-pass/gp-reconciliation` | 1 | 0 | 0 |
| 5 | Gate Pass List | `/m/gate-pass/gp-list` | 1 | 12 | 0 |
<!-- AUTO:screens:end -->

## ২. প্রতিটা স্ক্রিনের লাইভ বিবরণ (ফিল্ড, ট্যাব, গ্রিড, ডায়ালগ, ভ্যালিডেশন)

<!-- AUTO:detail:start -->
#### Gate Pass Approval

`/m/gate-pass/gp-approval`

**ভিউ**
> 03 of 13
- গ্রিড কলাম: GATE PASS NO., DATE, CATEGORY, TYPE, PURPOSE, DESTINATION, TAKEN BY, ITEMS, QTY, STATUS, PREPARED BY
- গ্রিড কলাম: GATE PASS NO., DATE, CATEGORY, TYPE, PURPOSE, DESTINATION, TAKEN BY, ITEMS, QTY, STATUS
- সেকশন: Waiting for Approval (0), Approved — Waiting at the Gate (0)

#### Gate Pass Out

`/m/gate-pass/gp-out`

**ভিউ**
> 04 of 13

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন/প্লেসহোল্ডার |
|---|---|---|---|
| Gate Pass No. / Barcode / QR Scan | text |  | Scan or type GP-2026-000125 |
- গ্রিড কলাম: GATE PASS NO., DATE, CATEGORY, TYPE, PURPOSE, DESTINATION, TAKEN BY, ITEMS, QTY, STATUS
- গ্রিড কলাম: GATE PASS NO., DATE, CATEGORY, TYPE, PURPOSE, DESTINATION, TAKEN BY, ITEMS, QTY, STATUS, OUT TIME
- সেকশন: Approved — Waiting to Go Out (0), Today's Gate OUT (0)
- বাটন/অ্যাকশন: Search

#### Gate Pass In

`/m/gate-pass/gp-in`

**ভিউ**
> 05 of 13

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন/প্লেসহোল্ডার |
|---|---|---|---|
| Gate Pass No. / Barcode / QR Scan | text |  | Scan or type GP-2026-000125 |
- গ্রিড কলাম: GATE PASS NO., DATE, CATEGORY, TYPE, PURPOSE, DESTINATION, TAKEN BY, ITEMS, QTY, STATUS, BALANCE, EXPECTED RETURN
- গ্রিড কলাম: GATE PASS NO., DATE, CATEGORY, TYPE, PURPOSE, DESTINATION, TAKEN BY, ITEMS, QTY, STATUS
- সেকশন: Out — Waiting to Come Back (0), Today's Gate IN (0)
- বাটন/অ্যাকশন: Search

#### Return Reconciliation

`/m/gate-pass/gp-reconciliation`

**ভিউ**
> 06 of 13
- গ্রিড কলাম: GATE PASS NO., DESTINATION, PURPOSE, ITEMS, EXPECTED RETURN, OUT, RETURNED, SHORT, BALANCE, RETURN, STATUS
- সেকশন: OUT vs IN

#### Gate Pass List

`/m/gate-pass/gp-list`

**ভিউ**
> 07 of 13

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন/প্লেসহোল্ডার |
|---|---|---|---|
| Search | text |  | GP No., employee, item, buyer… |
| From Date | date |  |  |
| To Date | date |  |  |
| Category | select |  | All, Main (Employee), Item |
| Gate Pass Type | select |  | All, OUT & IN, OUT ONLY |
| Destination / Location | select |  | All |
| Employee (Card / Name) | text |  |  |
| Item | text |  |  |
| Buyer | text |  |  |
| Style | text |  |  |
| PO | text |  |  |
| Carrier | select |  | All, Employee, Non-Employee |
- গ্রিড কলাম: GATE PASS NO., DATE, CATEGORY, TYPE, PURPOSE, DESTINATION, TAKEN BY, ITEMS, QTY, STATUS, RETURN
- সেকশন: Gate Passes (0)
- বাটন/অ্যাকশন: All, Search, Reset, Excel
<!-- AUTO:detail:end -->

## ৩. বিদ্যমান কোড যা কাজে লাগতে পারে

- 11/03-এর `gate_passes`, `gate_pass_lines`, `gate_pass_ins`, `gate_pass_in_lines`, `gate_pass_events` আর `GatePassBook` (OUT, ফেরত, কম আর বাকির হিসাব, ফেরতের অবস্থা, তালিকার সারি)।

## ৪. ডাটা মডেল (Entity ও Field)

নতুন টেবিল নেই ([11/03](03_General.md) দেখুন)। প্রতিটা ধাপ `gate_pass_events`-এ লেখা হয়: CREATED, SUBMITTED, APPROVED, REJECTED, SENT_BACK, CANCELLED, OUT, IN, CLOSED।

## ৫. Workflow / Status ও Business Rules

- **অনুমোদন:**
  - Waiting for Approval (পুরনোটা আগে) আর Approved — Waiting at the Gate।
  - সারিতে ক্লিক করলে pass পাতার ভেতরেই খোলে (dialog নয়)।
  - PENDING হলে: Approve (নোট ঐচ্ছিক), Reject (কারণ লাগে), Cancel Gate Pass।
  - APPROVED হলে: Send Back for Amendment (কারণ লাগে; আবার খসড়া হয়), Gate OUT (Out স্ক্রিনে pass লোড করা অবস্থায় খোলে, `?no=GP-…`), Cancel।
  - বাতিল করতে পারেন অনুমোদনকারী, বা খসড়া / অপেক্ষমাণ pass হলে প্রস্তুতকারী।
- **Gate OUT:**
  - নম্বর স্ক্যান বা লেখা (Search / Clear), বা সারিতে ক্লিক।
  - শুধু অনুমোদিত pass; নইলে লাল নোটিস ("… is Pending Approval — only an approved gate pass can go out.")।
  - Gate Remarks, তারপর CONFIRM OUT dialog। OUT-এর সময় আর ব্যক্তি রেকর্ড হয়।
  - ফলাফল: ফেরতযোগ্য → OUT; শুধু-OUT → COMPLETED।
  - তালিকা: Approved — Waiting to Go Out, Today's Gate OUT (OUT TIME সহ)।
- **Gate IN:**
  - শুধু বাইরে থাকা ফেরতযোগ্য pass (OUT / PARTIAL)।
  - চারটা কার্ড: OUT Quantity, Already Returned, Total IN (after this), Balance (শেষ ফেরতে Short)।
  - প্রতিটা আইটেমে Return Qty (placeholder = বাকি, বাকির বেশি নয়) আর Left Out / Short কলাম।
  - "Final return — nothing more is coming back" টিক দিলে বাকিটা কম ঘোষণা হয়, তখন Short Reason লাগে (Missing, Damage, Rejected, Quality Issue, Other)।
  - নিচে ফলাফলের লেখা: "4 stays out — the pass becomes PARTIALLY RETURNED." / "1 will be declared SHORT (Missing). An approver must close it." / সব ফিরলে বন্ধ।
  - প্রতিটা IN-এর নিজের নম্বর GPI-YYYY-NNNNNN।
  - ফলাফল: সব ফিরলে CLOSED, শেষ ফেরতে কম থাকলে SHORT, নইলে PARTIAL।
  - Main pass: IN মানে কর্মী ফিরেছেন → CLOSED।
  - নিশ্চিত হলে panel বন্ধ হয়, তালিকা নতুন করে আসে।
- **ফেরত মিলিয়ে দেখা:**
  - বাইরে যাওয়া প্রতিটা ফেরতযোগ্য Item pass: OUT, RETURNED, SHORT, BALANCE, RETURN, STATUS।
  - অনুমোদনকারীর জন্য ব্যানার: "1 gate pass is short and waiting for your approval to close."।
  - SHORT pass খুললে লাল লাইন ("1 Pcs short — Missing. …") আর Approve Short & Close (Approval note লাগে) → CLOSED; কম পরিমাণ রেকর্ডে থাকে।
- **ফেরতের অবস্থা** (RETURN কলাম): Pending Return, Partial Return, Fully Returned, Short। শুধু-OUT pass-এ বা বের হওয়ার আগে "—"।
- **Gate Pass List:**
  - ফিল্টার: Search, From / To, Category, Type, Status, Purpose, Destination / Location, Employee (Card / Name), Item, Buyer, Style, PO, Carrier; Search / Reset।
  - "Gate Passes (n)", Excel, New Gate Pass।
  - খোলা pass খসড়া হলে Edit, Submit for Approval, Cancel।
- **খোলা pass-এর অংশ** (সব স্ক্রিনে এক): নম্বর, অবস্থা ও ধরনের pill, Print; Gate Pass; Taken By; Product / Item Details (OUT, RETURNED, SHORT, BALANCE); Additional Information (Gate OUT-এর সময়, ব্যক্তি, মন্তব্যসহ); Gate IN Records; Audit Trail ("Approved → Approved", নোট, কে · কখন)।
- **Print:** গেট পাস ছাপা হয়, নিচে চারটা সইয়ের ঘর।

## ৬. API ও পারমিশন

| Method | Path | পারমিশন |
|---|---|---|
| GET | `/gate-pass/passes?list=approval\|approved\|out-today\|in-await\|in-today\|recon\|all` | তালিকা অনুযায়ী (approve / gate / view) |
| GET | `/gate-pass/passes/excel`, `/gate-pass/passes/{no}` | যেকোনো gatepass পারমিশন |
| POST | `/gate-pass/passes/{no}/approve`, `/reject`, `/send-back`, `/close` | gatepass.approve |
| POST | `/gate-pass/passes/{no}/cancel` | approve, বা নিজের খসড়া / অপেক্ষমাণ pass-এ create |
| POST | `/gate-pass/passes/{no}/out`, `/in` | gatepass.gate |

## ৭. Frontend পেজ (লাইট থিম)

- সাইডবার: Gate Pass → DASHBOARD → Gate Pass Approval (03), Out (04), In (05), Return Reconciliation (06), Gate Pass List (07)।
- প্রতিটা স্ক্রিনে তালিকার ওপরে খোলা pass; ধাপগুলো dialog-এ জিজ্ঞেস করে; পাতা রিলোড হয় না।
- URL-এ শুধু GP নম্বর থাকে, ULID নয়।

## ৮. Implementation Files

<!-- AUTO:impl:start -->
As-built:

**Backend**
- `app/Modules/GatePass/Http/Controllers/Api/GatePassApiController.php`
- `app/Modules/GatePass/Support/GatePassBook.php`
- `app/Modules/GatePass/Routes/api.php`

**Frontend**
- `frontend/src/components/gatepass/workflow.jsx`, `common.jsx`
- `frontend/src/app/(app)/gate-pass/[screen]/{page,screens,actions}.js`
- `frontend/src/app/api/gate-pass/excel/[kind]/route.js` (`list`)
- `lang/en/gatepass.php`, `lang/bn/gatepass.php` (`gp.*`)

**Tests**
- `tests/Feature/GatePass/GatePassFlowTest.php`
<!-- AUTO:impl:end -->

### পরিকল্পিত নতুন/বদলানো ফাইল

_তৈরি হয়ে গেছে।_

## ৯. টেস্ট ও যাচাই

- **`GatePassFlowTest`:**
  - Storekeeper অনুমোদন দিতে পারেন না; Factory Manager অনুমোদন দেন, Gate OUT দিতে পারেন না।
  - Send back-এ কারণ লাগে → খসড়া → সংশোধন → আবার অনুমোদন।
  - IN-এ বাকির বেশি (5 Pcs) ফেরত আটকায়; খালি IN আটকায়; ৩টা ফিরলে PARTIAL; শেষ ফেরতে কারণ লাগে, তারপর SHORT; লাইনের OUT / ফেরত / কম / বাকি; audit-এর নোট।
  - Reconciliation-এ অপেক্ষার সংখ্যা ১; নোট ছাড়া close আটকায়; close হলে CLOSED (ফেরত Short); ঘটনার পুরো ক্রম।
  - Main pass: IN হলে CLOSED (Fully Returned)। শুধু-OUT: OUT হলে COMPLETED, IN আটকায়।
  - Reject আর Cancel-এর নিয়ম; অজানা নম্বরের বার্তা।
  - List-এর ফিল্টার আর Excel।
- **ব্রাউজার (dev):**
  - GP-2026-000002: অনুমোদন (নোটসহ) → Gate OUT লিংক → Confirm OUT → IN ৩ Pcs (GPI-2026-000001) → শেষ IN ১ Pcs + ২ Kg, Missing (GPI-2026-000002) → Reconciliation-এ ব্যানার → Approve Short & Close।
  - GP-2026-000003 (Main): অনুমোদন → OUT → IN → Closed / Fully Returned।
  - List-এ Item = "grey" দিয়ে ফিল্টার।
- **সংশোধন:**
  - IN-এর পরে আগের পরিমাণ ফর্মে থেকে যাচ্ছিল, আর panel-এ লাল নোটিস দেখাচ্ছিল। এখন panel বন্ধ হয়।
  - Main pass-এর পরিমাণ "0" দেখাচ্ছিল, এখন "—"।
  - শুধু-OUT pass-এ বাকি এখন শূন্য।

## ১০. খোলা প্রশ্ন

- Brickland-এর Out / In স্ক্রিনে বারকোড বা QR স্ক্যানার কীবোর্ডের মতো নম্বর লেখে ধরে নেওয়া হয়েছে; ক্যামেরা-স্ক্যান নেই।
- একাধিক ইউনিটের লাইন থাকলে তালিকার QTY-তে শুধু সংখ্যা দেখায় (যেমন "7"), Brickland-এর একক ইউনিটের উদাহরণ ছাড়া মেলানো যায়নি।

## ১১. As-built নোট

- **✅** (২০২৬-১০-২৪)। লাইভ প্রোব: `tools/erp_crawler/plans/gp04.json`–`gp10.json`।
- UI যাচাই: `tools/app_check/plans/gpu02.json`–`gpu06.json`।
- dev-এর GP-2026-000001 (Main, Pending Approval) আমার তৈরি নয়; owner লগইনে আগে তৈরি হয়েছিল, তাতে হাত দেওয়া হয়নি।
