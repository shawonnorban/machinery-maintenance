# General

> **স্ট্যাটাস:** ✅ সম্পন্ন
> **মডিউল:** [Gate Pass](00_Index.md) · **ফেজ ৩:** সাপ্লাই ও স্টোর · **কী:** `11/03`

**⬅ ফিরে যান:** [Gate Pass](00_Index.md) | [AnnotechERP Index](../00_Index.md)

---

## ১. স্ক্রিন (Brickland ERP থেকে)

<!-- AUTO:screens:start -->
| # | স্ক্রিন | পাথ | ট্যাব | ফিল্ড | ডায়ালগ |
|---|---|---|---|---|---|
| 1 | Create Gate Pass | `/m/gate-pass/gp-create` | 1 | 22 | 1 |
<!-- AUTO:screens:end -->

## ২. প্রতিটা স্ক্রিনের লাইভ বিবরণ (ফিল্ড, ট্যাব, গ্রিড, ডায়ালগ, ভ্যালিডেশন)

<!-- AUTO:detail:start -->
#### Create Gate Pass

`/m/gate-pass/gp-create`

**ভিউ**
> 02 of 13

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন/প্লেসহোল্ডার |
|---|---|---|---|
| Main Gate Pass (Employee) | radio |  |  |
| Item Gate Pass (Product/Material) | radio |  |  |
| OUT & IN (Returnable) | radio |  |  |
| OUT ONLY (Non-Returnable) | radio |  |  |
| Gate Pass Date* | date | ✔ |  |
| Buyer | select |  | Select buyer, LPP |
| Order | select |  | Select order (optional), LPP-2026-000008 — 529, LPP-2026-000007 — 1111, LPP-2026-000006 — 529, LPP-2026-000005 — 234234, |
| Style | text |  | HM-1025 |
| PO No. | text |  | PO-5001 |
| Destination Factory / Location* | select | ✔ | Select… |
| Expected Return Date* | date | ✔ |  |
| Location Type | text |  |  |
| Address | text |  |  |
| Contact Person | text |  |  |
| Contact No. | text |  |  |
| Employee | radio |  |  |
| Non-Employee | radio |  |  |
| Employee Card No.* | text | ✔ | EMP-1025 |
| Vehicle No. | text |  | DHA-METRO-TA-11-5501 |
| Carrier Remarks | text |  |  |
| Remarks | textarea |  |  |
| Documents (if any) | textarea |  | Challan no., invoice no.… |
- গ্রিড কলাম: SL, ITEM *, UNIT *, QUANTITY *, REMARKS, ACTION
- সেকশন: 1. Gate Pass Category & Type, 2. General Information, 3. Taken By (Carrier Information), 4. Item / Product Details, 5. Remarks & Documents
- বাটন/অ্যাকশন: Select…, Add Item, Save as Draft, Submit for Approval, Print Preview

**ডায়ালগ: Add Item**

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন |
|---|---|---|---|
| Main Gate Pass (Employee) | radio |  |  |
| Item Gate Pass (Product/Material) | radio |  |  |
| OUT & IN (Returnable) | radio |  |  |
| OUT ONLY (Non-Returnable) | radio |  |  |
| Gate Pass Date* | date | ✔ |  |
| Buyer | select |  | Select buyer, LPP |
| Order | select |  | Select order (optional), LPP-2026-000008 — 529, LPP-2026-000007 — 1111, LPP-2026-000006 — 529, LPP-2026-000005 — 234234, |
| Style | text |  |  |
| PO No. | text |  |  |
| Destination Factory / Location* | select | ✔ | Select… |
| Expected Return Date* | date | ✔ |  |
| Location Type | text |  |  |
| Address | text |  |  |
| Contact Person | text |  |  |
| Contact No. | text |  |  |
| Employee | radio |  |  |
| Non-Employee | radio |  |  |
| Employee Card No.* | text | ✔ |  |
| Vehicle No. | text |  |  |
| Carrier Remarks | text |  |  |
| Remarks | textarea |  |  |
| Documents (if any) | textarea |  |  |
<!-- AUTO:detail:end -->

## ৩. বিদ্যমান কোড যা কাজে লাগতে পারে

