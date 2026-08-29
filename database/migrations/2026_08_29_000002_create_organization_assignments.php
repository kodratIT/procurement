<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('offices', function (Blueprint $table) {
            $table->boolean('is_active')->default(true)->index();
            $table->timestamp('disabled_at')->nullable()->index();
        });

        Schema::create('branches', function (Blueprint $table) {
            $table->id();
            $table->foreignId('office_id')->constrained()->restrictOnDelete();
            $table->string('code');
            $table->string('name');
            $table->boolean('is_active')->default(true);
            $table->timestamp('disabled_at')->nullable();
            $table->timestamps();

            $table->unique(['office_id', 'code']);
            $table->index(['office_id', 'is_active']);
        });

        Schema::create('departments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('office_id')->constrained()->restrictOnDelete();
            $table->foreignId('branch_id')->nullable()->constrained()->nullOnDelete();
            $table->string('code');
            $table->string('name');
            $table->boolean('is_active')->default(true);
            $table->timestamp('disabled_at')->nullable();
            $table->timestamps();

            $table->unique(['office_id', 'code']);
            $table->index(['office_id', 'is_active']);
            $table->index(['branch_id', 'is_active']);
        });

        Schema::create('cost_centers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('office_id')->constrained()->restrictOnDelete();
            $table->string('code');
            $table->string('name');
            $table->boolean('is_active')->default(true);
            $table->timestamp('disabled_at')->nullable();
            $table->timestamps();

            $table->unique(['office_id', 'code']);
            $table->index(['office_id', 'is_active']);
        });

        Schema::create('user_assignments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('office_id')->constrained()->restrictOnDelete();
            $table->foreignId('branch_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('department_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('cost_center_id')->nullable()->constrained()->nullOnDelete();
            $table->date('valid_from');
            $table->date('valid_until')->nullable();
            $table->boolean('is_primary')->default(false);
            $table->boolean('is_active')->default(true);
            $table->timestamp('disabled_at')->nullable();
            $table->timestamps();

            $table->unique(['user_id', 'office_id', 'valid_from']);
            $table->index(['user_id', 'is_active', 'valid_from']);
            $table->index(['office_id', 'is_active']);
            $table->index(['branch_id', 'department_id', 'cost_center_id']);
            $table->check('valid_until IS NULL OR valid_until >= valid_from');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_assignments');
        Schema::dropIfExists('cost_centers');
        Schema::dropIfExists('departments');
        Schema::dropIfExists('branches');

        Schema::table('offices', function (Blueprint $table) {
            $table->dropColumn(['is_active', 'disabled_at']);
        });
    }
};
