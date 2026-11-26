<?php

declare(strict_types=1);

namespace App\Modules\Erp\Support;

use App\Modules\Accounting\Resources\AccBankResource as AccAccBankResource;
use App\Modules\Accounting\Resources\GlSourceLineResource as AccGlSourceLineResource;
use App\Modules\Accounting\Resources\GlSourceResource as AccGlSourceResource;
use App\Modules\Accounting\Resources\AccountClassResource as AccAccountClassResource;
use App\Modules\Accounting\Resources\AccountResource as AccAccountResource;
use App\Modules\Accounting\Resources\BankAccountResource as AccBankAccountResource;
use App\Modules\Accounting\Resources\ChequeBookResource as AccChequeBookResource;
use App\Modules\Accounting\Resources\ComplianceRuleResource as AccComplianceRuleResource;
use App\Modules\Accounting\Resources\CostCenterGroupResource as AccCostCenterGroupResource;
use App\Modules\Accounting\Resources\CostCenterResource as AccCostCenterResource;
use App\Modules\Accounting\Resources\FiscalYearResource as AccFiscalYearResource;
use App\Modules\Accounting\Resources\PaymentModeResource as AccPaymentModeResource;
use App\Modules\AiCore\Resources\AiFeatureResource;
use App\Modules\AiCore\Resources\AiServiceResource;
use App\Modules\AiImport\Resources\ApprovalStepResource;
use App\Modules\AiImport\Resources\ExtractionRuleResource;
use App\Modules\AiImport\Resources\IntakeFileResource;
use App\Modules\AiImport\Resources\SignatureResource;
use App\Modules\AiImport\Resources\ValidationResource;
use App\Modules\Approval\Resources\ApprovalRequestTypeResource;
use App\Modules\BuyerGateway\Resources\ActivityEntryResource;
use App\Modules\BuyerGateway\Resources\AnnouncementResource;
use App\Modules\BuyerGateway\Resources\BrandingProfileResource;
use App\Modules\BuyerGateway\Resources\BuyerRequestResource;
use App\Modules\BuyerGateway\Resources\ComplaintResource;
use App\Modules\BuyerGateway\Resources\PaymentLineResource;
use App\Modules\BuyerGateway\Resources\PortalInvoiceResource;
use App\Modules\BuyerGateway\Resources\PortalNotificationPrefResource;
use App\Modules\BuyerGateway\Resources\PortalOnboardingResource;
use App\Modules\BuyerGateway\Resources\PortalPermissionSetResource;
use App\Modules\BuyerGateway\Resources\PortalUserResource;
use App\Modules\BuyerGateway\Resources\PriceLineResource;
use App\Modules\BuyerGateway\Resources\RfqResource;
use App\Modules\BuyerGateway\Resources\SampleProgressResource;
use App\Modules\BuyerGateway\Resources\SharedDocumentResource;
use App\Modules\BuyerGateway\Resources\ShipmentProgressResource;
use App\Modules\BuyerGateway\Resources\SurveyResource;
use App\Modules\BuyerGateway\Resources\TicketResource;
use App\Modules\BuyerGateway\Resources\UsageLineResource;
use App\Modules\BuyerGateway\Resources\VisibilityRuleResource;
use App\Modules\ColourLab\Resources\CertificateResource as LabCertificateResource;
use App\Modules\ColourLab\Resources\CertTypeResource as LabCertTypeResource;
use App\Modules\ColourLab\Resources\EquipmentResource as LabEquipmentResource;
use App\Modules\ColourLab\Resources\RegisterResource as LabRegisterResource;
use App\Modules\CommandCenter\Resources\BoardResource;
use App\Modules\CommandCenter\Resources\CompositionResource;
use App\Modules\CommandCenter\Resources\DrillPathResource;
use App\Modules\DyeHouse\Resources\DyeBatchPlanResource;
use App\Modules\DyeHouse\Resources\DyeCheckRollParamResource;
use App\Modules\DyeHouse\Resources\DyeCheckRollReturnResource;
use App\Modules\DyeHouse\Resources\DyeChemLoanOpeningResource;
use App\Modules\DyeHouse\Resources\DyeChemOpeningResource;
use App\Modules\DyeHouse\Resources\DyeColourGroupResource;
use App\Modules\DyeHouse\Resources\DyeColourTransferResource;
use App\Modules\DyeHouse\Resources\DyeFinishedBatchResource;
use App\Modules\DyeHouse\Resources\DyeGreyIndentResource;
use App\Modules\DyeHouse\Resources\DyeGreyOrderTransferResource;
use App\Modules\DyeHouse\Resources\DyeLabDipRecipeResource;
use App\Modules\DyeHouse\Resources\DyeLeftoverTransferResource;
use App\Modules\DyeHouse\Resources\DyeMachineListResource;
use App\Modules\DyeHouse\Resources\DyeMachineMaintenanceResource;
use App\Modules\DyeHouse\Resources\DyeMachineResource;
use App\Modules\DyeHouse\Resources\DyeManualBatchResource;
use App\Modules\DyeHouse\Resources\DyeMethodResource;
use App\Modules\DyeHouse\Resources\DyePtBillResource;
use App\Modules\DyeHouse\Resources\DyePtChallanResource;
use App\Modules\DyeHouse\Resources\DyePtProgrammeResource;
use App\Modules\DyeHouse\Resources\DyePtReceiptResource;
use App\Modules\DyeHouse\Resources\DyeSampleIndentResource;
use App\Modules\DyeHouse\Resources\DyeSampleQcResource;
use App\Modules\DyeHouse\Resources\DyeSampleTransferResource;
use App\Modules\DyeHouse\Resources\DyeScBillResource;
use App\Modules\DyeHouse\Resources\DyeSciProgrammeResource;
use App\Modules\DyeHouse\Resources\DyeSciReceiptResource;
use App\Modules\DyeHouse\Resources\DyeShadeApprovalResource;
use App\Modules\DyeHouse\Resources\ResponsibleEmployeeResource;
use App\Modules\FabricPlanning\Resources\BatchHourResource;
use App\Modules\FabricPlanning\Resources\BatchLeadRuleResource;
use App\Modules\FabricPlanning\Resources\HolidayResource;
use App\Modules\FabricPlanning\Resources\MachineCapacityResource;
use App\Modules\FabricPlanning\Resources\MachineRouteResource;
use App\Modules\FabricPlanning\Resources\ReportRunResource;
use App\Modules\FabricPlanning\Resources\SchedulePlanResource;
use App\Modules\FabricPlanning\Resources\StatusAreaResource;
use App\Modules\Finishing\Resources\FinBatchReceiptResource;
use App\Modules\Finishing\Resources\FinProductionAmendmentResource;
use App\Modules\Finishing\Resources\FinQcEntryResource;
use App\Modules\Finishing\Resources\FinReprocessAdviceResource;
use App\Modules\Finishing\Resources\FinReprocessReturnResource;
use App\Modules\Finishing\Resources\FinShadeDecisionResource;
use App\Modules\Finishing\Resources\FinStoreReturnResource;
use App\Modules\Finishing\Resources\FinStoreRollResource;
use App\Modules\Finishing\Resources\MachineProcessResource as FinMachineProcessResource;
use App\Modules\Finishing\Resources\MachineResource as FinMachineResource;
use App\Modules\Finishing\Resources\MachineTargetResource as FinMachineTargetResource;
use App\Modules\Finishing\Resources\ProcessTypeResource as FinProcessTypeResource;
use App\Modules\GatePass\Resources\GatePassCategoryResource;
use App\Modules\GatePass\Resources\GatePassItemResource;
use App\Modules\GatePass\Resources\GatePassLocationResource;
use App\Modules\GatePass\Resources\GatePassLocationTypeResource;
use App\Modules\GatePass\Resources\GatePassPurposeResource;
use App\Modules\GeneralStore\Resources\StoreCategoryResource;
use App\Modules\GeneralStore\Resources\StoreDepartmentResource;
use App\Modules\GeneralStore\Resources\StoreItemResource;
use App\Modules\GeneralStore\Resources\StoreLocationResource;
use App\Modules\GeneralStore\Resources\StoreSupplierResource;
use App\Modules\GeneralStore\Resources\StoreUnitResource;
use App\Modules\HrPayroll\Resources\AllowanceResource as HrAllowanceResource;
use App\Modules\HrPayroll\Resources\BonusRuleResource as HrBonusRuleResource;
use App\Modules\HrPayroll\Resources\DeductionResource as HrDeductionResource;
use App\Modules\HrPayroll\Resources\DesignationGroupResource as HrDesignationGroupResource;
use App\Modules\HrPayroll\Resources\EmploymentTypeResource as HrEmploymentTypeResource;
use App\Modules\HrPayroll\Resources\FestivalResource as HrFestivalResource;
use App\Modules\HrPayroll\Resources\GazetteGradeWageResource as HrGazetteGradeWageResource;
use App\Modules\HrPayroll\Resources\GazetteLineResource as HrGazetteLineResource;
use App\Modules\HrPayroll\Resources\HolidayResource as HrHolidayResource;
use App\Modules\HrPayroll\Resources\HrFiscalYearResource;
use App\Modules\HrPayroll\Resources\LeaveTypeResource as HrLeaveTypeResource;
use App\Modules\HrPayroll\Resources\OtRuleResource as HrOtRuleResource;
use App\Modules\HrPayroll\Resources\PayElementResource as HrPayElementResource;
use App\Modules\HrPayroll\Resources\PayGradeResource as HrPayGradeResource;
use App\Modules\HrPayroll\Resources\PayPeriodResource as HrPayPeriodResource;
use App\Modules\HrPayroll\Resources\ShiftResource as HrShiftResource;
use App\Modules\Identity\Resources\DeletionRequestResource;
use App\Modules\Identity\Resources\MailServiceResource;
use App\Modules\Identity\Resources\NotificationBroadcastResource;
use App\Modules\Identity\Resources\ReportTemplateResource;
use App\Modules\Identity\Resources\RuleViolationResource;
use App\Modules\Knitting\Resources\CapacityPlanResource as KnitCapacityPlanResource;
use App\Modules\Knitting\Resources\LabelPrinterResource as KnitLabelPrinterResource;
use App\Modules\Knitting\Resources\MachineResource as KnitMachineResource;
use App\Modules\Knitting\Resources\OperatorPostingResource as KnitOperatorPostingResource;
use App\Modules\Knitting\Resources\YarnCountResource as KnitYarnCountResource;
use App\Modules\MaterialProcurement\Resources\FabricItemResource;
use App\Modules\MaterialProcurement\Resources\MaterialSupplierResource;
use App\Modules\PreBudget\Resources\AdditionalProcessResource;
use App\Modules\PreBudget\Resources\GsmResource;
use App\Modules\PreBudget\Resources\OrderTypeResource;
use App\Modules\PreBudget\Resources\PbItemResource;
use App\Modules\PreBudget\Resources\PbFabricTypeResource;
use App\Modules\PreBudget\Resources\PbFabricCompositionResource;
use App\Modules\PreBudget\Resources\PbYarnCompositionResource;
use App\Modules\PreBudget\Resources\PbPartResource;
use App\Modules\PreBudget\Resources\PbYarnCountResource;
use App\Modules\PreBudget\Resources\PbAccessoryItemResource;
use App\Modules\PreBudget\Resources\SustainabilityResource;
use App\Modules\PreBudget\Resources\WashTypeResource;
use App\Modules\PreBudget\Resources\YarnTypeResource;
use App\Modules\MaterialProcurement\Resources\TrimItemResource;
use App\Modules\Merchandise\Resources\BookingLossAllowanceResource;
use App\Modules\Merchandise\Resources\BuyerOfficeResource;
use App\Modules\Merchandise\Resources\BuyerResource;
use App\Modules\Merchandise\Resources\BuyerSupplierConfigResource;
use App\Modules\Merchandise\Resources\BuyerTrimItemResource;
use App\Modules\Merchandise\Resources\CadRecordResource;
use App\Modules\Merchandise\Resources\ColourResource;
use App\Modules\Merchandise\Resources\CountryConfigResource;
use App\Modules\Merchandise\Resources\CuttingLossAllowanceResource;
use App\Modules\Merchandise\Resources\DyeingLossBandResource;
use App\Modules\Merchandise\Resources\EmbellishmentRateResource;
use App\Modules\Merchandise\Resources\FabProcessRateResource;
use App\Modules\Merchandise\Resources\FabricTypeResource;
use App\Modules\Merchandise\Resources\FiberCompositionResource;
use App\Modules\Merchandise\Resources\FiberResource;
use App\Modules\Merchandise\Resources\GarmentPartResource;
use App\Modules\Merchandise\Resources\InquiryResource;
use App\Modules\Merchandise\Resources\ItemTypeResource;
use App\Modules\Merchandise\Resources\LabdipProgramResource;
use App\Modules\Merchandise\Resources\MeasureChartResource;
use App\Modules\Merchandise\Resources\OrderResource;
use App\Modules\Merchandise\Resources\OriginCountryResource;
use App\Modules\Merchandise\Resources\PrintProgramResource;
use App\Modules\Merchandise\Resources\ProcCostHeadResource;
use App\Modules\Merchandise\Resources\ProcPostingSetupResource;
use App\Modules\Merchandise\Resources\RateLineResource;
use App\Modules\Merchandise\Resources\SampleChallanResource;
use App\Modules\Merchandise\Resources\SampleFeedbackResource;
use App\Modules\Merchandise\Resources\SampleProgramResource;
use App\Modules\Merchandise\Resources\SampleTypeResource;
use App\Modules\Merchandise\Resources\ServiceTypeResource;
use App\Modules\Merchandise\Resources\SizeResource;
use App\Modules\Merchandise\Resources\TestInstituteResource;
use App\Modules\Merchandise\Resources\TnaTaskGroupResource;
use App\Modules\Merchandise\Resources\TnaTaskResource;
use App\Modules\Merchandise\Resources\TnaTemplateResource;
use App\Modules\Merchandise\Resources\TrimSpecTemplateResource;
use App\Modules\Merchandise\Resources\WorkNatureResource;
use App\Modules\Merchandise\Resources\YarnCountConfigResource;
use App\Modules\Planning\Resources\EfficiencyStandardResource;
use App\Modules\Planning\Resources\LeadTimeResource;
use App\Modules\Planning\Resources\LineMachineResource;
use App\Modules\Planning\Resources\LineManpowerResource;
use App\Modules\Planning\Resources\LineShiftResource;
use App\Modules\Planning\Resources\PlanningLineResource;
use App\Modules\Planning\Resources\StyleSmvResource;
use App\Modules\Settings\Resources\BankIntegrationResource;
use App\Modules\Settings\Resources\CurrencyResource;
use App\Modules\Settings\Resources\LabelTemplateResource;
use App\Modules\Settings\Resources\SmsGatewayResource;
use App\Modules\TradeExport\Resources\BankBranchResource as TxBankBranchResource;
use App\Modules\TradeExport\Resources\BankResource as TxBankResource;
use App\Modules\TradeExport\Resources\ExchangeRateResource as TxExchangeRateResource;
use App\Modules\TradeExport\Resources\LcApplicantResource as TxLcApplicantResource;
use App\Modules\TradeExport\Resources\NotifyPartyResource as TxNotifyPartyResource;
use App\Modules\TradeExport\Resources\ReferenceEntryResource as TxReferenceEntryResource;
use App\Modules\WorkStudy\Resources\ChangeoverResource as WsChangeoverResource;
use App\Modules\WorkStudy\Resources\CmRateResource as WsCmRateResource;
use App\Modules\WorkStudy\Resources\GarmentItemResource as WsGarmentItemResource;
use App\Modules\WorkStudy\Resources\ItemGroupResource as WsItemGroupResource;
use App\Modules\WorkStudy\Resources\ItemPartResource as WsItemPartResource;
use App\Modules\WorkStudy\Resources\LearningPointResource as WsLearningPointResource;
use App\Modules\WorkStudy\Resources\LineCostingResource as WsLineCostingResource;
use App\Modules\WorkStudy\Resources\LineProfileResource as WsLineProfileResource;
use App\Modules\WorkStudy\Resources\ManMinuteAdjustmentResource as WsManMinuteAdjustmentResource;
use App\Modules\WorkStudy\Resources\OhCostResource as WsOhCostResource;
use App\Modules\WorkStudy\Resources\OhSectionResource as WsOhSectionResource;
use App\Modules\WorkStudy\Resources\OtPlanResource as WsOtPlanResource;
use App\Modules\WorkStudy\Resources\OutputAdjustmentResource as WsOutputAdjustmentResource;
use App\Modules\WorkStudy\Resources\ReportRunResource as WsReportRunResource;
use App\Modules\WorkStudy\Resources\SalaryRateResource as WsSalaryRateResource;
use App\Modules\WorkStudy\Resources\SectionResultResource as WsSectionResultResource;
use App\Modules\WorkStudy\Resources\SectionTargetResource as WsSectionTargetResource;
use App\Modules\WorkStudy\Resources\SewingTargetResource as WsSewingTargetResource;
use App\Modules\WorkStudy\Resources\TechVariationResource as WsTechVariationResource;
use App\Modules\WorkStudy\Resources\WashRequisitionResource as WsWashRequisitionResource;
use App\Modules\WorkStudy\Resources\YarnTargetResource as WsYarnTargetResource;

