<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('approver_delegations', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('delegator_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('delegate_id')->constrained('users')->cascadeOnDelete();
            $table->date('valid_from');
            $table->date('valid_until');
            $table->text('reason');
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['delegator_id', 'delegate_id', 'valid_from']);
            $table->index(['delegator_id', 'is_active', 'valid_from', 'valid_until']);
            $table->index(['delegate_id', 'is_active', 'valid_from', 'valid_until']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('approver_delegations');
    }
};
