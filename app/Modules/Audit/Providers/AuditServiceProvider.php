<?php

declare(strict_types=1);

namespace App\Modules\Audit\Providers;

use App\Modules\Approval\Models\ApprovalAction;
use App\Modules\Approval\Models\ApprovalRequest;
use App\Modules\Asset\Models\Asset;
use App\Modules\Asset\Models\AssetCategory;
use App\Modules\Asset\Models\AssetModel;
use App\Modules\Asset\Models\AssetType;
use App\Modules\Asset\Models\Manufacturer;
use App\Modules\Audit\Listeners\RecordAuthenticationEvents;
use App\Modules\Audit\Observers\AuditObserver;
use App\Modules\Audit\Observers\LoginAttemptObserver;
use App\Modules\Compliance\Models\BondLicence;
use App\Modules\Compliance\Models\DutyAssessment;
use App\Modules\Compliance\Models\UtilisationDeclaration;
use App\Modules\Breakdown\Models\Breakdown;
use App\Modules\Breakdown\Models\DowntimeReasonCode;
use App\Modules\Breakdown\Models\FailureCategory;
use App\Modules\Breakdown\Models\FailureCode;
use App\Modules\Breakdown\Models\RootCause;
use App\Modules\Costing\Models\CostCategory;
use App\Modules\Costing\Models\CostEntry;
use App\Modules\CustomField\Models\CustomFieldDefinition;
use App\Modules\Finance\Models\GlAccount;
use App\Modules\Finance\Models\GlEventMapping;
use App\Modules\Identity\Models\LoginAttempt;
use App\Modules\Identity\Models\Role;
use App\Modules\Identity\Models\User;
use App\Modules\Identity\Models\UserRole;
use App\Modules\Inventory\Models\SparePart;
use App\Modules\ItemMaster\Models\StockCount;
use App\Modules\ItemMaster\Models\SubcontractChallan;
use App\Modules\ItemMaster\Models\Item;
use App\Modules\Procurement\Models\FabricBooking;
use App\Modules\Procurement\Models\HsCode;
use App\Modules\Procurement\Models\LandedCostVoucher;
use App\Modules\Procurement\Models\LetterOfCredit;
use App\Modules\Procurement\Models\GoodsReceipt;
use App\Modules\Procurement\Models\PurchaseOrder;
use App\Modules\Procurement\Models\PurchaseReturn;
use App\Modules\Procurement\Models\RateContract;
use App\Modules\Procurement\Models\SupplierAdvance;
use App\Modules\Procurement\Models\SupplierClaim;
use App\Modules\Procurement\Models\PurchaseRequisition;
use App\Modules\Requisition\Models\FloorRequisition;
use App\Modules\Inventory\Models\SparePartCategory;
use App\Modules\Maintenance\Models\MaintenancePlan;
use App\Modules\Maintenance\Models\MaintenanceType;
use App\Modules\Metering\Models\MeterType;
use App\Modules\Settings\Models\Setting;
use App\Modules\Vendor\Models\ServiceContract;
use App\Modules\Vendor\Models\Vendor;
use App\Modules\Vendor\Models\Warranty;
use App\Modules\Vendor\Models\WarrantyClaim;
use App\Modules\WorkOrder\Models\Technician;
use App\Modules\WorkOrder\Models\WorkOrder;
use Illuminate\Auth\Events\Login;
use Illuminate\Auth\Events\Logout;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;

