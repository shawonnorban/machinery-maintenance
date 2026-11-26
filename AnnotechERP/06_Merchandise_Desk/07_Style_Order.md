# Style & Order

> **স্ট্যাটাস:** ✅ সম্পন্ন (মেনুর ৬টা স্ক্রিন আর স্টেপার থেকে খোলা ৩টা লুকানো স্ক্রিন — Development Style Catalogue, Style Registration Entry, Style Catalogue; উৎপাদন, ল্যাব ডিপ, স্যাম্পল, বুকিং আর বাজেটের কলাম সেই মডিউল তৈরি হলে ভরবে)
> **মডিউল:** [Merchandise Desk](00_Index.md) · **ফেজ ২:** বিক্রয় ও মার্চেন্ডাইজিং · **কী:** `06/07`

**⬅ ফিরে যান:** [Merchandise Desk](00_Index.md) | [AnnotechERP Index](../00_Index.md)

---

## ১. স্ক্রিন (Brickland ERP থেকে)

<!-- AUTO:screens:start -->
| # | স্ক্রিন | পাথ | ট্যাব | ফিল্ড | ডায়ালগ |
|---|---|---|---|---|---|
| 1 | Style & Order Dashboard | `/m/merchandising/style-order-dashboard` | 1 | 1 | 0 |
| 2 | Order Confirmation | `/m/merchandising/style-order-confirmation` | 1 | 8 | 0 |
| 3 | Order Register | `/m/merchandising/order-list` | 3 | 25 | 1 |
| 4 | Style Progress Board | `/m/merchandising/style-dashboard` | 1 | 6 | 0 |
| 5 | Buyer-Wise Style & Order Follow-up | `/m/merchandising/pms-buyer-wise-style-and-order-followup-list` | 1 | 8 | 0 |
| 6 | Projection & Call-Off Order | `/m/merchandising/projection-call-off-order` | 1 | 0 | 0 |
<!-- AUTO:screens:end -->

## ২. প্রতিটা স্ক্রিনের লাইভ বিবরণ (ফিল্ড, ট্যাব, গ্রিড, ডায়ালগ, ভ্যালিডেশন)

<!-- AUTO:detail:start -->
#### Style & Order Dashboard

`/m/merchandising/style-order-dashboard`

**ভিউ**

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন/প্লেসহোল্ডার |
|---|---|---|---|
| Items per page | select |  | 10, 25, 50, 100 |
- গ্রিড কলাম: Style No, Buyer, Garment Type, Season, Status, Created Date, ACTION
- গ্রিড কলাম: Order No, Buyer, Style No, Garment Type, Qty, Shipment, Status
- সেকশন: Recent Styles, Recent Orders
- বাটন/অ্যাকশন: Style No, Buyer, Garment Type, Season, Status, Created Date, Order No, Qty, Shipment

#### Order Confirmation

`/m/merchandising/style-order-confirmation`

**ভিউ**

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন/প্লেসহোল্ডার |
|---|---|---|---|
| Buyer* | select | ✔ | LPP |
| PO / Order Ref.* | text | ✔ | PO-2026-145 |
| Order Date* | date | ✔ |  |
| Shipment Date* | date | ✔ |  |
| Currency | select |  | Select…, USD, EUR, GBP, CAD, BDT |
| Search Approved styles of the buyer | search |  | Search style… |
| Items per page | select |  | 10, 25, 50, 100 |
| Search Confirmed orders | search |  | Search order / buyer / style… |
- গ্রিড কলাম: SELECT, Style No, Garment Type, STYLE IMAGE, STATUS, QTY (PCS), UNIT PRICE (USD)
- গ্রিড কলাম: Order No, Buyer, Style No, Garment Type, Qty, Shipment, Status, ACTION
- সেকশন: New Order / Quick Confirmation, Select Style(s) — approved styles of LPP, Auto Fill Information, Confirmed Orders
- বাটন/অ্যাকশন: Style No, Garment Type, View Details, Confirm Order, Order No, Buyer, Qty, Shipment, Status

#### Order Register

`/m/merchandising/order-list`

**ট্যাব: Records 8**

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন/প্লেসহোল্ডার |
|---|---|---|---|
| Buyer A/C | select |  | All, LPP |
| Style# | select |  | All, 1111, 234234, 529, 531, 867MI, 867MI-05X, 927 |
| Job/Work Style# | select |  | All, 1111, 234234, 529, 531, 867MI, 867MI-05X, 927 |
| Search Confirmed orders with their date, work style, ship date, quantity, price  | search |  | Search 8 records… |
| Items per page | select |  | 10, 25, 50, 100 |
- গ্রিড কলাম: Order #, Buyer, Style #, Garment Type, Order Date, Work Style #, Ship. Date, Total Qty, Unit, Total Price, Currency, Status, ACTION
- বাটন/অ্যাকশন: Size Breakdown, New order, Clear, Export, Order #, Buyer, Style #, Garment Type, Order Date, Work Style #, Ship. Date, Total Qty, Unit, Total Price, Currency, Status, Edit

**ট্যাব: Size Breakdown**

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন/প্লেসহোল্ডার |
|---|---|---|---|
| Search orders | search |  | Search… |
- সেকশন: Orders, Size Breakdown
- বাটন/অ্যাকশন: Size Breakdown, New order, LPP-2026-000008 529 · 1562 pcs 5, LPP-2026-000007 1111 · 2584 pcs 4, LPP-2026-000006 529 · 750 pcs 5, LPP-2026-000005 234234 · 621 pcs 4, LPP-2026-000004 867MI · 6592 pcs 5, LPP-2026-000003 531 · 4200 pcs 6, LPP-2026-000002 927 · 23700 pcs 6, LPP-2026-000001 867MI-05X · 7624 pcs 5

