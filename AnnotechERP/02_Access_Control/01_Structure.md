# Structure

> **স্ট্যাটাস:** ✅ সম্পন্ন (ধাপ A, B, C: ৯টা স্ক্রিনই; বিচ্যুতি আর বাকি কাজ As-built নোটে)
> **মডিউল:** [Access Control](00_Index.md) · **ফেজ ১:** ভিত্তি ও মাস্টার ডাটা · **কী:** `02/01`

**⬅ ফিরে যান:** [Access Control](00_Index.md) | [AnnotechERP Index](../00_Index.md)

---

## ১. স্ক্রিন (Brickland ERP থেকে)

<!-- AUTO:screens:start -->
| # | স্ক্রিন | পাথ | ট্যাব | ফিল্ড | ডায়ালগ |
|---|---|---|---|---|---|
| 1 | Module Registration | `/m/security/module-creation` | 2 | 9 | 1 |
| 2 | Menu Definition | `/m/security/menu-creation` | 3 | 14 | 1 |
| 3 | Lookup Table Master | `/m/security/lookup-tables` | 3 | 7 | 1 |
| 4 | System Parameter Update | `/m/security/system-param-data-update` | 3 | 12 | 1 |
| 5 | Role Definition | `/m/security/role-creation` | 2 | 10 | 1 |
| 6 | Role Menu Assignment | `/m/security/assign-menu-to-role` | 4 | 9 | 1 |
| 7 | User Account Registration | `/m/security/user-registration` | 3 | 19 | 1 |
| 8 | Password Change | `/m/security/change-password` | 2 | 6 | 0 |
| 9 | Category Access Permission | `/m/security/category-permission` | 3 | 4 | 1 |
<!-- AUTO:screens:end -->

## ২. প্রতিটা স্ক্রিনের লাইভ বিবরণ (ফিল্ড, ট্যাব, গ্রিড, ডায়ালগ, ভ্যালিডেশন)

<!-- AUTO:detail:start -->
#### Module Registration

`/m/security/module-creation`

**ট্যাব: Records 12**

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন/প্লেসহোল্ডার |
|---|---|---|---|
| Search ERP modules with code, URL slug, icon, display order and active state | search |  | Search 12 records… |
| Items per page | select |  | 10, 25, 50, 100 |
- গ্রিড কলাম: Code, Module, Slug, Icon, Menus, Order, Status, ACTION
- বাটন/অ্যাকশন: New module, Export, Code, Module, Slug, Icon, Menus, Order, Status, Edit

**ট্যাব: New module**

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন/প্লেসহোল্ডার |
|---|---|---|---|
| Module Code* | text | ✔ | SEC |
| Module Name* | text | ✔ | Security, Roles & Access Control |
| URL Slug* | text | ✔ | security |
| Icon Key | text |  | ShieldCheck |
| Display Order | number |  | 10 |
| Description | textarea |  | Users, roles, permission matrix, audit trail and session policy. |
| Active | checkbox |  |  |
- সেকশন: New module
- বাটন/অ্যাকশন: New module, Reset, Save module

**ডায়ালগ: New module**

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন |
|---|---|---|---|
| Module Code* | text | ✔ |  |
| Module Name* | text | ✔ |  |
| URL Slug* | text | ✔ |  |
| Icon Key | text |  |  |
| Display Order | number |  |  |
| Description | textarea |  |  |
| Active | checkbox |  |  |
- খালি Save-এ মেসেজ: Module Code is required.

#### Menu Definition

`/m/security/menu-creation`

**ট্যাব: Records 44**

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন/প্লেসহোল্ডার |
|---|---|---|---|
| Search Menu entries with their module, parent, URL, security level, depth and di | search |  | Search 44 records… |
| Items per page | select |  | 10, 25, 50, 100 |
- গ্রিড কলাম: Code, Menu Name, Sub Module, Parent Menu, Menu URL, Security Level, Level, Order, L W R, Status, ACTION
- বাটন/অ্যাকশন: Menu Tree, New menu, Export, Code, Menu Name, Sub Module, Parent Menu, Menu URL, Security Level, Level, Order, L W R, Status, Edit

**ট্যাব: Menu Tree**

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন/প্লেসহোল্ডার |
|---|---|---|---|
| Search modules | search |  | Search… |
- সেকশন: Modules, Menus
- বাটন/অ্যাকশন: Menu Tree, New menu, Security, Roles & Access Control SEC 28, Merchandising & Order Management BCL 16, Purchase & Procurement PUR 0, Production Planning & Scheduling PLN 0, Knitting Production Management KNT 0, Dyeing Production Management DYE 0, Cutting Section Management CUT 0, Dyeing Lab & Recipe Management LAB 0

**ট্যাব: New menu**

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন/প্লেসহোল্ডার |
|---|---|---|---|
| Menu Code* | text | ✔ | SEC-ROLE |
| Menu Name* | text | ✔ | Role Creation |
| Description | textarea |  |  |
| Menu URL | text |  | /m/security/role-creation |
| Security Level | select |  | Select…, Public, Internal, Restricted, Confidential |
| Icon Path | text |  | UserCog |
| Menu Order | number |  | 10 |
| Leaf? A leaf is a screen. Everything else is a folder that only groups. | checkbox |  |  |
| Open New Window? | checkbox |  |  |
| Report Menu? | checkbox |  |  |
| Active? | checkbox |  |  |
- সেকশন: New menu
- বাটন/অ্যাকশন: Menu Tree, New menu, Select…, Reset, Save menu

**ডায়ালগ: New menu**

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন |
|---|---|---|---|
| Menu Code* | text | ✔ |  |
| Menu Name* | text | ✔ |  |
| Description | textarea |  |  |
| Menu URL | text |  |  |
| Security Level | select |  | Select…, Public, Internal, Restricted, Confidential |
| Icon Path | text |  |  |
| Menu Order | number |  |  |
| Leaf? A leaf is a screen. Everything else is a folder that only groups. | checkbox |  |  |
| Open New Window? | checkbox |  |  |
| Report Menu? | checkbox |  |  |
| Active? | checkbox |  |  |
- খালি Save-এ মেসেজ: Sub Module is required.

#### Lookup Table Master

`/m/security/lookup-tables`

**ট্যাব: Records 6**

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন/প্লেসহোল্ডার |
|---|---|---|---|
| Search Lookup tables with their code, name, value count and active state | search |  | Search 6 records… |
| Items per page | select |  | 10, 25, 50, 100 |
- গ্রিড কলাম: Table Code, Name, Values, System, Description, Status, ACTION
- বাটন/অ্যাকশন: Lookup Tables, New lookup table, Export, Table Code, Name, Values, System, Description, Status, Edit

