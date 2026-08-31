<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sample_shipment_items', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('shipment_id')->constrained('sample_shipments')->cascadeOnDelete();
            $table->foreignId('purchase_order_item_id')->nullable()->constrained('purchase_order_items')->nullOnDelete();
            $table->foreignId('procurement_item_id')->constrained('procurement_items')->restrictOnDelete();
            $table->foreignId('procurement_variant_id')->nullable()->constrained('procurement_variants')->nullOnDelete();
            $table->decimal('quantity', 14, 2);
            $table->string('condition', 30)->default('good');
            $table->string('ownership', 30)->default('sender_office');
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->unique(['shipment_id', 'procurement_item_id', 'procurement_variant_id']);
            $table->index(['procurement_item_id', 'procurement_variant_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sample_shipment_items');
    }
};
