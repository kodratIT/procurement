<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pilgrim_distribution_items', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('distribution_item_id')->constrained('distribution_items')->cascadeOnDelete();
            $table->foreignId('pilgrim_id')->constrained('pilgrims')->restrictOnDelete();
            $table->decimal('quantity', 14, 2);
            $table->string('status', 30)->default('pending');
            $table->timestamps();

            $table->unique(['distribution_item_id', 'pilgrim_id'], 'pilgrim_distribution_item_unique');
            $table->index(['pilgrim_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pilgrim_distribution_items');
    }
};
