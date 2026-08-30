<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('procurement_variants', function (Blueprint $table): void {
            $table->string('variation_type', 30)->default('ukuran')->after('item_id');
            $table->json('attributes')->nullable()->after('value');
            $table->index(['item_id', 'variation_type']);
        });
    }

    public function down(): void
    {
        Schema::table('procurement_variants', function (Blueprint $table): void {
            $table->dropIndex('procurement_variants_item_id_variation_type_index');
            $table->dropColumn(['variation_type', 'attributes']);
        });
    }
};
