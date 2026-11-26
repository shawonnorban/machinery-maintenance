# Requisition

> **স্ট্যাটাস:** ✅ সম্পন্ন
> **মডিউল:** [General Store](00_Index.md) · **ফেজ ৩:** সাপ্লাই ও স্টোর · **কী:** `10/05`

**⬅ ফিরে যান:** [General Store](00_Index.md) | [AnnotechERP Index](../00_Index.md)

---

## ১. স্ক্রিন (Brickland ERP থেকে)

<!-- AUTO:screens:start -->
| # | স্ক্রিন | পাথ | ট্যাব | ফিল্ড | ডায়ালগ |
|---|---|---|---|---|---|
| 1 | Pending Requisition | `/m/general-store/gs-req-pending` | 1 | 0 | 0 |
| 2 | Approved Requisition | `/m/general-store/gs-req-approved` | 1 | 0 | 0 |
| 3 | Issued Requisition | `/m/general-store/gs-req-issued` | 1 | 0 | 0 |
<!-- AUTO:screens:end -->

## ২. প্রতিটা স্ক্রিনের লাইভ বিবরণ (ফিল্ড, ট্যাব, গ্রিড, ডায়ালগ, ভ্যালিডেশন)

<!-- AUTO:detail:start -->
#### Pending Requisition

`/m/general-store/gs-req-pending`

**ভিউ**
> 17 of 34
- গ্রিড কলাম: REQ. NO., DATE, DEPARTMENT, STORE, ITEMS, REQUESTED, APPROVED, ISSUED, REQUESTED BY, STATUS
- সেকশন: Pending Requisitions (0)

#### Approved Requisition

`/m/general-store/gs-req-approved`

**ভিউ**
> 18 of 34
- গ্রিড কলাম: REQ. NO., DATE, DEPARTMENT, STORE, ITEMS, REQUESTED, APPROVED, ISSUED, REQUESTED BY, STATUS
- সেকশন: Approved Requisitions (0)

#### Issued Requisition

`/m/general-store/gs-req-issued`

**ভিউ**
> 19 of 34
- গ্রিড কলাম: REQ. NO., DATE, DEPARTMENT, STORE, ITEMS, REQUESTED, APPROVED, ISSUED, REQUESTED BY, STATUS
- সেকশন: Issued Requisitions (0)
<!-- AUTO:detail:end -->

## ৩. বিদ্যমান কোড যা কাজে লাগতে পারে

- 10/03-এর Stock Issue (`GsDocumentApiController`) — requisition-এর বিপরীতে Issue সেখানেই হয়। `GsBook` (নম্বর, স্টক, audit)।
- 10/04-এর New Requisition একই controller আর component ব্যবহার করে (`GsRequisitionApiController`, `requisition.jsx`)।

## ৪. ডাটা মডেল (Entity ও Field)

মাইগ্রেশন `2026_12_24_000100_create_gs_requisitions`:

| টেবিল | কী রাখে |
|---|---|
| `gs_requisitions` | req_no (REQ-YYYY-NNNNNN), req_date, gs_department_id, gs_store_id, reason, status (DRAFT / PENDING / APPROVED / PARTIAL / ISSUED / REJECTED / CANCELLED), requested_by, submitted_at, decided_by, decided_at, approval_note, closing_reason (নাকচ বা বাতিলের কারণ), cancelled_by, cancelled_at |
| `gs_requisition_lines` | gs_item_id, requested_qty, approved_qty, remarks, sort_order |
| `gs_documents.requisition_id` | কোন requisition-এর বিপরীতে Issue হয়েছে (এখন FK) |

কত Issue হয়েছে তা চালু Issue-এর লাইন থেকে হিসাব হয় (`GsBook::issuedAgainst`), আলাদা ঘরে রাখা হয় না।

## ৫. Workflow / Status ও Business Rules

