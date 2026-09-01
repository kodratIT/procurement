<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Filament\Resources\SampleShipments\Pages\CreateSampleShipment;
use App\Filament\Resources\SampleShipments\Pages\ListSampleShipments;
use App\Filament\Resources\SampleShipments\Pages\ViewSampleShipment;
use App\Filament\Resources\SampleShipments\SampleShipmentResource;
use App\Models\SampleShipment;
use Database\Seeders\ProcurementRolesSeeder;
use Filament\Facades\Filament;
use Filament\Schemas\Schema;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class SampleShipmentResourceTest extends TestCase
{
    use RefreshDatabase;

    public function test_resource_registers_lifecycle_pages(): void
    {
        $this->seed(ProcurementRolesSeeder::class);

        $this->assertContains(SampleShipmentResource::class, Filament::getPanel('admin')->getResources());
        $this->assertSame(SampleShipment::class, SampleShipmentResource::getModel());
        $this->assertSame(ListSampleShipments::class, SampleShipmentResource::getPages()['index']->getPage());
        $this->assertSame(CreateSampleShipment::class, SampleShipmentResource::getPages()['create']->getPage());
        $this->assertSame(ViewSampleShipment::class, SampleShipmentResource::getPages()['view']->getPage());
    }

    public function test_form_declares_origin_offices_responsible_users_and_traceability_fields(): void
    {
        $components = SampleShipmentResource::form(Schema::make())->getComponents();
        $names = collect($components)->map(fn ($component): string => $component->getName())->all();

        foreach (['purchase_order_id', 'sender_office_id', 'receiver_office_id', 'sender_id', 'receiver_id', 'purpose', 'tracking_no', 'shipping_cost', 'cost_center_id', 'lines', 'evidence'] as $name) {
            $this->assertContains($name, $names);
        }
    }
}
