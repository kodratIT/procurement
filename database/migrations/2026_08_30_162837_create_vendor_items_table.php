<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('vendor_items', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('vendor_id')->constrained('vendors')->restrictOnDelete();
            $table->foreignId('item_id')->constrained('procurement_items')->restrictOnDelete();
            $table->decimal('reference_price', 14, 2);
            $table->string('currency', 3)->default('IDR');
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
