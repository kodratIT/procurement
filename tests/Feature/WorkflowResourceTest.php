<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Filament\Resources\WorkflowResource;
use App\Models\Workflow;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

final class WorkflowResourceTest extends TestCase
{
    use RefreshDatabase;

    public function test_workflow_resource_exposes_configuration_crud_and_lifecycle_pages(): void
    {
        $this->assertSame(Workflow::class, WorkflowResource::getModel());
        $this->assertArrayHasKey('index', WorkflowResource::getPages());
        $this->assertTrue(Schema::hasColumns('workflows', ['code', 'name', 'description', 'is_active']));
        $this->assertTrue(Schema::hasColumns('workflow_bindings', ['minimum_amount', 'maximum_amount', 'priority', 'conditions']));
    }
}
