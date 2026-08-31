<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\WorkflowStepType;
use App\Enums\WorkflowVersionStatus;
use App\Models\Workflow;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

final class WorkflowVersioningTest extends TestCase
{
    use RefreshDatabase;

    public function test_workflow_configuration_tables_store_versioned_ordered_steps_and_conditions(): void
    {
        $this->assertTrue(Schema::hasColumns('workflow_versions', ['workflow_id', 'version_number', 'status', 'effective_from', 'effective_until']));
        $workflow = Workflow::create(['code' => 'standard', 'name' => 'Standard']);
        $version = $workflow->versions()->create(['version_number' => 1, 'status' => WorkflowVersionStatus::Draft]);
        $step = $version->steps()->create(['sequence' => 1, 'name' => 'Review', 'step_type' => WorkflowStepType::Review]);
        $step->conditions()->create(['field_key' => 'priority', 'operator' => 'equals', 'value' => ['high']]);

        $this->assertSame('standard', $version->fresh()->workflow->code);
        $this->assertSame('high', $step->fresh()->conditions->first()->value[0]);
    }

    public function test_activation_rejects_missing_ordered_steps(): void
    {
        $version = Workflow::create(['code' => 'invalid', 'name' => 'Invalid'])->versions()->create(['version_number' => 1]);
        $version->steps()->create(['sequence' => 2, 'name' => 'Review', 'step_type' => WorkflowStepType::Review]);

        $this->expectException(ValidationException::class);
        $version->activate();
    }
}
