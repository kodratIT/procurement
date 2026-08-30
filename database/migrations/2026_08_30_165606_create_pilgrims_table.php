<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pilgrims', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('office_id')->constrained()->restrictOnDelete();
            $table->foreignId('umrah_batch_id')->constrained()->restrictOnDelete();
            $table->string('name');
            $table->string('passport_no', 50);
            $table->string('phone', 30)->nullable();
            $table->string('status', 30)->default('registered');
            $table->boolean('is_active')->default(true);
            $table->timestamp('disabled_at')->nullable();
            $table->timestamps();

            $table->unique(['umrah_batch_id', 'passport_no']);
            $table->index(['office_id', 'is_active']);
            $table->index(['office_id', 'status']);
            $table->index(['umrah_batch_id', 'is_active']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pilgrims');
    }
};