- **Pending Requisition:** সব জমা পড়া requisition। সারিতে View, Print, Approve, Reject আর Cancel (আইকন)।
  - **Approve dialog:** "Approve REQ-…", নিচে "বিভাগ · স্টোর · requested by …"; "Lines to approve" টেবিলে Item, Requested, In Store আর Approved Qty। Approved Qty-তে শুরুতে চাওয়া পরিমাণ বসে; সাথে Approval Note।
  - চাওয়ার বেশি অনুমোদন হয় না: "Line 1: approve no more than the 6 REAM requested."
  - সব লাইন শূন্য হলে: "Approve at least one item — or reject the requisition."
  - **Reject dialog:** "Reject requisition? The department sees the reason. Nothing can be issued against a rejected requisition." কারণ বাধ্যতামূলক।
  - **Cancel dialog:** "Cancel requisition? It stays on record as Cancelled." কারণ ঐচ্ছিক। খসড়া বা অপেক্ষমাণ requisition বাতিল করতে পারেন যিনি চেয়েছেন, অথবা Store Manager। অনুমোদিত requisition আর বাতিল করা যায় না।
- **Approved Requisition:** Approved আর Partially Issued। সারিতে View, Print, Issue।
  - Issue চাপলে Stock Issue খোলে `?req=REQ-2026-000001` দিয়ে (Brickland-এর মতো)। Store আর Department requisition থেকে বসে বন্ধ হয়ে যায়, Employee / Receiver-এ যিনি চেয়েছেন তাঁর নাম বসে, অনুমোদিত আইটেমগুলো লাইনে আসে, নিচে "Up to n" (অনুমোদিত − ইস্যু হয়ে গেছে)।
  - Issue Qty খালি রাখা লাইন এবার ইস্যু হয় না।
  - বেশি দিলে: "Line 1: only 4 REAM of A4 Paper can be issued against REQ-…"; requisition-এ না থাকা আইটেম: "Line 1: Tape is not on REQ-…"।
  - Issue-এর পরে অবস্থা নিজে বদলায়: আংশিক হলে Partially Issued, সব হলে Issued। Issue বাতিল হলে আবার Partially Issued বা Approved হয়।
- **Issued Requisition:** পুরোটা ইস্যু হওয়া requisition; View আর Print।
- **View:** অবস্থার ব্যাজ; Date, Department, Store, Requested By, Reason, Submitted, Decided By (নাম · সময়), Approval Note, Reject / Cancel Reason; লাইনে SL, Item, Unit, Requested, Approved, Issued, Remaining, In Store।
- **Print:** ভাউচারের নাম (Settings) মাথায় রেখে A4; প্রতিটা print audit-এ "Requisition REQ-… printed" (REPORT_EXPORT)।
- **Audit:** saved as draft, submitted, approved, rejected (কারণসহ), cancelled।

## ৬. API ও পারমিশন

| Method | Path |
|---|---|
| GET | `/general-store/requisitions?list=mine\|pending\|approved\|issued`, `/requisitions/options`, `/requisitions/{no}`, `/requisitions/{no}/issuable` |
| POST / PUT | `/general-store/requisitions`, `/requisitions/{no}` (খসড়া), `/{no}/submit`, `/{no}/approve`, `/{no}/reject`, `/{no}/cancel`, `/{no}/printed` |

- **পারমিশন** (নতুন): `store.requisition.create` (FACTORY_ADMIN, STORE_MANAGER, STOREKEEPER, FACTORY_MANAGER, MAINTENANCE_MANAGER), `store.requisition.approve` (FACTORY_ADMIN, STORE_MANAGER)।
- Approved আর Issued তালিকা দেখতে পারেন approve, `store.inventory.post` বা `store.inventory.view` যাঁর আছে।

## ৭. Frontend পেজ (লাইট থিম)

- সাইডবার: General Store → REQUISITION → Pending Requisition, Approved Requisition, Issued Requisition।
- একটা component (`ReqTable`), প্রতিটা স্ক্রিনে নিজের তালিকা আর কাজ; সব dialog পাতার ভেতরে, কিছুই রিলোড হয় না। Approved থেকে Issue আলাদা স্ক্রিন, তাই লিংক।
- Dashboard-এ দুটো banner: "n requisitions are waiting for approval →" আর "n approved requisitions are waiting to be issued →"; দ্রুত বাটনে New Requisition।

## ৮. Implementation Files

<!-- AUTO:impl:start -->
As-built:

