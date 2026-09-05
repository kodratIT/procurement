<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('purchase_request_field_values', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('purchase_request_id')->constrained('purchase_requests')->cascadeOnDelete();
            $table->foreignId('field_id')->constrained('procurement_fields')->restrictOnDelete();
            $table->string('field_key', 100);
            $table->string('field_label', 255);
            $table->string('field_type', 30);
            $table->unsignedInteger('field_version');
            $table->json('definition_snapshot');
            $table->json('value')->nullable();
            $table->timestamps();

            $table->unique(['purchase_request_id', 'field_id'], 'pr_field_value_unique');
            $table->index(['purchase_request_id', 'field_key'], 'pr_field_value_key_index');
            $table->index(['field_id', 'field_version']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('purchase_request_field_values');
    }
};
