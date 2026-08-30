<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('procurement_category_fields', function (Blueprint $table) {
            $table->id();
            $table->foreignId('category_id')->constrained('procurement_categories')->cascadeOnDelete();
            $table->string('key', 100);
            $table->string('label');
            $table->string('type', 20);
            $table->boolean('is_required')->default(false);
            $table->unsignedInteger('sort_order')->default(0);
            $table->json('options')->nullable();
            $table->json('visibility')->nullable();
            $table->json('relation_config')->nullable();
            $table->timestamps();

            $table->unique(['category_id', 'key']);
            $table->index(['category_id', 'sort_order']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('procurement_category_fields');
    }
};
