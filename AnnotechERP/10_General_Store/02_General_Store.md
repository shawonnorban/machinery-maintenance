# General Store

> **স্ট্যাটাস:** ✅ সম্পন্ন
> **মডিউল:** [General Store](00_Index.md) · **ফেজ ৩:** সাপ্লাই ও স্টোর · **কী:** `10/02`

**⬅ ফিরে যান:** [General Store](00_Index.md) | [AnnotechERP Index](../00_Index.md)

---

## ১. স্ক্রিন (Brickland ERP থেকে)

<!-- AUTO:screens:start -->
| # | স্ক্রিন | পাথ | ট্যাব | ফিল্ড | ডায়ালগ |
|---|---|---|---|---|---|
| 1 | Dashboard | `/m/general-store/gs-dashboard` | 1 | 0 | 0 |
<!-- AUTO:screens:end -->

## ২. প্রতিটা স্ক্রিনের লাইভ বিবরণ (ফিল্ড, ট্যাব, গ্রিড, ডায়ালগ, ভ্যালিডেশন)

<!-- AUTO:detail:start -->
#### Dashboard

`/m/general-store/gs-dashboard`

**ভিউ**
> 01 of 34
- গ্রিড কলাম: SL, ITEM CODE, ITEM NAME, UNIT, CURRENT STOCK, MIN. STOCK, STATUS
- গ্রিড কলাম: SL, ITEM CODE, ITEM NAME, UNIT, CURRENT STOCK, STATUS
- গ্রিড কলাম: DATE, TYPE, REF NO., ITEM NAME, DEPARTMENT / PARTY, QTY, UNIT, USER
- গ্রিড কলাম: ITEM CODE, ITEM NAME, CATEGORY, UNIT, STOCK QTY, PRICE (৳), STOCK VALUE (৳), STATUS
- সেকশন: Stock Movement, Category-wise Stock, Stock Value by Category, Low Stock Items, Out of Stock Items, Recent Transactions, Current Stock
<!-- AUTO:detail:end -->

## ৩. বিদ্যমান কোড যা কাজে লাগতে পারে

- 10/03-এর ডকুমেন্ট আর `gs_stock_movements` থেকেই সব হিসাব। Current Stock-এর সারি (`GsStockApiController::stockRows`) Dashboard-ও ব্যবহার করে।
- চার্টে recharts আর `StatusDonut`; রঙ app-এর তিনটা যাচাই করা series token (`--series-1..3`)।

## ৪. ডাটা মডেল (Entity ও Field)

নতুন টেবিল নেই। পড়া হয় `gs_items`, `gs_stock_movements`, `gs_documents`, `gs_document_lines` আর `gs_categories` থেকে।

## ৫. Workflow / Status ও Business Rules

- **দ্রুত বাটন:** Quick Receive, Quick Issue, Quick Return, Stock Search (Current Stock) আর New Requisition। এগুলো অন্য স্ক্রিনে যায়।
- **রিকুইজিশনের banner** (ধাপ ২): "n requisitions are waiting for approval →" (Pending-এ যায়) আর "n approved requisitions are waiting to be issued →" (Approved-এ যায়); শূন্য হলে দেখায় না।
- **Tile:**
  - Total Stock Items (চালু আইটেম), Total Stock Quantity, Total Stock Value (দামওয়ালা আইটেম)।
  - Total Stock Received Value (চালু Receive আর Opening-এর মূল্য), Total Stock Issued Value (চালু Issue-এর মূল্য), Current In-House Stock Value = এই দুটোর পার্থক্য (Brickland-এর সংখ্যা মিলিয়ে বের করা: 6,640 − 1,320 = 5,320)।
  - Total Categories, Low Stock Items, Out of Stock।
  - Today Receive / Issue / Return / Adjustment: আজকের চালু ডকুমেন্টের সংখ্যা।
- **চার্ট:**
  - Stock Movement: শেষ ৩০ দিনের In আর Out (Transfer বাদ, কারণ তাতে মোট স্টক বদলায় না)।
  - Category-wise Stock: পরিমাণে প্রথম তিনটা ক্যাটাগরি রঙে, বাকি "Others"-এ, প্রতিটার পরিমাণ আর শতাংশ।
  - Stock Value by Category: দামওয়ালা আইটেমের মূল্য, বড় থেকে ছোট।
- **টেবিল:** Low Stock, Out of Stock Items (প্রথম ২০টা), Recent Transactions (শেষ ১০টা ডকুমেন্টের লাইন), Current Stock — Highest quantities (প্রথম ১০টা)।

## ৬. API ও পারমিশন

- `GET /general-store/dashboard`, অনুমতি `store.inventory.view`।

## ৭. Frontend পেজ (লাইট থিম)

- রুট `/general-store/gs-dashboard`, সাইডবারে General Store → GENERAL STORE → Dashboard।
- Brickland-এর বিন্যাস: ওপরে ডানে দ্রুত বাটন, ৬ + ৩ + ৪টা রঙিন-উপর-রেখার tile, তিনটা চার্ট এক সারিতে, নিচে দুই কলামে চারটা টেবিল।

## ৮. Implementation Files

<!-- AUTO:impl:start -->
As-built:

**Backend**
- `app/Modules/GeneralStore/Http/Controllers/Api/GsStockApiController.php` (`dashboard`)
- `app/Modules/GeneralStore/Routes/api.php`
- `app/Modules/Identity/Support/MenuRegistry.php` (GENERAL_STORE.DASHBOARD)

**Frontend**
- `frontend/src/components/generalstore/dashboard.jsx`
- `frontend/src/app/(app)/general-store/[screen]/screens.js`
- `lang/en/store.php`, `lang/bn/store.php` (`inv.dash.*`)

**Tests**
- `tests/Feature/GeneralStore/GeneralStoreInventoryTest.php` (Dashboard-এর অংশ)
<!-- AUTO:impl:end -->

### পরিকল্পিত নতুন/বদলানো ফাইল

_তৈরি হয়ে গেছে।_ রিকুইজিশনের banner আর New Requisition বাটন 10/05-এর সাথে।

## ৯. টেস্ট ও যাচাই

- `GeneralStoreInventoryTest`: Dashboard-এর আইটেম, পরিমাণ, মূল্য, Received Value, আজকের Adjustment, ৩০ দিনের সারি আর ক্যাটাগরির ক্রম।
- **ব্রাউজার (dev):** ১৬টা আইটেম, পরিমাণ 140, মূল্য ৳21,924, Received ৳26,800, Issued ৳8,800, In-House ৳18,000; আজকের চারটা tile 1 করে (বাতিল Transfer গোনা হয়নি); চার্ট আর চারটা টেবিল ঠিক আসে।

## ১০. খোলা প্রশ্ন

- Brickland-এর Dashboard-এর "Today …" tile সংখ্যা না পরিমাণ, ওদের ডাটায় বোঝা যায়নি (সবই 0 ছিল)। এখানে ডকুমেন্টের সংখ্যা।

## ১১. As-built নোট

- **✅** (২০২৬-১০-২৪)। লাইভ প্রোব: `tools/erp_crawler/plans/gs01.json` (dashboard), `gs06.json` (বাটন)।
