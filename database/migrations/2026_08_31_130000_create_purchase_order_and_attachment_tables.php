<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('purchase_orders', function (Blueprint $table): void {
            $table->id();
            $table->string('po_number', 50)->unique();
            $table->foreignId('purchase_request_id')->constrained('purchase_requests')->restrictOnDelete();
            $table->foreignId('vendor_id')->constrained('vendors')->restrictOnDelete();
            $table->foreignId('quotation_id')->nullable()->constrained('quotations')->nullOnDelete();
            $table->foreignId('created_by_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('office_id')->constrained('offices')->restrictOnDelete();
            $table->foreignId('branch_id')->nullable()->constrained('branches')->nullOnDelete();
            $table->foreignId('department_id')->nullable()->constrained('departments')->nullOnDelete();
            $table->foreignId('cost_center_id')->nullable()->constrained('cost_centers')->nullOnDelete();
            $table->char('currency', 3)->default('IDR');
            $table->decimal('subtotal_amount', 14, 2)->default(0);
            $table->decimal('discount_amount', 14, 2)->default(0);
            $table->decimal('tax_amount', 14, 2)->default(0);
            $table->decimal('shipping_amount', 14, 2)->default(0);
            $table->decimal('total_amount', 14, 2)->default(0);
            $table->text('terms')->nullable();
            $table->date('delivery_date')->nullable();
            $table->text('notes')->nullable();
            $table->string('status', 40)->default('draft')->index();
            $table->foreignId('approved_by_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->timestamps();

            $table->index(['office_id', 'status'], 'purchase_orders_office_status_index');
            $table->index(['purchase_request_id', 'status'], 'purchase_orders_request_status_index');
        });

        Schema::create('purchase_order_items', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('purchase_order_id')->constrained('purchase_orders')->cascadeOnDelete();
            $table->foreignId('purchase_request_item_id')->nullable()->constrained('purchase_request_items')->nullOnDelete();
            $table->foreignId('procurement_item_id')->nullable()->constrained('procurement_items')->nullOnDelete();
            $table->foreignId('procurement_variant_id')->nullable()->constrained('procurement_variants')->nullOnDelete();
            $table->string('item_name', 255)->nullable();
            $table->text('description')->nullable();
            $table->json('specifications')->nullable();
            $table->string('unit_name', 30)->nullable();
            $table->decimal('quantity', 14, 2)->default(0);
            $table->decimal('unit_price', 14, 2)->default(0);
            $table->decimal('line_total', 14, 2)->default(0);
            $table->text('notes')->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();

            $table->index(['purchase_order_id', 'sort_order'], 'purchase_order_items_order_index');
            $table->index(['procurement_item_id', 'procurement_variant_id'], 'purchase_order_items_catalog_index');
        });

        Schema::create('attachments', function (Blueprint $table): void {
            $table->id();
            $table->morphs('attachable');
            $table->foreignId('uploader_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('path', 2048);
            $table->string('original_name', 255);
            $table->string('mime_type', 255);
            $table->unsignedBigInteger('size');
            $table->string('collection', 100)->default('default');
            $table->string('disk', 50)->default('private');
            $table->timestamps();

            $table->index(['uploader_id', 'created_at'], 'attachments_uploader_created_index');
            $table->index(['collection', 'created_at'], 'attachments_collection_created_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('attachments');
        Schema::dropIfExists('purchase_order_items');
        Schema::dropIfExists('purchase_orders');
    }
};
