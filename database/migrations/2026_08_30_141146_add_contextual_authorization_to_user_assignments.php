<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('roles', function (Blueprint $table): void {
            $table->string('code')->nullable()->unique()->after('guard_name');
            $table->boolean('is_active')->default(true)->index()->after('code');
        });

        Schema::table('permissions', function (Blueprint $table): void {
            $table->string('code')->nullable()->unique()->after('guard_name');
            $table->string('module')->nullable()->index()->after('code');
            $table->string('action')->nullable()->after('module');
        });

        Schema::table('user_assignments', function (Blueprint $table): void {
            $table->dropUnique('user_assignments_user_id_office_id_valid_from_unique');
        });

        Schema::table('user_assignments', function (Blueprint $table): void {
            $table->foreignId('role_id')->nullable()->after('role')->constrained('roles')->restrictOnDelete();
            $table->index(['role_id', 'is_active', 'valid_from', 'valid_until'], 'user_assignments_role_status_validity_index');
            $table->index(['user_id', 'office_id', 'branch_id', 'department_id', 'is_active'], 'user_assignments_context_status_index');
            $table->index(['user_id', 'is_active', 'valid_from', 'valid_until'], 'user_assignments_status_validity_index');
        });

        if (Schema::hasColumn('user_assignments', 'role')) {
            DB::table('user_assignments')
                ->whereNull('role_id')
                ->whereNotNull('role')
                ->orderBy('id')
                ->get(['id', 'role'])
                ->each(function (object $assignment): void {
                    $roleId = DB::table('roles')
                        ->where('name', $assignment->role)
                        ->where('guard_name', 'web')
                        ->value('id');

                    if ($roleId !== null) {
                        DB::table('user_assignments')->where('id', $assignment->id)->update(['role_id' => $roleId]);
                    }
                });
        }

        Schema::create('assignment_scopes', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('assignment_id')->constrained('user_assignments')->cascadeOnDelete();
            $table->string('scope_type');
            $table->unsignedBigInteger('scope_id');
            $table->timestamps();

            $table->unique(['assignment_id', 'scope_type', 'scope_id']);
            $table->index(['scope_type', 'scope_id']);
        });

        Schema::create('assignment_permission_overrides', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('assignment_id')->constrained('user_assignments')->cascadeOnDelete();
            $table->foreignId('permission_id')->constrained('permissions')->restrictOnDelete();
            $table->string('effect')->default('allow');
            $table->timestamps();

            $table->unique(['assignment_id', 'permission_id'], 'assignment_permission_unique');
            $table->index(['assignment_id', 'effect']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('assignment_permission_overrides');
        Schema::dropIfExists('assignment_scopes');

        Schema::table('user_assignments', function (Blueprint $table): void {
            $table->dropIndex('user_assignments_role_status_validity_index');
            $table->dropIndex('user_assignments_context_status_index');
            $table->dropIndex('user_assignments_status_validity_index');
            $table->dropConstrainedForeignId('role_id');
        });

        Schema::table('user_assignments', function (Blueprint $table): void {
            $table->unique(['user_id', 'office_id', 'valid_from']);
        });

        Schema::table('permissions', function (Blueprint $table): void {
            $table->dropUnique(['code']);
            $table->dropColumn(['code', 'module', 'action']);
        });

        Schema::table('roles', function (Blueprint $table): void {
            $table->dropUnique(['code']);
            $table->dropColumn(['code', 'is_active']);
        });
    }
};
