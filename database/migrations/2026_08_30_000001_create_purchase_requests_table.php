<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('purchase_requests', function (Blueprint $table) {
            $table->id();
            $table->string('pr_number', 30)->unique();
            $table->foreignId('office_id')->constrained('offices')->restrictOnDelete();
            $table->foreignId('branch_id')->nullable()->constrained('branches')->nullOnDelete();
            $table->foreignId('department_id')->nullable()->constrained('departments')->nullOnDelete();
            $table->foreignId('cost_center_id')->nullable()->constrained('cost_centers')->nullOnDelete();
            $table->foreignId('departure_batch_id')->nullable()->constrained('departure_batches')->nullOnDelete();
            $table->foreignId('requester_id')->constrained('users')->restrictOnDelete();
            $table->string('title', 255)->nullable();
            $table->text('notes')->nullable();
            $table->date('required_date')->nullable();
            $table->string('status', 30)->default('draft')->index();
            $table->decimal('total_amount', 14, 2)->default(0);
            $table->timestamps();

            $table->unique(['office_id', 'pr_number']);
            $table->index(['office_id', 'status']);
            $table->index(['requester_id', 'status']);
            $table->index(['departure_batch_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('purchase_requests');
    }
};