- 11/01-এর master: `gate_pass_categories` (kind MAIN / ITEM), `gate_pass_location_types`, `gate_pass_locations`, `gate_pass_items` (নিজস্ব ইউনিট-তালিকা), `gate_pass_purposes`; `GatePassDefaults` প্রথমবার খুললে ক্যাটাগরি, লোকেশনের ধরন আর purpose বসায়।
- কর্মী: HR & Payroll-এর `hr_employees` (card_no, department, designation)।
- বায়ার আর অর্ডার: Merchandise-এর `buyers`, `merch_orders` (order_no, style_no, po_no)।

## ৪. ডাটা মডেল (Entity ও Field)

Migration `2026_12_25_000100_create_gate_passes.php` (11/02–11/04-এর সব টেবিল এখানে):

| টেবিল | মূল ঘর |
|---|---|
| `gate_passes` | gp_no (GP-YYYY-NNNNNN), gp_date, category_id, kind (MAIN / ITEM), pass_type (RETURNABLE / NON_RETURNABLE), buyer_id, order_id, style, po_no, purpose_id, location_id, destination_text, expected_return, carrier_type (EMPLOYEE / NON_EMPLOYEE), employee_id, carrier_name / company / mobile / nid, vehicle_no, carrier_remarks, remarks, documents, status, prepared_by, approved_by/at, out_at/by, out_remarks, closed_at/by |
| `gate_pass_lines` | item_id, unit, quantity, short_qty, short_reason, remarks, sort_order |
| `gate_pass_ins` | gpi_no (GPI-YYYY-NNNNNN), in_at, final, short_reason, remarks, received_by |
| `gate_pass_in_lines` | gate_pass_in_id, gate_pass_line_id, quantity |
| `gate_pass_events` | action, to_status, note, user_id, created_at (audit trail, শুধু যোগ হয়) |

- **অবস্থা:** DRAFT, PENDING, APPROVED, REJECTED, CANCELLED, OUT, PARTIAL, SHORT, CLOSED, COMPLETED।
- নম্বর advisory lock দিয়ে নেওয়া হয়, যাতে দুটো সেভ একই নম্বর না পায়।

## ৫. Workflow / Status ও Business Rules

- **১. ক্যাটাগরি ও ধরন:** Main Gate Pass (কর্মী) / Item Gate Pass (মালামাল); OUT & IN (ফেরতযোগ্য) / OUT ONLY। ডানে পরের নম্বর (Auto Generate) আর তারিখ।
- **২. সাধারণ তথ্য:**
  - Item-এ: Buyer, Order (বাছলে বায়ার, স্টাইল আর PO বসে; বায়ার বাছলে অর্ডারের তালিকা ছোট হয়), Style, PO No.।
  - Purpose / Reason সবসময় লাগে।
  - Destination: Item-এ বাধ্যতামূলক; Main-এ ঐচ্ছিক, সাথে "Destination (if not in the list)"।
  - লোকেশন বাছলে Location Type, Address, Contact Person, Contact No. নিজে বসে।
  - Expected Return Date: শুধু Item + ফেরতযোগ্য হলে, আর গেট পাসের তারিখের আগে নয়।
- **৩. বাহক:**
  - Item-এ Employee / Non-Employee; Main-এ সবসময় কর্মী।
  - কর্মী: কার্ড নং লিখে খুঁজলে নাম, আইডি, ডিপার্টমেন্ট, পদবি, মোবাইল আসে; না পেলে "No employee with card no. 99999 was found."।
  - কর্মী নন: Person Name আর Mobile লাগে; Company, NID ঐচ্ছিক।
  - Vehicle No., Carrier Remarks শুধু Item-এ।
- **৪. আইটেম (শুধু Item):** Item, Unit (গেট পাসের নিজস্ব ১২টা ইউনিট; আইটেম বাছলে তার ইউনিট বসে), Quantity > 0, Remarks; Add Item, সারি মোছা। পুরো খালি সারি বাদ পড়ে।
- **৫. মন্তব্য ও ডকুমেন্ট।**
- **"Still needed":** Purpose, (Item-এ) Destination, Expected Return, কর্মীর কার্ড (খুঁজে নিতে হয়) বা নাম ও মোবাইল, আর অন্তত একটা আইটেম। বাকি থাকলে Submit for Approval বন্ধ থাকে। সার্ভারও একই যাচাই করে ("Purpose / Reason is required.")।
- **Save as Draft** যেকোনো সময়। খসড়া বা সংশোধনে ফেরত আসা পাস List থেকে Edit (`?edit=GP-…`) করে এই ফর্মে খোলে। সংশোধন করতে পারেন প্রস্তুতকারী বা অনুমোদনকারী।
- সেভের পরে Gate Pass List খোলে (Brickland-এর আচরণ)। Cancel ফর্ম খালি করে; Print Preview ফর্মের তথ্য প্রিন্ট করে।

