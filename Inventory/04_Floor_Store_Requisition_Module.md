# Floor / Store Requisition Module

**Module Key:** `requisition.floor`
**নির্ভরতা:** [Master Data Module](01_Master_Data_Module.md) (Item Library — শুধু রেফারেন্স/লুকআপ হিসেবে, নিচে দ্রষ্টব্য), [Inventory Module](02_Inventory_Module.md) (Stock Check, Issue), [Procurement Module](03_Procurement_Module.md) (Shortage হলে Auto PR)

**⬅ ফিরে যান:** [Main Index](00_Main_Index.md)

---

## 1. Objective

কোনো User-এর কিছু প্রয়োজন হলে সে Floor Requisition দেবে; Store থেকে সেটা তাকে সরবরাহ করা হবে। কাঙ্ক্ষিত জিনিস Store-এ না থাকলে Store থেকে Purchase Requisition যাবে ([Procurement Module](03_Procurement_Module.md#5-purchase-requisition-pr))। এটা একটা **Two-Tier System**: Floor/User Level → Store Level → (প্রয়োজনে) Procurement Level।

> **বিদ্যমান `WorkOrderPart` ফ্লো-র সাথে সম্পর্ক (স্পষ্টীকরণ):** কাজের অর্ডারে (Work Order) পার্ট রিকোয়েস্ট/ইস্যু করার জন্য কোডবেসে ইতিমধ্যে একটা আলাদা, কার্যকর ফ্লো আছে (`WorkOrderPart` — নির্দিষ্ট Work Order-এর সাথে বাঁধা মেইনটেন্যান্স পার্ট consumption)। এই Floor/Store Requisition সিস্টেম সেটাকে **প্রতিস্থাপন করবে না** — সেটা যেভাবে আছে সেভাবেই থাকবে। Floor/Store Requisition হলো একটা **সমান্তরাল, আলাদা সিস্টেম** — যেকোনো Department-এর User-এর সাধারণ প্রয়োজন (কোনো নির্দিষ্ট Work Order-এর সাথে বাঁধা না) মেটানোর জন্য।

> **প্রজেক্ট নোট:** এই প্রজেক্টে আলাদা Employee Master/Entity নেই। যে কেউ Requisition দিচ্ছে সে সরাসরি সিস্টেমের বিদ্যমান **User** (Login/Auth account) — `employee_id` নয়, `user_id` ব্যবহার হবে। User-Role-Permission, Approval, Notification, Audit Log ব্যবস্থাপনা বর্তমান প্রজেক্টে ইতিমধ্যে ইমপ্লিমেন্ট করা আছে (দেখুন [Shared Systems](05_Shared_Systems_and_Compliance.md))।

## 2. Item Library — নিয়ন্ত্রিত লাইব্রেরি, ফ্রি-টেক্সট না

Item Library ([Master Data Module](01_Master_Data_Module.md#2-item-master--base--extension-model)) একটা **নিয়ন্ত্রিত লাইব্রেরি** — ইউজারের লেখার/টাইপ করার ঝামেলা কমানোর জন্যই এই মডিউল। User Requisition দেওয়ার সময় Item Library-এর বাইরে থেকে কোনো Item টাইপ/সিলেক্ট করতে পারবে না — শুধু লাইব্রেরিতে থাকা Item-ই বেছে নিতে পারবে। কাঙ্ক্ষিত Item লাইব্রেরিতে না থাকলে Admin সেটা Item Library-তে যোগ করে দেবে, তারপর User সেটা সিলেক্ট করতে পারবে। Master Data মডিউল অন্য কোনো মডিউলের ওপর নির্ভর করে না (bottom-layer), কিন্তু Item সিলেকশন এখন real FK দিয়ে হবে (দেখুন [Master Data §আর্কিটেকচার নীতি](01_Master_Data_Module.md))।

**Department Dropdown নিয়ম (গুরুত্বপূর্ণ):** Requisition ফর্মে Department dropdown-এ **সব Department** দেখাবে, User নিজে যে Department-এর হোক না কেন। কারণ একজন Sewing floor-এর User হয়তো Maintenance department-এর কোনো টুল চাইতে পারে, বা Admin ইউজার IT আইটেম চাইতে পারে — তাই নিজের department দিয়ে filter না করে, প্রয়োজনীয় item যে department-এর আন্ডারে সেটা User নিজেই সিলেক্ট করবে, Item Library (Master Data) থেকে সেই মুহূর্তে item list টেনে আনা হবে।

## 3. Floor/Store Requisition — ফর্ম ফ্লো

```
User লগইন করে "New Requisition" ওপেন করবে
        ↓
Step 1: Department ড্রপডাউন (সব Department দেখাবে — Item Library থেকে রেফারেন্স)
        ↓
Step 2: Department সিলেক্টের পর সেই item_department-এর আন্ডারে
        থাকা সব Item (Master Data Item Library থেকে রেফারেন্সড) দেখাবে
        ↓
Step 3: Item(s) সিলেক্ট + Quantity + Purpose/Reason
        ↓
Step 4: Submit
```

### 3.1 Floor Requisition Header
requisition_no, requisition_date, requested_by (**user_id**, auto — login থেকে), requester's_department (রিপোর্টিং-এর জন্য), target_department (User যেটা সিলেক্ট করেছে — এটা কোন Item দরকার তা নির্ধারণ করে), status

> **গ্যাপ-ফিক্স — requester's_department কোথা থেকে আসবে:** বিদ্যমান User Profile-এ যদি Department attribute থাকে, সেখান থেকে auto-capture হবে। যদি না থাকে (নিশ্চিত না), তাহলে Requisition ফর্মেই একটা ঐচ্ছিক "Your Department" ফিল্ড থাকবে যেটা User নিজে সিলেক্ট করবে — যাতে Department-wise/User-wise Consumption Reporting-এ (Section 10) গ্যাপ তৈরি না হয়। এটা `target_department` থেকে ভিন্ন — `target_department` হলো "কোন Department-এর Item চাই", আর `requester's_department` হলো "User নিজে কোন Department-এ কাজ করে", শুধু রিপোর্টিং-এর জন্য।

### 3.2 Floor Requisition Line
item_id (Item Library থেকে রেফারেন্স), requested_qty, unit, purpose/remarks, approved_qty (Store Keeper adjust করতে পারবে)

> **As built (Phase 1 — ফর্ম):** `app/Modules/Requisition` — `floor_requisitions` (`fr_no`, `requested_by`, `requester_department_id` → org `departments`, `target_item_department_id` → `item_departments`, priority, status `DRAFT|SUBMITTED|CANCELLED`) + `floor_requisition_lines` (item snapshot, uom, requested_qty, approved_qty, purpose)। API `/floor-requisitions` (form-options-এ **সব** item department, caller যেই হোক), UI `/requisitions`। প্রতিটি লাইনের আইটেম target department-এর হতে হবে (422)। Submit → STOREKEEPER/STORE_MANAGER-কে `FLOOR_REQUISITION_SUBMITTED` notification। Store review/issue/shortage → PR (§4–5) Phase 3। Permission: `requisition.floor.create` (technician/line chief-সহ সবাই, নিজেরটা দেখে), `requisition.floor.view_any` (store team, সবার)।

> **UX নোট (গ্যাপ-ফিক্স):** Department সিলেক্ট করার পর Item লিস্টে (বিশেষ করে "General Store"-এর মতো বড় Department, legacy ডাটায় ৫,০০০+ আইটেম) সরাসরি সব আইটেম না দেখিয়ে **Search/Typeahead** (item_name/item_code দিয়ে) এবং Category/Sub Category দিয়ে ফিল্টার করার অপশন থাকবে — বিস্তারিত [Master Data Module](01_Master_Data_Module.md)-এর Item Selection UX নোটে।

## 4. Store-Level Fulfillment Logic

```
Store Keeper Requisition ওপেন করবে
        ↓
প্রতিটা Item Line-এ দেখাবে: Requested Qty vs Available Stock Qty
        (real-time, Inventory Module থেকে — Reserved/Hard-Allocated Qty
         বাদ দিয়ে, শুধু Available Qty দেখাবে)
        ↓
Store Keeper প্রতি লাইনে Issue Qty এন্ট্রি করবে
        ↓
   ┌──────────────────────┬───────────────────────┬────────────────────┐
   ▼                       ▼                        ▼
Available ≥ Requested Available < Requested    Available = 0
   │                       │                        │
   ▼                       ▼                        ▼
Full Issue            Partial Issue             No Issue
(Stock কমবে,           (যতটুকু আছে ইস্যু,          (পুরো Qty Shortage)
 Inventory Module)      বাকিটা Shortage)
                             │                        │
                             └────────┬───────────────┘
                                      ▼
                    Shortage Qty নিয়ে Auto Purchase Requisition
                    ([Procurement Module](03_Procurement_Module.md#5-purchase-requisition-pr))
                    তৈরি হবে (source_reference = floor_requisition_id)
```

**নিয়ম:**
- Store থেকে ইস্যু হলে Floor Requisition-এর সেই লাইন "Fulfilled" হবে, [Inventory Module](02_Inventory_Module.md)-এ "Issue to User/Department" এন্ট্রি হবে।
- **Available Stock গণনায় Order/Style-এর জন্য Reserved/Hard-Allocated Qty কখনো ধরা হবে না** — শুধু সত্যিকারের ফ্রি স্টক থেকেই Floor Requisition Issue হবে, যাতে প্রোডাকশনের জন্য রিজার্ভ করা ফেব্রিক/আইটেম ভুলবশত Floor-এ ইস্যু হয়ে না যায়।
- Shortage অংশ **বাধ্যতামূলকভাবে** Auto PR-এ কনভার্ট হবে (ম্যানুয়াল স্কিপ করা যাবে না)।

### 4.1 যেভাবে তৈরি হয়েছে (Phase 3A)

Phase 1-এ শুধু ফর্মটা ছিল — `SUBMITTED`-এ থেমে যেত, `approved_qty` কখনো লেখা হতো না, স্টক নড়ত না। Phase 3A পুরো স্টোর-সাইড যোগ করেছে:

| জিনিস | কোথায় |
| --- | --- |
| `FulfilFloorRequisition` | `app/Modules/Requisition/Actions` — `startReview()`, `issue()`, `recordShortage()`, `reject()`, `close()`, `returnToStore()`, `availability()` |
| স্কিমা | `floor_requisitions` += reviewed_by/at, closed_at, rejection_reason, `shortage_pr_id`; `floor_requisition_lines` += issued/returned/shortage_qty, `status`, `item_reservation_id`; নতুন `floor_requisition_issues` (§6-এর ব্যাক-লিংক) |
| API | `GET {id}/availability`, `POST {id}/review|issue|reject|close|returns`; permission `requisition.floor.issue` |
| UI | রিকুইজিশন ডিটেইল পেজে স্টোর ফুলফিলমেন্ট প্যানেল (`can_issue` হলে) |

তিনটি সিদ্ধান্ত যা কোডে স্পষ্ট করে লেখা আছে:

1. **ঘাটতি ঐচ্ছিক নয়।** `issue()`-এর শেষে `recordShortage()` নিজে চলে; বাদ দেওয়ার কোনো প্যারামিটার নেই। সব short লাইন মিলে **একটি** PR (`pr_type = FROM_FLOOR_REQUISITION`, `source_reference = floor_requisition.id`) — DRAFT-এ রাখা, কারণ কেউ না দেখা ডকুমেন্ট কেউ বিশ্বাস করে না (§5.3)।
2. **ব্যালেন্স লক-ই concurrency গার্ড।** ইস্যুর আগে `ItemLedger::lockBalance()`; দুজন স্টোরকিপার একই মুহূর্তে শেষ ৫০ মিটার ইস্যু করতে গেলে একজন 409 পায়।
3. **§4-এর কঠিন নিয়ম বহাল:** `availability()` অন্যের জন্য ধরে রাখা স্টক বাদ দেয়, কিন্তু *এই* রিকুইজিশনের নিজের earmark যোগ করে — ধরে রাখার উদ্দেশ্যই তো সেটা।

## 5. PR তৈরি হওয়ার পর ফ্লো — Floor Requisition-এর সাথে লিংক (Reservation Earmarking সহ)

> **গ্যাপ-ফিক্স:** [Procurement Module](03_Procurement_Module.md#3-fabric-booking)-এ Fabric Booking Confirm হলে যেভাবে Auto Soft Reservation তৈরি হয়, ঠিক একইভাবে Floor Requisition Shortage থেকে তৈরি হওয়া Auto PR-এর জন্যও stock earmark করা দরকার — নাহলে PO→GRN হয়ে স্টক এলেও সেটা অন্য কোনো প্রয়োজনে আগেই ব্যবহার হয়ে যেতে পারে, আর মূল Requisition দেওয়া User অপেক্ষায় থেকে যাবে।

```
Shortage detect হলে → Auto Purchase Requisition তৈরি (Procurement Module)
        ↓
একইসাথে Inventory-তে Auto Soft Reservation তৈরি হবে
   (reservation_type = "Floor Requisition", ref = floor_requisition_id, qty = shortage_qty)
        ↓
Approval → RFQ/PO → GRN → Inspection Pass → Inventory-তে Stock Add
        ↓
Stock Add হওয়ার সাথে সাথে সেই earmarked Reservation qty
   স্বয়ংক্রিয়ভাবে "Available for this Floor Requisition" হিসেবে চিহ্নিত হবে
   (অন্য কোনো Requisition/Issue এই qty প্রথমে টানতে পারবে না)
        ↓
Store Keeper Notification পাবে (বর্তমান প্রজেক্টের বিদ্যমান
   Notification ব্যবস্থা ব্যবহার করে):
   "Item X এখন Stock-এ এসেছে, Floor Requisition #FR-এর Pending qty ইস্যু করুন"
        ↓
Store Keeper বাকি Qty User-কে ইস্যু করবে → Reservation Release
        ↓
Floor Requisition Status → Closed
```

**নিয়ম:** Floor Requisition-জনিত Reservation একটা **soft/short-duration earmark** — যদি নির্দিষ্ট সময়ের (configurable) মধ্যে Store Keeper ইস্যু না করে, সিস্টেম রিমাইন্ডার দেবে; তবে জরুরি প্রয়োজনে Store Manager ম্যানুয়ালি Override করে সেই স্টক অন্য জায়গায় ব্যবহার করতে পারবে (override reason mandatory, Audit Log-এ থাকবে)।

> **টেকনিক্যাল নোট (Concurrency):** একাধিক Floor Requisition একই আইটেমের Shortage-এর জন্য প্রায় একই সময়ে Auto PR/Reservation তৈরি করলে স্টক ডাবল-কাউন্ট হওয়ার ঝুঁকি থাকে। ইমপ্লিমেন্টেশনে Reservation তৈরির সময় row-level locking/transaction isolation ব্যবহার করে এই race condition এড়াতে হবে।

### 5.1 যেভাবে তৈরি হয়েছে (Phase 3A)

- **Earmark:** প্রতি short লাইনে একটি `ItemReservation` (`status = PENDING_ARRIVAL`, `reservation_type = FLOOR_REQUISITION`, `reference_type = floor_requisition_line`)। এটি কিছু ধরে রাখে না — ধরার মতো কিছু নেই — কিন্তু মাল এলে কার, সেটা বলে রাখে।
- **স্টক এলে:** `ManageGoodsReceipt::complete()` প্রতিটি RECEIPT পোস্টের পর `ReserveItemStock::fillPendingEarmarks()` ডাকে, **একই ট্রানজ্যাকশনের ভিতরে** — এনকাম্ব্রেন্স আর তার কারণ একসাথে টেকে বা একসাথে ফেল করে। পুরনো দাবি আগে; আংশিক এলে যতটুকু এসেছে ততটুকু `ACTIVE` হয় আর বাকিটার জন্য নতুন earmark থেকে যায়, তাই ধাপে ধাপে আসা PO-ও কাজ করে।
- **Notification:** `EARMARK_STOCK_ARRIVED` স্টোর টিমকে (স্পেকের "Item X এখন Stock-এ এসেছে…")। `FLOOR_REQUISITION_ISSUED` অনুরোধকারীকে, `FLOOR_REQUISITION_SHORTAGE` স্টোর ম্যানেজারকে (DRAFT PR অপেক্ষমাণ)।
- **Reminder + Override:** `requisition:remind-outstanding` কমান্ড প্রতিদিন চলে — `requisition.earmark_reminder_days` (ডিফল্ট ১৪) পেরোনো দাবি মনে করিয়ে দেয়, `requisition.returnable_overdue_days` (ডিফল্ট ৭) পেরোনো ধার তাড়া করে, আর মেয়াদোত্তীর্ণ hold ছেড়ে দেয়। Store Manager-এর override হলো `/item-reservations/{id}/release` — `reason` রেকর্ড হয় `override_reason`-এ।

**ROLL আইটেমে সরলীকরণ:** ফেব্রিকের earmark স্বয়ংক্রিয়ভাবে পূরণ হয় না। রোল ইন্সপেকশনে পাস করলে স্টোরকে জানানো হয়, কিন্তু কোন রোল কোন রিকুইজিশনে — সেটা মানুষ বাছে ([Inventory §7.2](02_Inventory_Module.md#72-যেভাবে-তৈরি-হয়েছে-phase-3a))।

## 6. Roll/Lot-Tracked Item Handling (গ্যাপ-ফিক্স)

Floor Requisition-এর মূল ডিজাইন Quantity-tracked General Item (Trims, Stationery, Spare Parts) ধরে করা। কিন্তু Item Library-এর কিছু আইটেম `tracking_mode = lot` বা `roll` হতে পারে (Yarn, Chemical, Fabric — [Master Data](01_Master_Data_Module.md#2-item-master--base--extension-model) দ্রষ্টব্য)। এক্ষেত্রে নিয়ম:

```
User Item সিলেক্ট করবে (শুধু Item + Qty, নির্দিষ্ট Roll/Lot না — User এটা জানবে না/দরকার নেই)
        ↓
Store Keeper Fulfillment-এর সময় tracking_mode চেক করবে
        ↓
   ┌─────────────────────────┬─────────────────────────┐
   ▼                          ▼
tracking_mode = quantity   tracking_mode = lot/roll
   ↓                          ↓
সরাসরি Qty ইস্যু           Store Keeper নির্দিষ্ট Lot/Roll
                            সিলেক্ট করে ইস্যু করবে (FIFO/
                            Nearest Expiry suggestion,
                            Inventory Module থেকে)
```

**নিয়ম:** Floor Requisition-এর Line-এ শুধু `item_id` + `requested_qty` থাকবে; নির্দিষ্ট `roll_id`/`lot_no` অ্যাসাইনমেন্ট Store Keeper-এর কাজ (Fulfillment টাইমে), User-এর না। Fulfillment হওয়ার পর কোন Roll/Lot থেকে ইস্যু হলো তার রেফারেন্স Floor Requisition Line-এ ব্যাক-লিংক থাকবে (ট্রেসেবিলিটি ও Audit Log-এর জন্য)।

**যেভাবে তৈরি হয়েছে (Phase 3A):** ব্যাক-লিংকটা `floor_requisition_issues` টেবিল — প্রতি হস্তান্তরে একটি সারি, যাতে `bin_id`, `lot_no`, `fabric_roll_id`, দাম ও যে `item_stock_transaction_id` তৈরি হলো তা থাকে। একটা লাইন একাধিকবার ইস্যু হতে পারে (এখন কিছু, বাকিটা মাল এলে) এবং প্রতিবার আলাদা বিন/লট/রোল থেকে আসতে পারে — তাই লাইনে একটা মাত্র কলাম যথেষ্ট নয়। `availability()` উৎসগুলো FIFO ক্রমে (রোলের ক্ষেত্রে `received_date`) ফেরত দেয়, তাই স্পেকের "FIFO suggestion" মানে তালিকার প্রথমটাই। ROLL আইটেম গোটা রোল হিসেবে ইস্যু হয় — যা মাপা আছে তা-ই যায়, কেউ সংখ্যা টাইপ করে না।

## 7. Returnable / Asset-Type Item Handling (গ্যাপ-ফিক্স)

সব Floor Requisition Issue-কে "permanent consumption" ধরা ঠিক না — Tools, Machine Parts-এর মতো কিছু আইটেম **ধার (borrow)** দিয়ে পরে ফেরত নেওয়া হয়। এজন্য:

- [Item Master](01_Master_Data_Module.md#22-base-table--items)-এ একটা `is_returnable` (Yes/No) ফ্ল্যাগ যোগ হবে।
- `is_returnable = Yes` হলে Floor Requisition Issue হওয়ার পর সেই Line-এর একটা আলাদা স্ট্যাটাস থাকবে: **"Issued — Not Yet Returned"**।

```
Store Keeper "Returnable" Item ইস্যু করবে
        ↓
Line Status → "Issued — Not Yet Returned" (স্টক থেকে সাময়িকভাবে বের হলো,
   কিন্তু এটা permanent consumption না)
        ↓
User ব্যবহার শেষে Item ফেরত দেবে
        ↓
Store Keeper "Return to Store" এন্ট্রি করবে (Line-এ ব্যাক-লিংক)
        ↓
Item আবার Stock-এ Available হবে; Line Status → "Returned"
```

**নিয়ম:** Returnable Item সময়মতো ফেরত না এলে (configurable overdue period) Store Keeper-কে Notification যাবে (বিদ্যমান Notification ব্যবস্থা)। এই ধরনের Item Consumption Report-এ আলাদাভাবে দেখানো হবে (Consumed vs Currently Borrowed), যাতে প্রকৃত ব্যবহার ও ধারে দেওয়া Item গুলিয়ে না যায়।

**যেভাবে তৈরি হয়েছে (Phase 3A):** `is_returnable` আইটেম ইস্যু হলে লাইনের status `ISSUED` নয়, `BORROWED` — স্পেকের "Issued — Not Yet Returned"। `returnToStore()` স্টক **যে দামে বেরিয়েছিল সেই দামেই** `RETURN` পোস্ট করে (`floor_requisition_issues.unit_cost`), গড় দাম যেখানেই গিয়ে থাকুক — নাহলে ধার দেওয়া-নেওয়ায় কস্ট সেন্টারের মধ্যে মূল্য চুপচাপ সরে যায়। ROLL হলে রোল `ISSUED → AVAILABLE`-এ ফেরে। ধার দেওয়া লাইন ফেরত না আসা পর্যন্ত `close` 409 দেয়; overdue তাড়া করে `requisition:remind-outstanding` (`RETURNABLE_OVERDUE`, দিনে একবার)।

## 8. Requisition Number Series

> **সংশোধিত** — নতুন লজিক না, বিদ্যমান Numbering সিস্টেম ব্যবহার হবে।
>
> **সিদ্ধান্ত:** প্ল্যাটফর্মে ইতিমধ্যে একটা জেনেরিক ডকুমেন্ট-নাম্বারিং সিস্টেম আছে (`NumberSequenceFormat` — company নিজের `document_type`-ভিত্তিক ফরম্যাট/প্যাডিং কনফিগার করতে পারে)। Floor Requisition ও Purchase Requisition-এর নাম্বার আলাদা কোনো নতুন হার্ডকোডেড লজিক দিয়ে না বানিয়ে এই বিদ্যমান সিস্টেমেই নতুন `document_type` হিসেবে যোগ করা হবে — বিস্তারিত [Shared Systems & Compliance §4](05_Shared_Systems_and_Compliance.md#4-document-numbering)-এ।

| Type | `document_type` | ডিফল্ট ফরম্যাট (কোম্পানি বদলাতে পারবে) | Owner |
|---|---|---|---|
| Floor/Store Requisition | `floor_requisition` | FR-{year}-{seq:4} | User → Store |
| Purchase Requisition | `purchase_requisition` | PR-{year}-{seq:4} | Store/System → Procurement |

কোম্পানি এই ফরম্যাট পরিবর্তন না করলে উপরের ডিফল্টই ব্যবহার হবে (`NumberSequenceFormat`-এর নিজস্ব নিয়ম অনুযায়ী — "কোনো রো না থাকা মানেই প্ল্যাটফর্ম ডিফল্ট")।

## 9. Status Flow

```
Draft → Submitted → Under Review (Store) →
   ┌─────────────┬──────────────────┐
   ▼             ▼                  ▼
Fully          Partially          Rejected
Fulfilled      Fulfilled          (reason সহ)
   │             │
   ▼             ▼
Closed      → Shortage Qty অটো detect → Auto PR Trigger (+ Reservation Earmark, সেকশন ৫ দেখুন)
```

**যেভাবে তৈরি হয়েছে (Phase 3A)** — `FloorRequisition::TRANSITIONS`:

```
DRAFT → SUBMITTED → UNDER_REVIEW → PARTIALLY_FULFILLED | FULLY_FULFILLED | REJECTED
PARTIALLY_FULFILLED → PARTIALLY_FULFILLED | FULLY_FULFILLED | CLOSED
FULLY_FULFILLED → CLOSED
```

`PARTIALLY_FULFILLED → PARTIALLY_FULFILLED` ইচ্ছাকৃত: ঘাটতির মাল ধাপে ধাপে এলে একই রিকুইজিশনে বারবার ইস্যু হয়। `SUBMITTED` অবস্থায় সরাসরি ইস্যু করলে review ধাপটা নিজে থেকেই হয়ে যায় (স্টোর তাক থেকে মাল তুলে ফেলেছে মানে সে রিভিউ করেছেই)। কিছুই ইস্যু না হলে status `PARTIALLY_FULFILLED`-ই থাকে — স্পেকের ভোকাবুলারিতে "কিছুই দেওয়া হয়নি কিন্তু বাতিলও হয়নি" আলাদা কোনো মান নেই, আর `REJECTED` মানে স্টোর স্পষ্টভাবে না বলেছে, যা আলাদা ব্যাপার।

লাইনের নিজস্ব status: `PENDING, PARTIALLY_ISSUED, ISSUED, SHORT, BORROWED, RETURNED, REJECTED`।

## 10. Reports

- Floor Requisition Register (Department-wise, User-wise)
- Pending Floor Requisition (Fulfillment বাকি)
- Floor Requisition → Auto PR Traceability Report
- User-wise Consumption Report (misuse detect করার জন্য)
- Department-wise Item Consumption Trend
- **Currently Borrowed (Returnable) Item Report** — Consumed vs Not-Yet-Returned আলাদা দেখাবে
- **Overdue Return Report** — Returnable Item যা সময়মতো ফেরত আসেনি

> **As built (Phase 6):** পাঁচটি রিপোর্ট `ReportRegistry`-র নতুন `requisition` গ্রুপে (`app/Modules/Reporting/Reports/Requisition`), সবই `requisition.floor.view_any` permission-এ: `requisition_register`, `requisition_pending`, `requisition_auto_pr_trace`, `requisition_consumption`, `requisition_borrowed_items`।
>
> উপরের সাতটি দাবি পাঁচটিতে মিলেছে, দুটি ইচ্ছাকৃত একীভবনে:
>
> - **Register একটাই**, department ও requester দুটোই কলাম। "Department-wise" আর "User-wise" একই সারির দুরকম গ্রুপিং, আর সেটা দ্বিতীয় কোয়েরির চেয়ে স্প্রেডশিট ভালো করে। একই কারণে **Consumption** রিপোর্টে দুটোই আছে, তাই "Department-wise Consumption Trend" আলাদা রিপোর্ট নয়।
> - **Borrowed ও Overdue একটাই তালিকা**, `overdue` কলামসহ। সীমা আসে `requisition.returnable_overdue_days` সেটিং থেকে, কোডে বাঁধা সংখ্যা নয় — টর্চ আর ট্রলি ভিন্ন সময়ে ফেরত আশা করা যায়। দুই স্ক্রিনে ভাগ করলে যিনি তাগাদা দেবেন তাকে দুটোই খুলতে হতো। Consumed আর not-yet-returned আলাদা কলামে রাখা হয়েছে (§10-র দাবি অনুযায়ী): আংশিক ফেরত ইস্যু আংশিকভাবেই বাইরে, আর যেদিকেই গোল করা হোক গুরুত্বপূর্ণ অর্ধেকটা হারায়।
>
> দুটি সিদ্ধান্ত আলাদা করে বলার মতো:
>
> - **Pending রিপোর্ট লাইন ধরে, রিকুইজিশন ধরে নয়** — চার লাইন ইস্যু হয়ে এক লাইন ঘাটতি থাকা রিকুইজিশন "pending" নয়, একটা আইটেম কম; স্টোরকে জানতে হয় কোনটা। এটি পিরিয়ড রিপোর্টও নয়: মার্চে যে লাইন কেউ পূরণ করেনি সেটাই সেপ্টেম্বরে দেখা যাওয়া উচিত।
> - **Consumption রিপোর্টে ফেরত আসা ধার বাদ যায়।** ধার দিয়ে ফেরত পাওয়া স্টক কখনো ব্যবহৃত হয়নি, আর গুনলে ধার নেওয়া টর্চ কারও নামে "খরচ" হিসেবে উঠত। স্পেক এটিকে misuse ধরার জন্য চেয়েছে, তাই ক্লাস-নোটে স্পষ্ট করা আছে যে এটি **প্রশ্ন শুরুর জায়গা, অভিযোগ নয়** — তালিকার ওপরে থাকা মানুষটি হয়তো ব্যস্ততম লাইনটাই চালান, আর সেই নিরীহ ব্যাখ্যাটা (কতগুলো রিকুইজিশন, কত বড়) মোটের পাশেই কলাম হিসেবে দেখানো হয়।

## 11. Business Rules

1. User শুধু নিজের Login থেকে requisition দিতে পারবে; Department dropdown-এ সব Department দেখাবে
2. Store Keeper সম্পূর্ণ বা আংশিক Issue করতে পারবে; আংশিক হলে বাকিটা Auto PR-এ যাবে বাধ্যতামূলকভাবে
3. Available Stock (Reserved/Hard-Allocated বাদে) থেকেই Floor Requisition Issue হবে
4. একই User একই Item বারবার অল্প অল্প করে চাইলে (সন্দেহজনক প্যাটার্ন) সিস্টেম Flag করবে
5. Floor Requisition-এ কোনো Approval লাগবে না (দ্রুত ফ্লোর অপারেশনের জন্য) — শুধু Store-এর Fulfillment ডিসিশন লাগবে; তবে High-Value/নির্দিষ্ট Category-তে Approval অপশনাল রাখা যায় (configurable, বিদ্যমান Role/Permission ব্যবস্থা ব্যবহার করে — Approval Routing সম্পূর্ণভাবে বিদ্যমান প্ল্যাটফর্মের Role/Permission নির্ধারণ করবে, Master Data-র Department Lookup-এর সাথে এর কোনো সরাসরি সম্পর্ক নেই)
6. সব Critical Transaction (Submit, Issue, Auto PR Trigger, Return)-এ বিদ্যমান Audit Log ব্যবস্থায় এন্ট্রি হবে
7. Floor Requisition Shortage থেকে তৈরি Auto PR-এর জন্য stock আসার সাথে সাথে সেটা Reservation দিয়ে earmark করা থাকবে, যতক্ষণ না Store Keeper ইস্যু করে বা Store Manager ম্যানুয়ালি Override করে
8. Roll/Lot-tracked Item-এ User শুধু Item+Qty চাইবে; নির্দিষ্ট Roll/Lot অ্যাসাইনমেন্ট Store Keeper Fulfillment টাইমে করবে
9. `is_returnable = Yes` Item Consumed হিসেবে গণ্য হবে না যতক্ষণ না তা স্থায়ীভাবে ব্যবহৃত/নষ্ট হয়ে গেছে বলে নিশ্চিত করা হয়; ফেরত না আসা পর্যন্ত "Issued — Not Yet Returned" স্ট্যাটাসে থাকবে

---

**পরবর্তী:** [Master Data Module](01_Master_Data_Module.md) | [Inventory Module](02_Inventory_Module.md) | [Procurement Module](03_Procurement_Module.md)
