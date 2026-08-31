<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('purchase_requests', function (Blueprint $table): void {
            $table->string('pr_number', 30)->nullable()->change();
            $table->text('reason')->nullable()->after('notes');
            $table->string('priority', 20)->default('normal')->after('required_date');
            $table->index(['office_id', 'status', 'updated_at']);
        });

        Schema::table('purchase_request_items', function (Blueprint $table): void {
            $table->text('description')->nullable()->after('item_name');
            $table->json('specifications')->nullable()->after('unit_name');
            $table->index(['purchase_request_id', 'procurement_item_id']);
        });
    }

    public function down(): void
    {
        Schema::table('purchase_request_items', function (Blueprint $table): void {
            $table->dropIndex(['purchase_request_id', 'procurement_item_id']);
            $table->dropColumn(['description', 'specifications']);
        });

        Schema::table('purchase_requests', function (Blueprint $table): void {
            $table->dropIndex(['office_id', 'status', 'updated_at']);
            $table->dropColumn(['reason', 'priority']);
        });
    }
};