**ট্যাব: Lookup Tables**

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন/প্লেসহোল্ডার |
|---|---|---|---|
| Search tables | search |  | Search… |
- সেকশন: Lookup Tables, Values
- বাটন/অ্যাকশন: Lookup Tables, New lookup table, Approval State APPROVAL_STATE · system 4, Fabric Type FABRIC_TYPE 6, Payment Term PAYMENT_TERM 5, Shift SHIFT 4, Shipment Mode SHIPMENT_MODE 4, Unit of Measure UOM · system 8

**ট্যাব: New lookup table**

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন/প্লেসহোল্ডার |
|---|---|---|---|
| Table Code* | text | ✔ | LT-254 |
| Table Name* | text | ✔ | Shipment Mode |
| Description | textarea |  |  |
| Active? | checkbox |  |  |
- সেকশন: New lookup table
- বাটন/অ্যাকশন: Lookup Tables, New lookup table, Reset, Save lookup table

**ডায়ালগ: New lookup table**

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন |
|---|---|---|---|
| Table Code* | text | ✔ |  |
| Table Name* | text | ✔ |  |
| Description | textarea |  |  |
| Active? | checkbox |  |  |
- খালি Save-এ মেসেজ: Table Code is required.

#### System Parameter Update

`/m/security/system-param-data-update`

**ট্যাব: Records 70**

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন/প্লেসহোল্ডার |
|---|---|---|---|
| Search System parameters with their group, current value, data type and last upd | search |  | Search 70 records… |
| Items per page | select |  | 10, 25, 50, 100 |
- গ্রিড কলাম: Group, Key, Parameter, Value, Type, Updated, Status, ACTION
- বাটন/অ্যাকশন: System Setting, New parameter, Export, Group, Key, Parameter, Value, Type, Updated, Status, Edit

**ট্যাব: System Setting**

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন/প্লেসহোল্ডার |
|---|---|---|---|
| Search groups | search |  | Search… |
- সেকশন: Group, Rules
- বাটন/অ্যাকশন: System Setting, New parameter, Clndr Month Day Offset 1, Designation Group + Dept 6, General 14, Notification 2, Parameter for Accounting 5, Prod Planning 3, Quality 1, Rule for Cutting 9, Rule for Dyeing 3, Rule for Fair Shop 7, Rule for Knitting 3, Rule for Merchandising 3, Rule for Yarn Purchase Requisition 2, Session & Access 6, Shop Floor User Permission 2, Yarn Spec Mail Cfg 2

**ট্যাব: New parameter**

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন/প্লেসহোল্ডার |
|---|---|---|---|
| Parameter Key* | text | ✔ | SESSION.TIMEOUT.MINUTES |
| Parameter Name* | text | ✔ | Session timeout |
| Value* | text | ✔ | 30 |
| Data Type* | select | ✔ | Select…, String, Number, Boolean, Date, Json |
| Group | text |  | Rule for Cutting |
| Display Order | number |  |  |
| Description | textarea |  |  |
| Editable from this screen Clear this for parameters only a deployment should cha | checkbox |  |  |
| Active? An inactive rule is ignored and the code that reads it falls back to its | checkbox |  |  |
- সেকশন: New parameter
- বাটন/অ্যাকশন: System Setting, New parameter, Reset, Save parameter

**ডায়ালগ: New parameter**

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন |
|---|---|---|---|
| Parameter Key* | text | ✔ |  |
| Parameter Name* | text | ✔ |  |
| Value* | text | ✔ |  |
| Data Type* | select | ✔ | Select…, String, Number, Boolean, Date, Json |
| Group | text |  |  |
| Display Order | number |  |  |
| Description | textarea |  |  |
| Editable from this screen Clear this for parameters only a deployment should cha | checkbox |  |  |
| Active? An inactive rule is ignored and the code that reads it falls back to its | checkbox |  |  |
- খালি Save-এ মেসেজ: Value is required.

#### Role Definition

`/m/security/role-creation`

**ট্যাব: Records 38**

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন/প্লেসহোল্ডার |
|---|---|---|---|
| Search Roles with code, short name, rank, admin flag, the number of menus grante | search |  | Search 38 records… |
| Items per page | select |  | 10, 25, 50, 100 |
- গ্রিড কলাম: Code, Name [E], Name [B], Short Name, Is Admin?, Rank, Menus, Users, Status, ACTION
- বাটন/অ্যাকশন: New role, Export, Code, Name [E], Name [B], Short Name, Is Admin?, Rank, Menus, Users, Status, Edit

**ট্যাব: New role**

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন/প্লেসহোল্ডার |
|---|---|---|---|
| Role Code* | text | ✔ | MERCH |
| Role name [E]* | text | ✔ | Merchandiser |
| Role Name [B] | text |  | মার্চেন্ডাইজার |
| Short name* | text | ✔ | Merch |
| Rank* | number | ✔ |  |
| Description | textarea |  |  |
| Admin? An admin role bypasses the menu matrix entirely. Grant it to as few roles | checkbox |  |  |
| Active? | checkbox |  |  |
- সেকশন: New role
- বাটন/অ্যাকশন: New role, Reset, Save role

**ডায়ালগ: New role**

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন |
|---|---|---|---|
| Role Code* | text | ✔ |  |
| Role name [E]* | text | ✔ |  |
| Role Name [B] | text |  |  |
| Short name* | text | ✔ |  |
| Rank* | number | ✔ |  |
| Description | textarea |  |  |
| Admin? An admin role bypasses the menu matrix entirely. Grant it to as few roles | checkbox |  |  |
| Active? | checkbox |  |  |
- খালি Save-এ মেসেজ: Role Code is required.

#### Role Menu Assignment

`/m/security/assign-menu-to-role`

**ট্যাব: Records 56**

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন/প্লেসহোল্ডার |
|---|---|---|---|
| Search Menu rights per role, showing view, create, edit, delete, approve, print  | search |  | Search 56 records… |
| Items per page | select |  | 10, 25, 50, 100 |
- গ্রিড কলাম: Role, Module, Menu, V C E D A P X, Updated, ACTION
- বাটন/অ্যাকশন: Menu Assign, Report Assign, New menu grant, Export, Role, Module, Menu, V C E D A P X, Updated, Edit

**ট্যাব: Menu Assign**
- সেকশন: Menu Assign
- বাটন/অ্যাকশন: Menu Assign, Report Assign, New menu grant, -- Select role --

**ট্যাব: Report Assign**
- সেকশন: Report Assign
- বাটন/অ্যাকশন: Menu Assign, Report Assign, New menu grant, -- Select role --

**ট্যাব: New menu grant**

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন/প্লেসহোল্ডার |
|---|---|---|---|
| View | checkbox |  |  |
| Create | checkbox |  |  |
| Edit | checkbox |  |  |
| Delete | checkbox |  |  |
| Approve | checkbox |  |  |
| Print | checkbox |  |  |
| Export | checkbox |  |  |
- সেকশন: New menu grant
- বাটন/অ্যাকশন: Menu Assign, Report Assign, New menu grant, Select…, Reset, Save menu grant

