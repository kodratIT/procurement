<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sample_shipment_receipts', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('shipment_id')->constrained('sample_shipments')->cascadeOnDelete();
            $table->foreignId('receiver_id')->constrained('users')->restrictOnDelete();
            $table->date('received_at');
            $table->decimal('quantity', 14, 2);
            $table->json('quantities')->nullable();
            $table->string('condition', 30);
            $table->string('disposition', 30)->default('stored');
            $table->string('ownership', 30)->default('receiver_office');
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->unique('shipment_id');
            $table->index(['receiver_id', 'received_at']);
            $table->index(['condition', 'disposition']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sample_shipment_receipts');
    }
};