## ৬. API ও পারমিশন

| Method | Path | পারমিশন |
|---|---|---|
| GET | `/gate-pass/options` | gatepass.create |
| GET | `/gate-pass/employees/{card}` | gatepass.create |
| POST | `/gate-pass/passes` (submit=true হলে জমা) | gatepass.create |
| PUT | `/gate-pass/passes/{no}` | gatepass.create (খসড়া) |
| POST | `/gate-pass/passes/{no}/submit` | gatepass.create |

- নতুন পারমিশন: `gatepass.view`, `gatepass.create`, `gatepass.approve`, `gatepass.gate`।
- রোল:
  - FACTORY_ADMIN: সব।
  - FACTORY_MANAGER: view, create, approve।
  - STORE_MANAGER: সব।
  - STOREKEEPER: view, create, gate।

## ৭. Frontend পেজ (লাইট থিম)

- সাইডবার: Gate Pass → GENERAL → Create Gate Pass (`/gate-pass/gp-create`, "02 of 13")।
- Brickland-এর মতো নম্বর দেওয়া রঙিন সেকশন কার্ড; ক্যাটাগরি, ধরন আর বাহকের জন্য বক্সে radio। Main-এ আইটেমের সেকশন থাকে না বলে নম্বরগুলো সরে যায়।

## ৮. Implementation Files

<!-- AUTO:impl:start -->
As-built:

**Backend**
- `app/Modules/GatePass/Database/Migrations/2026_12_25_000100_create_gate_passes.php`
- `app/Modules/GatePass/Models/GatePass.php`, `GatePassLine.php`, `GatePassIn.php`, `GatePassInLine.php`, `GatePassEvent.php`
- `app/Modules/GatePass/Support/GatePassBook.php`
- `app/Modules/GatePass/Http/Controllers/Api/GatePassApiController.php`
- `app/Modules/GatePass/Routes/api.php`
- `app/Modules/Identity/Database/Seeders/PermissionSeeder.php`, `RoleSeeder.php`
- `app/Modules/Identity/Support/MenuRegistry.php`

**Frontend**
- `frontend/src/components/gatepass/create.jsx`, `common.jsx`
- `frontend/src/app/(app)/gate-pass/[screen]/{page,screens,actions}.js`
- `lang/en/gatepass.php`, `lang/bn/gatepass.php` (`gp.*`)

**Tests**
- `tests/Feature/GatePass/GatePassFlowTest.php`
<!-- AUTO:impl:end -->

### পরিকল্পিত নতুন/বদলানো ফাইল

_তৈরি হয়ে গেছে।_

## ৯. টেস্ট ও যাচাই

- **`GatePassFlowTest`:** "Still needed"-এর সব ঘর; সারি ১-এ শূন্য পরিমাণ; খসড়া → submit-এ বাকি তালিকা; সংশোধন করে জমা; Main pass-এ কর্মী বাধ্যতামূলক, বাড়তি গাড়ি বা নাম বাদ পড়ে; OUT ONLY-তে ফেরতের তারিখ বাদ পড়ে; কার্ড খোঁজা।
- **ব্রাউজার (dev):**
  - GP-2026-000002: Item, ফেরতযোগ্য, কর্মী নন, ২টা আইটেম (Polo Shirt 5 Pcs, Grey Fabric 2 Kg)।
  - GP-2026-000003: Main, কর্মী 50001001; ভুল কার্ড 99999-এর বার্তা যাচাই; খসড়া → List → Edit → জমা।

## ১০. খোলা প্রশ্ন

- Brickland-এর Create-এর purpose তালিকায় "Official Work" আর "Personal" দেখা গেছে, অথচ 11/01-এর master প্রোবে ডিফল্ট ১২টা ছিল। এখানে ডিফল্ট ১২টাই; দরকার হলে Purpose / Reason master-এ যোগ করা যায়।
- Brickland কর্মীর ছবি দেখায়; আমাদের HR-এ এখনো ছবি নেই, তাই আইকন দেখানো হয়।

## ১১. As-built নোট

- **✅** (২০২৬-১০-২৪)। লাইভ প্রোব: `tools/erp_crawler/plans/gp01.json`–`gp11.json` (WRITE=1, live demo-তে GP-2026-000001)।
- UI যাচাই: `tools/app_check/plans/gpu01.json`, `gpu05.json`।
