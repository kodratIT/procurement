<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sample_shipments', function (Blueprint $table): void {
            $table->id();
            $table->string('shipment_number', 50)->nullable()->unique();
            $table->foreignId('purchase_order_id')->constrained('purchase_orders')->restrictOnDelete();
            $table->foreignId('office_id')->constrained('offices')->restrictOnDelete();
            $table->foreignId('sender_office_id')->constrained('offices')->restrictOnDelete();
            $table->foreignId('receiver_office_id')->constrained('offices')->restrictOnDelete();
            $table->foreignId('sender_id')->constrained('users')->restrictOnDelete();
            $table->foreignId('receiver_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('cost_center_id')->nullable()->constrained('cost_centers')->nullOnDelete();
            $table->string('purpose', 255);
            $table->date('requested_at');
            $table->date('planned_ship_date')->nullable();
            $table->date('shipped_at')->nullable();
            $table->date('received_at')->nullable();
            $table->date('confirmed_at')->nullable();
            $table->date('returned_at')->nullable();
            $table->date('completed_at')->nullable();
            $table->string('tracking_no', 100)->nullable();
            $table->decimal('shipping_cost', 14, 2)->default(0);
            $table->char('currency', 3)->default('IDR');
            $table->string('approval_route', 30)->default('procurement');
            $table->string('condition', 30)->default('good');
            $table->string('ownership', 30)->default('sender_office');
            $table->string('status', 30)->default('draft');
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['office_id', 'status']);
            $table->index(['sender_office_id', 'status']);
            $table->index(['receiver_office_id', 'status']);
            $table->index(['purchase_order_id', 'status']);
            $table->index(['tracking_no']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sample_shipments');
    }
};
