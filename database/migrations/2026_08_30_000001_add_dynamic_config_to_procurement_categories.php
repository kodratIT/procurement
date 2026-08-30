<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('procurement_categories', function (Blueprint $table): void {
            $table->string('type', 20)->default('goods')->index()->after('name');
            $table->boolean('requires_batch')->default(false)->after('type');
            $table->boolean('requires_vendor')->default(false)->after('requires_batch');
            $table->boolean('receiving')->default(false)->after('requires_vendor');
            $table->boolean('invoice')->default(false)->after('receiving');
            $table->boolean('jamaah')->default(false)->after('invoice');
        });
    }

    public function down(): void
    {
        Schema::table('procurement_categories', function (Blueprint $table): void {
            $table->dropIndex(['type']);
            $table->dropColumn(['type', 'requires_batch', 'requires_vendor', 'receiving', 'invoice', 'jamaah']);
        });
    }
};
