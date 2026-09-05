<?php

declare(strict_types=1);

namespace App\Services;

use App\Filament\Resources\ApprovalInboxResource;
use App\Filament\Resources\ApproverDelegations\ApproverDelegationResource;
use App\Filament\Resources\ApproverMappings\ApproverMappingResource;
use App\Filament\Resources\AutomationWorkflowResource;
use App\Filament\Resources\Branches\BranchResource;
use App\Filament\Resources\Budgets\BudgetResource;
use App\Filament\Resources\CostCenters\CostCenterResource;
use App\Filament\Resources\Departments\DepartmentResource;
use App\Filament\Resources\Distributions\DistributionResource;
use App\Filament\Resources\Invoices\InvoiceResource;
use App\Filament\Resources\Offices\OfficeResource;
use App\Filament\Resources\Pilgrims\PilgrimResource;
use App\Filament\Resources\ProcurementCategoryResource;
use App\Filament\Resources\ProcurementFields\ProcurementFieldResource;
use App\Filament\Resources\ProcurementItemResource;
use App\Filament\Resources\ProcurementReviewResource;
use App\Filament\Resources\ProcurementUnitResource;
use App\Filament\Resources\ProcurementVariantResource;
use App\Filament\Resources\PurchaseOrders\PurchaseOrderResource;
use App\Filament\Resources\PurchaseRequestResource;
use App\Filament\Resources\Quotations\QuotationResource;
use App\Filament\Resources\SampleShipments\SampleShipmentResource;
use App\Filament\Resources\UmrahBatches\UmrahBatchResource;
use App\Filament\Resources\UserAssignments\UserAssignmentResource;
use App\Filament\Resources\VendorResource;
use App\Filament\Resources\WorkflowResource;
use App\Filament\Resources\WorkflowStepResource;
use App\Filament\Resources\WorkflowVersionResource;
use App\Models\Activity;
use App\Models\ApprovalInstanceStep;
use App\Models\ApproverDelegation;
use App\Models\ApproverMapping;
use App\Models\Attachment;
use App\Models\Branch;
use App\Models\Budget;
use App\Models\CostCenter;
use App\Models\Department;
use App\Models\Distribution;
use App\Models\DistributionItem;
use App\Models\GoodsReceipt;
use App\Models\Invoice;
use App\Models\Office;
use App\Models\Payment;
use App\Models\Pilgrim;
use App\Models\PilgrimDistributionItem;
use App\Models\ProcurementCategory;
use App\Models\ProcurementField;
use App\Models\ProcurementItem;
use App\Models\ProcurementUnit;
use App\Models\ProcurementVariant;
use App\Models\PurchaseOrder;
use App\Models\PurchaseRequest;
use App\Models\Quotation;
use App\Models\QuotationRecommendation;
use App\Models\Role;
use App\Models\SampleShipment;
use App\Models\SampleShipmentReceipt;
use App\Models\UmrahBatch;
use App\Models\UserAssignment;
use App\Models\Vendor;
use App\Models\Workflow;
use App\Models\WorkflowStep;
use App\Models\WorkflowVersion;
use BezhanSalleh\FilamentShield\Resources\Roles\RoleResource;
use Illuminate\Database\Eloquent\Model;
use MrAdder\FilamentLogger\Resources\ActivityResource;
use RuntimeException;

final class FeatureRegistry
{
    public const SECTION_PROCUREMENT = 'section.procurement';

    public const SECTION_APPROVALS = 'section.approvals';

    public const SECTION_MASTER_DATA = 'section.master-data';

    public const SECTION_UMRAH_OPERATIONS = 'section.umrah-operations';

    public const SECTION_ORGANIZATION_FINANCE = 'section.organization-finance';

    public const SECTION_AUTOMATION = 'section.automation';

    public const SECTION_SETTINGS = 'section.settings';

    public const FEATURE_REQUESTS = 'procurement.requests';

    public const FEATURE_QUOTES = 'procurement.quotes';

    public const FEATURE_PURCHASE_ORDERS = 'procurement.purchase-orders';