**Backend**
- `app/Modules/GeneralStore/Database/Migrations/2026_12_24_000100_create_gs_requisitions.php`
- `app/Modules/GeneralStore/Models/GsRequisition.php`
- `app/Modules/GeneralStore/Models/GsRequisitionLine.php`
- `app/Modules/GeneralStore/Http/Controllers/Api/GsRequisitionApiController.php`
- `app/Modules/GeneralStore/Http/Controllers/Api/GsDocumentApiController.php` (requisition-এর বিপরীতে Issue)
- `app/Modules/GeneralStore/Http/Controllers/Api/GsStockApiController.php` (Dashboard-এর banner)
- `app/Modules/GeneralStore/Support/GsBook.php` (`nextRequisitionNumber`, `issuedAgainst`, `refreshRequisition`)
- `app/Modules/GeneralStore/Routes/api.php`
- `app/Modules/Identity/Support/MenuRegistry.php`, `Database/Seeders/PermissionSeeder.php`, `RoleSeeder.php`

**Frontend**
- `frontend/src/components/generalstore/requisition.jsx`
- `frontend/src/components/generalstore/document.jsx` (Issue-এ requisition)
- `frontend/src/components/generalstore/dashboard.jsx`
- `frontend/src/app/(app)/general-store/[screen]/{page,screens,actions}.js`
- `lang/en/store.php`, `lang/bn/store.php` (`req.*`)

**Tests**
- `tests/Feature/GeneralStore/GeneralStoreRequisitionTest.php`
<!-- AUTO:impl:end -->

### পরিকল্পিত নতুন/বদলানো ফাইল

_তৈরি হয়ে গেছে; ওপরের as-built তালিকা দেখুন।_

## ৯. টেস্ট ও যাচাই

- **`GeneralStoreRequisitionTest`:** ২টা টেস্ট।
  - ফর্মের বার্তা; খসড়া, অন্যের খসড়া বদলানো যায় না, Edit, Submit, আবার Submit আটকায়; STOREKEEPER অপেক্ষমাণ তালিকা দেখতে বা Approve করতে পারেন না।
  - Approve: বেশি আর সব শূন্য আটকায়; ৬ / ৫০ / ২-এর বদলে ৪ / ৫০ / ০ অনুমোদন, নোটসহ।
  - Issue: issuable লাইন, options-এর তালিকা, বেশি আর না-থাকা আইটেম আটকায়; store আর department requisition থেকে; প্রথম Issue-এ Partially Issued, দ্বিতীয়তে Issued; Issue বাতিলে আবার Partially Issued; অনুমোদিত requisition বাতিল আটকায়।
  - Reject (কারণ বাধ্যতামূলক) আর Cancel; audit-এর সব লেখা; print-এর REPORT_EXPORT।
- পুরো General Store-এর ৮টা টেস্ট সবুজ (২৪৫টা assertion); MenuAccess, RoleMatrix, ApiGateCoverage আর Store Material মিলিয়ে ৩২টা সবুজ।
- **ব্রাউজার যাচাই** (owner, dev DB-তে রাখা):
  - **REQ-2026-000001:** Maintenance, A4 Paper 80 GSM 5 আর Ball Pen 10; খসড়া → Edit (পেন 12) → Submit → Approve (পেন 8, নোট "Half the pens now") → Approved থেকে Issue (IS-2026-000002: A4 5, পেন 4; পেনে 9 দিলে লাল "Up to 8") → Partially Issued (১৩-র মধ্যে ৯)।
  - **REQ-2026-000002:** IT, Printer Toner 2 → নাকচ ("Toner was bought last week")।
  - Dashboard-এ "1 approved requisitions are waiting to be issued →"।

## ১০. খোলা প্রশ্ন

- Brickland-এ Approved তালিকায় কোনো Cancel নেই, তাই এখানেও অনুমোদিত requisition বাতিল করা যায় না। আংশিক ইস্যুর পরে বাকিটা বন্ধ করার উপায় দরকার হলে পরে যোগ করা যাবে।

## ১১. As-built নোট

- **✅** (২০২৬-১০-২৪)। লাইভ প্রোব: `tools/erp_crawler/plans/gs08.json` থেকে `gs12.json`।
  - gs11 আর gs12-এ লেখার অনুমতি ব্যবহার করে Brickland-এ খসড়া REQ-2026-000012 বানিয়ে Edit-এর রূপ দেখা হয়েছে, তারপর সেটা বাতিল করা হয়েছে। Approve আর Reject-এর dialog শুধু খুলে দেখা হয়েছে, confirm করা হয়নি।
- **ধাপ:** General Store-এর ধাপ ২।
