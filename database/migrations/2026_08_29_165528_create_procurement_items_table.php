<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('procurement_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('category_id')->constrained('procurement_categories')->restrictOnDelete();
            $table->foreignId('unit_id')->constrained('procurement_units')->restrictOnDelete();
            $table->string('code', 50)->unique();
            $table->string('name');
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true)->index();
            $table->timestamps();
        });
    }
    public function down(): void { Schema::dropIfExists('procurement_items'); }
};
