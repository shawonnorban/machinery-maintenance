# General

> **স্ট্যাটাস:** ✅ সম্পন্ন (Trims & Accessories Warehouse চালু)
> **মডিউল:** [Store & Material](00_Index.md) · **ফেজ ৩:** সাপ্লাই ও স্টোর · **কী:** `09/02`

**⬅ ফিরে যান:** [Store & Material](00_Index.md) | [AnnotechERP Index](../00_Index.md)

---

## ১. স্ক্রিন (Brickland ERP থেকে)

<!-- AUTO:screens:start -->
| # | স্ক্রিন | পাথ | ট্যাব | ফিল্ড | ডায়ালগ |
|---|---|---|---|---|---|
| 1 | Trims & Accessories Warehouse | `/m/inventory/trims-warehouse` | 1 | 2 | 0 |
<!-- AUTO:screens:end -->

## ২. প্রতিটা স্ক্রিনের লাইভ বিবরণ (ফিল্ড, ট্যাব, গ্রিড, ডায়ালগ, ভ্যালিডেশন)

<!-- AUTO:detail:start -->
#### Trims & Accessories Warehouse

`/m/inventory/trims-warehouse` — মেনুতে: Trims & Accessories

**ভিউ**
> 04 of 10

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন/প্লেসহোল্ডার |
|---|---|---|---|
| Category | select |  | All |
| Item | select |  | All |
- গ্রিড কলাম: ITEM, CATEGORY, COLOR, SIZE, UNIT, RECV QTY, OUT QTY, RETURN QTY, AVAILABLE
- সেকশন: Trims & Accessories Warehouse — Stock View
- বাটন/অ্যাকশন: Search, Reset
<!-- AUTO:detail:end -->

## ৩. বিদ্যমান কোড যা কাজে লাগতে পারে

- [Store & Material](01_Store_Material.md)-এর একই হিসাব: `sm_stocks`, `sm_ledger`, `StoreBook`।

## ৪. ডাটা মডেল (Entity ও Field)

আলাদা কোনো টেবিল নেই। Trims & Accessories Warehouse হলো TRIMS ধরনের material-এর stock bucket আর তাদের ledger।

## ৫. Workflow / Status ও Business Rules

- **Filter:** Category আর Item, সাথে Search আর Reset।
- **কলাম:** Item, Category, Color, Size, Unit, Buyer / Style, Recv Qty, Out Qty, Return Qty, Available।
- **হিসাব:**
  - **Recv:** GRN থেকে reversal বাদ দিয়ে, adjustment-এর বাড়তি যোগ করে।
  - **Out:** Material Out আর adjustment-এর কমতি।
  - **Available:** Recv − Out + Return।
- **ফাঁকা হলে:** "No trims in the warehouse for this filter."

## ৬. API ও পারমিশন

`storematerial.view`। Path: GET `/store-material/trims-warehouse?category=&material=`।

## ৭. Frontend পেজ (লাইট থিম)

সাইডবার: Store & Material → **Trims & Accessories** (০৪ of ১০)। filter-এর সারি আর "Trims & Accessories Warehouse — Stock View" টেবিল; পাতা রিলোড হয় না।

## ৮. Implementation Files

<!-- AUTO:impl:start -->
| স্তর | ফাইল |
|---|---|
| ব্যাকএন্ড | `app/Modules/StoreMaterial/Http/Controllers/Api/StockApiController.php` (`trims`) |
| ফ্রন্টএন্ড | `frontend/src/components/storematerial/warehouse.jsx` (`TrimsWarehouse`), `frontend/src/app/(app)/store-material/[screen]/screens.js` |
| টেস্ট | `tests/Feature/StoreMaterial/StoreGrnTest.php` (general stock আর LPP-এর trims, buyer filter) |
<!-- AUTO:impl:end -->

### পরিকল্পিত নতুন/বদলানো ফাইল

_নেই।_

## ৯. টেস্ট ও যাচাই

- **`StoreGrnTest`:** Direct receive-এ Main Label LPP / 927-এর জন্য ৩০০, আর general stock-এ ৫০। তালিকায় দুটো আলাদা সারি আসে; `?buyer=LPP` দিলে ৩০০।
- **রিগ্রেশন:** [Store & Material](01_Store_Material.md)-এর সাথে।
- **ব্রাউজার যাচাই** (২০২৬-১০-২৪): Main Label-এর GRN-2026-00002 reverse করার পরে Available ০ দেখায় ✔

## ১০. খোলা প্রশ্ন

_নেই।_

## ১১. As-built নোট

- **✅** (২০২৬-১০-২৪)। Store & Material-এর সাথে বানানো। লাইভ প্রোব: `st01.json`।
