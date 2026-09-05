<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('approval_instance_steps', function (Blueprint $table): void {
            $table->foreignId('workflow_step_id')->nullable()->after('approval_instance_id')->constrained('workflow_steps')->nullOnDelete();
            $table->index('workflow_step_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('approval_instance_steps', function (Blueprint $table): void {
            $table->dropForeign(['workflow_step_id']);
            $table->dropIndex(['workflow_step_id']);
            $table->dropColumn('workflow_step_id');
        });
    }
};
