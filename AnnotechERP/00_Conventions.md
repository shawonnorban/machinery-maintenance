# কাজের নিয়ম, ফাইল টেমপ্লেট ও Definition of Done

**⬅ ফিরে যান:** [AnnotechERP Index](00_Index.md)

---

## ১. ফোল্ডার ও ফাইল

- `AnnotechERP/<NN>_<Module>/00_Index.md`: মডিউলের গ্রুপ-তালিকা আর স্ট্যাটাস (জেনারেটর লেখে)। `NN` = বাস্তবায়নের ক্রম।
- `AnnotechERP/<NN>_<Module>/<SS>_<Group>.md`: Brickland-এর একটা সাইডবার-গ্রুপের সম্পূর্ণ স্পেক আর implementation রেকর্ড। MASTER DATA সবসময় `01`।
- কোড `app/Modules/<Module>/…`, `frontend/src/…`, `tests/Feature/<Module>/…`-এ থাকবে। এই ফোল্ডারে শুধু ডক।
- গ্রুপের কী `NN/SS` (যেমন `06/01` = Merchandise Desk-এর MASTER DATA)। টাস্ক বা আলোচনায় এই কী ব্যবহার করুন।
- `tools/source/brickland_screens.json`: Brickland-এর স্ক্রিন-কাঠামোর স্ন্যাপশট, যাতে ক্রলারের আউটপুট ছাড়াও ডক আবার বানানো যায়।

## ২. গ্রুপ-ফাইলের কাঠামো

| § | অংশ | কে লেখে |
|---|---|---|
| হেডার | স্ট্যাটাস (`✅ সম্পন্ন` / `🟡 আংশিক` / `⬜ শুরু হয়নি`), মডিউল, ফেজ, কী | হাতে। জেনারেটর এই লাইন পড়ে ইনডেক্সে বসায় |
| ১ | স্ক্রিন তালিকা (নাম, পাথ, ট্যাব, ফিল্ড আর ডায়ালগের সংখ্যা) | জেনারেটর (`AUTO:screens`) |
| ২ | প্রতিটা স্ক্রিনের লাইভ বিবরণ: ফিল্ড, টাইপ, বাধ্যতামূলক কিনা, অপশন, ট্যাব, গ্রিড-কলাম, বাটন, New/Add ডায়ালগ, খালি-Save ভ্যালিডেশন | জেনারেটর (`AUTO:detail`) |
| ৩ | বিদ্যমান কোড যা কাজে লাগতে পারে (যেমন পুরনো ItemMaster লেজার) | হাতে |
| ৪-৭ | ডাটা মডেল · Workflow ও Business Rules · API ও পারমিশন · Frontend পেজ (লাইট থিম) | হাতে, বাস্তবায়নের ঠিক আগে |
| ৮ | Implementation Files: বিদ্যমান কোড (`AUTO:impl`) + পরিকল্পিত নতুন/বদলানো ফাইল (হাতে) | দুটোই |
| ৯-১১ | টেস্ট ও যাচাই · খোলা প্রশ্ন · As-built নোট | হাতে |

`<!-- AUTO:x:start -->` আর `<!-- AUTO:x:end -->`-এর ভেতরের অংশ হাতে এডিট করবেন না, পরের রানে মুছে যাবে। এর বাইরের সবকিছু জেনারেটর ছোঁয় না।

## ৩. একটা গ্রুপের কাজের চক্র

1. **লাইভ বিবরণ দেখা:** §২ আর `tools/erp_crawler/out/screens/<module>/`-এর স্ক্রিনশট। দরকার হলে ওই মডিউল আবার ক্রল করা (`--only <slug> --refresh`)।
2. **স্পেক লেখা:** §৩-৭ আর "পরিকল্পিত ফাইল", খোলা প্রশ্ন §১০-এ। স্ট্যাটাস `🟡 আংশিক`।
3. **রিভিউ:** খোলা প্রশ্নের উত্তর ব্যবহারকারীর কাছ থেকে নেওয়া।
4. **বাস্তবায়ন:** নিচের §৪-এর কনভেনশন মেনে। UI হুবহু Brickland-এর মতো, শুধু থিম লাইট (§৭)। বানানোর আগে স্ক্রিনটার Brickland-স্ক্রিনশট খুলে দেখতে হবে।
5. **টেস্ট:** নিচের §৫।
6. **রেকর্ড:** §৮-এ as-built ফাইল, §১১ As-built নোট, স্ট্যাটাস `✅ সম্পন্ন`, জেনারেটর চালানো।
7. তারপর পরের গ্রুপ।

