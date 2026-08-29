<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('procurement_variants', function (Blueprint $table) {
            $table->id();
            $table->foreignId('item_id')->constrained('procurement_items')->cascadeOnDelete();
            $table->string('code', 50);
            $table->string('name');
            $table->string('value')->nullable();
            $table->boolean('is_active')->default(true)->index();
            $table->timestamps();
            $table->unique(['item_id', 'code']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('procurement_variants');
    }
};
