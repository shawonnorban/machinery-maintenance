<?php

declare(strict_types=1);

namespace App\Modules\Identity\Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;

/**
 * The permission catalog: Handbook Section 2.
 *
 * Codes follow {module}.{resource}.{action} (SRS 5.2). A permission absent
 * from this catalog cannot be granted, and a permission granted to no seeded
 * role is dead code that a seed test fails on (Seed Catalog 13).
 */
class PermissionSeeder extends Seeder
{
    /**
     * @return array<string, array<string, array{0: string, 1?: bool}>>
     *                                                                  module => [code => [label, isElevated]]
     */
    public static function catalog(): array
    {
        return [
            'asset' => [
                'asset.asset.view_any' => ['View assets'],
                'asset.asset.view' => ['View an asset'],
                'asset.asset.create' => ['Create assets'],
                'asset.asset.update' => ['Update assets'],
                'asset.asset.delete' => ['Archive assets'],
                'asset.status.update' => ['Change asset status'],
                'asset.transfer.create' => ['Request asset transfer'],
                'asset.transfer.approve' => ['Approve asset transfer'],
                'asset.transfer.receive' => ['Receive transferred asset'],
                'asset.document.manage' => ['Manage asset documents'],
                'asset.qr.regenerate' => ['Regenerate asset QR token', true],
                'asset.financial.view' => ['View asset cost and book value'],
            ],
            'maintenance' => [
                'maintenance.plan.view_any' => ['View maintenance plans'],
                'maintenance.plan.create' => ['Create maintenance plans'],
                'maintenance.plan.update' => ['Update maintenance plans'],
                'maintenance.plan.delete' => ['Delete maintenance plans'],
                'maintenance.plan.activate' => ['Activate or deactivate plans'],
                'maintenance.template.view_any' => ['View templates'],
                'maintenance.template.create' => ['Create templates'],
                'maintenance.template.update' => ['Update templates'],
                'maintenance.template.publish' => ['Publish a template version'],
                'maintenance.schedule.view_any' => ['View maintenance schedules'],
                'maintenance.schedule.reschedule' => ['Reschedule maintenance'],
                'maintenance.schedule.skip' => ['Skip scheduled maintenance'],
            ],
            'metering' => [
                'meter.reading.view_any' => ['View meter readings'],
                'meter.reading.create' => ['Record meter readings'],
                'meter.meter.manage' => ['Manage asset meters'],
                'meter.meter.reset' => ['Reset a meter', true],
            ],
            'work_order' => [
                'work_order.work_order.view_any' => ['View work orders'],
                'work_order.work_order.view' => ['View a work order'],
                'work_order.work_order.create' => ['Create work orders'],
                'work_order.work_order.update' => ['Update work orders'],
                'work_order.work_order.assign' => ['Assign work orders'],
                'work_order.work_order.start' => ['Start, hold and resume work'],
                'work_order.work_order.complete' => ['Complete work orders'],
                'work_order.work_order.verify' => ['Verify completed work'],
                'work_order.work_order.close' => ['Close work orders'],
                'work_order.work_order.cancel' => ['Cancel work orders'],
                'work_order.work_order.reopen' => ['Reopen a closed work order', true],
                'work_order.labor.manage' => ['Record labor time'],
                'work_order.part.request' => ['Request parts on a work order'],
                'work_order.cost.view' => ['View work order costs'],
            ],
            'breakdown' => [
                'breakdown.breakdown.view_any' => ['View breakdowns'],
                'breakdown.breakdown.view' => ['View a breakdown'],
                'breakdown.breakdown.create' => ['Report a breakdown'],
                'breakdown.breakdown.acknowledge' => ['Acknowledge a breakdown'],
                'breakdown.breakdown.assign' => ['Assign a breakdown'],
                'breakdown.breakdown.repair' => ['Record repair progress'],
                'breakdown.breakdown.verify' => ['Verify a repaired breakdown'],
                'breakdown.breakdown.close' => ['Close a breakdown'],
            ],
            'inventory' => [
                'inventory.part.view_any' => ['View spare parts'],
                'inventory.part.create' => ['Create spare parts'],
                'inventory.part.update' => ['Update spare parts'],
                'inventory.stock.view' => ['View stock balances and valuation'],
                'inventory.stock.receive' => ['Receive stock'],
                'inventory.stock.issue' => ['Issue and consume stock'],
                'inventory.stock.return' => ['Return stock'],
                'inventory.reservation.manage' => ['Reserve and release stock'],
                'inventory.adjustment.create' => ['Adjust stock', true],
                'inventory.transfer.create' => ['Request stock transfer'],
                'inventory.transfer.approve' => ['Approve stock transfer'],
                'inventory.transfer.dispatch' => ['Dispatch stock transfer'],
                'inventory.transfer.receive' => ['Receive stock transfer'],
            ],
            'cost' => [
                'cost.entry.view' => ['View cost entries'],
                'cost.entry.create' => ['Create cost entries'],
                'cost.entry.reverse' => ['Reverse a posted cost entry', true],
            ],
            'itemmaster' => [
                'itemmaster.item.view_any' => ['View the item library'],
                'itemmaster.item.manage' => ['Maintain the item library'],
                'itemmaster.roll.view_any' => ['View fabric rolls'],
                'itemmaster.roll.inspect' => ['Inspect fabric rolls at receiving'],
                'itemmaster.roll.split' => ['Split and merge fabric rolls'],
                'itemmaster.inspection.approve' => ['Accept or reject an inspected fabric lot', true],
                'itemmaster.subcontract.view_any' => ['View subcontract challans'],
                'itemmaster.subcontract.manage' => ['Send fabric out for subcontract and take it back'],
                'itemmaster.count.view_any' => ['View stock counts'],
                'itemmaster.count.create' => ['Open stock counts and record what was found'],
                'itemmaster.count.post' => ['Post a count\'s adjustment to the ledger', true],
                'itemmaster.reservation.view_any' => ['See who is holding stock'],
                'itemmaster.reservation.manage' => ['Hold stock by hand and release it'],
            ],
            'procurement' => [
                // Material Procurement → Master Data (AnnotechERP 08/01).
                'material.master.view' => ['View procurement master data'],
                'material.master.manage' => ['Manage procurement master data'],
                // Material Procurement → Purchase Requisition (AnnotechERP 08/03).
                'material.requisition.view' => ['View purchase requisitions and their lines'],
                'material.requisition.manage' => ['Raise, edit, submit and delete draft purchase requisitions'],
                // Material Procurement → Control → Supplier Portal Access, and the portal itself (08/07, 08/04).
                'material.portal_access.manage' => ['Create supplier portal logins and switch them on or off'],
                'material.portal.view' => ['Use the Supplier Portal as a supplier'],
                // Material Procurement → Price Review & Approval (08/05): sees supplier prices.
                'material.price.approve' => ['Review supplier prices, negotiate, reject, and approve and hand over'],
                // Material Procurement → Approved Material & Follow-Up (08/06).
                'material.followup.view' => ['Follow up the approved materials handed to you and raise their goods receipts'],
                'material.followup.monitor' => ['Monitor every approved material and its delivery tracking'],
                // Material Procurement → Control → Audit Trail (08/07).
                'material.audit.view' => ['Read the module\'s permanent audit trail of every critical transaction'],
                // Inventory & Stores → Store & Material (09).
                'storematerial.view' => ['View the material store — dashboard, warehouses, ledger, traceability and reports'],
                'storematerial.receive' => ['Receive material in-house — GRN against an approved PR or direct'],
                'storematerial.issue' => ['Issue material to the floor and take returns back'],
                'storematerial.adjust' => ['Count stock and ask for an adjustment'],
                'storematerial.manage' => ['Store Manager: reverse a GRN, approve adjustments and set minimum stock'],
                'procurement.requisition.view_any' => ['View purchase requisitions'],
                'procurement.requisition.create' => ['Raise and submit purchase requisitions'],
                'procurement.order.view_any' => ['View purchase orders'],
                'procurement.order.create' => ['Raise, revise and close purchase orders'],
                'procurement.receipt.view_any' => ['View goods receipts'],
                'procurement.receipt.create' => ['Draft goods receipts'],
                'procurement.receipt.complete' => ['Complete goods receipts and post stock'],
                'procurement.booking.view_any' => ['View fabric bookings'],
                'procurement.booking.manage' => ['Raise, confirm and convert fabric bookings'],
                'procurement.landed_cost.view_any' => ['View landed cost vouchers'],
                'procurement.landed_cost.manage' => ['Draft landed cost vouchers'],
                'procurement.landed_cost.post' => ['Post landed cost and re-value stock', true],
                'procurement.return.view_any' => ['View purchase returns'],
                'procurement.return.create' => ['Raise purchase returns'],
                'procurement.return.dispatch' => ['Send returned goods out and raise the debit note', true],
                'procurement.claim.view_any' => ['View supplier claims'],
                'procurement.claim.manage' => ['Raise and settle supplier claims'],
                'procurement.advance.view_any' => ['View supplier advances'],
                'procurement.advance.manage' => ['Record and adjust supplier advances'],
                'procurement.scorecard.view_any' => ['View supplier scorecards'],
                'procurement.scorecard.calculate' => ['Re-score suppliers and move their rating', true],
                'procurement.contract.view_any' => ['View rate contracts'],
                'procurement.contract.manage' => ['Agree and activate rate contracts', true],
                'procurement.import.view_any' => ['View proforma invoices, letters of credit and shipments'],
                'procurement.import.manage' => ['Maintain import documents and shipment tracking'],
            ],
            'finance' => [
                'finance.gl.view_any' => ['View GL postings and the event mapping'],
                'finance.gl.manage' => ['Point business events at accounts in the chart', true],
            ],
            'compliance' => [
                'compliance.bond.view_any' => ['View bond licences and utilisation declarations'],
                'compliance.bond.manage' => ['Maintain bond licences and declarations', true],
                'compliance.duty.assess' => ['Assess duty on goods leaving the bond', true],
            ],
            'production' => [
                'production.order.view_any' => ['View orders, styles and bills of materials'],
                'production.order.manage' => ['Maintain orders, styles, BOMs and cutting programmes'],
                'production.issue.create' => ['Issue materials to a cutting programme'],
            ],
            'requisition' => [
                'requisition.floor.view_any' => ['View every floor requisition'],
                'requisition.floor.create' => ['Raise floor requisitions'],
                'requisition.floor.issue' => ['Review, issue and close floor requisitions'],
            ],
            'vendor' => [
                'vendor.vendor.view_any' => ['View vendors'],
                'vendor.vendor.create' => ['Create vendors'],
                'vendor.vendor.update' => ['Update vendors'],
                'vendor.warranty.manage' => ['Manage warranties and claims'],
                'vendor.contract.manage' => ['Manage service contracts'],
            ],
            'technician' => [
                'technician.technician.manage' => ['Manage technicians'],
                'technician.performance.view' => ['View individual technician KPIs'],
            ],
            'admin' => [
                'admin.user.manage' => ['Manage users'],
                'admin.role.manage' => ['Manage roles and assignments'],
                'admin.team.manage' => ['Manage teams'],
                'admin.api_client.manage' => ['Manage API clients', true],
            ],
            'settings' => [
                'settings.company.manage' => ['Manage company settings'],
                'settings.device.view' => ['View attendance devices, their punches, enrolled users and command queue'],
                'settings.device.manage' => ['Register attendance devices, send them commands and post their punches to HR'],
                'settings.factory.manage' => ['Manage factory settings'],
                'settings.calendar.manage' => ['Manage shifts, holidays and calendars'],
                'settings.numbering.manage' => ['Manage document numbering'],
                // Settings → Backup & Restore (AnnotechERP 03/04): writing a
                // backup back over the company's data. The owner's alone.
                'settings.backup.restore' => ['Restore a company backup', true],
            ],
            // General Store → Master Data (AnnotechERP 10/01).
            'store' => [
                'store.master.view' => ['View general store master data'],
                'store.master.manage' => ['Manage general store master data'],
                // General Store → Inventory and Dashboard (AnnotechERP 10/02–10/03).
                'store.inventory.view' => ['View general store stock, documents and ledger'],
                'store.inventory.post' => ['Post general store opening, receive, issue, return, adjustment and transfer'],
                'store.inventory.cancel' => ['Cancel and reverse a posted general store document'],
                // General Store → Requisition and Administration (AnnotechERP 10/04–10/07).
                'store.requisition.create' => ['Raise and submit a general store requisition'],
                'store.requisition.approve' => ['Approve, reject or cancel general store requisitions'],
                'store.audit.view' => ['View the general store audit log'],
                'store.settings.manage' => ['Manage general store settings'],
            ],
            // Gate Pass → Master Data (AnnotechERP 11/01).
            'gatepass' => [
                'gatepass.master.view' => ['View gate pass master data'],
                'gatepass.master.manage' => ['Manage gate pass master data'],
                // Dashboard, Reports, Create, Approval, Out, In, Reconciliation and List (11/02–11/04).
                'gatepass.view' => ['View gate passes, the gate pass dashboard and reports'],
                'gatepass.create' => ['Prepare and submit gate passes'],
                'gatepass.approve' => ['Approve, reject, send back or cancel gate passes and close short returns'],
                'gatepass.gate' => ['Confirm Gate OUT and Gate IN at the gate'],
            ],
            // Capacity Planning → Master Data (AnnotechERP 12/01).
            'planning' => [
                'planning.master.view' => ['View capacity planning master data'],
                'planning.master.manage' => ['Manage capacity planning master data'],
                // Production Plan (12/02).
                'planning.plan.view' => ['View projections, master plans, daily plans and capacity'],
                'planning.plan.manage' => ['Prepare master plans, request revisions and keep daily plans'],
                'planning.plan.approve' => ['Confirm and lock master plans and decide revision requests'],
            ],
            // Dye House → Master Data (AnnotechERP 17/01).
            'dyehouse' => [
                'dyehouse.master.view' => ['View dye house master data'],
                'dyehouse.master.manage' => ['Manage dye house master data'],
                // Dye House → Lab-Dip (AnnotechERP 17/02).
                'dyehouse.labdip.view' => ['View dosing templates and lab dip recipes'],
                'dyehouse.labdip.manage' => ['Write dosing templates and lab dip recipes'],
                // Dye House → Batch (Bulk) (AnnotechERP 17/03).
                'dyehouse.batch.view' => ['View dye batch plans, batch cards, grey indents and their transfers'],
                'dyehouse.batch.manage' => ['Plan, raise, split and transfer dye batches and their grey indents'],
                'dyehouse.batch.issue' => ['Issue grey fabric from the batch store against a dye batch indent'],
                // Dye House → Requisition (AnnotechERP 17/05).
                'dyehouse.requisition.view' => ['View dye and chemical requisitions, re-processes and dye machine maintenance'],
                'dyehouse.requisition.manage' => ['Raise dye and chemical requisitions, check-roll returns and dye machine maintenance'],
                'dyehouse.requisition.approve' => ['Approve or reject a re-process before the store issues against it'],
                'dyehouse.requisition.issue' => ['Issue a dye and chemical requisition from the chemical store'],
                'dyehouse.chemstore.view' => ['View the chemical store: stock, receipts, returns, issues, loans, comparative statements and month closings'],
                'dyehouse.chemstore.manage' => ['Raise chemical store papers: opening balances, receipts, returns, transfers, issue notes, sales, loans and comparative statements'],
                'dyehouse.chemstore.issue' => ['Finalise chemical receipts and returns and issue chemicals out of the store'],
                'dyehouse.chemstore.approve' => ['Verify chemical receipts for accounts and approve or reject comparative statements'],
                'dyehouse.chemstore.close' => ['Strike a month of the chemical store'],
                'dyehouse.production.view' => ['View the dye house production board: batch production, machine wash output, bookings, trim programmes, check roll parameters and closings'],
                'dyehouse.production.manage' => ['Record dye batch production and machine wash output, raise fabric bookings and trim programmes, and set check roll parameters'],
                'dyehouse.production.close' => ['Close an order out of dyeing and finishing'],
                'dyehouse.qc.view' => ['View dyeing QC: check roll inspections, batch programme completion and rib and collar shade approvals'],
                'dyehouse.qc.manage' => ['Inspect check rolls, approve rib and collar shades and complete batch programmes'],
                'dyehouse.sc.view' => ['View out-house subcontract dyeing: programmes, grey indents, challans, receipts and bills'],
                'dyehouse.sc.manage' => ['Raise out-house subcontract dyeing programmes, grey indents, challans, receipts and bills'],
                'dyehouse.sc.issue' => ['Issue grey fabric against subcontract dyeing indents'],
            ],
            // HR & Payroll → Master Data (AnnotechERP 24/01).
            'hrpayroll' => [
                'hrpayroll.master.view' => ['View HR & payroll master data'],
                'hrpayroll.master.manage' => ['Manage HR & payroll master data'],
                // HR & Payroll → Employee (24/03).
                'hrpayroll.employee.view' => ['View employees, their salary and postings'],
                'hrpayroll.employee.manage' => ['Register employees, change their salary and assign or transfer them'],
                'hrpayroll.biometric.view' => ['View the employees fingerprint status and device mapping'],
                'hrpayroll.biometric.manage' => ['Enrol employees on attendance devices, map and remove them'],
                'hrpayroll.attendance.view' => ['View daily attendance, missing punches, the month summary and the shift roster'],
                'hrpayroll.attendance.manage' => ['Correct attendance, request missing-punch corrections, import punches and assign shifts'],
                'hrpayroll.attendance.approve' => ['Approve or reject missing-punch corrections'],
                'hrpayroll.leave.view' => ['View leave applications and leave balances'],
                'hrpayroll.leave.manage' => ['Key and delete leave applications'],
                'hrpayroll.ot.view' => ['View OT, OT eligibility and OT requests'],
                'hrpayroll.ot.manage' => ['Raise OT requests, correct OT and set OT eligibility and limits'],
                'hrpayroll.ot.approve' => ['Approve, reject or return OT requests'],
                'hrpayroll.salary.view' => ['View daily, OT and monthly salary, festival bonus and payroll adjustments'],
                'hrpayroll.salary.calculate' => ['Calculate daily salary, monthly salary and festival bonus, and mark them reviewed'],
                'hrpayroll.salary.approve' => ['Approve or un-approve daily salary, the monthly run and festival bonus'],
                'hrpayroll.salary.finalize' => ['Finalize, re-open and mark paid the monthly run and festival bonus'],
                'hrpayroll.adjustment.manage' => ['Raise payroll adjustments on finalized slips'],
                'hrpayroll.adjustment.approve' => ['Approve or reject payroll adjustments'],
                'hrpayroll.movement.view' => ['View transfers, promotions, increments and re-joins'],
                'hrpayroll.movement.manage' => ['Save and finalize transfers, request promotions and increments, and record re-joins'],
                'hrpayroll.movement.approve' => ['Approve or reject promotions and increments'],
                'hrpayroll.separation.view' => ['View separations, lefty cases and final settlements'],
                'hrpayroll.separation.manage' => ['Request separations, report lefty cases and prepare final settlements'],
                'hrpayroll.separation.approve' => ['Decide separations and lefty cases, and approve and pay final settlements'],
                'hrpayroll.letter.view' => ['View letter types, templates and the letters issued'],
                'hrpayroll.letter.template' => ['Edit letter types and templates'],
                'hrpayroll.letter.generate' => ['Generate and print letters'],
                'hrpayroll.dashboard.view' => ['View the HR dashboard'],
                'buyergateway.access.view' => ['View buyer portal users, permissions, onboarding and notification settings'],
                'buyergateway.access.manage' => ['Manage buyer portal users, permissions, onboarding and notification settings'],
                'buyergateway.visibility.view' => ['View what buyers see: visibility rules, sample and shipment progress, shared documents'],
                'buyergateway.visibility.manage' => ['Manage what buyers see: visibility rules, sample and shipment progress, shared documents'],
                'buyergateway.billing.view' => ['View buyer quotation requests, price schedules, invoices and payments'],
                'buyergateway.billing.manage' => ['Manage buyer quotation requests, price schedules, invoices and payments'],
                'buyergateway.engagement.view' => ['View buyer requests, complaints, announcements, surveys and support tickets'],
                'buyergateway.engagement.manage' => ['Manage buyer requests, complaints, announcements, surveys and support tickets'],
                'buyergateway.admin.view' => ['View buyer portal usage, branding profiles and the activity trail'],
                'buyergateway.admin.manage' => ['Manage buyer portal usage, branding profiles and the activity trail'],
                'aiimport.intake.view' => ['View the file intake, recognition signatures, extraction rules, validations and import approval steps'],
                'aiimport.intake.manage' => ['Manage the file intake, recognition signatures, extraction rules, validations and import approval steps'],
                'commandcenter.command.view' => ['View the command deck, departmental boards, drill paths and dashboard compositions'],
                'commandcenter.command.manage' => ['Manage the departmental boards, drill paths and dashboard compositions'],
                'hrpayroll.report.view' => ['View the HR reports'],
                'hrpayroll.report.export' => ['Print and export the HR reports'],
            ],
            // Finance & Ledger → Master Data (AnnotechERP 23/01).
            'accounting' => [
                'accounting.master.view' => ['View finance & ledger master data'],
                'accounting.master.manage' => ['Manage finance & ledger master data'],
                'accounting.voucher.view' => ['View draft and committed vouchers and the statutory voucher register'],
                'accounting.voucher.manage' => ['Raise, edit and approve draft vouchers'],
                'accounting.voucher.post' => ['Post vouchers to the ledger and unpost them'],
                'accounting.bill.view' => ['View combined MRR bills'],
                'accounting.bill.manage' => ['Raise, finalise and remove combined MRR bills'],
                'accounting.period.view' => ['View stock value closings, bank reconciliations and fiscal year closings'],
                'accounting.period.manage' => ['Record stock value closings, reconcile bank lines and close fiscal years'],
                'accounting.cheque.view' => ['View cheque issues and cheque cancellations'],
                'accounting.cheque.manage' => ['Issue, print and cancel cheques'],
                'accounting.report.view' => ['Run the finance reports and see their run history'],
                'accounting.report.manage' => ['Remove finance report runs from the run history'],
            ],
            // Prototype Desk → Sample Planning & Development (AnnotechERP 07/03).
            'prototype' => [
                'prototype.dashboard.view' => ['View the Prototype Desk dashboard'],
                'prototype.sample360.view' => ['View the sample register, sample 360° view and sample documents'],
                'prototype.sample360.manage' => ['Attach documents to samples'],
                'prototype.alerts.view' => ['View and read Prototype Desk alerts'],
                'prototype.alerts.manage' => ['Resolve and reopen Prototype Desk alerts'],
                'prototype.requisition.view' => ['View sample requisitions'],
                'prototype.requisition.manage' => ['Raise, submit, assign and cancel sample requisitions'],
                'prototype.requisition.approve' => ['Decide sample requisitions at an approval level and assign the sample'],
                'prototype.development.view' => ['View sample developments'],
                'prototype.development.manage' => ['Start sample developments and work their checklist'],
                'prototype.revision.view' => ['View sample revisions'],
                'prototype.revision.manage' => ['Raise sample revisions'],
                'prototype.revision.approve' => ['Approve sample revisions'],
                'prototype.material.view' => ['View prototype material requisitions and issues'],
                'prototype.material.manage' => ['Raise, submit, cancel and delete prototype material requisitions'],
                'prototype.material.approve' => ['Department-approve, store-verify, approve and reject prototype material requisitions'],
                'prototype.material.issue' => ['Issue material against approved prototype material requisitions'],
                'prototype.bundle.view' => ['View sample bundle cards'],
                'prototype.bundle.manage' => ['Create sample bundle cards, issue them and move their bundles'],
                'prototype.tracking.view' => ['View sample bundle movements, lookups and sample cards'],
                'prototype.tracking.manage' => ['Scan sample bundles through the processes and update their tracking'],
                'prototype.target.view' => ['View the sample room daily targets and plans'],
                'prototype.target.manage' => ['Set the sample room daily targets and weekly / monthly plans'],
                'prototype.quality.view' => ['View sample quality checks'],
                'prototype.quality.manage' => ['Check samples against their QC checklist'],
                'prototype.approval.view' => ['View sample approvals'],
                'prototype.approval.manage' => ['Put samples up for approval and record the decision'],
                'prototype.feedback.view' => ['View buyer feedback on samples'],
                'prototype.feedback.manage' => ['Record buyer feedback and assign the action'],
                'prototype.handover.view' => ['View sample handovers'],
                'prototype.handover.manage' => ['Hand over and courier samples, and track delivery'],
            ],
            // Sales & Shipment → Operation (AnnotechERP 21/01).
            'salesshipment' => [
                'salesshipment.settings.view' => ['View the Sales & Shipment settings'],
                'salesshipment.settings.manage' => ['Change the Sales & Shipment rules, alert windows and document prefixes'],
                // Order & Delivery (AnnotechERP 21/02).
                'salesshipment.order.view' => ['View sales orders, S/C programmes, amendments and delivery completion'],
                'salesshipment.order.manage' => ['Book sales orders and S/C programmes, submit orders, raise amendments'],
                'salesshipment.order.approve' => ['Check, approve, confirm, return or close sales orders; approve, reject and apply amendments'],
                'salesshipment.delivery.view' => ['View delivery challans and challan returns'],
                'salesshipment.delivery.manage' => ['Raise, submit, deliver and cancel delivery challans; record and confirm challan returns'],
                'salesshipment.bill.view' => ['View delivery check bills'],
                'salesshipment.bill.manage' => ['Raise and submit delivery check bills'],
                'salesshipment.bill.approve' => ['Verify, approve, mark paid or reject delivery check bills'],
                'salesshipment.close.manage' => ['Close and reopen sales order months'],
                'salesshipment.close.override' => ['Close a sales order month over its exceptions (administrator)'],
                // Sub-Contract (AnnotechERP 21/03).
                'salesshipment.subcon.view' => ['View sub-contract deliveries, receipts, returns, reconciliations and pending'],
                'salesshipment.subcon.manage' => ['Send work out to sub-contractors, book what comes back, return and reconcile'],
                'salesshipment.subcon.reopen' => ['Reopen a reconciled sub-contract chalan (administrator)'],
                // General → Shipment Planning (AnnotechERP 21/04).
                'salesshipment.shipment.view' => ['View shipment plans'],
                'salesshipment.shipment.manage' => ['Plan shipments; book them, record containers, invoices, packing lists and document handovers'],
                // Shipment (AnnotechERP 21/05).
                'salesshipment.shipment.confirm' => ['Confirm shipments as shipped, unconfirm them, and close their files'],
                'salesshipment.shipment.reopen' => ['Reopen a closed shipment (administrator)'],
                // Export Documentation (AnnotechERP 21/06).
                'salesshipment.exportdoc.view' => ['View export documents, certificates, final handovers and shipment attachments'],
                'salesshipment.exportdoc.manage' => ['File export documents and certificates, hand the final documents over, attach shipment files'],
                'salesshipment.report.view' => ['Run the Sales & Shipment reports, trace an order and read the live alerts'],
            ],
            // Trade & Export → Master Data (AnnotechERP 22/01).
            'tradeexport' => [
                'tradeexport.master.view' => ['View trade & export master data'],
                'tradeexport.master.manage' => ['Manage trade & export master data'],
                'tradeexport.export.view' => ['View export L/Cs, PIs, commercial invoices, bills and realisations'],
                'tradeexport.export.manage' => ['Raise and finalise export L/Cs, PIs, commercial invoices, bills and realisations'],
                'tradeexport.import.view' => ['View import PIs, B2B L/Cs, customs bills of entry and import bill acceptances'],
                'tradeexport.import.manage' => ['Raise and finalise import PIs, B2B L/Cs, customs bills of entry and import bill acceptances'],
                'tradeexport.report.view' => ['Run the Trade & Export reports: export L/Cs, invoices, bills and realisation, B2B L/Cs, IFDBC and UDs'],
            ],
            // Cutting Floor (AnnotechERP 19).
            'cutting' => [
                'cutting.bundle.view' => ['View cutting bundle cards, the cutting list and the cutting dashboard'],
                'cutting.bundle.manage' => ['Record the output of a cutting, cut its bundle cards, print and reprint them, and cancel them'],
                'cutting.input.manage' => ['Lay a cutting (Cutting Input): PO, colour, part, fabric lot, marker, plies and size ratio'],
                'cutting.report.view' => ['View the cutting bundle reports'],
            ],
            // Assembly Floor → Sewing (AnnotechERP 20/01).
            'assembly' => [
                'assembly.sewing.view' => ['View the sewing dashboard and the bundle IN / OUT desks'],
                'assembly.sewing.manage' => ['Scan bundles into and off sewing lines, and withdraw them while the day is open'],
                'assembly.finishing.view' => ['View the finishing dashboard and the bundle IN / OUT desks'],
                'assembly.finishing.manage' => ['Scan bundles onto and off finishing lines with their good, reject and rework'],
                'assembly.finishing.supervise' => ['Move finishing rework on from pending to processing and completed'],
                'assembly.quality.view' => ['View the quality dashboard and the Quality IN / Inspection / OUT desks'],
                'assembly.quality.manage' => ['Receive bundles into quality, inspect them into pass, reject and rework, and release them'],
                'assembly.quality.supervise' => ['Move quality rework on from pending to processing and completed'],
                'assembly.packing.view' => ['View the packing dashboard, Packing In / Out, the seven packing processes and the packing reports'],
                'assembly.packing.manage' => ['Pack bundles in, run the packing processes (labels, cartons, marking, sealing) and pack batches out'],
                'assembly.packing.supervise' => ['Remove a mistaken Packing In scan, reopen a completed process and unseal a carton'],
                'assembly.ready.view' => ['View the Ready for Shipment dashboard and the finished goods stock with its ledger'],
                'assembly.shipment.view' => ['View the shipment dashboard, open shipments, history and shipment reports'],
                'assembly.shipment.manage' => ['Enter shipments from Ready for Shipment, confirm them, mark them as shipped and cancel planned ones'],
                'assembly.shipment.supervise' => ['Cancel a confirmed shipment and correct (reverse and re-ship) a shipped one'],
                'assembly.trims.view' => ['View the floor trims & accessories receipts, issues, receipt returns and store stock'],
                'assembly.trims.manage' => ['Write trims receipts, issues and receipt returns, complete receipts and returns, and submit issues'],
                'assembly.trims.approve' => ['Approve (take off stock) or reject a submitted trims issue'],
                'assembly.report.view' => ['Run the Apparel Assembly Reports'],
            ],
            // Production Tracking (AnnotechERP 30).
            'productiontracking' => [
                'productiontracking.view' => ['View the production dashboards, order progress, TV screens, reports and settings'],
                'productiontracking.entry.manage' => ['Add, edit and delete Production Master Data entries'],
                'productiontracking.capacity.manage' => ['Add and edit line capacities'],
                'productiontracking.plan.prepare' => ['Prepare, submit, revise and cancel daily production plans'],
                'productiontracking.plan.approve' => ['Approve or reject daily production plans — never one\'s own'],
                'productiontracking.tv.manage' => ['Set up TV screens and issue, replace or revoke their display links'],
                'productiontracking.settings.manage' => ['Change the refresh, stale-data, approval and threshold settings'],
            ],
            // Dev Sample Status (AnnotechERP 31, from Norban).
            'devsample' => [
                'devsample.view' => ['View development samples (one\'s own, or everyone\'s with View All)'],
                'devsample.view_all' => ['See every user\'s development samples, not only one\'s own'],
                'devsample.create' => ['Add development samples'],
                'devsample.update' => ['Edit development samples, a whole sample or one field from the list'],
                'devsample.delete' => ['Delete development samples'],
                'devsample.report' => ['Run the development sample Pivot Report'],
                'devsample.columns.manage' => ['Dev Sample Column Setup: columns, formulas, colour rules and who sees each column'],
            ],
            // Export Statement (AnnotechERP 35, from Norban's MD Sir Export Status).
            'exportstatement' => [
                'exportstatement.view' => ['View export statements, the dashboard and the edits waiting for approval'],
                'exportstatement.create' => ['Add export statements (saved at once)'],
                'exportstatement.update' => ['Edit export statements — the edit waits for approval unless one may approve'],
                'exportstatement.delete' => ['Delete export statements'],
                'exportstatement.import' => ['Import export statements from Excel'],
                'exportstatement.export' => ['Export export statements to Excel'],
                'exportstatement.approve' => ['Save export statement edits at once, without approval (Norban\'s department head)'],
                'exportstatement.columns.manage' => ['Export Statement Column Setup: columns, formulas, colour rules and who sees each column'],
            ],
            // Quality Tally (AnnotechERP 36, from annotech-rmg): sold separately — LicensedModules
            // refuses every qtally.* permission until the platform switches the module on.
            'qtally' => [
                'qtally.view' => ['View the quality tally of every section: dashboard, job flow, analytics, reports, jobs and QC assignment'],
                'qtally.tally.create' => ['Tap the tally (RFT, defect, rectify, reject) in any section, on the lines one is given as a QC'],
                'qtally.job.manage' => ['Add, edit, close and delete quality tally jobs'],
                'qtally.target.manage' => ['Set, copy and delete line targets and input of every section'],
                'qtally.cutting.view' => ['See the cutting section only: its dashboard, tally sheet, history and cut panel inspection'],
                'qtally.cutting.tally' => ['Cutting QC: tap panels on the cutting tables one is given'],
                'qtally.cutting.manage' => ['Cutting manager: give bundle cards as cutting input (scan or type), set cutting targets and attendance'],
                'qtally.sewing.view' => ['See the sewing section only: its dashboard, tally sheet and history'],
                'qtally.sewing.tally' => ['Sewing QC: tap on the sewing lines one is given'],
                'qtally.sewing.manage' => ['Sewing manager: see the pending orders and set sewing line targets and attendance'],
                'qtally.quality.view' => ['See the quality section only: its dashboard, tally sheet and history'],
                'qtally.quality.tally' => ['Quality QC: tap on the quality lines one is given'],
                'qtally.quality.manage' => ['Quality manager: see the pending orders and set quality line targets and attendance'],
                'qtally.inspector.manage' => ['Assign QC users (and their HR employee) to lines'],
                'qtally.phone.manage' => ['Approve, name, tie to a line and block the phones QCs sign in on'],
                'qtally.defect.manage' => ['Keep the defect types of cutting, sewing and quality'],
            ],
            // Order Summary (AnnotechERP 32, from Norban).
            'ordersummary' => [
                'ordersummary.view' => ['View the order summary sheet (the orders of one\'s own buyers), the dashboard and the audit log'],
                'ordersummary.view_all' => ['See every buyer\'s orders, not only one\'s own team\'s'],
                'ordersummary.create' => ['Add order rows and copy rows'],
                'ordersummary.update' => ['Edit order rows, in the sheet or by Bulk Update'],
                'ordersummary.delete' => ['Delete order rows'],
                'ordersummary.rebudget' => ['Change an order\'s Quantity, PAC or FOB (or drop it) after its budget was sent for approval or approved — the budget is cancelled and budgeted again'],
                'ordersummary.report' => ['Run the LDD / RFI pending summaries and Buyer Month Wise'],
                'ordersummary.columns.manage' => ['Column Setup: add and change columns, formulas, colour rules and who sees each column'],
                'ordersummary.team.manage' => ['Set up merchandising teams, their heads and each merchandiser\'s buyers'],
                'ordersummary.mail.manage' => ['Set when the order edit mail goes out and send it now'],
            ],
            // Pre-Budget (AnnotechERP 33/34, from Norban's Knit Woven and Accessories Pre-Budget).
            'prebudget' => [
                'prebudget.view' => ['View requisitions and pre-budgets of one\'s own buyers (merchandising team)'],
                'prebudget.view_all' => ['See every buyer\'s requisitions and pre-budgets, not only one\'s own team\'s'],
                'prebudget.create' => ['Add requisitions and create pre-budgets'],
                'prebudget.update' => ['Edit requisitions and pre-budgets: costing, stock yarn use, BOMs, finalize and revise'],
                'prebudget.delete' => ['Delete draft requisitions and draft pre-budgets'],
                'prebudget.stock' => ['Store: fill Stock Yarn Use and see the Stock Yarn Declaration'],
                'prebudget.columns.manage' => ['Requisition Column Setup: columns, formulas, colour rules and who sees each column'],
                'prebudget.master.view' => ['View Pre-Budget master data'],
                'prebudget.master.manage' => ['Manage Pre-Budget master data'],
            ],
            // Finishing Line → Master Data (AnnotechERP 18/01).
            'finishing' => [
                'finishing.master.view' => ['View finishing line master data'],
                'finishing.master.manage' => ['Manage finishing line master data'],
                // DF Production (AnnotechERP 18/02).
                'finishing.production.view' => ['View finishing production: batch receipts, production sheets, the delivery board, re-process returns and amendments'],
                'finishing.production.manage' => ['Receive batches, enter and post production sheets, pass batches on, return them for re-processing and amend posted production'],
                // S/C Process (AnnotechERP 18/03).
                'finishing.sc.view' => ['View finishing sub-contract challans, receipts, bills and party balances'],
                'finishing.sc.manage' => ['Raise and post finishing sub-contract challans and receipts, and enter processor bills'],
                // QC Process (AnnotechERP 18/04).
                'finishing.qc.view' => ['View finishing quality entries, shade decisions, four-point inspections and re-process advice'],
                'finishing.qc.manage' => ['Enter finishing quality readings, decide shades, inspect and post rolls, and advise re-processing'],
                // Fabric Store (AnnotechERP 18/05).
                'finishing.store.view' => ['View finished fabric store deliveries, lots, rolls, cutting requisitions, returns and transfers'],
                'finishing.store.manage' => ['Send and receive finished fabric deliveries, rack rolls, issue to cutting, and book returns and transfers'],
                // Report (AnnotechERP 18/06).
                'finishing.report.view' => ['View the Fabric Finishing Reports'],
            ],
            // Colour Lab → Operations (AnnotechERP 16/01).
            'colourlab' => [
                'colourlab.view' => ['View the colour lab: certificates, the issue register, chemical trials and fabric tests'],
                'colourlab.manage' => ['Work the colour lab: certificates, the issue register, chemical trials and fabric tests'],
                'colourlab.approve' => ['Approve or reject in-house fabric tests'],
                'colourlab.report.view' => ['Preview the lab test reports'],
            ],
            // Knit Production → Master Data (AnnotechERP 15/01).
            'knitting' => [
                'knitting.master.view' => ['View knit production master data'],
                'knitting.master.manage' => ['Manage knit production master data'],
                'knitting.yarn.view' => ['View yarn purchase indents, comparative statements and purchase orders'],
                'knitting.yarn.manage' => ['Raise yarn purchase indents, comparative statements and purchase orders'],
                'knitting.yarn.approve' => ['Approve or reject yarn purchase indents'],
                'knitting.ystore.view' => ['View the yarn store: opening balances, receipts, returns, racks, adjustments and month closing'],
                'knitting.ystore.manage' => ['Work the yarn store: opening balances, receipts, returns, racks, adjustments and month closing'],
                'knitting.production.view' => ['View knit production: production orders, programmes, knit cards, rolls, roll inspection and daily output'],
                'knitting.production.manage' => ['Work knit production: production orders, programmes, knit cards, rolls and roll inspection'],
                'knitting.fstore.view' => ['View the grey fabric store: receipts, stock, opening balances, transfers, corrections and month closing'],
                'knitting.fstore.manage' => ['Work the grey fabric store: receive rolls, opening balances, transfers, corrections and month closing'],
                'knitting.needle.view' => ['View the needle store: opening balances, loans, receipts, returns, breakage, indents, issues and month closing'],
                'knitting.needle.manage' => ['Work the needle store: opening balances, loans, receipts, returns, breakage, indents, issues and month closing'],
                'knitting.report.view' => ['Preview the knit production reports and stock positions'],
            ],
            // Work Study → Master Data (AnnotechERP 14/01).
            'workstudy' => [
                'workstudy.master.view' => ['View work study master data'],
                'workstudy.master.manage' => ['Manage work study master data'],
                'workstudy.spec.view' => ['View garment item specifications'],
                'workstudy.spec.manage' => ['Manage garment item specifications'],
                'workstudy.costing.view' => ['View CM rates, overhead allocation and daily line costs'],
                'workstudy.costing.manage' => ['Manage CM rates, overhead allocation and daily line costs'],
                'workstudy.ot.view' => ['View overtime plans and monthly salary and OT rates'],
                'workstudy.ot.manage' => ['Manage overtime plans and monthly salary and OT rates'],
                'workstudy.target.view' => ['View yarn allowances, style change-overs and sewing targets'],
                'workstudy.target.manage' => ['Manage yarn allowances, style change-overs and sewing targets'],
                'workstudy.floorops.view' => ['View sewing output and man-minute adjustments and garment wash requisitions'],
                'workstudy.floorops.manage' => ['Manage sewing output and man-minute adjustments and garment wash requisitions'],
                'workstudy.profit.view' => ['View section income targets and section profit and loss'],
                'workstudy.profit.manage' => ['Manage section income targets and section profit and loss'],
                'workstudy.report.view' => ['Run Work Study reports'],
            ],
            // Fabric Requirement → Master Data (AnnotechERP 13/01).
            'fabricplanning' => [
                'fabricplanning.master.view' => ['View fabric requirement master data'],
                'fabricplanning.master.manage' => ['Manage fabric requirement master data'],
                'fabricplanning.plan.view' => ['View fabric batch plans, capacity and schedules'],
                'fabricplanning.plan.manage' => ['Manage fabric batch plans, capacity and schedules'],
                'fabricplanning.report.view' => ['Run fabric requirement reports'],
            ],
            'masterdata' => [
                'masterdata.manage' => ['Manage master data'],
            ],
            // Merchandise Desk → Master Data (AnnotechERP 06/01).
            'merchandise' => [
                'merchandise.master.view' => ['View merchandising master data'],
                'merchandise.master.manage' => ['Manage merchandising master data'],
                // Merchandise Desk → Inquiry (AnnotechERP 06/05).
                'merchandise.inquiry.view' => ['View inquiries and price quotations'],
                'merchandise.inquiry.manage' => ['Enter inquiries and move their quotations along'],
                'merchandise.style.view' => ['View development styles'],
                'merchandise.style.manage' => ['Register and change development styles'],
                'merchandise.style.approve' => ['Approve, return or reject development styles sent for approval'],
                // Merchandise Desk → Style & Order (AnnotechERP 06/07).
                'merchandise.order.view' => ['View the order book'],
                'merchandise.order.manage' => ['Enter and change orders and their size breakdown'],
                // Merchandise Desk → Costing & Budget (AnnotechERP 06/09).
                'merchandise.costing.view' => ['View rate charts, CAD consumption and budget sheets'],
                'merchandise.costing.manage' => ['Set rates and prepare budget sheets'],
                'merchandise.costing.approve' => ['Approve budget sheets, locking them for costing, or unlock them'],
                // Merchandise Desk → Lab Dip & Sample (AnnotechERP 06/10).
                'merchandise.sample.view' => ['View lab dip and sample programmes, submissions and feedback'],
                'merchandise.sample.manage' => ['Raise lab dip and sample programmes and record their submissions'],
                // Merchandise Desk → Fabric Booking (AnnotechERP 06/11).
                'merchandise.booking.view' => ['View fabric bookings'],
                'merchandise.booking.manage' => ['Raise and revise fabric bookings'],
                'merchandise.tna.view' => ['View order T&A calendars'],
                'merchandise.tna.manage' => ['Recalculate T&A calendars and record milestones'],
                'merchandise.report.view' => ['Preview the merchandising reports'],
                'merchandise.accessories.view' => ['View trims and accessories purchasing'],
                'merchandise.accessories.manage' => ['Raise and revise trims and accessories requisitions and orders'],
                'merchandise.accessories.approve' => ['Approve or reject trims and accessories requisitions'],
            ],
            'custom_field' => [
                'custom_field.definition.manage' => ['Manage custom field definitions'],
            ],
            'report' => [
                'report.report.view' => ['View reports'],
                'report.report.export' => ['Export reports and data'],
            ],
            'dashboard' => [
                'dashboard.management.view' => ['View management dashboard'],
                'dashboard.maintenance.view' => ['View maintenance dashboard'],
                'dashboard.store.view' => ['View store dashboard'],
            ],
            'approval' => [
                'approval.request.approve' => ['Approve requests'],
                'approval.request.reject' => ['Reject requests'],
                // Approval Center (AnnotechERP 04): raise and decide requests,
                // see every request, shape the request types and chains.
                'approval.ticket.create' => ['Raise and decide approval requests'],
                'approval.request.see_all' => ['See every approval request', true],
                'approval.type.manage' => ['Manage request types and approval chains'],
            ],
            'audit' => [
                'audit.log.view' => ['View the audit log'],
            ],
            'billing' => [
                'billing.subscription.manage' => ['Manage subscription', true],
                'billing.payment.manage' => ['Record payments and refunds', true],
            ],
            'webhook' => [
                'webhook.endpoint.manage' => ['Manage webhook endpoints', true],
            ],
            'import' => [
                'import.job.create' => ['Run bulk imports'],
                'export.job.create' => ['Run bulk exports'],
            ],
        ];
    }

    public function run(): void
    {
        // Spatie's `name` is the permission code (its own uniqueness key);
        // the human-readable label lives in `description` instead — see the
        // migration note in 2026_09_03_083841_create_permission_tables.php.
        foreach (self::catalog() as $module => $permissions) {
            foreach ($permissions as $code => $definition) {
                Permission::updateOrCreate(
                    ['name' => $code, 'guard_name' => 'web'],
                    [
                        'module' => $module,
                        'description' => $definition[0],
                        'is_elevated' => $definition[1] ?? false,
                    ],
                );
            }
        }
    }

    /**
     * @return list<string>
     */
    public static function allCodes(): array
    {
        $codes = [];

        foreach (self::catalog() as $permissions) {
            foreach (array_keys($permissions) as $code) {
                $codes[] = $code;
            }
        }

        return $codes;
    }
}