## ৪. কোডবেস কনভেনশন (বিদ্যমান প্রজেক্টে প্রতিষ্ঠিত)

- **মডিউল:** `app/Modules/{Module}` অটো-ডিসকভার হয় (Routes/api.php → `api/v1` prefix, `api.v1.` নাম; Console; Migrations)। Brickland-এর প্রতিটা মডিউলের জন্য একটা backend মডিউল হবে। পুরনো ERP কোড (ItemMaster, Procurement, Requisition, Compliance, Production) শুধু রেফারেন্স বা পুনর্ব্যবহারের জন্য, Brickland-এর কাঠামোই চূড়ান্ত।
- **মডেল:** ULID PK, `BaseModel`, `BelongsToTenant`, টাকার জন্য `MoneyCast`। `preventLazyLoading` চালু, তাই `with`/`loadMissing` ব্যবহার করতে হবে।
- **কন্ট্রোলার:** `ApiController::allow()`, `caller()->auditUserId()`, `caller()->can()`, `applyFilters/applySort/perPage`। উত্তর যায় `ApiResponse::ok/created/paginated` দিয়ে।
- **স্টক:** সব স্টক মুভমেন্ট `ItemLedger::post()` দিয়ে। রিজার্ভেশনের জন্য `ReserveItemStock` (reserve/earmark/consume/release)। রোলের অবস্থা বদলাতে `FabricRoll::TRANSITIONS`।
- **ফাইল/ছবি:** পলিমরফিক `FileAttachment` + `StoreFileAttachment`, সাথে `FileApiController`/`FileAttachmentController`-এ `authorizeView`/`authorizeManage` match arm।
- **অনুমোদন:** কোম্পানির ভেতরের সই-চেইন = `Approval` মডিউল (`ApprovalEntities::MODELS`), যা Brickland-এর Approval Center-এর ভিত্তি হতে পারে।
- **রিপোর্ট:** `app/Modules/Reporting/Reports/<Group>/*Report.php` → `ReportRegistry::REPORTS` আর `GROUPS`। এক্সপোর্ট আর পারমিশন বিনা পরিবর্তনে চলে।
- **নম্বর:** `NumberSequenceGenerator::next()` (Factory মডেল লাগে)।
- **সেটিং/নোটিফিকেশন:** `SettingDefinitionSeeder` + `SettingsResolver::get()`। `Notification::EVENT_TYPES` + lang `notification.event.{EVENT}.title|body`।
- **অডিট:** নতুন মডেল `AuditServiceProvider::AUDITED`-এ যোগ করতে হবে।
- **i18n:** `lang/{en,bn}/*.php` → `docker exec machinery-maintenance-backend-1 php scripts/extract-i18n.php`। বাংলা টেক্সটে য়/ড়/ঢ় decomposed রূপে (U+09AF U+09BC ইত্যাদি)।
- **পারমিশন:** `PermissionSeeder`/`RoleSeeder`-এ যোগ করার পর FQCN দিয়ে seed: `php artisan db:seed --class="App\\Modules\\…\\Seeder"`।
- **স্ক্রিন-কিট (ERP Resource, ২০২৬-১০-১০):** Brickland-এর সাধারণ "Records + New X" স্ক্রিনের জন্য নিজের controller না লিখে `app/Modules/<Module>/Resources/<X>Resource.php` (`App\Modules\Erp\Support\ResourceDefinition` থেকে) বানিয়ে `ResourceRegistry::DEFINITIONS`-এ যোগ করুন। এতে API আসে `/api/v1/erp/{key}`-এ। ফ্রন্টএন্ডে `resourceScreen({...})` (দেখুন `access-control/[tab]/config-screens.js`) আর `RecordsGrid`/`EntryForm`/`GroupedRecords`। বিশেষ আচরণ (ম্যাট্রিক্স, ট্রি, ওয়ার্কফ্লো) থাকলে তবেই নিজস্ব controller।
- **হিসাব:** পরিমাণ/টাকার হিসাব bcmath দিয়ে (truncation, যাতে কাগজের শিটের সংখ্যার সাথে মেলে)।
- **UOM (একক), ব্যবহারকারীর নির্দেশ, ২০২৬-১০-২০:** পরিমাণের একক কখনো খালি টেক্সট ইনপুট হবে না, Brickland-এর ক্রলে "text" এলেও না। একক আসবে Settings → Master Data → Units of Measure (`units_of_measure`, প্ল্যাটফর্মের একক আর কোম্পানির নিজের) থেকে, ড্রপডাউনে।
  - **Frontend:** `components/erp/uom-select.jsx`-এর `UomSelect`। তালিকা app layout একবার `/lookups/uoms` থেকে এনে `UomProvider` দিয়ে দেয়। grid-ঘরে `compact` বা `size="sm"` দিলে শুধু code দেখায়। master-স্ক্রিনের ফর্মে ফিল্ড `type: "uom"`। মাপের একক (measure point) `units={MEASURE_UNITS}` (CM/INCH)।
  - **Backend:** validation-এ `UomCodes::rule($companyId)`, save করার আগে `UomCodes::normalize()`। "pcs", "Kgs", "yards"-এর মতো লেখা গ্রহণ করে master-এর code (PCS, KG, YDS) হিসেবে রাখা হয়। master-এ নেই এমন একক হলে বার্তা আসে। `uom_id` FK থাকা পুরনো মডিউলগুলো যেমন আছে তেমন থাকবে।
  - **নতুন স্ক্রিন:** নতুন স্ক্রিনে যেখানেই একক লাগবে, এই দুটোই ব্যবহার করতে হবে।
