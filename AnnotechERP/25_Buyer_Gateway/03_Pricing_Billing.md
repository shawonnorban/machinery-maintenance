# Pricing & Billing

> **স্ট্যাটাস:** ✅ সম্পন্ন (৪টার ৪টা স্ক্রিন)
> **মডিউল:** [Buyer Gateway](00_Index.md) · **ফেজ ৮:** পোর্টাল, AI ও অ্যানালিটিক্স · **কী:** `25/03`

**⬅ ফিরে যান:** [Buyer Gateway](00_Index.md) | [AnnotechERP Index](../00_Index.md)

---

## ১. স্ক্রিন (Brickland ERP থেকে)

<!-- AUTO:screens:start -->
| # | স্ক্রিন | পাথ | ট্যাব | ফিল্ড | ডায়ালগ |
|---|---|---|---|---|---|
| 1 | Quotation Request Inbox | `/m/buyer-portal/rfq-inbox` | 1 | 3 | 0 |
| 2 | Buyer Price Schedules | `/m/buyer-portal/buyer-price-lists` | 1 | 3 | 1 |
| 3 | Invoice Lookup | `/m/buyer-portal/invoice-view` | 1 | 3 | 0 |
| 4 | Payment Progress View | `/m/buyer-portal/payment-status` | 1 | 3 | 1 |
<!-- AUTO:screens:end -->

## ২. প্রতিটা স্ক্রিনের লাইভ বিবরণ (ফিল্ড, ট্যাব, গ্রিড, ডায়ালগ, ভ্যালিডেশন)

<!-- AUTO:detail:start -->
#### Quotation Request Inbox

`/m/buyer-portal/rfq-inbox`

**ভিউ**
> 11 of 20 Demo data

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন/প্লেসহোল্ডার |
|---|---|---|---|
| Search Quotation request inbox | search |  | Search requests… |
| Filter by state | select |  | All states, New, Under costing, Quoted, Declined, Converted |
| Items per page | select |  | 10, 25, 50, 100 |
- গ্রিড কলাম: Reference, Buyer, Quantity, Target, Respond by, State
- সেকশন: Quotation requests
- বাটন/অ্যাকশন: Log an RFQ, Reference, Buyer, Quantity, Target, Respond by, State

#### Buyer Price Schedules

`/m/buyer-portal/buyer-price-lists`

**ভিউ**
> 12 of 20 Demo data

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন/প্লেসহোল্ডার |
|---|---|---|---|
| Search Buyer price schedules | search |  | Search prices… |
| Filter by season | select |  | All seasons, Winter 26, Summer 26, Winter 27 |
| Items per page | select |  | 10, 25, 50, 100 |
- গ্রিড কলাম: Product, Season, Price, Incoterm, Min qty, Valid to
- সেকশন: Price schedules
- বাটন/অ্যাকশন: New price line, Product, Season, Price, Incoterm, Min qty, Valid to

**ডায়ালগ: New price line** (মডাল)

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন |
|---|---|---|---|
| Product* | text | ✔ |  |
| Buyer | select |  | Select…, H&M, Primark, Zara, Target, Uniqlo, Kmart, C&A, Landmark |
| Season | select |  | Select…, Winter 26, Summer 26, Winter 27 |
| Price | number |  |  |
| Incoterm | select |  | Select…, FOB, CIF, DDP, EXW |
| Minimum quantity | number |  |  |
| Valid from | date |  |  |
| Valid to | date |  |  |
- খালি Save-এ মেসেজ: Name the product.
- invalid চিহ্নিত ফিল্ড: 1টা

#### Invoice Lookup

`/m/buyer-portal/invoice-view`

**ভিউ**
> 13 of 20 Demo data

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন/প্লেসহোল্ডার |
|---|---|---|---|
| Search Invoices published to the buyer | search |  | Search invoices… |
| Filter by state | select |  | All states, Published, Paid, Overdue, Unpublished |
| Items per page | select |  | 10, 25, 50, 100 |
- গ্রিড কলাম: Invoice, Buyer, Amount, Issued, Due, State
- সেকশন: Invoice lookup
- বাটন/অ্যাকশন: Publish invoice, Invoice, Buyer, Amount, Issued, Due, State