**ডায়ালগ: New menu grant**

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন |
|---|---|---|---|
| View | checkbox |  |  |
| Create | checkbox |  |  |
| Edit | checkbox |  |  |
| Delete | checkbox |  |  |
| Approve | checkbox |  |  |
| Print | checkbox |  |  |
| Export | checkbox |  |  |
- খালি Save-এ মেসেজ: Role is required.

#### User Account Registration

`/m/security/user-registration`

**ট্যাব: Records 31**

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন/প্লেসহোল্ডার |
|---|---|---|---|
| Search User accounts with type, user id, name, mail, employee code, primary role | search |  | Search 31 records… |
| Items per page | select |  | 10, 25, 50, 100 |
- গ্রিড কলাম: User Type, User ID, User Full Name, Mail, Emp.Code, Role, Roles, 2FA, Last Sign-in, Status, ACTION
- বাটন/অ্যাকশন: User Roles, New user account, Export, User Type, User ID, User Full Name, Mail, Emp.Code, Role, Roles, 2FA, Last Sign-in, Status

**ট্যাব: User Roles**
- সেকশন: User Roles
- বাটন/অ্যাকশন: User Roles, New user account, -- Select user --

**ট্যাব: New user account**

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন/প্লেসহোল্ডার |
|---|---|---|---|
| User Type* | select | ✔ | Select…, Internal, External, Guest |
| User ID* | text | ✔ | r.islam |
| Password* | password | ✔ |  |
| Confirm Password* | password | ✔ |  |
| Memorable Word | password |  |  |
| Confirm Memorable Word | password |  |  |
| User Full Name* | text | ✔ | Rashedul Islam |
| Email* | text | ✔ | name@bricklandcompositeltd.com |
| Employee Code | text |  | EMP-01042 |
| System Admin Leave clear for a general user. This is separate from any role gran | checkbox |  |  |
| Status | select |  | Select…, Active, Read Only, Password Expiring, Dormant, Locked, Separated |
| Two-Factor Method | select |  | Select…, Disabled, Authenticator, Sms Otp, Email Otp |
| Account Expires On | date |  |  |
| User must change password at next login | checkbox |  |  |
| User can not change password | checkbox |  |  |
| Password never expires | checkbox |  |  |
| Justification | textarea |  | Approved by the Factory Manager for the Q3 audit… |
- সেকশন: New user account
- বাটন/অ্যাকশন: User Roles, New user account, Select…, Reset, Save user account

**ডায়ালগ: New user account**

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন |
|---|---|---|---|
| User Type* | select | ✔ | Select…, Internal, External, Guest |
| User ID* | text | ✔ |  |
| Password* | password | ✔ |  |
| Confirm Password* | password | ✔ |  |
| Memorable Word | password |  |  |
| Confirm Memorable Word | password |  |  |
| User Full Name* | text | ✔ |  |
| Email* | text | ✔ |  |
| Employee Code | text |  |  |
| System Admin Leave clear for a general user. This is separate from any role gran | checkbox |  |  |
| Status | select |  | Select…, Active, Read Only, Password Expiring, Dormant, Locked, Separated |
| Two-Factor Method | select |  | Select…, Disabled, Authenticator, Sms Otp, Email Otp |
| Account Expires On | date |  |  |
| User must change password at next login | checkbox |  |  |
| User can not change password | checkbox |  |  |
| Password never expires | checkbox |  |  |
| Justification | textarea |  |  |
- খালি Save-এ মেসেজ: Password is required.

#### Password Change

`/m/security/change-password`

**ট্যাব: Records 13**

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন/প্লেসহোল্ডার |
|---|---|---|---|
| Search Password rotation history with the account, when it changed and who chang | search |  | Search 13 records… |
| Items per page | select |  | 10, 25, 50, 100 |
- গ্রিড কলাম: Account, Name, Changed, Changed By
- বাটন/অ্যাকশন: Change password, Export, Account, Name, Changed, Changed By

**ট্যাব: Change password**

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন/প্লেসহোল্ডার |
|---|---|---|---|
| Current Password | password |  |  |
| New Password* | password | ✔ |  |
| Confirm New Password* | password | ✔ |  |
| Force a change at next sign-in | checkbox |  |  |
- সেকশন: New password change
- বাটন/অ্যাকশন: Change password, Select…, Reset

#### Category Access Permission

`/m/security/category-permission`

**ট্যাব: Records 0**
- বাটন/অ্যাকশন: Item Category Permission, New category permission

**ট্যাব: Item Category Permission**
- সেকশন: Item Category Permission
- বাটন/অ্যাকশন: Item Category Permission, New category permission, -- Select role --

**ট্যাব: New category permission**

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন/প্লেসহোল্ডার |
|---|---|---|---|
| View | checkbox |  |  |
| Create | checkbox |  |  |
| Edit | checkbox |  |  |
| Approve | checkbox |  |  |
- সেকশন: New category permission
- বাটন/অ্যাকশন: Item Category Permission, New category permission, Select…, Reset, Save category permission

**ডায়ালগ: New category permission**

| ফিল্ড | টাইপ | বাধ্যতামূলক | অপশন |
|---|---|---|---|
| View | checkbox |  |  |
| Create | checkbox |  |  |
| Edit | checkbox |  |  |
| Approve | checkbox |  |  |
- খালি Save-এ মেসেজ: Role is required.
<!-- AUTO:detail:end -->

## ৩. বিদ্যমান কোড যা কাজে লাগতে পারে

- `roles`, `permissions` (১৪৫টা, `module` কলামসহ), `role_permissions`, `user_roles` (ফ্যাক্টরি-স্কোপড), `users`, `login_attempts`
- `setting_definitions` + `settings` (System Parameter), বিদ্যমান Settings → Users/Roles পেজ আর API (`/users`, `/roles`, `/permissions`)
- `item_categories` (Category Access Permission-এর জন্য)

## ৪. ডাটা মডেল (Entity ও Field)

