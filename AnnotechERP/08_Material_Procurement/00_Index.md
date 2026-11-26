# Material Procurement

> **ফেজ ৩:** সাপ্লাই ও স্টোর · **ফোল্ডার:** `08_Material_Procurement`

**⬅ ফিরে যান:** [AnnotechERP Index](../00_Index.md)

<!-- AUTO:status:start -->
| কী | গ্রুপ | স্ক্রিন | স্ট্যাটাস |
|---|---|---|---|
| `08/01` | [Master Data](01_Master_Data.md) | ৩ | ✅ সম্পন্ন |
| `08/02` | [Dashboard](02_Dashboard.md) | ১ | ✅ সম্পন্ন (Procurement Dashboard চালু) |
| `08/03` | [Purchase Requisition](03_Purchase_Requisition.md) | ২ | ✅ সম্পন্ন (২টা স্ক্রিনই চালু: Create Requisition, My Requisitions) |
| `08/04` | [Supplier Portal](04_Supplier_Portal.md) | ৩ | ✅ সম্পন্ন (৩টা স্ক্রিনই চালু: Supplier Dashboard, Available Requirements, Quotation History) |
| `08/05` | [Price Review & Approval](05_Price_Review_Approval.md) | ১ | ✅ সম্পন্ন (Price Review & Approval চালু) |
| `08/06` | [Approved Material & Follow-Up](06_Approved_Material_Follow_Up.md) | ২ | ✅ সম্পন্ন (Assigned Materials, Delivery Tracking চালু) |
| `08/07` | [Control](07_Control.md) | ৩ | ✅ সম্পন্ন (Notifications, Audit Trail, Supplier Portal Access চালু) |
<!-- AUTO:status:end -->

## নোট

- **নিজের টেবিল:** Machinery Maintenance বাদে সবকিছু নতুন। সাপ্লায়ার (`procurement_suppliers`) আর ক্যাটালগ (`procurement_items`) Material Procurement-এর নিজের। Supplier Portal, কোটেশন আর assignment এগুলোর সাথেই যুক্ত হবে, Machinery-র `vendors`-এর সাথে নয়।
- মেনুতে মডিউল কোড `MATERIAL`, রুট `/material-procurement/*`। আগের `PROCUREMENT` মডিউলের PR, PO আর GRN এই মডিউলের পরের ধাপে নতুন করে গড়ার সময় এখানে আসবে।