**ট্যাব: New order**

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন/প্লেসহোল্ডার |
|---|---|---|---|
| Order # | text |  | Issued on save |
| Job #* | text | ✔ | 0036-R26-03 |
| Style | select |  | Select…, 867MI-05X — 867MI-05X (not active), 927 — Ladies Trousers (not active), 531 — Ladies Leggings (not active), 867 |
| Style No* | text | ✔ |  |
| Work Style # | text |  |  |
| Buyer* | select | ✔ | Select…, LPP (LPP) |
| Buyer A/C | select |  | Select… |
| Company | select |  | Select…, Brickland Composite Ltd. |
| Order Type | select |  | Select…, Bulk New, Development, Repeat, Projection, Buffer, Special Sample |
| Item | text |  |  |
| GMT Type | text |  |  |
| Order Date | date |  |  |
| Order Conf. Date | date |  |  |
| Ship Date | date |  |  |
| Total Qty* | number | ✔ |  |
| Unit | select |  | Select…, Pcs, Set, Dzn |
| Unit Price | number |  |  |
| Currency | text |  |  |
| Status | select |  | Select…, Draft, Confirmed, In Production, Shipped, Closed, Cancelled |
- সেকশন: New order
- বাটন/অ্যাকশন: Size Breakdown, New order, Select…, Reset, Save order

**ডায়ালগ: New order**

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন |
|---|---|---|---|
| Order # | text |  |  |
| Job #* | text | ✔ |  |
| Style | select |  | Select…, 867MI-05X — 867MI-05X (not active), 927 — Ladies Trousers (not active), 531 — Ladies Leggings (not active), 867 |
| Style No* | text | ✔ |  |
| Work Style # | text |  |  |
| Buyer* | select | ✔ | Select…, LPP (LPP) |
| Buyer A/C | select |  | Select… |
| Company | select |  | Select…, Brickland Composite Ltd. |
| Order Type | select |  | Select…, Bulk New, Development, Repeat, Projection, Buffer, Special Sample |
| Item | text |  |  |
| GMT Type | text |  |  |
| Order Date | date |  |  |
| Order Conf. Date | date |  |  |
| Ship Date | date |  |  |
| Total Qty* | number | ✔ |  |
| Unit | select |  | Select…, Pcs, Set, Dzn |
| Unit Price | number |  |  |
| Currency | text |  |  |
| Status | select |  | Select…, Draft, Confirmed, In Production, Shipped, Closed, Cancelled |
- খালি Save-এ মেসেজ: Total Qty is required.

#### Style Progress Board

`/m/merchandising/style-dashboard`

**ভিউ**

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন/প্লেসহোল্ডার |
|---|---|---|---|
| Buyer A/C | select |  | All, LPP |
| Style | search |  | 0376 |
| Season | select |  | All, Autumn/Winter 2026, Spring/Summer 2026, Spring/Summer 2027 |
| Nature Of Work | select |  | All |
| Search One row per style with its buyer, season, nature of work and how far its  | search |  | Search 18 records… |
| Items per page | select |  | 10, 25, 50, 100 |
- গ্রিড কলাম: Style#, Buyer, Season, Nature Of Work, Orders, Order Qty, Lab Dips, Samples, Bookings, Budgets
- বাটন/অ্যাকশন: Clear, Export, Style#, Buyer, Season, Nature Of Work, Orders, Order Qty, Lab Dips, Samples, Bookings, Budgets

#### Buyer-Wise Style & Order Follow-up

`/m/merchandising/pms-buyer-wise-style-and-order-followup-list`

**ভিউ**

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন/প্লেসহোল্ডার |
|---|---|---|---|
| Buyer | select |  | All, LPP |
| Style / Order | search |  |  |
| Shipment from | date |  |  |
| Shipment to | date |  |  |
| Verified? | select |  | All, Verified, Not verified |
| Order | select |  | All, Open, Closed |
| Search Production progress per order: cutting, input, sewing, finishing and ship | search |  | Search 8 records… |
| Items per page | select |  | 10, 25, 50, 100 |
- গ্রিড কলাম: Buyer, Order/Style, Style Desc., Order Qty, Order Value, Ship, Cutting Qty, Ready to Input, Inputed, Sewing, Sew Undone, Iron, Poly, GMT Fin, Shipment, Verified?, Status
- বাটন/অ্যাকশন: Clear, Export, Buyer, Order/Style, Style Desc., Order Qty, Order Value, Ship, Cutting Qty, Ready to Input, Inputed, Sewing, Sew Undone, Iron, Poly, GMT Fin, Shipment, Verified?, Status

#### Projection & Call-Off Order

`/m/merchandising/projection-call-off-order`

**ভিউ**
<!-- AUTO:detail:end -->

## ৩. বিদ্যমান কোড যা কাজে লাগতে পারে

