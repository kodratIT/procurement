<?php

namespace Tests\Feature;

use App\Filament\Resources\Branches\BranchResource;
use App\Filament\Resources\CostCenters\CostCenterResource;
use App\Filament\Resources\Departments\DepartmentResource;
use App\Filament\Resources\Offices\OfficeResource;
use App\Models\Branch;
use App\Models\CostCenter;
use App\Models\Department;
use App\Models\Office;
use App\Models\User;
use Database\Seeders\ProcurementRolesSeeder;
use Filament\Facades\Filament;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use InvalidArgumentException;
use Tests\TestCase;

class OrganizationResourceTest extends TestCase
{
    use RefreshDatabase;

    public function test_organization_resources_are_registered_and_admin_permission_is_required(): void
    {
        $this->seed(ProcurementRolesSeeder::class);

        $panel = Filament::getPanel('admin');
        $admin = User::factory()->create();
        $admin->assignRole('Admin');
        $viewer = User::factory()->create();
        $viewer->assignRole('Viewer');

        foreach ([
            OfficeResource::class => Office::class,
            BranchResource::class => Branch::class,
            DepartmentResource::class => Department::class,
            CostCenterResource::class => CostCenter::class,
        ] as $resource => $model) {
            $this->assertContains($resource, $panel->getResources());
            $this->assertSame($model, $resource::getModel());
            $this->assertTrue($admin->can('viewAny', $model));
            $this->assertTrue($admin->can('create', $model));
            $this->assertFalse($viewer->can('viewAny', $model));
        }
    }

    public function test_codes_and_names_are_unique_within_an_office_but_isolated_between_offices(): void
    {
        $officeA = Office::factory()->create(['code' => 'OFF-A', 'name' => 'Office A']);
        $officeB = Office::factory()->create(['code' => 'OFF-B', 'name' => 'Office B']);

        Branch::factory()->create([
            'office_id' => $officeA->id,
            'code' => 'OPS',
            'name' => 'Operations',
        ]);
        $branchInOtherOffice = Branch::factory()->create([
            'office_id' => $officeB->id,
            'code' => 'OPS',
            'name' => 'Operations',
        ]);

        $this->assertModelExists($branchInOtherOffice);

        $this->expectException(QueryException::class);
        Branch::factory()->create([
            'office_id' => $officeA->id,
            'code' => 'OPS',
            'name' => 'Operations Alt',
        ]);
    }

    public function test_names_are_unique_within_each_office_for_all_child_records(): void
    {
        $officeA = Office::factory()->create(['code' => 'OFF-A', 'name' => 'Office A']);
        $officeB = Office::factory()->create(['code' => 'OFF-B', 'name' => 'Office B']);

        foreach ([
            Branch::class => ['code' => 'BR'],
            Department::class => ['code' => 'DEP'],
            CostCenter::class => ['code' => 'CC'],
        ] as $model => $attributes) {
            $model::factory()->create([
                'office_id' => $officeA->id,
                'code' => $attributes['code'].'-A',
                'name' => 'Shared name',
            ]);
            $model::factory()->create([
                'office_id' => $officeB->id,
                'code' => $attributes['code'].'-B',
                'name' => 'Shared name',
            ]);

            try {
                $model::factory()->create([
                    'office_id' => $officeA->id,
                    'code' => $attributes['code'].'-C',
                    'name' => 'Shared name',
                ]);
                $this->fail("Duplicate {$model} name should be rejected.");
            } catch (QueryException $exception) {
                $this->assertStringContainsString('name', $exception->getMessage());
            }
        }
    }

    public function test_department_cannot_attach_a_branch_from_another_office(): void
    {
        $officeA = Office::factory()->create(['code' => 'OFF-A', 'name' => 'Office A']);
        $officeB = Office::factory()->create(['code' => 'OFF-B', 'name' => 'Office B']);
        $branchInOfficeB = Branch::factory()->create(['office_id' => $officeB->id]);

        $this->expectException(InvalidArgumentException::class);
        Department::factory()->create([
            'office_id' => $officeA->id,
            'branch_id' => $branchInOfficeB->id,
        ]);
    }

    public function test_referenced_organization_records_cannot_be_deleted_but_can_be_deactivated(): void
    {
        $office = Office::factory()->create();
        $branch = Branch::factory()->create(['office_id' => $office->id]);

        try {
            $office->delete();
            $this->fail('Referenced office deletion should be blocked.');
        } catch (QueryException $exception) {
            $this->assertStringContainsString('offices', $exception->getMessage());
        }

        $office->deactivate();
        $branch->deactivate();

        $this->assertModelExists($office->refresh());
        $this->assertFalse($office->is_active);
        $this->assertNotNull($office->disabled_at);
        $this->assertModelExists($branch->refresh());
        $this->assertFalse($branch->is_active);
        $this->assertNotNull($branch->disabled_at);
    }

    public function test_delete_policy_is_disabled_for_all_organization_records(): void
    {
        $this->seed(ProcurementRolesSeeder::class);
        $admin = User::factory()->create();
        $admin->assignRole('Admin');

        foreach ([Office::class, Branch::class, Department::class, CostCenter::class] as $model) {
            $this->assertFalse($admin->can('deleteAny', $model));
        }
    }
}
