# Cm Payment Receive

> **স্ট্যাটাস:** ✅ সম্পন্ন (৩টার ৩টা স্ক্রিন)
> **মডিউল:** [Trade & Export](00_Index.md) · **ফেজ ৬:** শিপমেন্ট ও কমার্শিয়াল · **কী:** `22/04`

**⬅ ফিরে যান:** [Trade & Export](00_Index.md) | [AnnotechERP Index](../00_Index.md)

---

## ১. স্ক্রিন (Brickland ERP থেকে)

<!-- AUTO:screens:start -->
| # | স্ক্রিন | পাথ | ট্যাব | ফিল্ড | ডায়ালগ |
|---|---|---|---|---|---|
| 1 | CM Payment Receipts | `/m/commercial/payment-receives` | 1 | 10 | 0 |
| 2 | Bill Adjustment (Retired) | `/m/commercial/bill-adjustment-not-used` | 1 | 10 | 0 |
| 3 | Margin Closing (Retired) | `/m/commercial/margin-closing-not-used` | 1 | 4 | 0 |
<!-- AUTO:screens:end -->

## ২. প্রতিটা স্ক্রিনের লাইভ বিবরণ (ফিল্ড, ট্যাব, গ্রিড, ডায়ালগ, ভ্যালিডেশন)

<!-- AUTO:detail:start -->
#### CM Payment Receipts

`/m/commercial/payment-receives`

**ভিউ**

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন/প্লেসহোল্ডার |
|---|---|---|---|
| Bill Of Exchange No | text |  |  |
| L/C No | text |  |  |
| Bank Bill Ref No. | text |  |  |
| Bank Bill Type | select |  | FDBP, FDBC, DA, DP, IP |
| BoE. From Date | date |  |  |
| BoE. To Date | date |  |  |
| Bill Submit From date | date |  |  |
| Bill Submit To date | date |  |  |
| Payment Receive From Date | date |  |  |
| Payment Receive To Date | date |  |  |
- গ্রিড কলাম: Actions, Final? ▼, Bill of Exchange No ▼, Bank Bill Type ▼, Bank Bill Reference No. ▼, Bill Date ▼, Bill Amount ▼, Buyer Name ▼, L/C No ▼, L/C Value ▼, Bill Submit Date At Bank ▼, Payment Recv Date ▼
- সেকশন: Searching Data
- বাটন/অ্যাকশন: 🔍 Search

#### Bill Adjustment (Retired)

`/m/commercial/bill-adjustment-not-used`

**ভিউ**

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন/প্লেসহোল্ডার |
|---|---|---|---|
| L/C No | text |  |  |
| Bill Of Exchange No | text |  |  |
| Bank Bill Ref. No | text |  |  |
| BoE. Invoice No | text |  |  |
| Company | select |  | Brickland Composite Ltd. |
| Buyer/Importer | select |  | LPP |
| Advance Agnst Exp. | select |  | YES, NO |
| With Finalized? | select |  | YES, NO |
| BoE. From Date | date |  |  |
| BoE. To Date | date |  |  |
- গ্রিড কলাম: Actions, Final? ▼, Bill of Exchange No ▼, Bill Date ▼, Bill Submit Date At Bank ▼, Payment Recv Date at Bank ▼, Bill Ref. No ▼, L/C No ▼, L/C Value ▼, Bill Amount ▼, Negotiated Value ▼
- সেকশন: Searching Data
- বাটন/অ্যাকশন: 🔍 Search

#### Margin Closing (Retired)

`/m/commercial/margin-closing-not-used`

**ভিউ**

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন/প্লেসহোল্ডার |
|---|---|---|---|
| Bank | select |  | ABN AMRO BANK, AGRANI BANK LIMITED, AL ARAFAH ISLAMI BANK LIMITED, BASIC BANK LIMITED, BRAC Bank Ltd, Bank Asia Ltd, DUT |
| Accounting Type | select |  | Margin, Commission, Interest, Bank Charge |
| From Date | date |  |  |
| To Date | date |  |  |
- গ্রিড কলাম: Bank Name ▼, Account Type ▼, Amount ▼, Created On ▼, Action
- সেকশন: Searching Data
- বাটন/অ্যাকশন: 🔍 Search
<!-- AUTO:detail:end -->

## ৩. বিদ্যমান কোড যা কাজে লাগতে পারে

- **22/02-এর Bill of Exchange (`tx_bills_of_exchange`):** CM পেমেন্ট এর বিপরীতেই আসে। Advance (IP) বিল আর হিসাব বণ্টন (`tx_bill_distributions`) এখানে আগে থেকেই আছে।
- **22/02-এর `Desk`, `Box`, `ReadGrid`, `BoeForm`:** তালিকা আর এন্ট্রি একই পেজে, ঠিকানায় `?no=`। Bill Adjustment আর Margin Closing এই ফর্মের ওপরেই বানানো।
- **Export L/C, Commercial Invoice, Realisation:** রসিদের ফর্মে L/C-র মূল্য, ইনভয়েস নম্বর আর পেমেন্টের তারিখ এগুলো থেকে আসে।

