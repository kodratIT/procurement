<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('budgets', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('office_id')->constrained('offices')->restrictOnDelete();
            $table->foreignId('cost_center_id')->constrained('cost_centers')->restrictOnDelete();
            $table->unsignedSmallInteger('year');
            $table->decimal('amount', 14, 2);
            $table->string('status', 20)->default('active');
            $table->timestamps();

            $table->unique(['office_id', 'cost_center_id', 'year']);
            $table->index(['office_id', 'status', 'year']);
            $table->index(['cost_center_id', 'status', 'year']);
        });

        Schema::create('budget_reservations', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('budget_id')->constrained('budgets')->restrictOnDelete();
            $table->foreignId('purchase_request_id')->constrained('purchase_requests')->restrictOnDelete();
            $table->decimal('amount', 14, 2);
            $table->string('status', 20)->default('reserved');
            $table->timestamps();

            $table->unique(['budget_id', 'purchase_request_id']);
            $table->index(['budget_id', 'status']);
            $table->index(['purchase_request_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('budget_reservations');
        Schema::dropIfExists('budgets');
    }
};