- স্ক্রিন কিট: `ResourceDefinition` (`/erp/merch-orders`) আর Merchandise-এর `masterScreen`; New/Edit-এর জন্য `EntryForm` (`optionsFrom` দিয়ে বায়ার অনুযায়ী Buyer A/C)।
- `PickPanel`: Size Breakdown-এ বাঁয়ে অর্ডারের তালিকা, ডানে বিস্তারিত।
- Style Development Sheet-এর স্টাইল (`merch_styles`), তার রং (`merch_style_colours`) আর সাইজ রেঞ্জ (`merch_style_size_ranges`)।
- মাস্টার: বায়ার, বায়ার অফিস (Buyer A/C), Factory (Company), Origin Country (Ship Country)।

## ৪. ডাটা মডেল (Entity ও Field)

- **`merch_orders`:**
  - order_no (কোম্পানিতে ইউনিক), job_no, style_id (ঐচ্ছিক) আর style_no, work_style_no।
  - buyer_id, buyer_office_id, factory_id।
  - order_type (BULK_NEW, DEVELOPMENT, REPEAT, PROJECTION, BUFFER, SPECIAL_SAMPLE), item, gmt_type।
  - order_date, conf_date, ship_date, ship_country_id।
  - total_qty, unit (PCS, SET, DZN), unit_price, currency (৩ অক্ষর)।
  - status (DRAFT, CONFIRMED, IN_PRODUCTION, SHIPPED, CLOSED, CANCELLED), created_by।
- C2-তে `merch_orders`-এ যোগ হয়েছে: po_no, sub_style, inquiry_id, sales_contract_no, projection_only, is_multi, pcs_per_unit (Set/Dzn-এ কত পিস), exchange_rate (USD to Local), tna_template_id।
- C4-এ `merch_orders`-এ যোগ হয়েছে `projection_id`: call-off অর্ডার কোন প্রজেকশনের বিপরীতে। call-off থাকা প্রজেকশন মোছা যায় না।
- **`merch_order_sizes`:** order_id (অর্ডার মুছলে সাথে মোছে), colour, size, qty, sort_order। একটা অর্ডারে একই রং-সাইজ একবারই থাকে।

## ৫. Workflow / Status ও Business Rules

- অর্ডার নম্বর ফাঁকা রাখলে বায়ারের পরের নম্বর পড়ে: `<বায়ার কোড>-<বছর>-<000001>`। হাতে লেখা নম্বরও চলে, তবে কোম্পানিতে ইউনিক হতে হবে। নম্বর দেওয়া হয় advisory lock-এর ভেতরে, যাতে দুজনে একই নম্বর না পায়।
- স্টাইল বাছাই করে Style No, Buyer বা Buyer A/C ফাঁকা রাখলে সেগুলো স্টাইল থেকে বসে। অন্য বায়ারের স্টাইল বাছাই করলে সেভ হয় না।
- Ship Date অর্ডারের তারিখের আগে হতে পারে না।
- Total Price = Total Qty × Unit Price; Lead Time = Ship Date − Conf. Date (Conf. Date না থাকলে Order Date)। দুটোই হিসাব হয়, লেখা হয় না।
- **Order Confirmation (দ্রুত):**
  - বাছাই করা প্রতিটা অনুমোদিত স্টাইলের (STYLE_APPROVED) জন্য একটা করে অর্ডার হয়, নম্বর আসে বায়ারের সিরিজ থেকে।
  - PO / Order Ref. অর্ডারের PO আর Job # দুটোতেই বসে। Order Date আর Conf. Date একই হয়।
  - একক আর প্রতি এককে পিস স্টাইল থেকে আসে, আইটেমের নাম স্টাইলের প্রথম আইটেম থেকে।
  - স্টাইলের আগে কোনো অর্ডার থাকলে ধরন REPEAT, না থাকলে BULK_NEW।
  - বায়ার, PO, দুই তারিখ, অন্তত একটা স্টাইল আর প্রতিটা স্টাইলের পরিমাণ বাধ্যতামূলক। অন্য বায়ারের বা অনুমোদন না পাওয়া স্টাইল আটকায়।
- **স্টাইল শিটের Order Confirmation ট্যাব:**
  - স্টাইলের অর্ডারের পুরো তথ্য নিয়ে নতুন অর্ডার তৈরি বা আগেরটা এডিট করা যায়।
  - বাধ্যতামূলক: Order Type, Job/Working Style, Confirmed Date, Shipment Date (Confirmed Date-এর আগে নয়), পরিমাণ, একক, দাম, মুদ্রা, স্থানীয় মুদ্রায় রেট, অবস্থা আর Delivery To।
  - এডিটে অর্ডার নম্বর বদলায় না, আর সাইজ ব্রেকডাউন থাকলে পরিমাণ তার যোগফলই থাকে।
- **Projection & Call-Off:**
  - প্রজেকশন মানে ধরন PROJECTION এমন অর্ডার। call-off হলো তার বিপরীতে নেওয়া পাকা অর্ডার: ধরন BULK_NEW, অবস্থা CONFIRMED, বায়ারের সিরিজে নিজের নম্বর। স্টাইল, বায়ার, একক, দাম, মুদ্রা ইত্যাদি প্রজেকশন থেকে আসে।
  - বাতিল ছাড়া call-off-এর মোট পরিমাণ কখনো প্রজেকশন ছাড়াতে পারে না। নতুন call-off নেওয়া, call-off এডিট, তার সাইজ ব্রেকডাউন, সব জায়গায় এটা যাচাই হয়। নতুন call-off নেওয়ার সময় প্রজেকশনের সারি লক থাকে।
  - call-off থাকলে প্রজেকশনের পরিমাণ call-off-এর মোটের নিচে নামানো যায় না, আর তার ধরনও বদলানো যায় না। কোনো call-off বাতিল হলে তার পরিমাণ আবার ব্যালান্সে ফিরে আসে।
