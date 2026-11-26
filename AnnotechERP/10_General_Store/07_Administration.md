# Administration

> **স্ট্যাটাস:** ✅ সম্পন্ন
> **মডিউল:** [General Store](00_Index.md) · **ফেজ ৩:** সাপ্লাই ও স্টোর · **কী:** `10/07`

**⬅ ফিরে যান:** [General Store](00_Index.md) | [AnnotechERP Index](../00_Index.md)

---

## ১. স্ক্রিন (Brickland ERP থেকে)

<!-- AUTO:screens:start -->
| # | স্ক্রিন | পাথ | ট্যাব | ফিল্ড | ডায়ালগ |
|---|---|---|---|---|---|
| 1 | Audit Log | `/m/general-store/gs-audit-log` | 1 | 1 | 0 |
| 2 | Settings | `/m/general-store/gs-settings` | 1 | 0 | 0 |
<!-- AUTO:screens:end -->

## ২. প্রতিটা স্ক্রিনের লাইভ বিবরণ (ফিল্ড, ট্যাব, গ্রিড, ডায়ালগ, ভ্যালিডেশন)

<!-- AUTO:detail:start -->
#### Audit Log

`/m/general-store/gs-audit-log`

**ভিউ**
> 33 of 34

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন/প্লেসহোল্ডার |
|---|---|---|---|
| Search | text |  | User, action or text |
- গ্রিড কলাম: WHEN, USER, ACTION, SCREEN, SUMMARY
- সেকশন: Audit Log (26)
- বাটন/অ্যাকশন: Excel

#### Settings

`/m/general-store/gs-settings`

**ভিউ**
> 34 of 34
- সেকশন: This screen is not available
<!-- AUTO:detail:end -->

## ৩. বিদ্যমান কোড যা কাজে লাগতে পারে

- `gs_audit_trail` (10/03-এ তৈরি) আর `GsBook::audit()`। ডকুমেন্ট, requisition, Excel আর আইটেম master সবাই এখানেই লেখে।
- রোল কারা ধরে আছেন তা আসে `user_roles` + `roles` থেকে; রোল দেওয়া হয় Access Control-এ।

## ৪. ডাটা মডেল (Entity ও Field)

| টেবিল | কী রাখে |
|---|---|
| `gs_audit_trail` | user, action (CREATE / UPDATE / PRICE_CHANGE / REPORT_EXPORT), screen, reference, summary, created_at (মাইক্রোসেকেন্ড); শুধু যোগ করা যায় |
| `gs_settings` | (মাইগ্রেশন `2026_12_24_000100_create_gs_requisitions`) কোম্পানিপ্রতি একটা সারি: requisition_approval, direct_issue, receiver_required, voucher_name, voucher_footer, updated_by |

## ৫. Workflow / Status ও Business Rules

- **Audit Log:**
  - "Audit Log (n)", নিচে "Latest 1,000 entries: …"; Search (ব্যবহারকারী, কাজ, স্ক্রিন বা সারাংশ) আর Excel।
  - কলাম: When, User, Action, Screen, Summary; নতুনটা আগে।
  - যা লেখা হয় (Brickland-এর মতোই ইংরেজিতে):
    - ডকুমেন্ট তৈরি ("Stock Receive RC-…: 2 item(s), 55 qty, ৳4200") আর বাতিল ("… cancelled and reversed: কারণ")।
    - Issue-এর দাম বদল (PRICE_CHANGE)।
    - Requisition-এর খসড়া, জমা, অনুমোদন, নাকচ, বাতিল আর print।
    - Excel ডাউনলোড (REPORT_EXPORT)।
    - আইটেম master-এ যোগ বা বদল ("Item ITM-001 — A4 Paper")।
    - Settings বদল ("Settings updated: …")।
