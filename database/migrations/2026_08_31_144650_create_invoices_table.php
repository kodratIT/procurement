<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('invoices', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('purchase_order_id')->constrained('purchase_orders')->restrictOnDelete();
            $table->foreignId('vendor_id')->constrained('vendors')->restrictOnDelete();
            $table->foreignId('recorded_by_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('office_id')->constrained('offices')->restrictOnDelete();
            $table->foreignId('branch_id')->nullable()->constrained('branches')->nullOnDelete();
            $table->foreignId('department_id')->nullable()->constrained('departments')->nullOnDelete();
            $table->char('currency', 3)->default('IDR');
            $table->string('invoice_number', 100);
            $table->string('normalized_invoice_number', 100);
            $table->decimal('total_amount', 14, 2);
            $table->date('due_date');
            $table->string('status', 40)->default('unpaid');
            $table->string('match_status', 40)->default('matched');
            $table->string('review_status', 40)->default('pending');
            $table->text('mismatch_reason')->nullable();
            $table->timestamp('matched_at')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->unique(['vendor_id', 'normalized_invoice_number']);
            $table->index(['purchase_order_id', 'status']);
            $table->index(['office_id', 'status']);
            $table->index(['due_date', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('invoices');
    }
};