- **Follow-up:** কোন অর্ডার খোলা বা বন্ধ তা অবস্থা দেখে ঠিক হয়: SHIPPED, CLOSED আর CANCELLED বন্ধ, বাকিগুলো খোলা।
- Size Breakdown-এ লাইন থাকলে অর্ডারের Total Qty লাইনগুলোর যোগফল। প্রতিবার ব্রেকডাউন সেভ হলে, আর অর্ডার এডিট করলেও, আবার হিসাব হয়। সব লাইন মুছে দিলে শেষ পরিমাণটা থেকে যায়।

## ৬. API ও পারমিশন

- `GET/POST /erp/merch-orders`, `GET/PATCH/DELETE /erp/merch-orders/{id}`: স্ক্রিন কিট।
- `GET /merchandise/orders/lookups`: বায়ার, বায়ার অফিস, factory, দেশ আর স্টাইল।
- `GET/PUT /merchandise/orders/{order}/sizes`: সাইজ ব্রেকডাউন। GET-এর সাথে স্টাইলের রং আর সাইজ আসে, লাইন লেখার সময় সাজেশন হিসেবে দেখানোর জন্য।
- `GET /merchandise/order-confirmation/styles?buyer=`: বায়ারের অনুমোদিত স্টাইল, প্রতিটার সাথে অর্ডার কী পাবে তার গণনা (আইটেম, ফ্যাব্রিক, রং, সার্ভিস, BOM লাইন, ফ্যাব্রিক ম্যাপিং, প্যাকিং, সংযুক্তি) আর আগের অর্ডারের সংখ্যা।
- `POST /merchandise/order-confirmation`: দ্রুত কনফার্মেশন।
- `GET/POST /merchandise/styles/{style}/orders`, `PUT /merchandise/style-orders/{order}`: স্টাইল শিটের ট্যাব।
- `GET /merchandise/style-order/dashboard`: চারটা গণনা, সবচেয়ে নতুন ১০টা স্টাইল আর ১০টা অর্ডার।
- `GET /merchandise/style-order/progress`: প্রতি স্টাইলে এক সারি, বাতিল ছাড়া অর্ডারের সংখ্যা আর মোট পরিমাণসহ।
- `GET /merchandise/style-order/follow-up`: প্রতি অর্ডারে এক সারি, শিপের তারিখ অনুযায়ী সাজানো।
- `GET /merchandise/projections`, `GET/POST /merchandise/projections/{order}/call-offs`: প্রজেকশন আর তার call-off।
- পারমিশন: `merchandise.order.view` আর `merchandise.order.manage`। FACTORY_ADMIN দুটোই পায়, FACTORY_MANAGER শুধু view।
- মেনু: ফোল্ডার `MERCHANDISE.STYLE_ORDER` ("STYLE & ORDER"), তার ভেতরে Brickland-এর ক্রমে: `MERCHANDISE.STYLE_ORDER_DASHBOARD` → `/merchandise-desk/style-order-dashboard`, `MERCHANDISE.ORDER_CONFIRMATION` → `/merchandise-desk/order-confirmation`, `MERCHANDISE.ORDER_REGISTER` → `/merchandise-desk/order-register`, `MERCHANDISE.STYLE_PROGRESS` → `/merchandise-desk/style-dashboard`, `MERCHANDISE.STYLE_ORDER_FOLLOW_UP` → `/merchandise-desk/buyer-wise-follow-up`, `MERCHANDISE.PROJECTION_CALL_OFF` → `/merchandise-desk/projection-call-off-order`।

## ৭. Frontend পেজ (লাইট থিম)

- **Buyer-Wise Style & Order Follow-up** (`/merchandise-desk/buyer-wise-follow-up`):
  - ফিল্টার: Buyer, Shipment থেকে/পর্যন্ত (তারিখ; `UrlFilters`-এ এখন `type: "date"` চলে), Verified? আর Order (খোলা/বন্ধ)। গ্রিডের সার্চে অর্ডার বা স্টাইল খোঁজা যায়।
  - কলাম: Buyer, Order/Style, Style Desc., Order Qty, Order Value, Ship, Cutting Qty, Ready to Input, Inputed, Sewing, Sew Undone, Iron, Poly, GMT Fin, Shipment, Verified?, Status।
- **Projection & Call-Off Order** (`/merchandise-desk/projection-call-off-order`):
  - **Records:** প্রজেকশনের তালিকা: Order No, Buyer, Style, Projected Qty, Called Off, Balance, Call-offs, Ship Date, Status। খালি থাকলে Brickland-এর "No projection records yet"।
  - **Call-Offs** (`?tab=calloffs&projection=<অর্ডার নম্বর>`): বাঁয়ে প্রজেকশনের তালিকা। ডানে Projected / Called off / Balance, call-off-এর তালিকা, আর "নতুন call-off" ফর্ম (Job, PO, তারিখ, পরিমাণ, দাম, Delivery To)।