    public const FEATURE_INVOICES = 'procurement.invoices';

    public const FEATURE_DISTRIBUTIONS = 'procurement.distributions';

    public const FEATURE_APPROVAL_INBOX = 'approvals.approval-inbox';

    public const FEATURE_PROCUREMENT_REVIEWS = 'approvals.procurement-reviews';

    public const FEATURE_ITEMS = 'master-data.items';

    public const FEATURE_CATEGORIES = 'master-data.categories';

    public const FEATURE_UNITS = 'master-data.units';

    public const FEATURE_VARIANTS = 'master-data.variants';

    public const FEATURE_CUSTOM_FIELDS = 'master-data.custom-fields';

    public const FEATURE_VENDORS = 'master-data.vendors';

    public const FEATURE_WORKFLOWS = 'master-data.workflows';

    public const FEATURE_WORKFLOW_STAGES = 'master-data.workflow-stages';

    public const FEATURE_WORKFLOW_VERSIONS = 'master-data.workflow-versions';

    public const FEATURE_PILGRIMS = 'umrah-operations.pilgrims';

    public const FEATURE_UMRAH_BATCHES = 'umrah-operations.umrah-batches';

    public const FEATURE_SAMPLE_SHIPMENTS = 'umrah-operations.sample-shipments';

    public const FEATURE_ASSIGNMENTS = 'umrah-operations.assignments';

    public const FEATURE_BRANCHES = 'organization-finance.branches';

    public const FEATURE_OFFICES = 'organization-finance.offices';

    public const FEATURE_DEPARTMENTS = 'organization-finance.departments';

    public const FEATURE_COST_CENTERS = 'organization-finance.cost-centers';

    public const FEATURE_BUDGETS = 'organization-finance.budgets';

    public const FEATURE_APPROVER_MAPPINGS = 'settings.approver-mappings';

    public const FEATURE_APPROVER_DELEGATIONS = 'settings.approver-delegations';

    public const FEATURE_AUTOMATIONS = 'automation.workflows';

    public const FEATURE_ROLES = 'settings.roles';

    public const FEATURE_ACTIVITY_LOG = 'settings.activity-log';

    public const CORE_DASHBOARD = 'core.dashboard';

    public const CORE_FEATURE_MODULES = 'core.feature-modules';

    /** @var array<string, array{key: string, scope: string, label: string, sort: int, feature_keys: list<string>}> */
    private const SECTIONS = [
        self::SECTION_PROCUREMENT => [
            'key' => self::SECTION_PROCUREMENT,
            'scope' => 'section',
            'label' => 'Procurement',
            'sort' => 10,
            'feature_keys' => [
                self::FEATURE_REQUESTS,
                self::FEATURE_QUOTES,
                self::FEATURE_PURCHASE_ORDERS,
                self::FEATURE_INVOICES,
                self::FEATURE_DISTRIBUTIONS,
            ],
        ],
        self::SECTION_APPROVALS => [
            'key' => self::SECTION_APPROVALS,
            'scope' => 'section',
            'label' => 'Approvals',
            'sort' => 20,
            'feature_keys' => [self::FEATURE_APPROVAL_INBOX, self::FEATURE_PROCUREMENT_REVIEWS],
        ],
        self::SECTION_MASTER_DATA => [
            'key' => self::SECTION_MASTER_DATA,
            'scope' => 'section',
            'label' => 'Master Data',

            'sort' => 30,
            'feature_keys' => [
                self::FEATURE_ITEMS,
                self::FEATURE_CATEGORIES,
                self::FEATURE_UNITS,
                self::FEATURE_VARIANTS,
                self::FEATURE_CUSTOM_FIELDS,
                self::FEATURE_VENDORS,
                self::FEATURE_WORKFLOWS,
                self::FEATURE_WORKFLOW_STAGES,
                self::FEATURE_WORKFLOW_VERSIONS,
            ],
        ],
        self::SECTION_UMRAH_OPERATIONS => [
            'key' => self::SECTION_UMRAH_OPERATIONS,
            'scope' => 'section',
            'label' => 'Umrah Operations',
            'sort' => 40,
            'feature_keys' => [
                self::FEATURE_PILGRIMS,
                self::FEATURE_UMRAH_BATCHES,
                self::FEATURE_SAMPLE_SHIPMENTS,
                self::FEATURE_ASSIGNMENTS,
            ],
        ],
        self::SECTION_ORGANIZATION_FINANCE => [
            'key' => self::SECTION_ORGANIZATION_FINANCE,
            'scope' => 'section',
            'label' => 'Organization & Finance',
            'sort' => 50,
            'feature_keys' => [
                self::FEATURE_BRANCHES,
                self::FEATURE_OFFICES,
                self::FEATURE_DEPARTMENTS,
                self::FEATURE_COST_CENTERS,
                self::FEATURE_BUDGETS,
            ],
        ],
        self::SECTION_AUTOMATION => [
            'key' => self::SECTION_AUTOMATION,
            'scope' => 'section',
            'label' => 'Automation',
            'sort' => 55,
            'feature_keys' => [self::FEATURE_AUTOMATIONS],
        ],
        self::SECTION_SETTINGS => [
            'key' => self::SECTION_SETTINGS,
            'scope' => 'section',
            'label' => 'Settings',
            'sort' => 60,
            'feature_keys' => [
                self::FEATURE_APPROVER_MAPPINGS,
                self::FEATURE_APPROVER_DELEGATIONS,
                self::FEATURE_ROLES,
                self::FEATURE_ACTIVITY_LOG,
            ],
        ],
    ];

