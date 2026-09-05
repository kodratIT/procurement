<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('approval_instance_steps', function (Blueprint $table): void {
            $table->index(['approval_instance_id', 'step_order', 'status'], 'approval_steps_instance_status_index');
            $table->dropUnique(['approval_instance_id', 'step_order']);
            $table->string('approval_mode', 30)->default('sequential')->after('resolver_type');
            $table->string('step_type', 30)->nullable()->after('approval_mode');
            $table->boolean('is_required')->default(true)->after('step_type');
            $table->unsignedInteger('sla_minutes')->nullable()->after('is_required');
            $table->string('escalation_type', 30)->nullable()->after('sla_minutes');
            $table->timestamp('assigned_at')->nullable()->after('acted_at');
            $table->timestamp('due_at')->nullable()->after('assigned_at');
            $table->timestamp('sla_warning_at')->nullable()->after('due_at');
            $table->timestamp('sla_warning_sent_at')->nullable()->after('sla_warning_at');
            $table->timestamp('expired_at')->nullable()->after('sla_warning_sent_at');
            $table->timestamp('escalated_at')->nullable()->after('expired_at');
            $table->timestamp('completed_at')->nullable()->after('escalated_at');
            $table->foreignId('original_approver_id')->nullable()->after('approver_id')->constrained('users')->nullOnDelete();
            $table->index(['approver_id', 'status', 'due_at']);
        });

        Schema::create('approval_histories', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('approval_instance_step_id')->constrained()->restrictOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('role_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('office_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('branch_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('department_id')->nullable()->constrained()->nullOnDelete();
            $table->string('action', 30);
            $table->text('notes')->nullable();
            $table->timestamp('acted_at');
            $table->unsignedInteger('workflow_version')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->text('device')->nullable();
            $table->json('context')->nullable();
            $table->timestamps();

            $table->index(['approval_instance_step_id', 'acted_at']);
            $table->index(['user_id', 'acted_at']);
        });

        Schema::create('notifications', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('type');
            $table->string('notifiable_type');
            $table->string('notifiable_id');
            $table->text('data');
            $table->timestamp('read_at')->nullable();
            $table->timestamps();

            $table->index(['notifiable_type', 'notifiable_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notifications');
        Schema::dropIfExists('approval_histories');

        Schema::table('approval_instance_steps', function (Blueprint $table): void {
            $table->dropForeign(['original_approver_id']);
            $table->dropIndex('approval_steps_instance_status_index');
            $table->dropIndex(['approver_id', 'status', 'due_at']);
            $table->dropColumn([
                'approval_mode',
                'step_type',
                'is_required',
                'sla_minutes',
                'escalation_type',
                'assigned_at',
                'due_at',
                'sla_warning_at',
                'sla_warning_sent_at',
                'expired_at',
                'escalated_at',
                'completed_at',
                'original_approver_id',
            ]);
            $table->unique(['approval_instance_id', 'step_order']);
        });
    }
};
