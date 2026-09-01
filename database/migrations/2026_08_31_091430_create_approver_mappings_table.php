<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('approver_mappings', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('workflow_step_id')->nullable()->constrained('workflow_steps')->nullOnDelete();
            $table->string('resolver_type', 50);
            $table->foreignId('role_id')->nullable()->constrained('roles')->nullOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('office_id')->nullable()->constrained('offices')->nullOnDelete();
            $table->foreignId('branch_id')->nullable()->constrained('branches')->nullOnDelete();
            $table->foreignId('department_id')->nullable()->constrained('departments')->nullOnDelete();
            $table->foreignId('cost_center_id')->nullable()->constrained('cost_centers')->nullOnDelete();
            $table->string('scope_source', 50)->default('request_office');
            $table->string('fallback_type', 30)->default('block');
            $table->foreignId('fallback_role_id')->nullable()->constrained('roles')->nullOnDelete();
            $table->foreignId('fallback_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->unsignedInteger('priority')->default(0);
            $table->boolean('allow_self_approval')->default(false);
            $table->date('valid_from')->nullable();
            $table->date('valid_until')->nullable();
            $table->boolean('is_active')->default(true);
            $table->json('settings')->nullable();
            $table->timestamps();

            $table->index(['resolver_type', 'is_active', 'valid_from', 'valid_until']);
            $table->index(['workflow_step_id', 'resolver_type', 'priority']);
            $table->index(['office_id', 'branch_id', 'department_id', 'cost_center_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('approver_mappings');
    }
};
