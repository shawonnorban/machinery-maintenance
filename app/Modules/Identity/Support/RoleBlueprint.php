<?php

declare(strict_types=1);

namespace App\Modules\Identity\Support;

/**
 * The company's roles by department and designation (user, 2026-11-22).
 *
 * Every department has two roles — its Executive (does the work) and its
 * Head (also sees the whole department's records, approves and deletes) —
 * holding only the modules that department works in. Master data, company
 * settings and the other setup screens belong to the admins alone (Owner,
 * Company Admin, Factory Admin): no other role is given their write
 * permissions, and their menus show only to one who holds them.
 *
 * These are templates. Each company works on its own copy of them
 * (RoleProvisioner), so every role stays editable without touching another
 * company's.
 */
final class RoleBlueprint
{
    /** The roles that keep master data, company settings and setup. */
    public const ADMIN_ROLES = ['COMPANY_OWNER', 'COMPANY_ADMIN', 'FACTORY_ADMIN'];

    /** Held only by an admin: the write side of master data, settings and setup. */
    public const ADMIN_ONLY = [
        '*.master.manage', '*.settings.manage', '*.columns.manage',
        'ordersummary.team.manage', 'ordersummary.mail.manage',
        'settings.*', 'masterdata.manage', 'custom_field.*', 'webhook.*', 'billing.*',
        'admin.user.manage', 'admin.role.manage', 'admin.api_client.manage',
        'approval.type.manage', 'buyergateway.admin.manage', 'hrpayroll.letter.template',
        'material.portal_access.manage',
    ];

    /** Verbs a department's Head holds over its Executive. */
    public const HEAD_VERBS = ['approve', 'reject', 'delete', 'view_all', 'finalize', 'supervise', 'override', 'reopen', 'reverse', 'see_all', 'post', 'close', 'rebudget'];

    /** A menu under one of these modules, or in a folder ending so, is a setup screen. */
    public const ADMIN_MODULES = ['ACCESS', 'SETTINGS', 'COMPANY'];

    public const ADMIN_FOLDERS = ['MASTER_DATA', 'SETUP', 'SETTINGS', 'CONFIGURATION', 'ADMINISTRATION'];

    /** What every Head is also given: the Approval Center's inbox and the reports. */
    private const HEAD_EXTRA = ['approval.request.approve', 'approval.request.reject', 'report.report.view', 'report.report.export'];