- **`menu_modules` (নতুন):** code, name, name_bn, slug, icon, sort_order, description, active। Brickland-এর ২৮টা মডিউল + Machinery + MIS, `MenuRegistry` থেকে seed।
- **`menus` (নতুন):** module_id, parent_id (ফোল্ডার), code, name, name_bn, url, security_level (`PUBLIC`/`INTERNAL`/`RESTRICTED`/`CONFIDENTIAL`), icon, sort_order, is_leaf, open_new_window, is_report, is_system (কোড-seeded leaf, মোছা যায় না), active
- **`menu_permissions` (নতুন):** menu_id, flag (`V`/`C`/`E`/`D`/`A`/`P`/`X`), permission_name। কোন ফ্ল্যাগ কোন পারমিশন, সেটা কোডে (`MenuRegistry`) সংজ্ঞায়িত।
- **`roles`** + code, name_bn, short_name, rank, is_admin (সব মেনু বাইপাস, শুধু অনুমোদিত কয়েকজনের জন্য), active
- **`users`** + user_type (`INTERNAL`/`EXTERNAL`/`GUEST`), username (User ID), employee_code, account_expires_at, must_change_password, cannot_change_password, password_never_expires, two_factor_method (`DISABLED`/`AUTHENTICATOR`/`SMS_OTP`/`EMAIL_OTP`), memorable_word (hash), justification। Status বাড়বে: `ACTIVE`/`READ_ONLY`/`PASSWORD_EXPIRING`/`DORMANT`/`LOCKED`/`SEPARATED`।
- **`password_changes` (নতুন):** user_id, changed_by, changed_at, forced (Password Change-এর Records গ্রিড)
- **`lookup_tables` + `lookup_values` (নতুন):** code, name, description, is_system, active / table_id, code, label, label_bn, sort_order, active। সাধারণ ড্রপডাউনের উৎস (Shift, Payment Term, Shipment Mode …)।
- **`role_item_category_permissions` (নতুন):** role_id, item_category_id, can_view, can_create, can_edit, can_approve

## ৫. Workflow / Status ও Business Rules

- Role Code, Name [E], Short Name, Rank বাধ্যতামূলক (Brickland: "Role Code is required.")। `is_admin` রোল মেনু-ম্যাট্রিক্স বাইপাস করে, তাই এটা দেওয়া-নেওয়া অডিট-লগড।
- Role-Menu grant সেভ = ফ্ল্যাগ থেকে পারমিশন-সেট হিসাব, তারপর `role_permissions` sync। বিদ্যমান সিস্টেম-রোল (`is_system`) এর পারমিশন seed থেকে আসে, তাই এগুলোর grant এডিট করলে সতর্কতা দেখাবে।
- User: Password আর Confirm মিলতে হবে, পাসওয়ার্ড-নীতি মানতে হবে। "User must change password at next login" থাকলে লগইনের পরে প্রথমে পাসওয়ার্ড-বদলের স্ক্রিন। Account Expires On পার হলে লগইন বন্ধ। Status LOCKED/SEPARATED/DORMANT হলে লগইন বন্ধ, READ_ONLY হলে শুধু দেখা যাবে।
- Menu: Sub Module (module_id) বাধ্যতামূলক। কোড-seeded leaf মোছা যায় না, শুধু নিষ্ক্রিয় করা যায়।

## ৬. API ও পারমিশন

`/access/modules`, `/access/menus` (ট্রি), `/access/roles` (বিদ্যমান `/roles` সম্প্রসারণ), `/access/roles/{role}/menu-grants` (GET/PUT ম্যাট্রিক্স), `/access/users` (বিদ্যমান `/users` সম্প্রসারণ), `/access/password` (নিজের আর অন্যের, ইতিহাসসহ), `/access/lookups`, `/access/category-permissions`। পারমিশন: বিদ্যমান `admin.role.manage`, `admin.user.manage`, আর নতুন `admin.menu.manage`।

## ৭. Frontend পেজ (লাইট থিম)

Access Control মডিউল, Company Management-এর মতো Brickland-এর ট্যাব-শেল আর গ্রিড: Module Registration, Menu Definition (ট্রি + গ্রিড), Lookup Table Master, System Parameter, Role Definition, Role Menu Assignment (Role বাছাই → মেনু-ট্রি × ৭টা চেকবক্সের ম্যাট্রিক্স), User Account Registration, Password Change, Category Access Permission।

## ৮. Implementation Files

<!-- AUTO:impl:start -->
As-built (ধাপ A):

**Backend**
- `app/Modules/Identity/Database/Migrations/2026_10_21_000100_create_access_control_tables.php`
- `app/Modules/Identity/Models/MenuModule.php`
- `app/Modules/Identity/Models/Menu.php`
- `app/Modules/Identity/Models/MenuPermission.php`
- `app/Modules/Identity/Models/Role.php`
- `app/Modules/Identity/Support/MenuRegistry.php`
- `app/Modules/Identity/Actions/SyncMenus.php`
- `app/Modules/Identity/Actions/ManageRole.php`
- `app/Modules/Identity/Console/SyncMenusCommand.php`
- `app/Modules/Identity/Database/Seeders/MenuSeeder.php`
- `database/seeders/DatabaseSeeder.php`
- `app/Modules/Identity/Http/Controllers/Api/MenuAccessApiController.php`
- `app/Modules/Identity/Http/Controllers/Api/RoleApiController.php`
- `app/Modules/Identity/Routes/api.php`

**Frontend**
- `frontend/src/app/(app)/layout.js`
- `frontend/src/components/layout/menu-icon.jsx`
- `frontend/src/components/layout/module-tab-nav.jsx`
- `frontend/src/app/(app)/access-control/page.js`
- `frontend/src/app/(app)/access-control/[tab]/page.js`
- `frontend/src/app/(app)/access-control/[tab]/actions.js`
- `frontend/src/components/access/access-tabs.js`
- `frontend/src/components/access/access-tab-nav.jsx`
- `frontend/src/components/access/role-menu-matrix.jsx`
- `frontend/src/components/access/menu-definition.jsx`
- `frontend/src/components/company/company-grid.jsx`
- `frontend/src/components/company/company-tab-nav.jsx`
- `lang/en/access.php`
- `lang/bn/access.php`
- `lang/en/nav.php`
- `lang/bn/nav.php`

**Tests**
- `tests/Feature/Identity/MenuAccessTest.php`

As-built (ধাপ B):

**Backend**
- `app/Modules/Identity/Database/Migrations/2026_10_21_000200_add_account_fields_to_users.php`
- `app/Modules/Identity/Models/User.php`
- `app/Modules/Identity/Models/PasswordChange.php`
- `app/Modules/Identity/Actions/ManageCompanyUser.php`
- `app/Modules/Identity/Actions/AttemptLogin.php`
- `app/Modules/Identity/Http/Controllers/Api/UserApiController.php`
- `app/Modules/Api/Support/ApiCaller.php`
- `app/Modules/Api/Http/Controllers/Api/AuthController.php`

**Frontend**
- `frontend/src/components/access/user-accounts.jsx`
- `frontend/src/components/access/password-change.jsx`
- `frontend/src/components/account/force-password-change.jsx`
- `frontend/src/components/users/user-form.jsx`
- `frontend/src/components/users/reset-password-button.jsx`
- `frontend/src/components/auth/login-form.jsx`
- `frontend/src/app/(app)/settings/users/create/actions.js`
- `frontend/src/app/(app)/settings/users/[userId]/actions.js`
- `frontend/src/app/(app)/settings/users/[userId]/page.js`
- `frontend/src/app/(app)/account/page.js`
- `frontend/src/app/(app)/account/actions.js`
- `lang/en/user.php`
- `lang/bn/user.php`
- `lang/en/account.php`
- `lang/bn/account.php`
- `lang/en/common.php`
- `lang/bn/common.php`

