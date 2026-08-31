<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('distributions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('umrah_batch_id')->constrained('umrah_batches')->restrictOnDelete();
            $table->date('distributed_at');
            $table->string('receipt_mode', 30)->default('batch');
            $table->string('status', 30)->default('recorded');
            $table->timestamps();

            $table->index(['umrah_batch_id', 'status', 'distributed_at']);
            $table->index(['receipt_mode', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('distributions');
    }
};
