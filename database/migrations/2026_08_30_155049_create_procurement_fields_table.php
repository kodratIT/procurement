<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('procurement_fields', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('category_id')->constrained('procurement_categories')->restrictOnDelete();
            $table->string('key', 100);
            $table->string('label', 255);
            $table->string('field_type', 30);
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('is_required')->default(false);
            $table->json('options')->nullable();
            $table->json('default_value')->nullable();
            $table->string('min_value', 100)->nullable();
            $table->string('max_value', 100)->nullable();
            $table->json('visibility_conditions')->nullable();
            $table->string('editable_stage', 50)->default('draft');
            $table->unsignedInteger('version')->default(1);
            $table->boolean('is_active')->default(true);
            $table->timestamp('activated_at')->nullable();
            $table->timestamp('deactivated_at')->nullable();
            $table->timestamps();

            $table->unique(['category_id', 'key']);
            $table->index(['category_id', 'is_active', 'sort_order']);
            $table->index(['category_id', 'version']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('procurement_fields');
    }
};
