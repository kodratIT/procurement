<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('vendors', function (Blueprint $table): void {
            $table->string('vendor_type', 30)->default('goods')->after('name')->index();
            $table->string('tax_number', 50)->nullable()->after('email')->index();
        });
    }

    public function down(): void
    {
        Schema::table('vendors', function (Blueprint $table): void {
            $table->dropIndex(['vendor_type']);
            $table->dropIndex(['tax_number']);
            $table->dropColumn(['vendor_type', 'tax_number']);
        });
    }
};