**Tests**
- `tests/Feature/Identity/UserAccountRegistrationTest.php`

As-built (ধাপ C):

**Backend**
- `app/Modules/Identity/Database/Migrations/2026_10_21_000300_create_menu_overrides_table.php`
- `app/Modules/Identity/Models/MenuOverride.php`
- `app/Modules/Identity/Support/MenuOverlay.php`
- `app/Modules/Identity/Models/LookupTable.php`
- `app/Modules/Identity/Models/LookupValue.php`
- `app/Modules/Identity/Models/RoleItemCategoryPermission.php`
- `app/Modules/Identity/Support/CategoryAccess.php`
- `app/Modules/Identity/Http/Controllers/Api/MenuAccessApiController.php`
- `app/Modules/Identity/Http/Controllers/Api/LookupApiController.php`
- `app/Modules/Identity/Http/Controllers/Api/CategoryPermissionApiController.php`
- `app/Modules/Identity/Database/Seeders/LookupSeeder.php`
- `database/seeders/DatabaseSeeder.php`
- `app/Modules/Identity/Support/MenuRegistry.php`
- `app/Modules/Identity/Routes/api.php`
- `app/Modules/Settings/Http/Controllers/Api/SettingsApiController.php`

**Frontend**
- `frontend/src/app/(app)/access-control/[tab]/page.js`
- `frontend/src/app/(app)/access-control/[tab]/actions.js`
- `frontend/src/components/access/access-tabs.js`
- `frontend/src/components/access/lookup-tables.jsx`
- `frontend/src/components/access/system-parameters.jsx`
- `frontend/src/components/access/category-permission-matrix.jsx`
- `frontend/src/components/company/company-grid.jsx`
- `lang/en/access.php`
- `lang/bn/access.php`
- `lang/en/settings.php`
- `lang/bn/settings.php`

**Tests**
- `tests/Feature/Identity/AccessControlConfigurationTest.php`
- `tests/Feature/Identity/MenuAccessTest.php`

As-built (UI parity — Brickland-এর শেল আর স্ক্রিন-কিট):

**Backend**
- `app/Modules/Identity/Database/Migrations/2026_10_21_000400_add_section_to_menu_modules.php`
- `app/Modules/Identity/Support/MenuRegistry.php`
- `app/Modules/Identity/Actions/SyncMenus.php`
- `app/Modules/Identity/Models/MenuModule.php`
- `app/Modules/Identity/Actions/ManageRole.php`
- `app/Modules/Identity/Http/Controllers/Api/MenuAccessApiController.php`
- `app/Modules/Identity/Http/Controllers/Api/CategoryPermissionApiController.php`
- `app/Modules/Identity/Http/Controllers/Api/UserApiController.php`
- `app/Modules/Identity/Routes/api.php`

**Frontend**
- `frontend/src/app/globals.css`
- `frontend/src/app/(app)/layout.js`
- `frontend/src/lib/navigation.js`
- `frontend/src/lib/module-tones.js`
- `frontend/src/lib/i18n.js`
- `frontend/src/lib/i18n-server.js`
- `frontend/src/components/layout/app-shell.jsx`
- `frontend/src/components/layout/erp-sidebar.jsx`
- `frontend/src/components/layout/erp-topbar.jsx`
- `frontend/src/components/layout/erp-page-header.jsx`
- `frontend/src/components/erp/screen-tabs.jsx`
- `frontend/src/components/erp/records-grid.jsx`
- `frontend/src/components/erp/entry-form.jsx`
- `frontend/src/components/erp/status-pill.jsx`
- `frontend/src/app/(app)/access-control/page.js`
- `frontend/src/app/(app)/access-control/[tab]/page.js`
- `frontend/src/app/(app)/access-control/[tab]/screens.js`
- `frontend/src/app/(app)/access-control/[tab]/form-actions.js`
- `frontend/src/components/access/user-picker.jsx`
- `lang/en/erp.php`
- `lang/bn/erp.php`
- `lang/en/nav.php`
- `lang/bn/nav.php`
- `lang/en/access.php`
- `lang/bn/access.php`

**Tests**
- `tests/Feature/Identity/AccessControlConfigurationTest.php`
- `tests/Feature/Identity/MenuAccessTest.php`
<!-- AUTO:impl:end -->

### পরিকল্পিত নতুন/বদলানো ফাইল

_ধাপ A, B, C-র as-built তালিকা উপরে।_

## ৯. টেস্ট ও যাচাই

- `tests/Feature/Identity/MenuAccessTest.php`: মেনু-ট্রি, সাইডবার, grant-ম্যাট্রিক্স, Menu Definition
- `tests/Feature/Identity/UserAccountRegistrationTest.php`: অ্যাকাউন্ট-ফিল্ড, User ID দিয়ে লগইন, অবস্থা, পাসওয়ার্ড-নীতি আর ইতিহাস
- `tests/Feature/Identity/AccessControlConfigurationTest.php`: কোম্পানি-ভিত্তিক override, লুকআপ, প্যারামিটার, ক্যাটাগরি-পারমিশন
- `tests/Feature/Identity/ApiGateCoverageTest.php`: নতুন প্রতিটা endpoint গেট-করা

## ১০. খোলা প্রশ্ন

_সমাধান হয়েছে (ব্যবহারকারী, ২০২৬-১০-০৯):_
- Menu × ৭টা ফ্ল্যাগ, পারমিশন-ইঞ্জিনের ওপর একটা স্তর হিসেবে; সাইডবার DB-র মেনু-ট্রি থেকে: **অনুমোদিত**।
- 2FA: এখন শুধু ফিল্ড (`two_factor_method`), আসল OTP/Authenticator যাচাই পরে আলাদা নিরাপত্তা-ধাপে।
- Memorable Word: **রাখা হবে**, হ্যাশ করে। অ্যাডমিন পাসওয়ার্ড রিসেট করার সময় ব্যবহারকারীর পরিচয় যাচাইয়ে লাগবে।

## ১১. As-built নোট

