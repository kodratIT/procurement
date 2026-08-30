<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('vendor_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('vendor_id')->constrained()->cascadeOnDelete();
            $table->foreignId('item_id')->constrained('procurement_items')->cascadeOnDelete();
            $table->decimal('price', 14, 2)->default(0);
            $table->string('currency', 10)->default('IDR');
            $table->date('price_valid_from')->nullable();
            $table->date('price_valid_until')->nullable();
            $table->string('vendor_sku')->nullable();
            $table->unsignedInteger('min_order_qty')->default(1);
            $table->unsignedInteger('lead_time_days')->nullable();
            $table->boolean('is_active')->default(true)->index();
            $table->timestamps();

            $table->unique(['vendor_id', 'item_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('vendor_items');
    }
};
