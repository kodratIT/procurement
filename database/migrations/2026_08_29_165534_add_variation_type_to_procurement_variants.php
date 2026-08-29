<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('procurement_variants', function (Blueprint $table) {
            $table->string('variation_type', 30)->default('ukuran')->after('item_id');
        });
    }

    public function down(): void
    {
        Schema::table('procurement_variants', function (Blueprint $table) {
            $table->dropColumn('variation_type');
        });
    }
};
