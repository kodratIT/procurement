<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('umrah_batches', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('office_id')->constrained()->restrictOnDelete();
            $table->string('code', 50);
            $table->string('name');
            $table->date('departure_date');
            $table->date('return_date')->nullable();
            $table->unsignedInteger('capacity')->nullable();
            $table->unsignedInteger('pilgrim_count')->default(0);
            $table->string('status', 30)->default('planned');
            $table->boolean('is_active')->default(true);
            $table->timestamp('disabled_at')->nullable();
            $table->timestamps();

            $table->unique(['office_id', 'code']);
            $table->index(['office_id', 'is_active', 'departure_date']);
            $table->index(['office_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('umrah_batches');
    }
};
