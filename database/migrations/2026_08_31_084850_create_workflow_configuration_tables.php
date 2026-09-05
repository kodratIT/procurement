<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('workflows', function (Blueprint $table): void {
            $table->id();
            $table->string('code', 100)->unique();
            $table->string('name');
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('workflow_versions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('workflow_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('version_number');
            $table->string('status', 20)->default('draft');
            $table->timestamp('effective_from')->nullable();
            $table->timestamp('effective_until')->nullable();
            $table->timestamp('activated_at')->nullable();
            $table->timestamp('retired_at')->nullable();
            $table->timestamps();
            $table->unique(['workflow_id', 'version_number']);
            $table->index(['workflow_id', 'status']);
        });

        Schema::create('workflow_steps', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('workflow_version_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('sequence');
            $table->string('name');
            $table->string('step_type', 30);
            $table->string('approval_mode', 30)->default('sequential');
            $table->string('resolver_type', 50)->nullable();
            $table->string('required_permission', 100)->nullable();
            $table->boolean('is_required')->default(true);
            $table->unsignedInteger('sla_minutes')->nullable();
            $table->string('escalation_type', 30)->nullable();
            $table->json('settings')->nullable();
            $table->timestamps();
            $table->unique(['workflow_version_id', 'sequence']);
        });

        Schema::create('workflow_conditions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('workflow_step_id')->constrained()->cascadeOnDelete();
            $table->string('field_key', 100);
            $table->string('operator', 30);
            $table->json('value');
            $table->timestamps();
            $table->index(['workflow_step_id', 'field_key']);
        });

        Schema::create('workflow_bindings', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('workflow_id')->constrained()->cascadeOnDelete();
            $table->foreignId('office_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('branch_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('department_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('category_id')->nullable()->constrained('procurement_categories')->nullOnDelete();
            $table->foreignId('cost_center_id')->nullable()->constrained()->nullOnDelete();
            $table->string('transaction_type', 50)->nullable();
            $table->decimal('minimum_amount', 18, 2)->nullable();
            $table->decimal('maximum_amount', 18, 2)->nullable();
            $table->unsignedInteger('priority')->default(0);
            $table->json('conditions')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->index(['workflow_id', 'is_active', 'priority']);
            $table->index(['office_id', 'branch_id', 'department_id', 'category_id'], 'workflow_bindings_scope_index');
        });

        Schema::table('approval_instances', function (Blueprint $table): void {
            $table->foreignId('workflow_version_id')->nullable()->after('purchase_request_id')->constrained()->nullOnDelete();
            $table->index(['workflow_version_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::table('approval_instances', function (Blueprint $table): void {
            $table->dropForeign(['workflow_version_id']);
            $table->dropIndex(['workflow_version_id', 'status']);
            $table->dropColumn('workflow_version_id');
        });
        Schema::dropIfExists('workflow_bindings');
        Schema::dropIfExists('workflow_conditions');
        Schema::dropIfExists('workflow_steps');
        Schema::dropIfExists('workflow_versions');
        Schema::dropIfExists('workflows');
    }
};