## ৪. ডাটা মডেল (Entity ও Field)

মাইগ্রেশন `2027_03_03_000100_create_tx_cm_receipts.php`:

- **`tx_cm_receipts` (নতুন):** receipt no (`CMR-2026-000001`, সেভে), Bill of Exchange, Bank Bill Type, পরিমাণ, গ্রহণের তারিখ, বিনিময় হার, DFC Margin, final, created_by।
- **`tx_bills_of_exchange`-এ নতুন ঘর:**
  - `bank_submit_date`: ব্যাংকে বিল জমার তারিখ।
  - `payment_recv_date`: ব্যাংক থেকে পেমেন্ট পাওয়ার তারিখ।
  - `margin_type`: MARGIN / COMMISSION / INTEREST / BANK_CHARGE।

## ৫. Workflow / Status ও Business Rules

- **CM Payment Receipts:**
  - Bill of Exchange-এর ড্রপডাউনে শুধু Final বিল থাকে। বিল বাছলে L/C, Buyer, L/C-র মূল্য, বিলের তারিখ, ইনভয়েস, Bank Bill Ref, মোট বিল আর ব্যাংকে জমার তারিখ নিজে থেকে আসে।
  - Bank Bill Type আর বিনিময় হার বিল থেকে আগে বসে; বদলানো যায়।
  - একই বিলের সব রসিদ মিলিয়ে বিলের পরিমাণের বেশি হতে পারে না। বেশি হলে বার্তায় বাকি পরিমাণ দেখায়।
  - নিচের গ্রিডে বিলের সব রসিদ, আর মোট পরিমাণ ও DFC Margin।
  - Finalize করলে লক, মোছা যায় না।
- **Bill Adjustment (Retired):**
  - তালিকায় সব বিল, সাথে ব্যাংকে জমা আর পেমেন্টের তারিখ, L/C, L/C-র মূল্য, বিলের পরিমাণ আর Negotiated Value।
  - "Manage Bill Submit and Payment Date": Export L/C বেছে Search করলে তার সব ড্রাফট আসে; প্রতিটায় দুটো তারিখ হাতে বসানো যায়, Final বিলেও।
  - জমার তারিখ বিলের তারিখের আগে হতে পারে না; পেমেন্ট জমার আগে হতে পারে না।
  - হাতে না দিলে জমার তারিখ = Bill Ref Date, আর পেমেন্টের তারিখ = সবচেয়ে শেষ realisation বা CM রসিদের তারিখ।
- **Margin Closing (Retired):**
  - একটা Advance (IP) বিল, সাথে Accounting Type (Margin, Commission, Interest, Bank Charge)।
  - Detail Distribution-এ Dr আর Cr সমান লাগে।
  - তালিকায় Bank Name, Account Type, Amount, Created On; Bank আর Accounting Type আর তারিখ দিয়ে ফিল্টার।

## ৬. API ও পারমিশন

- **পারমিশন:** নতুন কিছু নেই; 22/02-এর `tradeexport.export.view` / `manage`।
- **API (`/tradeexport/ops/…`):**
  - `cm-receipts` (তালিকা, `bills`, `source/{boe}`, POST, `{id}` GET / PUT / DELETE, `{id}/final`)
  - `boes/dates/{lc}` (GET), `boes/dates` (PUT)
  - `margin-closings` (তালিকা); সেভ হয় `boes`-এ, `margin_type` সহ
- **মেনু:** Trade & Export-এর নিচে নতুন ফোল্ডার CM_PAYMENT, ৩টা স্ক্রিন।

## ৭. Frontend পেজ (লাইট থিম)

- **কম্পোনেন্ট:**
  - `components/tradeexport/cm-payment.jsx`: `CmReceiptDesk`।
  - `operations.jsx`: `BillAdjustmentDesk`, `MarginClosingDesk`। দুটোই `BoeDesk`-এর ওপর `variant` দিয়ে বানানো; তারিখের প্যানেল `BillDatesForm`।
- **ঠিকানা:** `/trade-export/payment-receives`, `/bill-adjustment-not-used`, `/margin-closing-not-used`; রেকর্ড খুললে `?no=`।

## ৮. Implementation Files

<!-- AUTO:impl:start -->
As-built (৩টার ৩টা স্ক্রিন):

**Backend (`app/Modules/TradeExport`)**
- `Database/Migrations/2027_03_03_000100_create_tx_cm_receipts.php` (নতুন)
- `Models/TxCmReceipt.php` (নতুন), `Models/TxBillOfExchange.php` (বদলানো)
- `Http/Controllers/Api/CmPaymentApiController.php` (নতুন)
- `Http/Controllers/Api/ExportBillApiController.php`, `Routes/api.php` (বদলানো)
- `app/Modules/Identity/Support/MenuRegistry.php` (বদলানো)
- `lang/en/tradeop.php`, `lang/bn/tradeop.php` (বদলানো)

