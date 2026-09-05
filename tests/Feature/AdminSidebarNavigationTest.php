<?php

declare(strict_types=1);

namespace Tests\Feature;

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
use App\Services\FeatureRegistry;
use BezhanSalleh\FilamentShield\Resources\Roles\RoleResource;
use Filament\Facades\Filament;
use MrAdder\FilamentLogger\Resources\ActivityResource;
use Tests\TestCase;

final class AdminSidebarNavigationTest extends TestCase
{
    public function test_admin_sidebar_uses_ordered_english_navigation_groups(): void
    {
        $panel = Filament::getPanel('admin');

        $this->assertSame([
            'Procurement',
            'Approvals',
            'Master Data',
            'Umrah Operations',
            'Organization & Finance',
            'Approval',
            'Automation',
            'Settings',
        ], $panel->getNavigationGroups());

        $resources = [
            PurchaseRequestResource::class => ['Procurement', 'Requests', 10],
            QuotationResource::class => ['Procurement', 'Quotes', 20],
            PurchaseOrderResource::class => ['Procurement', 'Purchase Orders', 30],
            InvoiceResource::class => ['Procurement', 'Invoices', 40],
            DistributionResource::class => ['Procurement', 'Distributions', 50],
            ApprovalInboxResource::class => ['Approvals', 'Approvals', 10],
            ProcurementItemResource::class => ['Master Data', 'Items', 10],
            ProcurementCategoryResource::class => ['Master Data', 'Categories', 20],
            ProcurementUnitResource::class => ['Master Data', 'Units', 30],
            ProcurementVariantResource::class => ['Master Data', 'Variants', 40],
            ProcurementFieldResource::class => ['Master Data', 'Custom Fields', 50],
            VendorResource::class => ['Master Data', 'Vendors', 60],
            WorkflowResource::class => ['Approval', 'Workflows', 10],
            WorkflowStepResource::class => ['Approval', 'Workflow Stages', 30],
            WorkflowVersionResource::class => ['Approval', 'Workflow Versions', 20],
            AutomationWorkflowResource::class => ['Automation', 'Automations', null],
            PilgrimResource::class => ['Umrah Operations', 'Pilgrims', 10],
            UmrahBatchResource::class => ['Umrah Operations', 'Umrah Batches', 20],
            SampleShipmentResource::class => ['Umrah Operations', 'Sample Shipments', 40],
            UserAssignmentResource::class => ['Umrah Operations', 'Assignments', 50],
            BranchResource::class => ['Organization & Finance', 'Branches', 10],
            OfficeResource::class => ['Organization & Finance', 'Offices', 20],
            DepartmentResource::class => ['Organization & Finance', 'Departments', 30],
            CostCenterResource::class => ['Organization & Finance', 'Cost Centers', 40],
            BudgetResource::class => ['Organization & Finance', 'Budgets', 50],
            ApproverMappingResource::class => ['Approval', 'Approver Mappings', 40],
            ApproverDelegationResource::class => ['Approval', 'Approver Delegations', 50],
        ];

        $registry = app(FeatureRegistry::class);

        foreach ($resources as $resource => [$group, $label, $sort]) {
            $this->assertSame($group, $resource::getNavigationGroup(), "{$resource} group mismatch");
            $this->assertSame($label, $resource::getNavigationLabel(), "{$resource} label mismatch");
            $this->assertSame($sort, $resource::getNavigationSort(), "{$resource} sort mismatch");
            $this->assertNotNull($registry->featureForResource($resource), "{$resource} feature mapping missing");
        }

        /** @var FilamentShieldPlugin $shield */
        $shield = $panel->getPlugin('filament-shield');
        $this->assertSame('Settings', $shield->getNavigationGroup());
        $this->assertSame('Roles', $shield->getNavigationLabel());
        $this->assertSame(30, $shield->getNavigationSort());
        $this->assertNotNull($registry->featureForResource(RoleResource::class));
        $this->assertSame('Settings', ActivityResource::getNavigationGroup());
        $this->assertSame('Activity Log', ActivityResource::getNavigationLabel());
        $this->assertSame(40, ActivityResource::getNavigationSort());
        $this->assertNotNull($registry->featureForResource(ActivityResource::class));
    }
}