- **Style & Order স্টেপার** (`components/erp/flow-steps.jsx`): Dashboard, Order Confirmation আর Order Register-এর উপরে "Style & Order · Step n of 5", সাথে Previous/Next step আর পাঁচটা ধাপের লিংক। ধাপ ২ আর ৩ (Development Style Catalogue, Style Registration Entry) নিয়ে যায় Style Development Sheet-এর Records আর New tab-এ।
- **Style & Order Dashboard** (`/merchandise-desk/style-order-dashboard`):
  - চারটা গণনা: Total Styles, Approved Styles (অনুমোদিত বা চালু), Total Orders (বাতিল ছাড়া), In Production।
  - চারটা কুইক অ্যাকশন: New Style, Confirm Order, Style Catalogue, Order List।
  - Recent Styles (Open → স্টাইলের শিট) আর Recent Orders, খোঁজা আর এক্সপোর্টসহ।
- **Style Progress Board** (`/merchandise-desk/style-dashboard`): Buyer A/C, Season আর Nature Of Work ফিল্টার (URL-এ থাকে), গ্রিডের সার্চে স্টাইল খোঁজা যায়। কলাম: Style#, Buyer, Season, Nature Of Work, Orders, Order Qty, Lab Dips, Samples, Bookings, Budgets। সারির Open → স্টাইলের শিট।
- **Order Confirmation** (`/merchandise-desk/order-confirmation`, কোনো ট্যাব নেই):
  - New Order / Quick Confirmation: Buyer, PO / Order Ref., Order Date, Shipment Date, Currency। বায়ার বাছাই করলে তার অনুমোদিত স্টাইলের তালিকা আসে: বাছাই, Style No, Garment Type, ছবি, অবস্থা, Qty (স্টাইলের একক), Unit Price; খোঁজা যায়।
  - Auto Fill Information: বাছাই করা স্টাইলগুলোর কতটায় কী আছে (যেমন ১/২)। View Details ডায়ালগে প্রতি স্টাইলের হিসাব। Confirm Order।
  - Confirmed Orders: অর্ডারের তালিকা খোঁজাসহ, চোখের আইকনে অর্ডারের বিস্তারিত।
- **স্টাইল শিট → Item/Combo/Model → Order Confirmation** (BOM-এর পরে): বাঁয়ে Working Style (Order Type, Job/Working Style, Order List, Sales Contract No; সারিতে ক্লিক করলে এডিট)। ডানে New/Repeat/Development চাপলে Order Information ফর্ম, Lead Time আর Order Value নিজে হিসাব হয়। ট্যাবটা শুধু `merchandise.order.view` থাকলে দেখায়, আর সেভ করা যায় `.manage` থাকলে।
- **Order Register** (`/merchandise-desk/order-register`):
  - **Records:** Buyer A/C, Style# আর Job/Work Style# ফিল্টার। কলাম: Order #, Buyer, Style #, Garment Type, Order Date, Work Style #, Ship. Date, Total Qty, Unit, Total Price, Currency, Status। সাথে Edit আর Delete।
  - **Size Breakdown** (`?tab=sizes&order=LPP-2026-000001`): বাঁয়ে অর্ডারের তালিকা (খোঁজা যায়, লাইনের সংখ্যা দেখায়)। ডানে GMT Colour / Size / Qty / Remove; উপরে Export আর Add line (ডায়ালগ); নিচে লাইনের সংখ্যা আর মোট পিস, Revert আর Save।
  - **New order:** ৩ কলামের ফর্ম। **Edit order:** `?tab=edit&ref=<অর্ডার নম্বর>`। URL-এ কোনো আইডি যায় না।

## ৮. Implementation Files

<!-- AUTO:impl:start -->
| স্তর | ফাইল |
|---|---|
| Migration | `app/Modules/Merchandise/Database/Migrations/2026_11_14_000100_create_merchandise_orders.php` |
| Model | `app/Modules/Merchandise/Models/Order.php`, `Models/OrderSize.php` |
| Resource | `app/Modules/Merchandise/Resources/OrderResource.php` (`ResourceRegistry.php`-এ যোগ) |
| Controller | `app/Modules/Merchandise/Http/Controllers/Api/OrderApiController.php` |
| Route | `app/Modules/Merchandise/Routes/api.php` |
| পারমিশন / মেনু | `PermissionSeeder.php`, `RoleSeeder.php`, `MenuRegistry.php` |
| Frontend | `frontend/src/app/(app)/merchandise-desk/[screen]/order.js`, `page.js`, `actions.js`, `screens.js` (`filters` এখন `rows` পায়) |
| Component | `frontend/src/components/merchandise/order-sizes.jsx`, `order-confirmation.jsx`, `style-orders.jsx` (`style-desk.jsx`-এ ট্যাব) |
| C5 ব্যাকএন্ড | `Database/Migrations/2026_11_16_000200_add_development_approval_to_merchandise_styles.php`, `2026_11_16_000300_create_merchandise_style_registrations.php`, `Models/StyleApproval.php`, `StyleAttachment.php`, `StylePackingDetail.php`, `StyleRegistration.php`, `StyleRegistrationEvent.php`, `Http/Controllers/Api/DevStyleApiController.php`, `StyleRegistrationApiController.php`, `StyleOrderBoardApiController.php` (`gallery`), `StyleApiController.php` (DEV নম্বর), `app/Shared/Files/Http/Controllers/Api/FileApiController.php` (Merchandise ফাইলের পারমিশন) |
| C5 ফ্রন্টএন্ড | `components/merchandise/dev-style-catalogue.jsx`, `dev-style-wizard.jsx`, `style-registrations.jsx`, `style-catalogue-grid.jsx`, `style-form.jsx` (`onSaved`); `app/api/merchandise/style-attachments/[style]/route.js`; `order.js` (তিনটা স্ক্রিন, `STYLE_ORDER_FLOW`, ড্যাশবোর্ডের লিংক) |
| C5 টেস্ট | `tests/Feature/Merchandise/MerchandiseDevStyleTest.php` |
| C4 | `Database/Migrations/2026_11_14_000300_add_projection_to_merchandise_orders.php`, `Http/Controllers/Api/ProjectionApiController.php`, `StyleOrderBoardApiController.php` (`followUp`), `OrderResource.php` (`guardProjection`); `frontend/src/components/merchandise/projection-call-offs.jsx`, `components/erp/url-filters.jsx` (তারিখ) |
| C3 | `Http/Controllers/Api/StyleOrderBoardApiController.php`; `frontend/src/components/erp/flow-steps.jsx`, `order.js` (`STYLE_ORDER_FLOW`), `page.js` |
| C2 ব্যাকএন্ড | `Database/Migrations/2026_11_14_000200_add_confirmation_fields_to_merchandise_orders.php`, `Support/OrderNumber.php`, `Http/Controllers/Api/OrderConfirmationApiController.php` |
| Lang | `lang/en/merchandise.php`, `lang/bn/merchandise.php` (`master.ord`, `order`) |
| Test | `tests/Feature/Merchandise/MerchandiseOrderTest.php` |
<!-- AUTO:impl:end -->