#### Payment Progress View

`/m/buyer-portal/payment-status`

**ভিউ**
> 14 of 20 Demo data

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন/প্লেসহোল্ডার |
|---|---|---|---|
| Search Payment progress by buyer | search |  | Search buyers… |
| Filter by ageing | select |  | All ageing bands, Not due, 1–30 days, 31–60 days, 61–90 days, Over 90 days |
| Items per page | select |  | 10, 25, 50, 100 |
- গ্রিড কলাম: Buyer, Billed, Received, Outstanding, Next due, Ageing
- সেকশন: Payment progress
- বাটন/অ্যাকশন: Add payment line, Buyer, Billed, Received, Outstanding, Next due, Ageing

**ডায়ালগ: Add payment line** (মডাল)

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন |
|---|---|---|---|
| Buyer | select |  | Select…, H&M, Primark, Zara, Target, Uniqlo, Kmart, C&A, Landmark |
| Billed | number |  |  |
| Received | number |  |  |
| Outstanding | number |  |  |
| Ageing band | select |  | Select…, Not due, 1–30 days, 31–60 days, 61–90 days, Over 90 days |
| Payment terms | select |  | Select…, L/C at sight, L/C 60 days, TT 30 days, TT 60 days, Open account 90 days |
| Next due | date |  |  |
- খালি Save-এ মেসেজ: H&M added.
<!-- AUTO:detail:end -->

## ৩. বিদ্যমান কোড যা কাজে লাগতে পারে

- **25/01–02-এর কাঠামো:** `BuyerGatewayResource`, `PortalBook`, `portalScreen()`, টাইল।
- **Masters:** buyer master; পণ্য — Work Study-র garment item (`ws_garment_items`)।

## ৪. ডাটা মডেল (Entity ও Field)

মাইগ্রেশন `2027_03_22_000100_create_buyer_gateway_billing`:
- **`bg_rfqs`:**
  - রেফারেন্স (অনন্য, বড় হাতে), buyer, পণ্য (garment item), পরিমাণ, লক্ষ্য দাম, পাওয়া ও উত্তরের শেষ তারিখ।
  - অবস্থা: New / Under costing / Quoted / Declined / Converted।
- **`bg_price_lines`:** পণ্য (লেখা), buyer, সিজন ("Winter 26"), দাম, ইনকোটার্ম (FOB / CIF / DDP / EXW), ন্যূনতম পরিমাণ, মেয়াদ শুরু ও শেষ।
- **`bg_invoices`:**
  - ইনভয়েস নম্বর (অনন্য), buyer, অর্ডার, পরিমাণ (USD), ইস্যু ও পরিশোধের তারিখ।
  - অবস্থা: Published / Paid / Overdue / Unpublished।
- **`bg_payment_lines`:**
  - buyer — প্রতি buyer-এ একটা লাইন।
  - বিল আর প্রাপ্ত (USD), পেমেন্টের শর্ত (L/C at sight, L/C 60, TT 30, TT 60, Open account 90), পরের তারিখ।
  - Outstanding আর ageing রাখা হয় না, প্রতিবার হিসাব হয়।

## ৫. Workflow / Status ও Business Rules

- **RFQ:**
  - buyer বাধ্যতামূলক। রেফারেন্স খালি রাখলে বছরের পরের নম্বর (RFQ-2026-001 …); ডায়ালগেও সেটাই আগে থেকে বসে থাকে।
  - একই রেফারেন্স আটকায়। পাওয়ার তারিখ না দিলে আজ। পাওয়ার আগে উত্তরের তারিখ আটকায়।
- **Price lines:** পণ্য আর buyer বাধ্যতামূলক। সিজনের তালিকা: গত বছর থেকে পরের দুই বছরের Summer / Winter। শেষের তারিখ শুরুর আগে হতে পারে না।
- **Invoices:**
  - নম্বর আর buyer বাধ্যতামূলক; একই নম্বর আটকায়।
  - ইস্যু না দিলে আজ। ইস্যুর আগে পরিশোধের তারিখ আটকায়।
  - Published অবস্থায় তারিখ পেরোলে Overdue দেখায়।