    /**
     * code => [English name, Bangla name, department, tier, permission patterns].
     * A pattern is a permission code or a `*` wildcard; the Executive keeps
     * what is not a HEAD_VERB, and an admin-only permission is never given.
     *
     * @return array<string, array{0: string, 1: string, 2: string, 3: string, 4: list<string>}>
     */
    public static function departments(): array
    {
        $merch = [
            'merchandise.*', 'devsample.*', 'ordersummary.*', 'prebudget.view', 'prebudget.view_all', 'prebudget.create', 'prebudget.update', 'prebudget.delete', 'prebudget.master.view',
            'prototype.*.view', 'prototype.requisition.manage', 'prototype.feedback.manage', 'prototype.approval.manage',
            'material.requisition.*', 'material.followup.view', 'material.master.view', 'aiimport.intake.*', 'commandcenter.command.view',
            'salesshipment.order.view', 'salesshipment.shipment.view', 'exportstatement.view', 'fabricplanning.plan.view', 'planning.plan.view',
            'productiontracking.view', 'cutting.report.view', 'assembly.report.view', 'knitting.report.view', 'finishing.report.view', 'dyehouse.labdip.view', 'colourlab.view', 'colourlab.report.view',
            'buyergateway.engagement.*', 'buyergateway.visibility.view',
        ];
        $commercial = [
            'tradeexport.*', 'exportstatement.*', 'salesshipment.order.view', 'salesshipment.exportdoc.*', 'salesshipment.shipment.*', 'salesshipment.bill.*', 'salesshipment.delivery.view', 'salesshipment.report.view',
            'ordersummary.view', 'ordersummary.view_all', 'ordersummary.report', 'merchandise.order.view', 'merchandise.report.view', 'merchandise.master.view', 'prebudget.view', 'prebudget.view_all',
        ];
        $procurement = [
            'material.*', 'merchandise.booking.view', 'merchandise.accessories.view', 'merchandise.master.view', 'prebudget.view', 'prebudget.view_all', 'knitting.yarn.*', 'knitting.master.view',
            'storematerial.view', 'store.master.view', 'store.requisition.create', 'gatepass.view', 'gatepass.create', 'vendor.vendor.view_any', 'ordersummary.view', 'ordersummary.view_all',
        ];
        $accounts = [
            'accounting.*', 'tradeexport.view', 'tradeexport.export.view', 'tradeexport.import.view', 'tradeexport.report.view', 'exportstatement.view', 'exportstatement.export',
            'salesshipment.bill.view', 'salesshipment.report.view', 'hrpayroll.salary.view', 'hrpayroll.report.view', 'cost.entry.view', 'cost.entry.create', 'workstudy.profit.view', 'workstudy.costing.view',
        ];
        $hr = ['hrpayroll.*', 'gatepass.view', 'gatepass.create', 'gatepass.approve', 'workstudy.ot.view'];
        $planning = [
            'planning.plan.*', 'planning.master.view', 'fabricplanning.plan.*', 'fabricplanning.master.view', 'fabricplanning.report.view',
            'productiontracking.view', 'productiontracking.plan.prepare', 'productiontracking.plan.approve', 'productiontracking.capacity.manage',
            'ordersummary.view', 'ordersummary.view_all', 'ordersummary.report', 'merchandise.order.view', 'merchandise.tna.*', 'merchandise.master.view', 'merchandise.report.view',
            'cutting.report.view', 'assembly.report.view', 'knitting.report.view', 'dyehouse.production.view', 'workstudy.target.view',
        ];
        $knitting = ['knitting.*', 'fabricplanning.plan.view', 'merchandise.booking.view', 'gatepass.view', 'gatepass.create', 'planning.plan.view'];
        $dyeing = ['dyehouse.*', 'colourlab.*', 'fabricplanning.plan.view', 'merchandise.booking.view', 'knitting.fstore.view', 'gatepass.view', 'gatepass.create'];
        $fabricFinishing = ['finishing.*', 'dyehouse.batch.view', 'dyehouse.production.view', 'gatepass.view', 'gatepass.create'];
        $cutting = ['cutting.*', 'finishing.store.view', 'planning.plan.view', 'productiontracking.view', 'productiontracking.entry.manage', 'merchandise.order.view', 'fabricplanning.plan.view', 'qtally.cutting.view', 'qtally.cutting.manage', 'qtally.job.manage'];
        $production = [
            'assembly.sewing.*', 'assembly.trims.view', 'assembly.ready.view', 'assembly.report.view', 'cutting.bundle.view', 'cutting.report.view',
            'productiontracking.view', 'productiontracking.entry.manage', 'productiontracking.plan.approve', 'planning.plan.view', 'workstudy.target.view',
            'asset.asset.view_any', 'asset.asset.view', 'breakdown.breakdown.view_any', 'breakdown.breakdown.view', 'breakdown.breakdown.create',
            'qtally.sewing.view', 'qtally.sewing.manage', 'qtally.job.manage',
        ];
        $quality = ['assembly.quality.*', 'finishing.qc.*', 'dyehouse.qc.*', 'prototype.quality.*', 'assembly.report.view', 'cutting.report.view', 'finishing.report.view', 'qtally.quality.view', 'qtally.quality.manage', 'qtally.job.manage'];
        $packing = ['assembly.finishing.*', 'assembly.packing.*', 'assembly.ready.view', 'assembly.trims.view', 'assembly.report.view', 'productiontracking.view', 'productiontracking.entry.manage'];
        $shipping = [
            'assembly.shipment.*', 'assembly.ready.view', 'salesshipment.delivery.*', 'salesshipment.shipment.*', 'salesshipment.exportdoc.view', 'salesshipment.order.view', 'salesshipment.report.view',
            'gatepass.view', 'gatepass.create', 'ordersummary.view', 'ordersummary.view_all',
        ];
        $ie = [
            'workstudy.*', 'productiontracking.view', 'productiontracking.capacity.manage', 'planning.plan.view', 'assembly.report.view', 'cutting.report.view', 'hrpayroll.employee.view',
        ];
        $sample = [
            'prototype.*', 'devsample.view', 'devsample.view_all', 'devsample.create', 'devsample.update', 'devsample.report',
            'merchandise.style.view', 'merchandise.sample.*', 'merchandise.inquiry.view', 'merchandise.master.view', 'dyehouse.labdip.view', 'colourlab.view',
        ];

        // Quality Tally's own roles (user, 2026-11-25): each section's manager and QC see their own section's menu, nothing else.
        $tally = [];
        foreach (['CUTTING' => ['Cutting', 'কাটিং', 'Cutting'], 'SEWING' => ['Sewing', 'সুইং', 'Sewing / Production'], 'QUALITY' => ['Quality', 'কোয়ালিটি', 'Quality']] as $s => [$en, $bn, $dept]) {
            $k = 'qtally.'.strtolower($s);
            $tally["QT_{$s}_MANAGER"] = ["{$en} Manager (Quality Tally)", "{$bn} ম্যানেজার (কোয়ালিটি টালি)", $dept, 'EXECUTIVE', ["{$k}.view", "{$k}.manage", 'qtally.job.manage']];
            $tally["QT_{$s}_QC"] = ["{$en} QC (Quality Tally)", "{$bn} কিউসি (কোয়ালিটি টালি)", $dept, 'EXECUTIVE', ["{$k}.view", "{$k}.tally"]];
        }

        return [
            'MERCHANDISER' => ['Merchandiser', 'মার্চেন্ডাইজার', 'Merchandising', 'EXECUTIVE', $merch],
            'MERCHANDISING_HEAD' => ['Merchandising Head', 'মার্চেন্ডাইজিং প্রধান', 'Merchandising', 'HEAD', $merch],
            'COMMERCIAL_EXECUTIVE' => ['Commercial Executive', 'কমার্শিয়াল এক্সিকিউটিভ', 'Commercial', 'EXECUTIVE', $commercial],
            'COMMERCIAL_HEAD' => ['Commercial Head', 'কমার্শিয়াল প্রধান', 'Commercial', 'HEAD', $commercial],
            'PROCUREMENT_EXECUTIVE' => ['Procurement Executive', 'প্রকিউরমেন্ট এক্সিকিউটিভ', 'Procurement', 'EXECUTIVE', $procurement],
            'PROCUREMENT_HEAD' => ['Procurement Head', 'প্রকিউরমেন্ট প্রধান', 'Procurement', 'HEAD', $procurement],
            'ACCOUNTS_EXECUTIVE' => ['Accounts Executive', 'হিসাব এক্সিকিউটিভ', 'Accounts & Finance', 'EXECUTIVE', $accounts],
            'ACCOUNTS_HEAD' => ['Accounts Head', 'হিসাব প্রধান', 'Accounts & Finance', 'HEAD', $accounts],
            'HR_EXECUTIVE' => ['HR Executive', 'এইচআর এক্সিকিউটিভ', 'HR & Administration', 'EXECUTIVE', $hr],
            'HR_HEAD' => ['HR Head', 'এইচআর প্রধান', 'HR & Administration', 'HEAD', $hr],
            'PLANNING_EXECUTIVE' => ['Planning Executive', 'প্ল্যানিং এক্সিকিউটিভ', 'PPC / Planning', 'EXECUTIVE', $planning],
            'PLANNING_HEAD' => ['Planning Head', 'প্ল্যানিং প্রধান', 'PPC / Planning', 'HEAD', $planning],
            'KNITTING_EXECUTIVE' => ['Knitting Executive', 'নিটিং এক্সিকিউটিভ', 'Knitting', 'EXECUTIVE', $knitting],
            'KNITTING_HEAD' => ['Knitting Head', 'নিটিং প্রধান', 'Knitting', 'HEAD', $knitting],
            'DYEING_EXECUTIVE' => ['Dyeing Executive', 'ডাইং এক্সিকিউটিভ', 'Dyeing', 'EXECUTIVE', $dyeing],
            'DYEING_HEAD' => ['Dyeing Head', 'ডাইং প্রধান', 'Dyeing', 'HEAD', $dyeing],
            'FABRIC_FINISHING_EXECUTIVE' => ['Fabric Finishing Executive', 'ফেব্রিক ফিনিশিং এক্সিকিউটিভ', 'Fabric Finishing', 'EXECUTIVE', $fabricFinishing],
            'FABRIC_FINISHING_HEAD' => ['Fabric Finishing Head', 'ফেব্রিক ফিনিশিং প্রধান', 'Fabric Finishing', 'HEAD', $fabricFinishing],
            'CUTTING_EXECUTIVE' => ['Cutting Executive', 'কাটিং এক্সিকিউটিভ', 'Cutting', 'EXECUTIVE', $cutting],
            'CUTTING_HEAD' => ['Cutting Head', 'কাটিং প্রধান', 'Cutting', 'HEAD', $cutting],
            'PRODUCTION_EXECUTIVE' => ['Production Executive', 'প্রোডাকশন এক্সিকিউটিভ', 'Sewing / Production', 'EXECUTIVE', $production],
            'PRODUCTION_HEAD' => ['Production Head', 'প্রোডাকশন প্রধান', 'Sewing / Production', 'HEAD', $production],
            'QUALITY_EXECUTIVE' => ['Quality Executive', 'কোয়ালিটি এক্সিকিউটিভ', 'Quality', 'EXECUTIVE', $quality],
            'QUALITY_HEAD' => ['Quality Head', 'কোয়ালিটি প্রধান', 'Quality', 'HEAD', $quality],
            'FINISHING_PACKING_EXECUTIVE' => ['Finishing & Packing Executive', 'ফিনিশিং ও প্যাকিং এক্সিকিউটিভ', 'Finishing & Packing', 'EXECUTIVE', $packing],
            'FINISHING_PACKING_HEAD' => ['Finishing & Packing Head', 'ফিনিশিং ও প্যাকিং প্রধান', 'Finishing & Packing', 'HEAD', $packing],
            'SHIPPING_EXECUTIVE' => ['Shipping Executive', 'শিপিং এক্সিকিউটিভ', 'Shipping', 'EXECUTIVE', $shipping],
            'SHIPPING_HEAD' => ['Shipping Head', 'শিপিং প্রধান', 'Shipping', 'HEAD', $shipping],
            'IE_EXECUTIVE' => ['IE Executive', 'আইই এক্সিকিউটিভ', 'Industrial Engineering', 'EXECUTIVE', $ie],
            'IE_HEAD' => ['IE Head', 'আইই প্রধান', 'Industrial Engineering', 'HEAD', $ie],
            'SAMPLE_EXECUTIVE' => ['Sample & Design Executive', 'স্যাম্পল ও ডিজাইন এক্সিকিউটিভ', 'Sample & Design', 'EXECUTIVE', $sample],
            'SAMPLE_HEAD' => ['Sample & Design Head', 'স্যাম্পল ও ডিজাইন প্রধান', 'Sample & Design', 'HEAD', $sample],
        ] + $tally;
    }