/**
 * Every screen built on the resource kit, by url key. A module adds its
 * definitions here as its screens are built.
 */
class ResourceRegistry
{
    /** @var list<class-string<ResourceDefinition>> */
    private const DEFINITIONS = [
        // Access Control → Configuration and Operations (AnnotechERP 02/03, 02/04).
        ReportTemplateResource::class,
        MailServiceResource::class,
        RuleViolationResource::class,
        NotificationBroadcastResource::class,
        DeletionRequestResource::class,
        // Settings → General Settings (AnnotechERP 03/03).
        CurrencyResource::class,
        LabelTemplateResource::class,
        // Settings → Integration Hub (AnnotechERP 03/02).
        SmsGatewayResource::class,
        BankIntegrationResource::class,
        // Approval Center → Request Types & Routing (AnnotechERP 04/03).
        ApprovalRequestTypeResource::class,
        // Merchandise Desk → Master Data (AnnotechERP 06/01).
        OriginCountryResource::class,
        CountryConfigResource::class,
        ColourResource::class,
        SizeResource::class,
        GarmentPartResource::class,
        ServiceTypeResource::class,
        ItemTypeResource::class,
        WorkNatureResource::class,
        FabricTypeResource::class,
        FiberResource::class,
        FiberCompositionResource::class,
        YarnCountConfigResource::class,
        BuyerOfficeResource::class,
        BuyerResource::class,
        SampleTypeResource::class,
        TestInstituteResource::class,
        BuyerTrimItemResource::class,
        BuyerSupplierConfigResource::class,
        DyeingLossBandResource::class,
        ProcCostHeadResource::class,
        ProcPostingSetupResource::class,
        BookingLossAllowanceResource::class,
        CuttingLossAllowanceResource::class,
        RateLineResource::class,
        TrimSpecTemplateResource::class,
        TnaTaskGroupResource::class,
        TnaTaskResource::class,
        TnaTemplateResource::class,
        // Merchandise Desk → Inquiry (AnnotechERP 06/05).
        InquiryResource::class,
        OrderResource::class,
        FabProcessRateResource::class,
        EmbellishmentRateResource::class,
        CadRecordResource::class,
        LabdipProgramResource::class,
        SampleProgramResource::class,
        PrintProgramResource::class,
        SampleChallanResource::class,
        SampleFeedbackResource::class,
        MeasureChartResource::class,
        // Material Procurement → Master Data (AnnotechERP 08/01).
        FabricItemResource::class,
        TrimItemResource::class,
        MaterialSupplierResource::class,
        // Pre-Budget → Master Data (AnnotechERP 33/34, from Norban).
        PbAccessoryItemResource::class,
        YarnTypeResource::class,
        WashTypeResource::class,
        AdditionalProcessResource::class,
        GsmResource::class,
        SustainabilityResource::class,
        OrderTypeResource::class,
        PbItemResource::class,
        PbFabricTypeResource::class,
        PbFabricCompositionResource::class,
        PbYarnCompositionResource::class,
        PbPartResource::class,
        PbYarnCountResource::class,
        // General Store → Master Data (AnnotechERP 10/01).
        StoreCategoryResource::class,
        StoreItemResource::class,
        StoreUnitResource::class,
        StoreDepartmentResource::class,
        StoreSupplierResource::class,
        StoreLocationResource::class,
        // Gate Pass → Master Data (AnnotechERP 11/01).
        GatePassCategoryResource::class,
        GatePassLocationTypeResource::class,
        GatePassLocationResource::class,
        GatePassItemResource::class,
        GatePassPurposeResource::class,
        // Buyer Gateway → Access & Onboarding (AnnotechERP 25/01).
        PortalUserResource::class,
        PortalPermissionSetResource::class,
        PortalOnboardingResource::class,
        PortalNotificationPrefResource::class,
        // Buyer Gateway → Order & Shipment Visibility (AnnotechERP 25/02).
        VisibilityRuleResource::class,
        SampleProgressResource::class,
        ShipmentProgressResource::class,
        SharedDocumentResource::class,
        // Buyer Gateway → Pricing & Billing (AnnotechERP 25/03).
        RfqResource::class,
        PriceLineResource::class,
        PortalInvoiceResource::class,
        PaymentLineResource::class,
        // Buyer Gateway → Buyer Engagement (AnnotechERP 25/04).
        BuyerRequestResource::class,
        ComplaintResource::class,
        AnnouncementResource::class,
        SurveyResource::class,
        TicketResource::class,
        // Buyer Gateway → Portal Administration (AnnotechERP 25/05).
        UsageLineResource::class,
        BrandingProfileResource::class,
        ActivityEntryResource::class,
        // AI File & PO Import → Intake (AnnotechERP 26/01).
        IntakeFileResource::class,
        SignatureResource::class,
        ExtractionRuleResource::class,
        ValidationResource::class,
        ApprovalStepResource::class,
        // Settings → AI Services: the AI services and which one each AI feature uses.
        AiServiceResource::class,
        AiFeatureResource::class,
        // Command Center → Command Center (AnnotechERP 27/01).
        BoardResource::class,
        DrillPathResource::class,
        CompositionResource::class,
        // Capacity Planning → Master Data (AnnotechERP 12/01).
        PlanningLineResource::class,
        LineMachineResource::class,
        LineManpowerResource::class,
        LineShiftResource::class,
        EfficiencyStandardResource::class,
        StyleSmvResource::class,
        LeadTimeResource::class,
        // Colour Lab → Operations (AnnotechERP 16/01).
        LabCertTypeResource::class,
        LabEquipmentResource::class,
        LabCertificateResource::class,
        LabRegisterResource::class,
        // Dye House → Master Data (AnnotechERP 17/01).
        DyeMethodResource::class,
        DyeMachineListResource::class,
        DyeMachineResource::class,
        ResponsibleEmployeeResource::class,
        DyeLabDipRecipeResource::class,
        DyeBatchPlanResource::class,
        DyeManualBatchResource::class,
        DyeFinishedBatchResource::class,
        DyeGreyIndentResource::class,
        DyePtProgrammeResource::class,
        DyePtChallanResource::class,
        DyePtReceiptResource::class,
        DyePtBillResource::class,
        DyeGreyOrderTransferResource::class,
        DyeColourTransferResource::class,
        DyeLeftoverTransferResource::class,
        DyeSampleIndentResource::class,
        DyeSampleTransferResource::class,
        DyeSampleQcResource::class,
        DyeCheckRollReturnResource::class,
        DyeMachineMaintenanceResource::class,
        DyeChemOpeningResource::class,
        DyeChemLoanOpeningResource::class,
        DyeCheckRollParamResource::class,
        DyeShadeApprovalResource::class,
        DyeScBillResource::class,
        DyeSciProgrammeResource::class,
        DyeSciReceiptResource::class,
        DyeColourGroupResource::class,
        // Fabric Requirement → Master Data (AnnotechERP 13/01).
        BatchLeadRuleResource::class,
        StatusAreaResource::class,
        HolidayResource::class,
        MachineCapacityResource::class,
        SchedulePlanResource::class,
        ReportRunResource::class,
        BatchHourResource::class,
        MachineRouteResource::class,
        WsGarmentItemResource::class,
        WsItemGroupResource::class,
        WsTechVariationResource::class,
        WsCmRateResource::class,
        WsOhSectionResource::class,
        WsOhCostResource::class,
        WsLineCostingResource::class,
        WsOtPlanResource::class,
        WsSalaryRateResource::class,
        WsYarnTargetResource::class,
        WsChangeoverResource::class,
        WsSewingTargetResource::class,
        WsOutputAdjustmentResource::class,
        WsWashRequisitionResource::class,
        WsManMinuteAdjustmentResource::class,
        WsSectionTargetResource::class,
        WsSectionResultResource::class,
        WsReportRunResource::class,
        WsItemPartResource::class,
        WsLearningPointResource::class,
        WsLineProfileResource::class,
        KnitMachineResource::class,
        KnitCapacityPlanResource::class,
        KnitOperatorPostingResource::class,
        KnitYarnCountResource::class,
        KnitLabelPrinterResource::class,
        FinProcessTypeResource::class,
        FinMachineResource::class,
        FinMachineProcessResource::class,
        FinMachineTargetResource::class,
        FinBatchReceiptResource::class,
        FinReprocessReturnResource::class,
        FinProductionAmendmentResource::class,
        FinQcEntryResource::class,
        FinShadeDecisionResource::class,
        FinReprocessAdviceResource::class,
        FinStoreRollResource::class,
        FinStoreReturnResource::class,
        TxReferenceEntryResource::class,
        TxNotifyPartyResource::class,
        TxLcApplicantResource::class,
        TxExchangeRateResource::class,
        TxBankResource::class,
        TxBankBranchResource::class,
        AccPaymentModeResource::class,
        AccAccountResource::class,
        AccCostCenterGroupResource::class,
        AccCostCenterResource::class,
        AccBankAccountResource::class,
        AccChequeBookResource::class,
        AccFiscalYearResource::class,
        AccAccountClassResource::class,
        AccComplianceRuleResource::class,
        AccAccBankResource::class,
        // Access Control -> Ledger Integration Setup (AnnotechERP 02/03).
        AccGlSourceResource::class,
        AccGlSourceLineResource::class,
        // HR & Payroll → Master Data (AnnotechERP 24/01).
        HrEmploymentTypeResource::class,
        HrDesignationGroupResource::class,
        HrPayGradeResource::class,
        HrPayElementResource::class,
        HrAllowanceResource::class,
        HrDeductionResource::class,
        HrShiftResource::class,
        HrLeaveTypeResource::class,
        HrOtRuleResource::class,
        HrBonusRuleResource::class,
        HrGazetteLineResource::class,
        HrGazetteGradeWageResource::class,
        HrFestivalResource::class,
        HrHolidayResource::class,
        HrPayPeriodResource::class,
        HrFiscalYearResource::class,
    ];

    /** @var array<string, ResourceDefinition>|null */
    private ?array $resolved = null;

    public function find(string $key): ?ResourceDefinition
    {
        return $this->all()[$key] ?? null;
    }

    /** @return array<string, ResourceDefinition> */
    public function all(): array
    {
        if ($this->resolved === null) {
            $this->resolved = [];
            foreach (self::DEFINITIONS as $class) {
                $definition = app($class);
                $this->resolved[$definition->key()] = $definition;
            }
        }

        return $this->resolved;
    }
}