    /** @var array<string, array{key: string, scope: string, label: string, section_key: string, sort: int, resource: class-string, model: class-string}> */
    private const FEATURES = [
        self::FEATURE_REQUESTS => ['key' => self::FEATURE_REQUESTS, 'scope' => 'feature', 'label' => 'Requests', 'section_key' => self::SECTION_PROCUREMENT, 'sort' => 10, 'resource' => PurchaseRequestResource::class, 'model' => PurchaseRequest::class],
        self::FEATURE_QUOTES => ['key' => self::FEATURE_QUOTES, 'scope' => 'feature', 'label' => 'Quotes', 'section_key' => self::SECTION_PROCUREMENT, 'sort' => 20, 'resource' => QuotationResource::class, 'model' => Quotation::class],
        self::FEATURE_PURCHASE_ORDERS => ['key' => self::FEATURE_PURCHASE_ORDERS, 'scope' => 'feature', 'label' => 'Purchase Orders', 'section_key' => self::SECTION_PROCUREMENT, 'sort' => 30, 'resource' => PurchaseOrderResource::class, 'model' => PurchaseOrder::class],
        self::FEATURE_INVOICES => ['key' => self::FEATURE_INVOICES, 'scope' => 'feature', 'label' => 'Invoices', 'section_key' => self::SECTION_PROCUREMENT, 'sort' => 40, 'resource' => InvoiceResource::class, 'model' => Invoice::class],
        self::FEATURE_DISTRIBUTIONS => ['key' => self::FEATURE_DISTRIBUTIONS, 'scope' => 'feature', 'label' => 'Distributions', 'section_key' => self::SECTION_PROCUREMENT, 'sort' => 50, 'resource' => DistributionResource::class, 'model' => Distribution::class],
        self::FEATURE_APPROVAL_INBOX => ['key' => self::FEATURE_APPROVAL_INBOX, 'scope' => 'feature', 'label' => 'Approval Inbox', 'section_key' => self::SECTION_APPROVALS, 'sort' => 10, 'resource' => ApprovalInboxResource::class, 'model' => ApprovalInstanceStep::class],
        self::FEATURE_PROCUREMENT_REVIEWS => ['key' => self::FEATURE_PROCUREMENT_REVIEWS, 'scope' => 'feature', 'label' => 'Procurement Reviews', 'section_key' => self::SECTION_APPROVALS, 'sort' => 20, 'resource' => ProcurementReviewResource::class, 'model' => PurchaseRequest::class],
        self::FEATURE_ITEMS => ['key' => self::FEATURE_ITEMS, 'scope' => 'feature', 'label' => 'Items', 'section_key' => self::SECTION_MASTER_DATA, 'sort' => 10, 'resource' => ProcurementItemResource::class, 'model' => ProcurementItem::class],
        self::FEATURE_CATEGORIES => ['key' => self::FEATURE_CATEGORIES, 'scope' => 'feature', 'label' => 'Categories', 'section_key' => self::SECTION_MASTER_DATA, 'sort' => 20, 'resource' => ProcurementCategoryResource::class, 'model' => ProcurementCategory::class],
        self::FEATURE_UNITS => ['key' => self::FEATURE_UNITS, 'scope' => 'feature', 'label' => 'Units', 'section_key' => self::SECTION_MASTER_DATA, 'sort' => 30, 'resource' => ProcurementUnitResource::class, 'model' => ProcurementUnit::class],
        self::FEATURE_VARIANTS => ['key' => self::FEATURE_VARIANTS, 'scope' => 'feature', 'label' => 'Variants', 'section_key' => self::SECTION_MASTER_DATA, 'sort' => 40, 'resource' => ProcurementVariantResource::class, 'model' => ProcurementVariant::class],
        self::FEATURE_CUSTOM_FIELDS => ['key' => self::FEATURE_CUSTOM_FIELDS, 'scope' => 'feature', 'label' => 'Custom Fields', 'section_key' => self::SECTION_MASTER_DATA, 'sort' => 50, 'resource' => ProcurementFieldResource::class, 'model' => ProcurementField::class],
        self::FEATURE_VENDORS => ['key' => self::FEATURE_VENDORS, 'scope' => 'feature', 'label' => 'Vendors', 'section_key' => self::SECTION_MASTER_DATA, 'sort' => 60, 'resource' => VendorResource::class, 'model' => Vendor::class],
        self::FEATURE_WORKFLOWS => ['key' => self::FEATURE_WORKFLOWS, 'scope' => 'feature', 'label' => 'Workflows', 'section_key' => self::SECTION_MASTER_DATA, 'sort' => 70, 'resource' => WorkflowResource::class, 'model' => Workflow::class],
        self::FEATURE_WORKFLOW_STAGES => ['key' => self::FEATURE_WORKFLOW_STAGES, 'scope' => 'feature', 'label' => 'Workflow Stages', 'section_key' => self::SECTION_MASTER_DATA, 'sort' => 80, 'resource' => WorkflowStepResource::class, 'model' => WorkflowStep::class],
        self::FEATURE_WORKFLOW_VERSIONS => ['key' => self::FEATURE_WORKFLOW_VERSIONS, 'scope' => 'feature', 'label' => 'Workflow Versions', 'section_key' => self::SECTION_MASTER_DATA, 'sort' => 90, 'resource' => WorkflowVersionResource::class, 'model' => WorkflowVersion::class],
        self::FEATURE_PILGRIMS => ['key' => self::FEATURE_PILGRIMS, 'scope' => 'feature', 'label' => 'Pilgrims', 'section_key' => self::SECTION_UMRAH_OPERATIONS, 'sort' => 10, 'resource' => PilgrimResource::class, 'model' => Pilgrim::class],
        self::FEATURE_UMRAH_BATCHES => ['key' => self::FEATURE_UMRAH_BATCHES, 'scope' => 'feature', 'label' => 'Umrah Batches', 'section_key' => self::SECTION_UMRAH_OPERATIONS, 'sort' => 20, 'resource' => UmrahBatchResource::class, 'model' => UmrahBatch::class],
        self::FEATURE_SAMPLE_SHIPMENTS => ['key' => self::FEATURE_SAMPLE_SHIPMENTS, 'scope' => 'feature', 'label' => 'Sample Shipments', 'section_key' => self::SECTION_UMRAH_OPERATIONS, 'sort' => 40, 'resource' => SampleShipmentResource::class, 'model' => SampleShipment::class],
        self::FEATURE_ASSIGNMENTS => ['key' => self::FEATURE_ASSIGNMENTS, 'scope' => 'feature', 'label' => 'Assignments', 'section_key' => self::SECTION_UMRAH_OPERATIONS, 'sort' => 50, 'resource' => UserAssignmentResource::class, 'model' => UserAssignment::class],
        self::FEATURE_BRANCHES => ['key' => self::FEATURE_BRANCHES, 'scope' => 'feature', 'label' => 'Branches', 'section_key' => self::SECTION_ORGANIZATION_FINANCE, 'sort' => 10, 'resource' => BranchResource::class, 'model' => Branch::class],
        self::FEATURE_OFFICES => ['key' => self::FEATURE_OFFICES, 'scope' => 'feature', 'label' => 'Offices', 'section_key' => self::SECTION_ORGANIZATION_FINANCE, 'sort' => 20, 'resource' => OfficeResource::class, 'model' => Office::class],
        self::FEATURE_DEPARTMENTS => ['key' => self::FEATURE_DEPARTMENTS, 'scope' => 'feature', 'label' => 'Departments', 'section_key' => self::SECTION_ORGANIZATION_FINANCE, 'sort' => 30, 'resource' => DepartmentResource::class, 'model' => Department::class],
        self::FEATURE_COST_CENTERS => ['key' => self::FEATURE_COST_CENTERS, 'scope' => 'feature', 'label' => 'Cost Centers', 'section_key' => self::SECTION_ORGANIZATION_FINANCE, 'sort' => 40, 'resource' => CostCenterResource::class, 'model' => CostCenter::class],
        self::FEATURE_BUDGETS => ['key' => self::FEATURE_BUDGETS, 'scope' => 'feature', 'label' => 'Budgets', 'section_key' => self::SECTION_ORGANIZATION_FINANCE, 'sort' => 50, 'resource' => BudgetResource::class, 'model' => Budget::class],
        self::FEATURE_AUTOMATIONS => ['key' => self::FEATURE_AUTOMATIONS, 'scope' => 'feature', 'label' => 'Automations', 'section_key' => self::SECTION_AUTOMATION, 'sort' => 10, 'resource' => AutomationWorkflowResource::class, 'model' => \Packstub\Flow\Models\Workflow::class],
        self::FEATURE_APPROVER_MAPPINGS => ['key' => self::FEATURE_APPROVER_MAPPINGS, 'scope' => 'feature', 'label' => 'Approver Mappings', 'section_key' => self::SECTION_SETTINGS, 'sort' => 10, 'resource' => ApproverMappingResource::class, 'model' => ApproverMapping::class],
        self::FEATURE_APPROVER_DELEGATIONS => ['key' => self::FEATURE_APPROVER_DELEGATIONS, 'scope' => 'feature', 'label' => 'Approver Delegations', 'section_key' => self::SECTION_SETTINGS, 'sort' => 20, 'resource' => ApproverDelegationResource::class, 'model' => ApproverDelegation::class],
        self::FEATURE_ROLES => ['key' => self::FEATURE_ROLES, 'scope' => 'feature', 'label' => 'Roles', 'section_key' => self::SECTION_SETTINGS, 'sort' => 30, 'resource' => RoleResource::class, 'model' => Role::class],
        self::FEATURE_ACTIVITY_LOG => ['key' => self::FEATURE_ACTIVITY_LOG, 'scope' => 'feature', 'label' => 'Activity Log', 'section_key' => self::SECTION_SETTINGS, 'sort' => 40, 'resource' => ActivityResource::class, 'model' => Activity::class],
    ];