**ধাপ A সম্পন্ন (২০২৬-১০-০৯): মেনু-ট্রি, DB-চালিত সাইডবার, Role Definition, Role Menu Assignment, Menu Definition।**
- **`MenuRegistry`** (কোড) → `access:sync-menus` (প্রতি deploy-এ চালাতে হবে; `MenuSeeder` হিসেবে `DatabaseSeeder`-এও আছে) → `menu_modules` (১১টা), `menus` (৮৪টা), `menu_permissions` (২০৪টা)। ১৪৫টা পারমিশনের প্রতিটা অন্তত একটা মেনুর কোনো ফ্ল্যাগে আছে (টেস্টে যাচাই করা)। Sync বারবার চালানো যায়: অ্যাডমিনের দেওয়া নাম, বাংলা নাম, ক্রম আর Active বদলায় না; কোডের url, parent আর ফ্ল্যাগ-ম্যাপ বদলায়; কোড থেকে বাদ পড়া স্ক্রিন নিষ্ক্রিয় হয়, মোছে না।
- **ফ্ল্যাগ কীভাবে আসে:** V = স্ক্রিনের নিজের API-চেকের পারমিশন (তাই সাইডবারে কে কী দেখে, তা আগের মতোই)। বাকি ফ্ল্যাগ আসে স্ক্রিনের পারমিশন-ফ্যামিলি (prefix) থেকে, পারমিশনের শেষ শব্দ দিয়ে: create/request→C, delete→D, approve/reject/post/verify/publish→A, print→P, export→X, বাকি সব কাজ→E।
- **সাইডবার** এখন `GET /api/v1/access/navigation` থেকে আসে (মডিউল → ফোল্ডার → স্ক্রিন, কলারের V-পারমিশন অনুযায়ী ফিল্টার করা, বাংলা নামসহ)। `layout.js`-এর হাতে-লেখা নেভিগেশন আর `/auth/permissions` কল সরানো হয়েছে। আইকন lucide-এর নাম থেকে (`MenuIcon`)।
- **Role Menu Assignment:** `GET/PUT /api/v1/access/roles/{role}/menu-grants`। সেভ করলে টিক দেওয়া ফ্ল্যাগের সব পারমিশন দেওয়া হয়, আর যে পারমিশন কোনো মেনুতে নেই সেটা রোলের যেমন ছিল তেমন থাকে। System রোল এডিট করা যায় না (403, "Roles-এ কপি করুন")। Admin রোল (`is_admin`) সব পারমিশন পায়, তাই ম্যাট্রিক্সে সব টিক দেওয়া আর লক করা। UI-তে কোনো ফ্ল্যাগ টিক দিলে V নিজে থেকে টিক হয়, আর V সরালে পুরো সারি খালি হয়।
- **Role Definition:** বিদ্যমান `/roles` API-তে নতুন ফিল্ড: name_bn, short_name, rank, is_admin, active। Code = Spatie-র `name`, Name [E] = `description`। গ্রিডে System রোল শুধু দেখা যায়।
- **Menu Definition:** `GET /api/v1/access/menus`, `PATCH /api/v1/access/menus/{menu}` (নাম, বাংলা নাম, ক্রম, Active, আইকন, security level)। URL বদলানো যায় না।
- **ধাপ C** নিচে।
- **পরিবর্তন যা চোখে পড়বে:** Production Orders আর Cutting Programmes পেজ আগে থেকেই ছিল কিন্তু সাইডবারে ছিল না; এখন "Merchandise Desk"-এর নিচে দেখা যায়।

**ধাপ B সম্পন্ন (২০২৬-১০-০৯): User Account Registration, Password Change, User ID দিয়ে লগইন, অ্যাকাউন্টের অবস্থা।**
- **User Account Registration** (`/access-control/user-account`): Brickland-এর Records গ্রিড (User Type, User ID, Name, Mail, Emp.Code, Roles, 2FA, Last Sign-in, Status) আর সার্চ। "New user account" আর এডিট একই ইউজার-ফর্ম খোলে (`/settings/users/create`, `/settings/users/{id}/edit`), যেখানে নতুন "Account" অংশ যোগ হয়েছে: User ID, User Type, Employee Code, Account Status, Account Expires On, Two-Factor Method, Password + Confirm (শুধু তৈরির সময়; খালি রাখলে আগের মতো তৈরি হয়ে একবার দেখায়), Memorable Word + Confirm (এডিটে খালি = আগেরটা থাকে), ৩টা চেকবক্স, Justification। User ID ছোট হাতের অক্ষরে সেভ হয়, ইউনিক।
- **লগইন:** ইমেইল অথবা User ID (`AttemptLogin`: email বা username)। লগইন ফর্মের লেবেল "Email or User ID"। `ACTIVE`, `READ_ONLY`, `PASSWORD_EXPIRING` লগইন করতে পারে; `DORMANT`, `LOCKED`, `SEPARATED` আর মেয়াদ-পার অ্যাকাউন্ট পারে না।
- **READ_ONLY:** লগইন করে শুধু দেখতে পারে। `ApiCaller::can` V-ফ্ল্যাগ ছাড়া সব পারমিশন (`MenuRegistry::flagFor`) অস্বীকার করে, তাই সব তৈরি/এডিট/অনুমোদন 403।
- **বাধ্যতামূলক পাসওয়ার্ড-বদল:** `must_change_password` (বা `PASSWORD_EXPIRING`) থাকলে `/auth/me` → `must_change_password: true`, আর লেআউটের `ForcePasswordChange` প্রতিটা স্ক্রিন থেকে `/account`-এ পাঠায় (সেখানে সতর্কবার্তা)। নিজে বদলালে ফ্ল্যাগ মুছে যায়, `PASSWORD_EXPIRING` → `ACTIVE`। "User can not change password" থাকলে নিজের বদল 403।
- **Password Change** (`/access-control/password-change`): অ্যাডমিন অ্যাকাউন্ট বাছাই করে নতুন পাসওয়ার্ড দেন, "Force a change at next sign-in" সহ (`POST /api/v1/users/{user}/password`)। Records গ্রিড `GET /api/v1/access/password-changes`: Account, Name, পরিবর্তনের ধরন (CREATED / SELF / ADMIN_SET / ADMIN_RESET), বাধ্যতামূলক কিনা, কখন, কে। পাসওয়ার্ড কখনো রাখা বা দেখানো হয় না।
- **Memorable Word:** হ্যাশ করে রাখা। অ্যাকাউন্টে থাকলে "Reset password" (তৈরি করে একবার দেখানো) করার আগে অ্যাডমিনকে এটা লিখতে হয়; না মিললে 422। রিসেট পরের লগইনে বদল বাধ্য করে।
- **2FA:** শুধু ফিল্ড (`two_factor_method`), যাচাই পরের নিরাপত্তা-ধাপে (ব্যবহারকারীর সিদ্ধান্ত)।
- **মেনু:** `ACCESS.USER_ACCOUNT`, `ACCESS.PASSWORD_CHANGE` (in_sidebar=false, Access Control ট্যাব)। সিঙ্কের পরে ৮৬টা মেনু, ২০৬টা ফ্ল্যাগ-পারমিশন।
- **যাচাই:** `UserAccountRegistrationTest` (৬টা টেস্ট, ৩৯টা assertion) পাস। ব্রাউজারে দেখা হয়েছে: দুটো ট্যাব রেন্ডার হয়; must-change অ্যাকাউন্ট `/` বা `/assets` খুললে `/account`-এ যায়; UI থেকে বদলানোর পরে মুক্ত হয় আর ইতিহাসে SELF আসে।
- **একটা বাগ ধরা পড়ে ঠিক করা হয়েছে:** `invite()`-এ `[...] + account()` থাকায় ডিফল্ট `status => ACTIVE` বেছে নেওয়া Account Status-কে ঢেকে দিচ্ছিল; এখন `array_merge`।