- **Payment lines:**
  - একই buyer-এর দ্বিতীয় লাইন আটকায় ("edit it instead")।
  - Outstanding = বিল − প্রাপ্ত। প্রাপ্তি বিলের চেয়ে বেশি হতে পারে না, বিলও প্রাপ্তির নিচে নামানো যায় না।
  - Ageing পরের তারিখ থেকে: সময় হয়নি / ১–৩০ / ৩১–৬০ / ৬১–৯০ / ৯০-এর বেশি দিন। কিছু বাকি না থাকলে সবসময় "Not due"।
- **টাইল:**
  - RFQ: Requests / New / Converted / Pieces requested।
  - Price lines: Price lines / Buyers / Seasons / Average price।
  - Invoices: Invoices / Value published (Unpublished বাদে) / Overdue / Paid।
  - Payments: Billed / Received / Outstanding / Over 90 days।
  - টাকা Brickland-এর মতো সংক্ষেপে: $1.84M, $964K।

## ৬. API ও পারমিশন

পারমিশন: `buyergateway.billing.view`, `buyergateway.billing.manage` (Factory Admin / Factory Manager; Auditor দেখা)।

API: `/api/v1/erp/bg-rfqs`, `bg-price-lines`, `bg-invoices`, `bg-payment-lines`; `GET /api/v1/buyer-gateway/options`-এ এখন পণ্য আর সিজনও আছে।

## ৭. Frontend পেজ (লাইট থিম)

একই `buyer-gateway/[screen]` route; সাইডবারে Pricing & Billing ফোল্ডার।
- **Quotation Request Inbox** (`/buyer-gateway/rfq-inbox`): রেফারেন্স, buyer (নিচে পণ্য), পরিমাণ, লক্ষ্য, উত্তরের তারিখ, অবস্থা; অবস্থা ফিল্টার।
- **Buyer Price Schedules** (`/buyer-gateway/buyer-price-lists`): পণ্য (নিচে buyer), সিজন, দাম, ইনকোটার্ম, ন্যূনতম পরিমাণ, মেয়াদ শেষ; সিজন ফিল্টার।
- **Invoice Lookup** (`/buyer-gateway/invoice-view`): ইনভয়েস, buyer (নিচে অর্ডার), পরিমাণ ($1.84M), ইস্যু, পরিশোধ, অবস্থা।
- **Payment Progress View** (`/buyer-gateway/payment-status`): buyer (নিচে শর্ত), বিল, প্রাপ্ত, বাকি, পরের তারিখ, ageing; ageing ফিল্টার।
- **Grid-এ যোগ হলো:** number কলামে `currency` (সংক্ষিপ্ত টাকা)।

## ৮. Implementation Files

<!-- AUTO:impl:start -->
As-built (৪টার ৪টা স্ক্রিন):

**Backend (`app/Modules/BuyerGateway`)**
- `Database/Migrations/2027_03_22_000100_create_buyer_gateway_billing.php`
- `Models/Rfq.php`, `PriceLine.php`, `PortalInvoice.php`, `PaymentLine.php`
- `Resources/RfqResource.php`, `PriceLineResource.php`, `PortalInvoiceResource.php`, `PaymentLineResource.php`
- `Support/PortalBook.php`, `Http/Controllers/Api/BuyerGatewayApiController.php` (বদলানো)
- `app/Modules/Erp/Support/ResourceRegistry.php`, `PermissionSeeder.php`, `RoleSeeder.php`, `MenuRegistry.php` (বদলানো)
- `lang/en/buyergateway.php`, `lang/bn/buyergateway.php` (বদলানো)

**Frontend**
- `app/(app)/buyer-gateway/[screen]/screens.js`, `components/buyergateway/tiles.jsx`, `components/erp/records-grid.jsx` (বদলানো)

**Test**
- `tests/Feature/BuyerGateway/BuyerGatewayBillingTest.php` (নতুন)
<!-- AUTO:impl:end -->

### পরিকল্পিত নতুন/বদলানো ফাইল

উপরের as-built তালিকাই চূড়ান্ত।

## ৯. টেস্ট ও যাচাই