    public function sections(): array
    {
        return array_values(self::SECTIONS);
    }

    public function features(): array
    {
        return array_values(self::FEATURES);
    }

    public function managedNavigationEntries(): array
    {
        return $this->features();
    }

    public function stateKeys(): array
    {
        return array_merge(array_keys(self::SECTIONS), array_keys(self::FEATURES));
    }

    /** @return array{key: string, scope: string, label: string, sort: int, feature_keys: list<string>}|null */
    public function section(string $key): ?array
    {
        return self::SECTIONS[$key] ?? null;
    }

    /** @return array{key: string, scope: string, label: string, section_key: string, sort: int, resource: class-string, model: class-string}|null */
    public function feature(string $key): ?array
    {
        return self::FEATURES[$key] ?? null;
    }

    public function isManagedKey(string $key): bool
    {
        return isset(self::SECTIONS[$key]) || isset(self::FEATURES[$key]);
    }

    public function featureForResource(string|object $resource): ?string
    {
        $resourceClass = is_string($resource) ? $resource : $resource::class;

        foreach (self::FEATURES as $feature) {
            if ($feature['resource'] === $resourceClass) {
                return $feature['key'];
            }
        }

        return null;
    }

    public function featureForModel(string|object $model): ?string
    {
        $modelClass = is_string($model) ? $model : $model::class;
        $keys = [];

        foreach (self::FEATURES as $feature) {
            if ($feature['model'] === $modelClass) {
                $keys[] = $feature['key'];
            }
        }

        return count($keys) === 1 ? $keys[0] : null;
    }