**ধাপ C সম্পন্ন (২০২৬-১০-০৯): Module Registration, Lookup Table Master, System Parameter Update, Category Access Permission; মেনু-সম্পাদনা এখন কোম্পানি-ভিত্তিক।**
- **একটা নিরাপত্তা-ত্রুটি ঠিক করা হয়েছে (ধাপ A থেকে):** Menu Definition-এর সম্পাদনা শেয়ার করা `menus` সারিতেই লেখা হচ্ছিল, তাই এক কোম্পানি কোনো মেনুর নাম বদলালে বা লুকালে সেটা সব কোম্পানির জন্য বদলে যেত। এখন সম্পাদনা যায় **`menu_overrides`**-এ (company_id + menu_id অথবা menu_module_id; null কলাম মানে কোডের মান মানা)। `MenuOverlay` পড়ার সময় কোম্পানির override মেমোরিতে বসায়, শেয়ার করা সারিতে কখনো লেখে না। কোডের মানই ফেরত পাঠালে override null হয়ে যায়, ফলে আবার কোডের মান মানে। এক কোম্পানির override অন্য কোম্পানির সাইডবারে পৌঁছায় না (টেস্টে যাচাই করা)।
- **Module Registration** (`/access-control/module-registration`): গ্রিডে Code, Name, বাংলা নাম, Icon, Order, Description, Active, মেনু-সংখ্যা। এডিট হয় কোম্পানির override হিসেবে (`GET /access/modules`, `PATCH /access/modules/{module}`)। **Brickland থেকে বিচ্যুতি:** "New module" নেই। মডিউল আর স্ক্রিন কোড (`MenuRegistry`) থেকে আসে, কোড ছাড়া একটা নতুন মডিউল খালিই থাকত। Menu Definition-এর "New menu"-ও একই কারণে নেই।
- **Lookup Table Master** (`/access-control/lookup-table?table=`): দুই প্যানে টেবিল আর বাছাই করা টেবিলের মান। সিস্টেম টেবিল (company_id null) সব কোম্পানির জন্য একই আর শুধু দেখা যায়। `LookupSeeder` দেয় SHIFT, PAYMENT_TERM, SHIPMENT_MODE, FABRIC_TYPE (বাংলা নামসহ); UOM আলাদা মাস্টার ডাটা, তাই এখানে নেই। কোম্পানি নিজের টেবিল বানাতে পারে, আর সিস্টেম টেবিলেও নিজের মান যোগ করতে পারে, যা অন্য কোম্পানি দেখে না। কোড বড় হাতের অক্ষরে সেভ হয়; টেবিল-কোড দেখা যায় এমন টেবিলগুলোর মধ্যে ইউনিক, মান-কোড টেবিলের ভেতরে ইউনিক। শেয়ার করা টেবিল বা মান বদলাতে গেলে 403, অন্য কোম্পানির জিনিস খুঁজলে 404। API: `/access/lookups` CRUD, `/access/lookups/{table}/values` CRUD; গেট `masterdata.manage`। **বাকি:** ফর্মের ড্রপডাউনে মান পড়ার endpoint (`GET /lookups/{code}`), প্রথম যে ফর্ম এটা ব্যবহার করবে তার সাথে আসবে।
- **System Parameter Update** (`/access-control/system-parameter`): বিদ্যমান সেটিংস-ইঞ্জিনের ওপর Brickland-এর Records গ্রিড। কলাম: Group (কী-র প্রথম অংশ, অনুবাদসহ), Key, Parameter (বিবরণসহ), Value (ধরন অনুযায়ী ইনপুট), Type, Source (Company/Factory/Default), Updated (কখন আর কে; `GET /settings`-এ এখন `updated_at`, `updated_by` আছে)। সারি ধরে Save, আর "Reset to default" দিলে কোম্পানির মান মুছে ডিফল্টে ফেরে। **বিচ্যুতি:** "New parameter" নেই, কারণ প্যারামিটার আসে যে কোড এটা পড়ে সেখান থেকে (অজানা কী সেট করা যায় না, ADR-054)। ফ্যাক্টরি-ভিত্তিক মান আগের মতো Settings → Company settings-এ।
- **Category Access Permission** (`/access-control/category-permission?role=`): রোল বাছাই করলে কোম্পানির আইটেম-ক্যাটাগরি ট্রি দেখায় (ভাঁজ করা যায়, সার্চ, "শুধু টিক দেওয়াগুলো" ফিল্টার, প্রতি পাতায় ৫০টা; ডেভ ডাটায় উপরের স্তরেই ৩১৩টা ক্যাটাগরি)। View/Create/Edit/Approve টিক দেওয়া যায়; Create/Edit/Approve দিলে View নিজে থেকে টিক হয়, আর কোনো টিক না থাকলে সারিই থাকে না। রোলের কোনো সারি না থাকলে রোলটা ক্যাটাগরি দিয়ে সীমাবদ্ধ নয়। সারি কোম্পানির নিজের, তাই সিস্টেম রোলও নিজের কোম্পানিতে সীমাবদ্ধ করা যায়; অন্য কোম্পানির ক্যাটাগরি দেওয়া যায় না (422)। API: `GET/PUT /access/roles/{role}/category-permissions`।
- **`CategoryAccess`** (প্রয়োগের প্রশ্ন): `allowedCategoryIds(user, action)` ফেরত দেয় null (সীমাবদ্ধ নয়) অথবা অনুমোদিত ক্যাটাগরির id, সাব-ক্যাটাগরিসহ, ইউজারের সব রোলের union। **বাকি:** প্রয়োগ। স্টোর মডিউলের স্ক্রিনগুলো (আইটেম তালিকা, রিকুইজিশন, রিসিভ, ইস্যু) বানানোর সময় এটা ডাকবে; তার আগে টিক শুধু সেভ হয়, কিছু আটকায় না।
- **মেনু:** `ACCESS.MODULE_REGISTRATION`, `ACCESS.LOOKUP_TABLE`, `ACCESS.SYSTEM_PARAMETER`, `ACCESS.CATEGORY_PERMISSION` (ট্যাব, in_sidebar=false)। ট্যাবের ক্রম Brickland-এর মতো: Module Registration, Menu Definition, Lookup Table, System Parameter, Role Definition, Role Menu Assignment, User Account Registration, Password Change, Category Access Permission। সিঙ্কের পরে ৯০টা মেনু, ২১০টা ফ্ল্যাগ-পারমিশন।
- **যাচাই:** `AccessControlConfigurationTest`-এ ৬টা টেস্ট (মডিউল-override এক কোম্পানিতেই সীমাবদ্ধ; কোডের মান ফেরত দিলে override সরে যায়; লুকআপের শেয়ার/নিজস্ব নিয়ম আর আলাদা থাকা; প্যারামিটারের হালনাগাদ-রেকর্ড আর রিসেট; ক্যাটাগরি-সীমাবদ্ধতা আর সাব-ক্যাটাগরি; গেট)। `MenuAccessTest` override-এর সাথে মিলিয়ে আপডেট করা হয়েছে। ব্রাউজারে চারটা ট্যাব দেখা হয়েছে, আর ক্যাটাগরি টিক → Save → রিলোড পরীক্ষা করা হয়েছে।
- **টেস্টে ধরা পড়ে ঠিক করা:** Laravel রুট কন্ট্রোলার-ইনস্ট্যান্স রেখে দেয়, তাই `MenuOverlay`-এর ক্যাশ এক কোম্পানির রিকোয়েস্ট থেকে পরের কোম্পানির রিকোয়েস্টে চলে যাচ্ছিল। এখন ক্যাশ কোম্পানি ধরে রাখা হয়।

