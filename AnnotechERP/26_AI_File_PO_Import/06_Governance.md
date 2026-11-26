# Governance

> **স্ট্যাটাস:** ⬜ শুরু হয়নি
> **মডিউল:** [AI File & PO Import](00_Index.md) · **ফেজ ৮:** পোর্টাল, AI ও অ্যানালিটিক্স · **কী:** `26/06`

**⬅ ফিরে যান:** [AI File & PO Import](00_Index.md) | [AnnotechERP Index](../00_Index.md)

---

## ১. স্ক্রিন (Brickland ERP থেকে)

<!-- AUTO:screens:start -->
| # | স্ক্রিন | পাথ | ট্যাব | ফিল্ড | ডায়ালগ |
|---|---|---|---|---|---|
| 1 | Governance & Access Rules | `/m/ai-file-import/security-governance` | 1 | 3 | 1 |
| 2 | Import Audit Trail | `/m/ai-file-import/audit-trail` | 1 | 3 | 1 |
| 3 | Engine Settings | `/m/ai-file-import/ai-settings` | 1 | 3 | 1 |
<!-- AUTO:screens:end -->

## ২. প্রতিটা স্ক্রিনের লাইভ বিবরণ (ফিল্ড, ট্যাব, গ্রিড, ডায়ালগ, ভ্যালিডেশন)

<!-- AUTO:detail:start -->
#### Governance & Access Rules

`/m/ai-file-import/security-governance`

**ভিউ**
> 30 of 32 Demo data

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন/প্লেসহোল্ডার |
|---|---|---|---|
| Search Governance and access rules | search |  | Search policies… |
| Filter by kind | select |  | All kinds, Retention, Residency, Access, Encryption, Redaction |
| Items per page | select |  | 10, 25, 50, 100 |
- গ্রিড কলাম: Policy, Kind, Buyer, Retention, Region, ENFORCED
- সেকশন: Governance policies
- বাটন/অ্যাকশন: New policy, Policy, Kind, Buyer, Retention, Region

**ডায়ালগ: New policy** (মডাল)

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন |
|---|---|---|---|
| Policy* | text | ✔ |  |
| Kind | select |  | Select…, Retention, Residency, Access, Encryption, Redaction |
| Buyer | select |  | Select…, H&M, Primark, Zara, Target, Uniqlo, Kmart, C&A, Landmark, All buyers |
| Retention | number |  |  |
| Storage region | select |  | Select…, Bangladesh, Singapore, European Union, United States |
| Owner | select |  | Select…, Tanvir Ahmed, Nusrat Jahan, Shamima Akter, Rashed Karim, Farhana Islam, Imran Hossain |
- খালি Save-এ মেসেজ: Name the policy.
- invalid চিহ্নিত ফিল্ড: 1টা

#### Import Audit Trail

`/m/ai-file-import/audit-trail`

**ভিউ**
> 31 of 32 Demo data

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন/প্লেসহোল্ডার |
|---|---|---|---|
| Search Import audit trail | search |  | Search the trail… |
| Filter by action | select |  | All actions, Extracted, Overrode a field, Approved, Rejected, Posted, Purged |
| Items per page | select |  | 10, 25, 50, 100 |
- গ্রিড কলাম: Date, Time, Actor, Kind, Action, Before, After
- সেকশন: Audit trail
- বাটন/অ্যাকশন: Add entry, Date, Time, Actor, Kind, Action, Before, After

**ডায়ালগ: Add entry** (মডাল)

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন |
|---|---|---|---|
| Actor* | text | ✔ |  |
| Actor kind | select |  | Select…, Person, Model, Scheduled job |
| Action | select |  | Select…, Extracted, Overrode a field, Approved, Rejected, Posted, Purged |
| Target | text |  |  |
| Date | date |  |  |
| Time | text |  |  |
| Before | text |  |  |
| After | text |  |  |
- খালি Save-এ মেসেজ: Name the actor.
- invalid চিহ্নিত ফিল্ড: 1টা

#### Engine Settings

`/m/ai-file-import/ai-settings`

**ভিউ**
> 32 of 32 Demo data

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন/প্লেসহোল্ডার |
|---|---|---|---|
| Search Engine settings | search |  | Search settings… |
| Filter by group | select |  | All groups, Model, Thresholds, Autonomy, Performance, Notifications |
| Items per page | select |  | 10, 25, 50, 100 |
- গ্রিড কলাম: Setting, Group, Value, Unit, Changed by, Changed
- সেকশন: Engine settings
- বাটন/অ্যাকশন: New setting, Setting, Group, Value, Unit, Changed by, Changed

**ডায়ালগ: New setting** (মডাল)

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন |
|---|---|---|---|
| Setting* | text | ✔ |  |
| Group | select |  | Select…, Model, Thresholds, Autonomy, Performance, Notifications |
| Value | text |  |  |
| Unit | select |  | Select…, %, Seconds, Files, Model name, On or off |
| Last changed by | select |  | Select…, Tanvir Ahmed, Nusrat Jahan, Shamima Akter, Rashed Karim, Farhana Islam, Imran Hossain |
| Changed on | date |  |  |
- খালি Save-এ মেসেজ: Name the setting.
- invalid চিহ্নিত ফিল্ড: 1টা
<!-- AUTO:detail:end -->

## ৩. বিদ্যমান কোড যা কাজে লাগতে পারে

_বাস্তবায়নের আগে লেখা হবে।_

## ৪. ডাটা মডেল (Entity ও Field)

_বাস্তবায়নের আগে লেখা হবে।_

## ৫. Workflow / Status ও Business Rules

_বাস্তবায়নের আগে লেখা হবে।_

## ৬. API ও পারমিশন

_বাস্তবায়নের আগে লেখা হবে।_

## ৭. Frontend পেজ (লাইট থিম)

_বাস্তবায়নের আগে লেখা হবে।_

## ৮. Implementation Files

<!-- AUTO:impl:start -->
_এখনো কোনো কোড নেই।_
<!-- AUTO:impl:end -->

### পরিকল্পিত নতুন/বদলানো ফাইল

_বাস্তবায়নের আগে লেখা হবে।_

## ৯. টেস্ট ও যাচাই

_বাস্তবায়নের আগে লেখা হবে।_

## ১০. খোলা প্রশ্ন

_নেই এখনো।_

## ১১. As-built নোট

_বাস্তবায়নের পরে লেখা হবে।_