class AuditServiceProvider extends ServiceProvider
{
    /**
     * What is audited (SRS 34).
     *
     * A named list rather than everything. Auditing every table would record
     * the ledger twice — inventory transactions, cost entries and status
     * histories are already append-only records of what happened — and bury the
     * decisions a person made under rows the system wrote to itself.
     *
     * What is here is what somebody would later have to answer for: money,
     * machines, access, and the records an inspector asks about.
     *
     * @var list<class-string<Model>>
     */
    private const AUDITED = [
        // Machines and the work done to them.
        Asset::class,
        WorkOrder::class,
        Breakdown::class,
        MaintenancePlan::class,

        // Money.
        CostEntry::class,
        SparePart::class,
        Item::class,
        // Procurement documents (Procurement spec §5, §7, §9): what was
        // asked for, what was ordered, what came through the gate.
        PurchaseRequisition::class,
        PurchaseOrder::class,
        GoodsReceipt::class,
        FloorRequisition::class,
        // Money that moves without goods moving, or goods that move
        // without an order behind them. A return takes stock back out of
        // the gate against a debit note; a claim and an advance are cash
        // positions with a supplier; a rate contract is the price every
        // later order quietly draws from; a landed-cost voucher
        // revalues stock that nobody touched; a booking commits the
        // company to a mill before any order exists; a subcontract
        // challan sends goods off the premises entirely.
        PurchaseReturn::class,
        SupplierClaim::class,
        SupplierAdvance::class,
        RateContract::class,
        LandedCostVoucher::class,
        FabricBooking::class,
        SubcontractChallan::class,
        // The count is the one routine way stock value changes with
        // nothing arriving or leaving, so "who wrote this difference
        // off" has to be answerable.
        StockCount::class,
        // Where each event posts, and the chart it posts into. Changing
        // a mapping rewrites what every later GRN means in the books
        // without a single receipt being edited — the same reason
        // `Setting` is on this list.
        GlAccount::class,
        GlEventMapping::class,
        // What a customs inspector asks for (Shared Systems spec §3):
        // the licence goods came in duty-free under, what the factory
        // told customs they were for, and every charge raised when they
        // went somewhere else. The HS code is the vocabulary all of it
        // is rated in, so moving one re-rates future assessments.
        BondLicence::class,
        UtilisationDeclaration::class,
        DutyAssessment::class,
        HsCode::class,
        // The bank opened credit against this document.
        LetterOfCredit::class,

        // Who may do what. A permission change nobody can trace is how an
        // access review ends in guesswork.
        User::class,
        Role::class,
        UserRole::class,
        Technician::class,

        // Decisions and commitments.
        ApprovalRequest::class,
        ApprovalAction::class,
        Vendor::class,
        Warranty::class,
        WarrantyClaim::class,
        ServiceContract::class,

        // Several settings change how money and KPIs are computed (SRS 20).
        Setting::class,

        // Master data (SRS 6). These lists are the vocabulary every report is
        // written in, so a change here rewrites the meaning of figures that
        // were already published: move a downtime reason out of the
        // availability calculation and last quarter's availability changes
        // without a single breakdown record being touched.
        AssetType::class,
        AssetCategory::class,
        Manufacturer::class,
        AssetModel::class,
        FailureCategory::class,
        FailureCode::class,
        RootCause::class,
        DowntimeReasonCode::class,
        MaintenanceType::class,
        MeterType::class,
        SparePartCategory::class,
        CostCategory::class,

        // A company's own custom fields are exactly this same kind of
        // vocabulary — deactivating or retyping one changes what every
        // existing value on it means, without a single record it's
        // attached to being touched.
        CustomFieldDefinition::class,

        // Dev Sample Status (AnnotechERP 31): a sample's statuses and quoted
        // prices are edited one cell at a time from the list; the trail says who.
        \App\Modules\DevSample\Models\DevSample::class,
    ];

    public function boot(): void
    {
        foreach (self::AUDITED as $model) {
            $model::observe(AuditObserver::class);
        }

        // Authentication is not a model change, so it is listened for rather
        // than observed.
        Event::listen(Login::class, [RecordAuthenticationEvents::class, 'onLogin']);
        Event::listen(Logout::class, [RecordAuthenticationEvents::class, 'onLogout']);
        Event::listen(PasswordReset::class, [RecordAuthenticationEvents::class, 'onPasswordReset']);

        // Failed sign-ins come from the attempt row rather than from Laravel's
        // Failed event: this application validates credentials itself and never
        // calls Auth::attempt, so that event never fires. The attempt row is
        // also richer — it says whether the address was unknown, the password
        // wrong, or the account locked out.
        LoginAttempt::observe(LoginAttemptObserver::class);
    }
}