    /** Whether a permission is the admins' alone. */
    public static function adminOnly(string $permission): bool
    {
        foreach (self::ADMIN_ONLY as $pattern) {
            if (fnmatch($pattern, $permission)) {
                return true;
            }
        }

        return false;
    }

    /** Whether a menu is a setup screen (master data, settings, setup). */
    public static function isAdminMenu(?string $moduleCode, ?string $folderCode): bool
    {
        if (in_array($moduleCode, self::ADMIN_MODULES, true)) {
            return true;
        }
        if ($folderCode === null) {
            return false;
        }
        foreach (self::ADMIN_FOLDERS as $suffix) {
            if (str_ends_with($folderCode, $suffix)) {
                return true;
            }
        }

        return false;
    }

    /**
     * A department role's permissions out of the catalog.
     *
     * @param  list<string>  $patterns
     * @param  list<string>  $all
     * @return list<string>
     */
    public static function expand(array $patterns, string $tier, array $all): array
    {
        $out = [];
        foreach ($all as $code) {
            if (self::adminOnly($code)) {
                continue;
            }
            foreach ($patterns as $pattern) {
                if ($code === $pattern || (str_contains($pattern, '*') && fnmatch($pattern, $code))) {
                    if ($tier === 'HEAD' || ! in_array(self::verb($code), self::HEAD_VERBS, true)) {
                        $out[] = $code;
                    }
                    break;
                }
            }
        }
        if ($tier === 'HEAD') {
            $out = array_merge($out, array_values(array_intersect(self::HEAD_EXTRA, $all)));
        }

        return array_values(array_unique($out));
    }