### পরিকল্পিত নতুন/বদলানো ফাইল

- এরপর ডেস্কের Packing Instruction ট্যাব।

## ৯. টেস্ট ও যাচাই

- `MerchandiseOrderTest`:
  - বায়ার ধরে নম্বর দেওয়া, স্টাইল থেকে ফাঁকা ঘর পূরণ, Total Price আর Lead Time।
  - নিজে লেখা নম্বর, আর নিষেধগুলো: নেওয়া নম্বর, অন্য বায়ারের স্টাইল, অর্ডারের আগের শিপ ডেট।
  - সাইজ ব্রেকডাউন থেকে পরিমাণ; একই রং-সাইজ দুবার দিলে আটকায়; অর্ডার মুছলে লাইনও মোছে।
  - দ্রুত কনফার্মেশন: শুধু অনুমোদিত স্টাইল, PO-র বার্তা, পরিমাণ বাধ্যতামূলক, একবারে কয়েকটা অর্ডার, Set-এর একক, দ্বিতীয়বারে REPEAT।
  - স্টাইল শিটের ট্যাব: অপশন, ভ্যালিডেশন, তৈরি, এডিট (নম্বর বদলায় না, ব্রেকডাউনের পরিমাণ থাকে)।
  - ড্যাশবোর্ডের চারটা গণনা আর সাম্প্রতিক তালিকা; বোর্ডে বাতিল অর্ডার বাদ।
  - Follow-up: সাজানোর ক্রম, খোলা/বন্ধ, মূল্য, উৎপাদনের কলাম শূন্য।
  - Projection: call-off নেওয়া, ব্যালান্সের সব পাহারা (call-off নেওয়া, call-off এডিট, সাইজ ব্রেকডাউন, প্রজেকশন ছোট করা, ধরন বদলানো, মোছা), বাতিল করলে পরিমাণ ফেরত আসা।
  - টেকনিশিয়ান খুলতে পারে না, ম্যানেজার শুধু দেখতে পারে।

## ১০. খোলা প্রশ্ন

_নেই এখনো।_

## ১১. As-built নোট

**C1 — Order Register (২০২৬-১০-১২):**
- Brickland লাইভ দেখে বানানো হয়েছে: Records, Size Breakdown (Add line ডায়ালগসহ), New order আর Edit order।
- **Brickland থেকে আলাদা:**
  - Brickland-এ Style বাছাই করলে কিছুই পূরণ হয় না। আমাদেরটায় ফাঁকা Style No, Buyer আর Buyer A/C স্টাইল থেকে বসে, আর অন্য বায়ারের স্টাইল আটকায়।
  - Add line-এ রং আর সাইজ Brickland-এর মতো লেখার ঘর। তবে স্টাইলের রং আর সাইজ সাজেশন হিসেবে আসে।
  - Ship Country Brickland-এ একটা পিকার বাটন; আমাদেরটায় Origin Country মাস্টারের ড্রপডাউন।
  - Company হিসেবে এই কোম্পানির factory/ইউনিট দেখায়।
- Brickland-এর "Style & Order · Step n of 5" স্টেপার C3-এ যোগ হয়েছে।

**C2 — Order Confirmation (২০২৬-১০-১২):**
- Brickland লাইভ দেখে বানানো হয়েছে: স্ক্রিন (বায়ার বাছাই, স্টাইল টিক, Auto Fill Information, View Details, খালি Confirm-এর বার্তা, অর্ডারের বিস্তারিত) আর স্টাইল শিটের ট্যাব (Working Style, Order Information-এর সব ফিল্ড)।
- **Brickland থেকে আলাদা:**
  - Company শুধু দেখায় (এই কোম্পানির নাম), Prod. Unit-এ factory বাছাই করা হয়। Order Register-এর "Company"-ও এই factory-ই।
  - Packing Instructions গোনা হয় স্টাইলের প্যাকিং লাইন থেকে (Inquiry B7)। Attachments গোনা হয় আইটেমের key image থেকে।
  - New/Repeat/Development একটা বাটন, ধরন বাছাই হয় ফর্মের Order Type থেকে: প্রথম অর্ডারে নতুন বাল্ক, পরেরগুলোতে রিপিট।
  - Sales Contract এখন লেখার ঘর। Export LC/SC মডিউল তৈরি হলে বাছাই হবে।

