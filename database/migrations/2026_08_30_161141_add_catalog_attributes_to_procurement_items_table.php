<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('procurement_items', function (Blueprint $table): void {
            $table->decimal('reference_price', 14, 2)->nullable()->after('description');
            $table->string('reference_currency', 3)->default('IDR')->after('reference_price');
            $table->json('specifications')->nullable()->after('reference_currency');
            $table->index('category_id');
        });
    }

    public function down(): void
    {
        Schema::table('procurement_items', function (Blueprint $table): void {
            $table->dropIndex('procurement_items_category_id_index');
            $table->dropColumn(['reference_price', 'reference_currency', 'specifications']);
        });
    }
};
