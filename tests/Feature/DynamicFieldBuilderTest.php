<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Filament\Resources\ProcurementFields\ProcurementFieldResource;
use App\Models\ProcurementCategory;
use App\Models\ProcurementField;
use App\Models\User;
use Database\Seeders\ProcurementRolesSeeder;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Tests\TestCase;

class DynamicFieldBuilderTest extends TestCase
{
    use RefreshDatabase;

    public function test_field_builder_is_registered_and_configuration_permission_is_required(): void
    {
        $this->seed(ProcurementRolesSeeder::class);
        $panel = Filament::getPanel('admin');
        $admin = User::factory()->create();
        $admin->assignRole('Admin');
        $viewer = User::factory()->create();
        $viewer->assignRole('Viewer');

        $this->assertContains(ProcurementFieldResource::class, $panel->getResources());
        $this->assertSame(ProcurementField::class, ProcurementFieldResource::getModel());
        $this->assertTrue($admin->can('viewAny', ProcurementField::class));
        $this->assertTrue($admin->can('create', ProcurementField::class));
        $this->assertFalse($viewer->can('viewAny', ProcurementField::class));
    }

    public function test_admin_can_reorder_activate_deactivate_and_preview_field_definitions(): void
    {
        $this->seed(ProcurementRolesSeeder::class);
        $admin = User::factory()->create();
        $admin->assignRole('Admin');
        Auth::login($admin);

        $category = ProcurementCategory::factory()->create();
        $first = ProcurementField::factory()->create([
            'category_id' => $category->id,
            'key' => 'first_field',
            'sort_order' => 20,
        ]);
        $second = ProcurementField::factory()->create([
            'category_id' => $category->id,
            'key' => 'second_field',
            'sort_order' => 10,
            'is_active' => false,
        ]);

        $second->activate();
        $first->deactivate();

        $this->assertSame(['second_field', 'first_field'], $category->fields()->ordered()->pluck('key')->all());
        $this->assertTrue($second->fresh()->is_active);
        $this->assertFalse($first->fresh()->is_active);
        $this->assertContains('index', array_keys(ProcurementFieldResource::getPages()));
    }
}