    /** The last segment of a permission code. */
    public static function verb(string $code): string
    {
        $parts = explode('.', $code);

        return (string) end($parts);
    }

    /**
     * The role a person gets from HR — their department and designation; null
     * leaves them as they are (no department, or one with no role of its own).
     */
    public static function roleFor(?string $department, ?string $designation): ?string
    {
        $dept = strtolower(trim((string) $department));
        $desig = strtolower(trim((string) $designation));
        if ($desig === '' && $dept === '') {
            return null;
        }
        // Support staff use the app only to raise and follow requests.
        if (preg_match('/\b(driver|cook|cleaner|peon|caretaker|office assistant|operator|helper|guard)\b/', $desig)) {
            return 'EMPLOYEE';
        }
        if (str_contains($desig, 'director')) {
            return 'FACTORY_MANAGER';
        }
        if ($desig === 'line chief') {
            return 'LINE_CHIEF';
        }
        $head = (bool) preg_match('/\b(gm|agm|dgm|manager|cfo|head|chief)\b/', $desig);

        $pair = match (true) {
            str_contains($dept, 'merchandis') => ['MERCHANDISER', 'MERCHANDISING_HEAD'],
            str_contains($dept, 'commercial') => ['COMMERCIAL_EXECUTIVE', 'COMMERCIAL_HEAD'],
            str_contains($dept, 'procurement'), str_contains($dept, 'supply chain') => ['PROCUREMENT_EXECUTIVE', 'PROCUREMENT_HEAD'],
            str_contains($dept, 'account'), str_contains($dept, 'finance') => ['ACCOUNTS_EXECUTIVE', 'ACCOUNTS_HEAD'],
            str_contains($dept, 'hr'), str_contains($dept, 'human'), str_contains($dept, 'compliance'), str_contains($dept, 'ehs') => ['HR_EXECUTIVE', 'HR_HEAD'],
            str_contains($dept, 'planning'), str_contains($dept, 'ppc') => ['PLANNING_EXECUTIVE', 'PLANNING_HEAD'],
            str_contains($dept, 'yarn'), str_contains($dept, 'store'), str_contains($dept, 'material handling') => ['STOREKEEPER', 'STORE_MANAGER'],
            str_contains($dept, 'knitting') => ['KNITTING_EXECUTIVE', 'KNITTING_HEAD'],
            str_contains($dept, 'dyeing'), str_contains($dept, 'chemical'), str_contains($dept, 'washing') => ['DYEING_EXECUTIVE', 'DYEING_HEAD'],
            str_contains($dept, 'fabric finishing') => ['FABRIC_FINISHING_EXECUTIVE', 'FABRIC_FINISHING_HEAD'],
            str_contains($dept, 'cutting') => ['CUTTING_EXECUTIVE', 'CUTTING_HEAD'],
            str_contains($dept, 'sewing'), str_contains($dept, 'production'), str_contains($dept, 'embroidery'), str_contains($dept, 'printing') => ['PRODUCTION_EXECUTIVE', 'PRODUCTION_HEAD'],
            str_contains($dept, 'quality') => ['QUALITY_EXECUTIVE', 'QUALITY_HEAD'],
            str_contains($dept, 'finishing'), str_contains($dept, 'packing') => ['FINISHING_PACKING_EXECUTIVE', 'FINISHING_PACKING_HEAD'],
            str_contains($dept, 'shipping') => ['SHIPPING_EXECUTIVE', 'SHIPPING_HEAD'],
            str_contains($dept, 'industrial engineering'), str_contains($dept, '(ie)') => ['IE_EXECUTIVE', 'IE_HEAD'],
            str_contains($dept, 'design'), str_contains($dept, 'cad'), str_contains($dept, 'pattern'), str_contains($dept, 'sample') => ['SAMPLE_EXECUTIVE', 'SAMPLE_HEAD'],
            str_contains($dept, 'maintenance'), str_contains($dept, 'utility') => ['MAINTENANCE_ENGINEER', 'MAINTENANCE_MANAGER'],
            str_contains($dept, 'audit') => ['AUDITOR', 'AUDITOR'],
            str_contains($dept, 'mis'), $dept === 'it' => ['VIEWER', 'VIEWER'],
            default => null,
        };

        return $pair === null ? null : $pair[$head ? 1 : 0];
    }
}