    public function featureForSubject(mixed $subject, ?string $explicitOwner = null): ?string
    {
        if ($explicitOwner !== null) {
            return $this->feature($explicitOwner) !== null ? $explicitOwner : null;
        }

        if (is_string($subject)) {
            return $this->featureForModel($subject);
        }

        if (! $subject instanceof Model) {
            return null;
        }

        $childOwners = [
            Payment::class => self::FEATURE_INVOICES,
            GoodsReceipt::class => self::FEATURE_PURCHASE_ORDERS,
            SampleShipmentReceipt::class => self::FEATURE_SAMPLE_SHIPMENTS,
            DistributionItem::class => self::FEATURE_DISTRIBUTIONS,
            PilgrimDistributionItem::class => self::FEATURE_DISTRIBUTIONS,
            QuotationRecommendation::class => self::FEATURE_QUOTES,
        ];

        if ($subject instanceof Attachment) {
            return [
                PurchaseRequest::class => self::FEATURE_REQUESTS,
                Quotation::class => self::FEATURE_QUOTES,
                QuotationRecommendation::class => self::FEATURE_QUOTES,
                Invoice::class => self::FEATURE_INVOICES,
                Payment::class => self::FEATURE_INVOICES,
                PurchaseOrder::class => self::FEATURE_PURCHASE_ORDERS,
                GoodsReceipt::class => self::FEATURE_PURCHASE_ORDERS,
                SampleShipment::class => self::FEATURE_SAMPLE_SHIPMENTS,
                SampleShipmentReceipt::class => self::FEATURE_SAMPLE_SHIPMENTS,
                Distribution::class => self::FEATURE_DISTRIBUTIONS,
                DistributionItem::class => self::FEATURE_DISTRIBUTIONS,
                PilgrimDistributionItem::class => self::FEATURE_DISTRIBUTIONS,
            ][$subject->getAttribute('attachable_type')] ?? null;
        }

        return $childOwners[$subject::class] ?? $this->featureForModel($subject);
    }

    public function validate(): void
    {
        $resourceKeys = [];
        foreach (self::FEATURES as $feature) {
            $resource = $feature['resource'];
            if (isset($resourceKeys[$resource])) {
                throw new RuntimeException("Resource {$resource} maps to multiple feature keys.");
            }
            $resourceKeys[$resource] = $feature['key'];

            if (! isset(self::SECTIONS[$feature['section_key']])) {
                throw new RuntimeException("Feature {$feature['key']} references an unknown section.");
            }
        }

        foreach (self::SECTIONS as $section) {
            foreach ($section['feature_keys'] as $key) {
                if (! isset(self::FEATURES[$key])) {
                    throw new RuntimeException("Section {$section['key']} references an unknown feature.");
                }
            }
        }
    }

    public function isCore(string $key): bool
    {
        return in_array($key, [self::CORE_DASHBOARD, self::CORE_FEATURE_MODULES], true);
    }
}