- **Settings** (Brickland-এ পাতাটা ভাঙা — "This screen is not available"। memory-র নিয়ম অনুযায়ী পুরোপুরি বানানো হয়েছে, ওদের বর্ণনা ধরে):
  - **Requisition approval:** "A submitted requisition waits for the Store Manager's approval" — বন্ধ হলে জমা দিলেই চাওয়া পরিমাণে অনুমোদিত।
  - **Issue rules:** "The store may issue without a requisition (Direct issue)" — বন্ধ হলে Issue-এ "Direct issue" থাকে না, আর server বলে "Issue against an approved requisition — direct issue is switched off in Settings."; "An issue must name the Employee / Receiver" — চালু হলে "Employee / Receiver is required."।
  - **Vouchers:** Name on vouchers (ফাঁকা থাকলে কোম্পানির নাম) আর Voucher footer; ডকুমেন্ট আর requisition-এর print-এ মাথায় নাম, নিচে ফুটার।
  - **Store roles:** Store Manager, Storekeeper আর Factory Admin রোলে কারা আছেন (নাম, ইমেইল); "Roles are given in Access Control → Users."।
  - নিচে "Last saved by … · সময়"।

## ৬. API ও পারমিশন

| Method | Path |
|---|---|
| GET | `/general-store/audit-log?q=`, `/audit-log/excel` |
| GET / PUT | `/general-store/settings` |

- **পারমিশন** (নতুন): `store.audit.view` আর `store.settings.manage` — দুটোই FACTORY_ADMIN আর STORE_MANAGER।

## ৭. Frontend পেজ (লাইট থিম)

- সাইডবার: General Store → ADMINISTRATION → Audit Log, Settings।
- Audit Log-এ খোঁজা পাতার ভেতরে; Settings-এ toggle আর ঘর, ডানে রোলের কার্ড; Save-এ রিলোড হয় না।

## ৮. Implementation Files

<!-- AUTO:impl:start -->
As-built:

**Backend**
- `app/Modules/GeneralStore/Http/Controllers/Api/GsAdminApiController.php`
- `app/Modules/GeneralStore/Models/GsSetting.php`
- `app/Modules/GeneralStore/Models/GsItem.php` (আইটেমের audit)
- `app/Modules/GeneralStore/Support/GsBook.php` (`settings`, `voucher`)
- `app/Modules/GeneralStore/Database/Migrations/2026_12_24_000100_create_gs_requisitions.php` (`gs_settings`)
- `app/Modules/GeneralStore/Routes/api.php`
- `app/Modules/Identity/Support/MenuRegistry.php`, `Database/Seeders/PermissionSeeder.php`, `RoleSeeder.php`

**Frontend**
- `frontend/src/components/generalstore/admin.jsx`
- `frontend/src/components/generalstore/common.jsx` (print-এ ভাউচারের নাম আর ফুটার)
- `frontend/src/app/api/general-store/excel/[kind]/route.js` (audit-log)
- `lang/en/store.php`, `lang/bn/store.php` (`admin.*`)

**Tests**
- `tests/Feature/GeneralStore/GeneralStoreRequisitionTest.php`
<!-- AUTO:impl:end -->

### পরিকল্পিত নতুন/বদলানো ফাইল

_তৈরি হয়ে গেছে।_

## ৯. টেস্ট ও যাচাই

- `GeneralStoreRequisitionTest::test_settings_switch_approval_and_direct_issue_and_the_audit_log_reads_it_all`:
  - ডিফল্ট মান, কোম্পানির নাম, Storekeeper রোলে কে; STOREKEEPER Settings পান না।
  - সব বদলে সেভ; options-এ ভাউচারের নাম আর ফুটার।
  - অনুমোদন বন্ধ → জমা দিলেই APPROVED; সরাসরি Issue বন্ধ → আটকায়; গ্রহীতা বাধ্যতামূলক → আটকায়; requisition আর গ্রহীতাসহ Issue হয়।
  - Audit Log: নতুনটা আগে, আইটেম আর Settings-এর লেখা, খোঁজা, Excel; STOREKEEPER পান না।
- **ব্রাউজার (dev):** Audit Log-এ ১৭টা এন্ট্রি (requisition, Issue, Excel, Transfer বাতিল, দাম বদল …); Settings-এ তিনটা toggle, ভাউচারের ঘর আর তিন রোলের মানুষ (Farhana Islam, Jashim Uddin, Delwar Hossain)।

## ১০. খোলা প্রশ্ন

- Brickland-এর Settings পাতা ভাঙা থাকায় ঘরগুলো ওদের এক লাইনের বর্ণনা থেকে বানানো। ওরা ঠিক করলে আবার মিলিয়ে দেখা দরকার।

## ১১. As-built নোট

- **✅** (২০২৬-১০-২৪)। লাইভ প্রোব: `tools/erp_crawler/plans/gs07.json`।