- **মাস্টার ডাটা seeder (২০২৬-১০-২০):** প্রতিটা বানানো মডিউলের নমুনা master থাকে `app/Modules/<Module>/Database/Seeders/<Module>MasterSeed.php`-এ (`App\Modules\Erp\Support\MasterSeed`)।
  - **কীভাবে চলে:** সারিগুলো যায় স্ক্রিনের নিজের rules, defaults আর beforeSave/afterSave দিয়ে (`ResourceSeeder`), কোম্পানির owner হিসেবে।
  - **ক্রম:** নির্ভরতার ক্রম ঠিক হয় `Database\Seeders\ErpMasterDataSeeder::SEEDS`-এ।
  - **চালানো:** `php artisan erp:seed-masters [DAL OTL …]`। আবার চালালে কিছু duplicate হয় না।
  - **বাদ পড়া সারি:** কোম্পানির নিজের সারির সাথে সংঘাত হলে, বা দরকারি সেটআপ (factory, floor, user) না থাকলে, সেই সারি বাদ পড়ে আর কারণসহ রিপোর্ট হয়।
  - **নতুন মডিউল:** নতুন মডিউল বানালে তার `MasterSeed` লিখে `SEEDS`-এ যোগ করতে হবে।
  - **টেস্ট:** `DatabaseSeeder`-এ এটা নেই, কারণ টেস্ট ফাঁকা master থেকে শুরু হয়।

## ৫. Definition of Done (একটা সাব-মডিউল ✅ হওয়ার শর্ত)

- [ ] §১-এর প্রতিটা স্ক্রিনের সমতুল্য পেজ আছে, Brickland-এর ফিল্ড, ট্যাব আর গ্রিড-কলামসহ (§২)। কোনোটা বাদ পড়লে কারণ §১১-এ লেখা
- [ ] Brickland-এ যে ফিল্ড বাধ্যতামূলক বা যে ভ্যালিডেশন মেসেজ আসে, সেগুলো একই রকম
- [ ] Migration, Model, Action, API, পারমিশন, Frontend, lang (en+bn) সব আছে
- [ ] Feature টেস্ট পাস করে, আর সংশ্লিষ্ট পুরনো টেস্টের রিগ্রেশনও পাস করে
- [ ] dev স্ট্যাকে লাইভ স্মোক টেস্ট হয়েছে (curl দিয়ে পেজ রেন্ডার, `__next_error__`/`MISSING_MESSAGE` নেই)
- [ ] §৮-এ as-built ফাইল তালিকা, জেনারেটর `--check` পরিষ্কার, স্ট্যাটাস ✅
- [ ] কোনো কমিট বা পুশ হয়নি (ব্যবহারকারী না বলা পর্যন্ত গিট ছোঁয়া হবে না)

## ৬. টেস্ট ও ডেভ-পরিবেশের নিয়ম