**C3 — Dashboard আর Style Progress Board (২০২৬-১০-১২):**
- Brickland লাইভ দেখে বানানো হয়েছে: গণনার কার্ড, রঙিন কুইক অ্যাকশন টাইল, Recent Styles/Orders, আর বোর্ডের ফিল্টার ও কলাম।
- Lab Dips, Samples, Bookings আর Budgets এখন ০ দেখায়, Brickland-এও এখন সব ০। Lab Dip/Sample, Fabric Booking আর Costing মডিউল তৈরি হলে গোনা শুরু হবে।
- Garment Type দেখায় স্টাইলের প্রথম আইটেমের নাম। Brickland-এ এটা "—" দেখায়।
- Brickland-এর বোর্ডে Style একটা আলাদা লেখার ঘর। আমাদেরটায় গ্রিডের নিজের সার্চ দিয়েই একই কাজ হয়।

**C4 — Follow-up আর Projection & Call-Off (২০২৬-১০-১২):**
- **Follow-up:** Brickland লাইভ দেখে বানানো হয়েছে, একই ফিল্টার আর কলাম।
  - উৎপাদনের কলামগুলো (Cutting থেকে Shipment) আর Verified? এখন ০ / No দেখায়, Brickland-এও তাই। উৎপাদন আর শিপমেন্ট মডিউল তৈরি হলে ভরবে।
  - Brickland-এর বর্ণনায় "PMS colour" অনুযায়ী ভাগের কথা আছে, কিন্তু তার গ্রিডে তা নেই। আমরাও গ্রিডটা হুবহু রেখেছি।
- **Projection & Call-Off:** Brickland-এ শুধু খালি তালিকা ("No projection records yet")। আগের সিদ্ধান্ত মেনে (scaffold-ও পুরো বানানো হবে) এখানে প্রজেকশন অর্ডার আর তার call-off পুরোপুরি চালু করা হয়েছে।
  - প্রজেকশন তৈরি হয় Order Register-এ বা স্টাইল শিটে, Order Type "Projection" দিয়ে।

**C5 — স্টেপার আর ড্যাশবোর্ডের লিংক, আর তিনটা লুকানো স্ক্রিন (২০২৬-১০-১৩, ব্যবহারকারীর ধরিয়ে দেওয়া ভুল থেকে):**
- **কী ভুল ছিল:**
  - স্টেপারের ধাপ ২ আর ৩, আর ড্যাশবোর্ডের New Style, Style Catalogue, All styles ও Open লিংক নিজের অনুমানে Style Development Sheet-এ পাঠানো হয়েছিল।
  - Brickland-এ এগুলো তিনটা আলাদা স্ক্রিনে যায়। সেগুলো সাইডবারে নেই, তাই মেনু-ক্রলার আগে এদের ধরতে পারেনি।
  - Style Progress Board-এ একটা "Open" লিংকও জোড়া ছিল, যা Brickland-এ নেই। সেটা সরানো হয়েছে।
- **প্রতিটা বাটনের গন্তব্য Brickland-এ চেপে মিলিয়ে দেখা হয়েছে:**

| বাটন | গন্তব্য |
|---|---|
| স্টেপারের ধাপ ১–৫ | Dashboard → Development Style Catalogue → Style Registration Entry → Order Confirmation → Order Register |
| Previous / Next step | প্রতিটা স্ক্রিনে আগের ও পরের ধাপ। Dashboard-এ শুধু Next, Order Register-এ শুধু Previous। |
| New Style টাইল | Development Style Catalogue, নতুন স্টাইলের উইজার্ড খোলা অবস্থায় (`?new=1`) |
| Confirm Order টাইল | Order Confirmation |
| Style Catalogue টাইল | Style Catalogue (`/merchandise-desk/style-gallery`) |
| Order List টাইল, Recent Orders-এর Order list | Order Register |
| Recent Styles-এর All styles | Development Style Catalogue |
| Recent Styles-এর Open | Development Style Catalogue; আমাদেরটায় সেই স্টাইলের উইজার্ডও খোলে (`?style=`) |
| Order Confirmation-এর চোখের আইকন | অর্ডারের বিস্তারিত ডায়ালগ |
| Order Register-এর সারি | Edit আর Delete |
| Style Progress Board, Follow-up, Projection-এর সারি | কোনো বাটন নেই |

