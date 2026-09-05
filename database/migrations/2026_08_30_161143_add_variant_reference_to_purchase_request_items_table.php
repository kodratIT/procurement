<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('purchase_request_items', function (Blueprint $table): void {
            $table->foreignId('procurement_variant_id')
                ->nullable()
                ->after('procurement_unit_id')
                ->constrained('procurement_variants')
                ->nullOnDelete();
            $table->string('variant_name', 255)->nullable()->after('unit_name');
            $table->string('variant_value', 255)->nullable()->after('variant_name');
            $table->index(['procurement_item_id', 'procurement_variant_id'], 'purchase_request_items_catalog_index');
        });
    }

    public function down(): void
    {
        Schema::table('purchase_request_items', function (Blueprint $table): void {
            $table->dropIndex('purchase_request_items_catalog_index');
            $table->dropForeign(['procurement_variant_id']);
            $table->dropColumn(['procurement_variant_id', 'variant_name', 'variant_value']);
        });
    }
};
