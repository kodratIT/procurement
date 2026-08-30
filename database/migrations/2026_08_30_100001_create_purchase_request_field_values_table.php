<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // The request table is owned by F4.1 and may be migrated independently.
        // Keep this EAV table standalone so migration order remains safe.
        Schema::create('purchase_request_field_values', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('purchase_request_id');
            $table->foreignId('field_id')->constrained('procurement_category_fields')->cascadeOnDelete();
            $table->longText('value')->nullable();
            $table->string('file_path')->nullable();
            $table->timestamps();

            $table->unique(['purchase_request_id', 'field_id']);
            $table->index('purchase_request_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('purchase_request_field_values');
    }
};