**UI-তে হুবহু Brickland (২০২৬-১০-১০, ব্যবহারকারীর মনে করিয়ে দেওয়ার পরে):** ধাপ A-C-র স্ক্রিনগুলো আমার নিজের লেআউটে ছিল (এক পাতায় সব স্ক্রিন ট্যাব হিসেবে)। এখন Brickland-এর লাইট থিমের স্ক্রিনশট (`tools/erp_crawler/light_shots.mjs`) মিলিয়ে নতুন করে সাজানো হয়েছে। নিয়ম লেখা আছে `00_Conventions.md` §৭-এ।
- **শেল (পুরো অ্যাপের):**
  - সাইডবার: ভাগ (OVERVIEW, SYSTEM ADMINISTRATION, HUMAN RESOURCE …) → মডিউল (স্ক্রিন-সংখ্যা) → ফোল্ডার (সংখ্যা) → স্ক্রিন।
  - টপবার: মডিউল-চিপ, স্ক্রিন সার্চ (Ctrl K), কোম্পানি-চিপ, থিম, ভাষা, নোটিফিকেশন, ডানে ইউজার-মেনু।
  - সাইডবারের নিচে System Status কার্ড।
  - পাতার মাথা: Home › Modules › মডিউল › স্ক্রিন, আইকন-টাইল, "NN of MM"।
  - রঙ: ইন্ডিগো অ্যাকসেন্ট (`--brand` #4f46e5)।
- **ডাটা:**
  - `menu_modules.section` কলাম যোগ হয়েছে, আর `MenuRegistry::SECTIONS` রাখে Brickland-এর ক্রম।
  - Access Control-এর ফোল্ডার STRUCTURE (৪টা স্ক্রিন) আর ACCESS CONTROL (৫টা স্ক্রিন)। প্রতিটা স্ক্রিন এখন সাইডবারে আলাদা লিংক; পুরনো `ACCESS.HOME` আর সাইডবারের "Users" সরানো হয়েছে।
  - `GET /access/navigation`-এর নতুন আকার: `section`, `count`, `entries` (ক্রম অনুযায়ী screen বা folder)।
  - SyncMenus এখন শেয়ার করা সারির নাম, ক্রম আর active পুরোপুরি কোড থেকে লেখে, কারণ কোম্পানির পরিবর্তন থাকে override-এ। কোড থেকে বাদ পড়া (inactive) সারি আর কোথাও দেখায় না।
- **স্ক্রিন-কিট (`components/erp/`):**
  - `ScreenTabs`: Records N | স্ক্রিনের নিজস্ব ট্যাব | + New X, `?tab=` দিয়ে।
  - `RecordsGrid`: সার্চ, Export, সাজানো যায় এমন কলাম, Status-পিল, Edit-ডায়ালগ আর মোছা, পেজার ও items per page, রিফ্রেশ। কলাম ডাটা হিসেবে আসে, `type` দিয়ে।
  - `EntryForm`: ফর্ম-কার্ড আর আলাদা অ্যাকশন-কার্ড (Reset, "Save X")। একই ফর্ম New ট্যাবে আর Edit ডায়ালগে চলে।
- **Access Control-এর ৯টা স্ক্রিন (প্রতিটা আলাদা রুট `/access-control/<screen>`, Brickland-এর ট্যাবসহ):**
  - Module Registration: Records
  - Menu Definition: Records | Menu Tree
  - Lookup Table: Records | Lookup Tables | New lookup table
  - System Parameter: Records | System Setting (বাঁয়ে গ্রুপ)
  - Role Definition: Records | New role
  - Role Menu Assignment: Records | Menu Assign | New menu grant
  - User Account Registration: Records | User Roles | New user account
  - Password Change: Records | Change password
  - Category Access Permission: Records | Item Category Permission | New category permission
- **নতুন API:**
  - `GET /access/menu-grants`: রোল × মেনু × অধিকারের Records।
  - `PUT /access/roles/{role}/menu-grants/{menu}`: একটা মেনুর অধিকার। ফ্ল্যাগ সরালে তার পারমিশন বাদ যায়, তবে যে পারমিশন আরেকটা মেনুরও লাগে সেটা থাকে।
  - `GET /access/category-permissions`, `PUT /access/roles/{role}/category-permissions/{category}`।
  - ইউজারের সারাংশে `department_id`, `role_ids`, `factory_id` (Edit ডায়ালগের জন্য)।
- **Brickland থেকে বিচ্যুতি, কারণসহ:**
  - User-এর "System Admin" চেকবক্স নেই (অ্যাডমিন হয় রোল দিয়ে)।
  - New user account-এ Factory ফিল্ড আছে, কারণ ফ্যাক্টরি-স্কোপড রোলে এটা লাগে।
  - Report Assign ট্যাব নেই (রিপোর্ট-মেনু আসবে MIS-এর সাথে)।
  - "New module", "New menu", "New parameter" নেই (কোডের সাথে আসে)।
  - পারমিশন শেয়ার হয়, তাই এক স্ক্রিনে V দিলে একই পারমিশনে চলা অন্য স্ক্রিনেও V আসে (যেমন `masterdata.manage`)।
- **যাচাই:** `AccessControlConfigurationTest`-এ ৪টা নতুন টেস্ট (সাইডবারের আকার, একক মেনু-অধিকারে শেয়ার করা পারমিশন রক্ষা, ক্যাটাগরি Records আর একক সারি, Company ড্যাশবোর্ড)। ব্রাউজারে New → Records, Edit-ডায়ালগ, মোছা, বাধ্যতামূলক ফিল্ডের মেসেজ পরীক্ষা করা হয়েছে; কনসোলে কোনো error নেই।
- **পথে ধরা পড়ে ঠিক করা:**
  - i18n-এ `:page` বদলাতে গিয়ে `:pages`-এর শুরুও বদলে যাচ্ছিল ("1s", "4tal")। এখন লম্বা প্লেসহোল্ডার আগে বদলায়, Laravel-এর মতো।
  - EntryForm render-এর সময় toast আর রাউটার চালাচ্ছিল, যাতে React সতর্ক করছিল; এখন `useEffect`-এ।
