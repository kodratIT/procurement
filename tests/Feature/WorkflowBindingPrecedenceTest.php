<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\Office;
use App\Models\Workflow;
use App\Services\WorkflowBindingSelector;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

final class WorkflowBindingPrecedenceTest extends TestCase
{
    use RefreshDatabase;

    public function test_more_specific_binding_wins_before_priority_tie_breaking(): void
    {
        $office = Office::factory()->create();
        $branch = Branch::factory()->for($office)->create();
        $workflow = Workflow::create(['code' => 'standard', 'name' => 'Standard']);
        $workflow->bindings()->create(['office_id' => $office->id, 'priority' => 100]);
        $specific = $workflow->bindings()->create(['office_id' => $office->id, 'branch_id' => $branch->id, 'priority' => 1]);

        $selected = app(WorkflowBindingSelector::class)->select(['workflow_id' => $workflow->id, 'office_id' => $office->id, 'branch_id' => $branch->id]);

        $this->assertTrue($selected->is($specific));
    }

    public function test_equal_specificity_and_priority_is_rejected(): void
    {
        $office = Office::factory()->create();
        $workflow = Workflow::create(['code' => 'ambiguous', 'name' => 'Ambiguous']);
        $workflow->bindings()->create(['office_id' => $office->id, 'priority' => 10]);
        $workflow->bindings()->create(['office_id' => $office->id, 'priority' => 10]);

        $this->expectException(ValidationException::class);
        app(WorkflowBindingSelector::class)->select(['workflow_id' => $workflow->id, 'office_id' => $office->id]);
    }

    public function test_simulation_returns_stable_selection_contract(): void
    {
        $workflow = Workflow::create(['code' => 'simulation', 'name' => 'Simulation']);
        $binding = $workflow->bindings()->create(['transaction_type' => 'purchase_request', 'priority' => 3]);

        $result = app(WorkflowBindingSelector::class)->simulate(['workflow_id' => $workflow->id, 'transaction_type' => 'purchase_request']);

        $this->assertSame($binding->id, $result['binding_id']);
        $this->assertSame(3, $result['priority']);
        $this->assertArrayHasKey('specificity', $result);
    }
}
