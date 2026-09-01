<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('distribution_items', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('distribution_id')->constrained('distributions')->cascadeOnDelete();
            $table->foreignId('procurement_item_id')->constrained('procurement_items')->restrictOnDelete();
            $table->decimal('quantity', 14, 2);
            $table->timestamps();

            $table->unique(['distribution_id', 'procurement_item_id']);
            $table->index(['procurement_item_id', 'distribution_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('distribution_items');
    }
};