- একসাথে দুটো phpunit কখনো না, কারণ দুটো একই টেস্ট DB শেয়ার করে। বড় রানে `vendor/bin/phpunit` সরাসরি, `php artisan test` না।
- নতুন Next.js রুট 404 দিলে: `docker compose rm -sf frontend`, তারপর `machinery-maintenance_frontend-next-cache` ভলিউম মুছে নতুন `page.js`-গুলো `touch` করতে হবে। এডিটের পরে পুরনো আউটপুট দেখালে `docker compose restart frontend`।
- ডেভ: backend `localhost:8010`, frontend `localhost:3010`। curl/docker পাথে `MSYS_NO_PATHCONV=1`।
- বিদ্যমান রিগ্রেশনে জানা পুরনো ফেইল (এই প্রজেক্টের ERP কাজের বাইরে): WorkOrder টেস্টে `'user-a'` padding (৪টা), ApiResourceTest-এ idempotency আর failure code (৫টা), FileApiTest/FileAttachmentTest-এ কন্টেইনারের GD-তে JPEG নেই (৭টা), AssetLifecycleTest ফুল রানে মাঝে মাঝে ফেইল (একা চালালে পাস)।

## ৭. UI: হুবহু Brickland (ব্যবহারকারীর নির্দেশ, ২০২৬-১০-০৯)

প্রতিটা পাতা Brickland-এর মিলে যাওয়া স্ক্রিনের মতো দেখতে আর চলতে হবে; একমাত্র পার্থক্য থিম (Brickland-এর নিজের লাইট থিম)। রেফারেন্স:

- ডার্ক স্ক্রিনশট: `tools/erp_crawler/out/screens/<module>/<screen>*.png` (মূল পাতা, প্রতিটা ট্যাব, `__dlg_*` ডায়ালগ)
- লাইট থিম স্ক্রিনশট: `node AnnotechERP/tools/erp_crawler/light_shots.mjs <path…>` → `tools/erp_crawler/out/light/` (শুধু পড়ে; লগইনের পরে সব non-GET ব্লক)

**শেল**
- **সাইডবার:** ভাগ (OVERVIEW, SYSTEM ADMINISTRATION, HUMAN RESOURCE …, ইন্ডিগো বড় হাতের লেখা) → মডিউল (রঙিন আইকন, স্ক্রিন-সংখ্যা, শেভরন) → ফোল্ডার (ছোট বড়-হাতের লেখা, আইকন, সংখ্যা) → স্ক্রিন (আইকন, সক্রিয় হলে হালকা-ইন্ডিগো পিল)। প্রতিটা স্ক্রিন সাইডবারে আলাদা লিংক।
- **টপবার:** সাইডবার-টগল, মডিউল-চিপ (আইকন + নাম + "MODULE"), মডিউল সার্চ (⌘K), কোম্পানি-চিপ, থিম, ভাষা, নোটিফিকেশন, ডানে ইউজার।
- **পাতার মাথা:** ব্রেডক্রাম্ব Home › Modules › মডিউল › স্ক্রিন; রঙিন আইকন-টাইল; শিরোনাম; "NN of MM" ব্যাজ (মডিউলে স্ক্রিনের ক্রম); এক লাইনের বিবরণ।
- **রঙ:** ইন্ডিগো অ্যাকসেন্ট; টেবিল-হেডার ইন্ডিগো, সাদা লেখা।

**স্ক্রিনের ভেতর**
- **ট্যাব:** "Records N" | স্ক্রিনের নিজস্ব ট্যাব (Lookup Tables, User Roles …) | "+ New X"। কিছু মডিউলে (যেমন Company Management) ট্যাব-সারিতে পাশের স্ক্রিনগুলো থাকে; যে স্ক্রিনশটে যা আছে তা-ই।
- **Records:** কার্ডে সার্চ ("Search N records…") আর Export; সাজানো যায় এমন কলাম; Status ব্যাজ (Active সবুজ); ACTION-এ Edit আর মোছার বাটন; নিচে « ‹ পাতা › », items per page (10/25/50/100), "1 - N of N items", রিফ্রেশ।
- **New X:** ফর্ম-কার্ড (শিরোনাম, এক লাইনের ব্যাখ্যা, ২-৩ কলামের ফিল্ড, ফিল্ডের নিচে সাহায্য-লেখা), তার নিচে আলাদা অ্যাকশন-কার্ড (নোট + Reset + "Save X")। Edit একই ফর্ম ডায়ালগে খোলে।
- **মডিউল ড্যাশবোর্ড:** স্ট্যাট-কার্ড আর সারাংশ-কার্ড।

