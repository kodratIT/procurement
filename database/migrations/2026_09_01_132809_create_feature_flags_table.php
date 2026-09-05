<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('feature_flags', function (Blueprint $table): void {
            $table->id();
            $table->string('key')->unique();
            $table->boolean('enabled')->default(true);
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        $now = now();
        DB::table('feature_flags')->insertOrIgnore(array_map(
            static fn (string $key): array => [
                'key' => $key,
                'enabled' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'section.procurement',
                'section.approvals',
                'section.master-data',
                'section.umrah-operations',
                'section.organization-finance',
                'section.automation',
                'section.settings',
                'procurement.requests',
                'procurement.quotes',
                'procurement.purchase-orders',
                'procurement.invoices',
                'procurement.distributions',
                'approvals.approval-inbox',
                'approvals.procurement-reviews',
                'master-data.items',
                'master-data.categories',
                'master-data.units',
                'master-data.variants',
                'master-data.custom-fields',
                'master-data.vendors',
                'master-data.workflows',
                'master-data.workflow-stages',
                'master-data.workflow-versions',
                'umrah-operations.pilgrims',
                'umrah-operations.umrah-batches',
                'umrah-operations.sample-shipments',
                'umrah-operations.assignments',
                'organization-finance.branches',
                'organization-finance.offices',
                'organization-finance.departments',
                'organization-finance.cost-centers',
                'organization-finance.budgets',
                'automation.workflows',
                'settings.approver-mappings',
                'settings.approver-delegations',
                'settings.roles',
                'settings.activity-log',
            ],
        ));
    }

    public function down(): void
    {
        Schema::dropIfExists('feature_flags');
    }
};