- **Development Style Catalogue** (`/merchandise-desk/development-style-gallery`):
  - উপরে New Development Style; তিনটা কার্ড: Inbox, Sent আর Quick Stats (Total, Draft, Sent, Approved, Rejected, Revision)।
  - Development Styles তালিকা: খোঁজা, বায়ার/সিজন/অবস্থা ফিল্টার, আর View।
  - Inbox আর Sent একই পেজে খোলে, ফেরার জন্য Dashboard বাটন। Inbox-এর সারিতে Review, Approve, Revision আর Reject; Sent-এর সারিতে অবস্থা, অনুমোদনকারীর নোট আর View।
  - **উইজার্ড, ১০ ধাপ:** Style Profile (স্টাইল ফর্ম আর Style Image), Garment Items, Fabric Studio, Colour Studio, Process & Services, Material BOM, Fabric Mapping, Packing Instructions (Packing Details, Packing Photo আর Pack Breakdown), Attachments (৮টা বাক্স), আর Review।
    - Previous/Continue চাপলে বা ধাপে ক্লিক করলে পেজ রিলোড হয় না।
    - নতুন স্টাইলে প্রোফাইল সেভ হওয়ার আগে বাকি ধাপ বন্ধ থাকে।
  - **Review ধাপ:** Style Summary, Completion % আর প্রতি ধাপের Completed/Not started; Approval অংশ আর History।
    - Draft, Revision বা Rejected অবস্থায় বাটন তিনটা: Save as Draft, Send for Approval, Approve (Direct)।
    - Sent অবস্থায় অনুমোদনকারী দেখেন শেষ নোট, আর বাটন: Approve, Return for revision, Reject।
    - Revision আর Reject-এ নোট বাধ্যতামূলক, Brickland-এর বার্তাসহ।
  - **অবস্থার নিয়ম:**
    - অনুমোদন চলাকালীন অবস্থা থাকে Sent, Revision বা Rejected (`approval_status`)।
    - এগুলোর কোনোটা না থাকলে শিটের Status থেকে আসে: Style Approved বা Running হলে Approved, নইলে Draft।
    - অনুমোদন হলে শিটের Status হয় Style Approved।
    - Sent বা Approved স্টাইল উইজার্ডে বদলানো যায় না, আর উইজার্ড সরাসরি Review ধাপে খোলে। Style Development Sheet থেকে তখনও এডিট করা যায়, Brickland-এর মতোই।
  - প্রতিটা স্টাইল তৈরির ক্রমে DEV-000001 ধরনের নম্বর পায়।
  - অনুমোদনের পারমিশন নতুন: `merchandise.style.approve`। FACTORY_ADMIN পায়।
- **Style Registration Entry** (`/merchandise-desk/style-registration`):
  - **Records:** চালু রেজিস্টার্ড স্টাইল। ফিল্টার Buyer A/C, Buyer, Season, Status; কলাম Brickland-এর মতো; Open অনুরোধটা খোলে।
  - **Registration:**
    - ৮টা কার্ড (Total Requests থেকে Rejected)।
    - Requests আর Registered Styles সাব-ট্যাব; অবস্থার ফিল্টার; Refresh।
    - New Style Registration Request: অনুমোদিত ডেভেলপমেন্ট স্টাইলের তালিকা থেকে Select। অনুরোধ পায় SRQ-2026-000001, স্টাইল পায় ST-2026-00001।
  - **রেজিস্ট্রেশন উইজার্ড, ৮ ধাপ:**
    - ধাপ ১ Style Profile: ডেভেলপমেন্ট থেকে আসা ঘর তালাবদ্ধ (Development / Buyer Master / System চিহ্নসহ), আর ৯টা নিজের ঘর। উপরে "Still required on this step" তালিকা। বাটন Save Draft, Save & Continue, Discard draft, PDF।
    - প্রোফাইল সম্পূর্ণ না হলে বাকি ধাপ Locked।
    - ধাপ ২–৭ অনুমোদিত ডেভেলপমেন্ট স্টাইলের তথ্য দেখায়, বদলানো যায় না।
    - ধাপ ৮ Review & Submit।
    - অনুমোদনকারী Approve, Return (নোট বাধ্যতামূলক) বা Reject (নোট বাধ্যতামূলক) করেন। Approve হলে REG-2026-000001 নম্বর আসে আর সেই ভার্সন চালু হয়।
    - চালু স্টাইল বদলাতে Change Request দিতে হয়: পরের ভার্সন, একই রেজিস্ট্রেশন নম্বর।
  - **Size Range:** অনুরোধে থাকা স্টাইলগুলোর সাইজ রেঞ্জ, Style Development Sheet-এর মতোই।
- **Style Catalogue** (`/merchandise-desk/style-gallery`): চালু (Running) স্টাইলের কার্ড। প্রতিটায় ছবি, বায়ার, সিজন, গার্মেন্টের ধরন, রং আর অর্ডারের সংখ্যা; খোঁজা আর বায়ার ফিল্টার। Brickland-এ এখন খালি।
- **যা Brickland-এ দেখা যায়নি:**
  - Brickland-এ Brand-এর তালিকা ফাঁকা, তাই সেখানে রেজিস্ট্রেশনের ধাপ ১ শেষ করা যায় না, আর ধাপ ২–৮ খোলা যায়নি।
  - আমাদের ধাপ ২–৭ বানানো হয়েছে Brickland-এর বর্ণনা অনুযায়ী ("its approved data is filled in for you"): তথ্য শুধু দেখায়। Brand একটা লেখার ঘর।
  - Brickland-এ Brand মাস্টার এলে এগুলো আবার মিলিয়ে দেখতে হবে।
- **সাথে ঠিক হওয়া দুটো পুরোনো বাগ:**
  - Merchandise-এর ছবি আর ফাইল ডাউনলোড করতে Work Order দেখার পারমিশন লাগত। এখন লাগে `merchandise.style.view`।
  - Merchandise-এর API-তে `created_by` সবসময় ফাঁকা থাকত, কারণ `auth()->id()` token দিয়ে চলা অনুরোধে কাজ করে না। এখন `caller()->auditUserId()` ব্যবহার হয়।