**Frontend**
- `frontend/src/components/tradeexport/cm-payment.jsx` (নতুন)
- `frontend/src/components/tradeexport/operations.jsx`, `app/(app)/trade-export/[screen]/screens.js` (বদলানো)

**Test**
- `tests/Feature/TradeExport/TradeExportOperationsTest.php` (বদলানো)
<!-- AUTO:impl:end -->

### পরিকল্পিত নতুন/বদলানো ফাইল

উপরের as-built তালিকাই চূড়ান্ত।

## ৯. টেস্ট ও যাচাই

**Feature test (`test_cm_payment_receipts_bill_dates_and_margin_closing`):** test2 DB-তে পাস, ৩৭টা assertion। পুরো Trade & Export (১৪টা টেস্ট, ৩৩৬টা assertion) আর MenuAccess + RoleMatrix (১৩টা টেস্ট) পাস।
- **রসিদ:**
  - Final না হওয়া বিলে আটকায়।
  - ১০০০-এর বিলে ৬০০ নিলে `CMR-2026-000001`; তারপর ৪০১ আটকায় (বাকি ৪০০), ৪০০ চলে।
  - Source-এ মোট ১০০০, DFC ২৪, দুটো রসিদ।
  - বিলের তালিকায় পেমেন্টের তারিখ শেষ রসিদের তারিখ। Final রসিদ বদলানো বা মোছা আটকায়।
- **বিলের তারিখ:** বিলের আগে জমা আটকায়; জমার আগে পেমেন্ট আটকায়; ঠিক তারিখ সেভ হয়ে তালিকায় দেখায়।
- **Margin Closing:** Advance ছাড়া আটকায়; Dr ≠ Cr আটকায়; COMMISSION সহ সেভ হয়ে তালিকায় ব্যাংক আর পরিমাণসহ দেখায়।

**UI চেক (`app_check/plans/tx08`, `tx08b`; error 0; dev ডাটা রাখা আছে):**
- **রসিদ:** BE-2026-2201-এ CMR-2026-000001: ১০০০, ১০ নভেম্বর ২০২৬, DFC ১২।
- **বিলের তারিখ:** LC-0098812-এর BE-2026-2201: ব্যাংকে জমা ৯ নভেম্বর, পেমেন্ট ১২ নভেম্বর। Bill Adjustment আর CM রসিদের তালিকায় দেখায়।
- **Margin Closing:** MC-2026-1101: ২৫, Bank Charge, ABN AMRO; বণ্টন DR ২৫ / CR ২৫।

## ১০. খোলা প্রশ্ন

১. **Retired স্ক্রিন:** Brickland-এ Bill Adjustment আর Margin Closing-এর নামে "(Retired)" / "not-used" লেখা। নিয়ম অনুযায়ী দুটোই পুরো বানানো হয়েছে। এগুলো মেনুতে রাখবেন, না লুকাবেন?

২. **Margin Closing-এর ফর্ম:** Brickland-এর demo-তে শুধু তালিকা আর ফিল্টার পাওয়া গেছে। আমরা এটা Advance (IP) বিল হিসেবে বানিয়েছি, সাথে Accounting Type আর Dr/Cr বণ্টন। Brickland-এর আসল ফর্ম অন্য রকম হলে জানাবেন।

৩. **DFC Margin:** রসিদে DFC Margin লেখা আর মোট দেখানো হয়। এটা কি পরিমাণ থেকে কেটে আলাদা DFC হিসাবে যাবে? এখন শুধু রেকর্ড থাকে।

৪. **রসিদ আর Realisation:** 22/02-এর Export Bill Realisation আর এই CM রসিদ দুটোই বিলের টাকা আসা ধরে। দুটো কি একসাথে বিলের পরিমাণের ভেতরে থাকতে হবে? এখন রসিদের সীমা আলাদা, শুধু রসিদ দিয়ে হিসাব হয়।

৫. **হিসাব পোস্টিং:** রসিদ বা Margin Closing Final হলে Accounts-এ voucher যাবে কি? Accounts মডিউল এলে যোগ করা যাবে।

## ১১. As-built নোট

- **Brickland প্রোব:** তিনটা স্ক্রিনেরই demo-তে শুধু তালিকা আর ফিল্টার; রেকর্ড নেই। ফর্মের ঘর এসেছে কলাম আর 22/02-এর Bill of Exchange থেকে।
- **Bill Adjustment:** নিজের টেবিল নেই; Bill of Exchange-এর ওপর আরেকটা ভিউ, সাথে তারিখ বসানোর প্যানেল।
- **UI চেকে ধরা পড়া:** Final বিলে তারিখের ঘর নিষ্ক্রিয় ছিল, অথচ ব্যাংকের তারিখ আসে বিল Final হওয়ার পরেই। এখন Final বিলেও তারিখ বসানো যায় (backend আগে থেকেই নিত)।
