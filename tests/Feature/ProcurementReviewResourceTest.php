<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Filament\Resources\ProcurementReviewResource;
use App\Filament\Resources\ProcurementReviews\Pages\EditProcurementReview;
use App\Filament\Resources\ProcurementReviews\Pages\ManageProcurementReviews;
use App\Filament\Resources\ProcurementReviews\Pages\ViewProcurementReview;
use App\Models\Office;
use App\Models\ProcurementCategory;
use App\Models\PurchaseRequest;
use App\Models\Role;
use App\Models\User;
use App\Models\UserAssignment;
use App\Services\AccessContextService;
use Database\Seeders\ProcurementRolesSeeder;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class ProcurementReviewResourceTest extends TestCase
{
    use RefreshDatabase;

    public function test_review_resource_registers_queue_detail_and_edit_pages(): void
    {
        $this->seed(ProcurementRolesSeeder::class);

        $pages = ProcurementReviewResource::getPages();

        $this->assertContains(ProcurementReviewResource::class, Filament::getPanel('admin')->getResources());
        $this->assertSame(PurchaseRequest::class, ProcurementReviewResource::getModel());
        $this->assertSame(ManageProcurementReviews::class, $pages['index']->getPage());
        $this->assertSame(ViewProcurementReview::class, $pages['view']->getPage());
        $this->assertSame(EditProcurementReview::class, $pages['edit']->getPage());
    }

    public function test_resource_query_uses_the_authenticated_procurement_scope(): void
    {
        $this->seed(ProcurementRolesSeeder::class);
        $reviewer = User::factory()->create();
        $office = Office::factory()->create();
        $otherOffice = Office::factory()->create();
        $category = ProcurementCategory::factory()->create();
        $inScope = PurchaseRequest::factory()->create([
            'office_id' => $office->id,
            'category_id' => $category->id,
            'status' => 'submitted',
        ]);
        $outOfScope = PurchaseRequest::factory()->create([
            'office_id' => $otherOffice->id,
            'category_id' => $category->id,
            'status' => 'submitted',
        ]);

        $this->actingAs($reviewer);
        app(AccessContextService::class)->setContext($this->assignment($reviewer, $office));

        $ids = ProcurementReviewResource::getEloquentQuery()->pluck('id')->all();

        $this->assertSame([$inScope->id], $ids);
        $this->assertNotContains($outOfScope->id, $ids);
    }

    private function assignment(User $user, Office $office): UserAssignment
    {
        $role = Role::query()->where('name', 'Pengadaan')->firstOrFail();

        return UserAssignment::factory()->create([
            'user_id' => $user->id,
            'office_id' => $office->id,
            'role_id' => $role->id,
            'role' => $role->name,
            'valid_from' => now()->subDay()->toDateString(),
            'is_primary' => true,
        ]);
    }
}
