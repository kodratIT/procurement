<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('procurement_categories', function (Blueprint $table): void {
            $table->boolean('requires_recommendation_reason')->default(false)->after('requires_quotation');
            $table->boolean('requires_recommendation_evidence')->default(false)->after('requires_recommendation_reason');
        });
    }

    public function down(): void
    {
        Schema::table('procurement_categories', function (Blueprint $table): void {
            $table->dropColumn(['requires_recommendation_reason', 'requires_recommendation_evidence']);
        });
    }
};
