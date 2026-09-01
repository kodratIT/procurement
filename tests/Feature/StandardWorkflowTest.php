<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\ApproverMapping;
use App\Models\ProcurementCategory;
use App\Models\Workflow;
use Database\Seeders\OrganizationSeeder;
use Database\Seeders\ProcurementMasterSeeder;
use Database\Seeders\ProcurementRolesSeeder;
use Database\Seeders\StandardWorkflowSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class StandardWorkflowTest extends TestCase
{
    use RefreshDatabase;

    public function test_standard_supplies_workflow_has_finance_gate_and_office_scoped_role_mappings(): void
    {
        $this->seed([
            ProcurementRolesSeeder::class,
            OrganizationSeeder::class,
            ProcurementMasterSeeder::class,
            StandardWorkflowSeeder::class,
        ]);

        $workflow = Workflow::query()->where('code', 'standard-procurement')->firstOrFail();
        $version = $workflow->activeVersion();
        $this->assertNotNull($version);
        $this->assertCount(2, $version->steps);
        $this->assertSame('finance_approval', $version->steps[1]->settings['step_key']);
        $this->assertTrue($version->steps[1]->settings['budget_check']['required']);
        $this->assertSame('E8-US01', $version->steps[1]->settings['budget_check']['hook']);

        $financeMappings = ApproverMapping::query()
            ->where('workflow_step_id', $version->steps[1]->id)
            ->get();
        $this->assertSame(4, ProcurementCategory::query()->where('workflow_reference', 'standard-procurement')->count());
        $this->assertTrue($financeMappings->every(fn (ApproverMapping $mapping): bool => $mapping->user_id === null
            && $mapping->role?->name === 'Keuangan'));
    }
}
