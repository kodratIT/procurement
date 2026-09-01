<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Filament\Resources\ApproverDelegations\ApproverDelegationResource;
use App\Filament\Resources\ApproverMappings\ApproverMappingResource;
use App\Models\ApproverDelegation;
use App\Models\ApproverMapping;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class ApproverMappingResourceTest extends TestCase
{
    use RefreshDatabase;

    public function test_mapping_and_delegation_resources_are_registered_with_crud_pages(): void
    {
        $panel = Filament::getPanel('admin');

        $this->assertContains(ApproverMappingResource::class, $panel->getResources());
        $this->assertContains(ApproverDelegationResource::class, $panel->getResources());
        $this->assertSame(ApproverMapping::class, ApproverMappingResource::getModel());
        $this->assertSame(ApproverDelegation::class, ApproverDelegationResource::getModel());
        $this->assertArrayHasKey('create', ApproverMappingResource::getPages());
        $this->assertArrayHasKey('edit', ApproverMappingResource::getPages());
        $this->assertArrayHasKey('create', ApproverDelegationResource::getPages());
        $this->assertArrayHasKey('edit', ApproverDelegationResource::getPages());
    }
}