**Feature test (`BuyerGatewayBillingTest.php`, ৩টা টেস্ট):** Buyer Gateway-এর ৯টা টেস্ট / ২২৩টা assertion আর MenuAccess + ERP master seeder ৭ / ৪৯ test2 DB-তে পাস।
- **RFQ ও দাম:**
  - picker-এ পণ্য আর সিজন; buyer ছাড়া আটকায়।
  - খালি রেফারেন্সে RFQ-2026-001; rfq-2026-118 → বড় হাতে; পরেরটা 119; একই রেফারেন্স আটকায়; পাওয়ার আগের উত্তরের তারিখ আটকায়; Converted।
  - দাম: পণ্য ছাড়া আটকায়; নাম ছেঁটে সেভ; অজানা সিজন আর ইনকোটার্ম আটকায়; শুরুর আগে শেষ আটকায়।
- **Invoice ও payment:**
  - Invoice: নম্বর ছাড়া আটকায়; বড় হাতে, ইস্যু আজ; একই নম্বর আটকায়; ইস্যুর আগে পরিশোধের তারিখ আটকায়; পুরোনো তারিখ → Overdue → Paid।
  - Payment: বিলের বেশি প্রাপ্তি আটকায়; বাকি ২.২M Not due; দ্বিতীয় লাইন আটকায়।
  - Ageing: ১০ দিন আগে D30, ৫২ দিন D60, ৮৩ দিন D90, ১৩৩ দিন Over 90; পুরো পরিশোধে Not due।
  - বিল প্রাপ্তির নিচে নামানো আটকায়।
- **কে পারেন:** Auditor দেখেন, যোগ করতে পারেন না। Manager পারেন। Storekeeper দেখেন না। অন্য কোম্পানি কিছু দেখে না, এই buyer নিতে পারে না।

**UI চেক (`app_check/plans/bg03`, `bg03i`, ২০২৬-১১-১১, owner; error 0; dev ডাটা রাখা আছে):**
- RFQ: RFQ-2026-001 (H&M, Basic T-Shirt, ৪২০,০০০, লক্ষ্য ২, Under costing) আর RFQ-2026-002 (PRIMARK, Pullover Hoodie, New) — নম্বর নিজে বসে ✔
- Price: Basic crew tee, 160 gsm (H&M, Winter 26, ২.১৫ FOB, ৫০,০০০) ✔
- Invoice: INV-20261184 (H&M, SO-4402, $1.84M, Published) আর INV-20261108 (KIABI, $486K, ২৩ আগস্ট পেরোনো → Overdue) ✔
- Payment: H&M ($18.4M / $16.2M / $2.2M, Not due) আর KIABI (Open account 90, ১ আগস্ট → Over 90 days) ✔

## ১০. খোলা প্রশ্ন

১. **RFQ থেকে ইনকোয়ারি:** "Converted" এখন শুধু অবস্থা বদলায়। Converted হলে Merchandise-এ নিজে থেকে ইনকোয়ারি তৈরি হবে কিনা জানাবেন।

২. **ইনভয়েস আর পেমেন্ট কোথা থেকে:** Brickland-এর মতো এখানে হাতে লেখা হয়। আমাদের Sales & Shipment / Trade & Export-এ কমার্শিয়াল ইনভয়েস আর Finance-এ প্রাপ্তি আছে। সেখান থেকে নিজে থেকে আনব (আর বায়ারকে প্রকাশের আগে যাচাই রাখব) কিনা জানাবেন।

৩. **মুদ্রা:** Brickland-এর মতো সব USD-তে। অন্য মুদ্রায় বিল হলে মুদ্রার ঘর লাগবে কিনা জানাবেন।

## ১১. As-built নোট

- **লাইভ প্রোব:** `tools/erp_crawler/out/bg03`। Brickland-এর ক্রম 11–14 of 20।
- **Value published:** Brickland সব ইনভয়েসের যোগফল দেখায় (Unpublished-সহ)। এখানে নামের সাথে মিলিয়ে Unpublished বাদ দেওয়া হয়েছে।
- **Test-এ ধরা পড়া:** শুধু বিল কমালে প্রাপ্তির নিচে নামছিল; এখন আটকায়।
- **UI চেকে:** Publish বোতামের selector-টা "Published" দেখানো State ঘরকেও ধরছিল (প্ল্যানের ভুল)। submit selector দিয়ে আবার চালানো হয়েছে।
